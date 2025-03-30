<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\field_encrypt\ProcessEntities;
use Drupal\field_encrypt\StateManager;

/**
 * Implements hooks for the field_encrypt module.
 */
class FieldEncryptHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $config,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ProcessEntities $processEntities,
    protected StateManager $fieldEncryptStateManager,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Adds settings to the field storage configuration forms to allow setting the
   * encryption state.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    // Check permissions.
    if (
      $this->currentUser->hasPermission('administer field encryption') &&
      $this->config->get('field_encrypt.settings')->get('encryption_profile') !== ''
    ) {
      /** @var \Drupal\Core\Entity\EntityFormInterface $entity_form */
      $entity_form = $form_state->getFormObject();

      /** @var \Drupal\field\Entity\FieldConfig $field */
      $field = $entity_form->getEntity();

      $field_type = $field->getType();
      $default_properties = $this->config->get('field_encrypt.settings')->get('default_properties');

      /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
      $field_storage = $field->getFieldStorageDefinition();

      // Add container for field_encrypt specific settings.
      $form['field_storage']['field_encrypt'] = [
        '#type' => 'details',
        '#title' => $this->t('Field encryption'),
        '#open' => TRUE,
      ];

      // Display a warning about changing field data.
      // phpstan needs this because hasData() is not part of the interface.
      /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
      if ($field_storage->hasData()) {
        $form['field_storage']['field_encrypt']['#prefix'] = '<div class="messages messages--warning">' . $this->t('Warning: changing field encryption settings may cause data corruption!<br />When changing these settings, existing fields will be (re)encrypted in batch according to the new settings. <br />Make sure you have a proper backup, and do not perform this action in an environment where the data will be changing during the batch operation, to avoid data loss.') . '</div>';
      }
      $form['field_storage']['field_encrypt']['field_encrypt'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];

      // Add setting to decide if field should be encrypted.
      $form['field_storage']['field_encrypt']['field_encrypt']['encrypt'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Encrypt field'),
        '#description' => $this->t('Makes the field storage encrypted.'),
        '#default_value' => $field_storage->getThirdPartySetting('field_encrypt', 'encrypt', FALSE),
      ];

      $properties = [];
      $definitions = $field_storage->getPropertyDefinitions();
      foreach ($definitions as $property => $definition) {
        $properties[$property] = $definition->getLabel();
      }

      $field_encrypt_default = $default_properties[$field_type] ?? [];
      $form['field_storage']['field_encrypt']['field_encrypt']['properties'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Properties'),
        '#description' => $this->t('Specify the field properties to encrypt.'),
        '#options' => $properties,
        '#default_value' => $field_storage->getThirdPartySetting('field_encrypt', 'properties', $field_encrypt_default),
        '#states' => ['visible' => [':input[name="field_storage[field_encrypt][field_encrypt][encrypt]"]' => ['checked' => TRUE]]],
      ];
      // We add functions to process the form when it is saved.
      $form['#entity_builders'][] = 'field_encrypt_form_field_add_form_builder';
    }
  }

  /**
   * Implements hook_entity_view().
   */
  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, ?string $view_mode): void {
    if (
      $this->hasEncryptedFields($entity) &&
      $this->config->get('field_encrypt.settings')->get('make_entities_uncacheable') &&
      $entity instanceof ContentEntityInterface
    ) {
      $this->processEntities->entitySetCacheTags($entity, $build);
    }
  }

  /**
   * Implements hook_entity_presave().
   *
   * Encrypt entity fields before they are saved.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (
      $this->hasEncryptedFields($entity) &&
      $entity instanceof ContentEntityInterface
    ) {
      $this->processEntities->encryptEntity($entity);
    }
  }

  /**
   * Implements hook_entity_storage_load().
   *
   * Decrypt entity fields when loading entities.
   */
  #[Hook('entity_storage_load')]
  public function entityStorageLoad(array $entities, string $entity_type): void {
    if (!$this->hasEncryptedFields(current($entities))) {
      return;
    }
    foreach ($entities as $entity) {
      $this->processEntities->decryptEntity($entity);
    }
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * @see \Drupal\field_encrypt\EventSubscriber\ConfigSubscriber::onConfigSave()
   * @see \Drupal\field_encrypt\StateManager::update()
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if ($this->config->get('field_encrypt.settings')->get('make_entities_uncacheable')) {
      // Exclude entities from cache if they contain an encrypted field.
      foreach ($this->fieldEncryptStateManager->getEncryptedEntityTypes() as $entity_type) {
        // Ignore entity types that do not exist. This is defensive coding.
        if (isset($entity_types[$entity_type])) {
          $entity_types[$entity_type]->set('render_cache', FALSE);
          $entity_types[$entity_type]->set('persistent_cache', FALSE);
        }
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): ?array {
    if (isset($this->fieldEncryptStateManager->getEncryptedEntityTypes()[$entity_type->id()])) {
      $fields[ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME] = StateManager::getEncryptedFieldStorageDefinition();
      return $fields;
    }
    return NULL;
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    /** @var \Drupal\field_encrypt\Entity\FieldEncryptEntityType $field_encrypt_settings */
    $field_encrypt_settings = $this->entityTypeManager
      ->getStorage('field_encrypt_entity_type')
      ->load($entity_type->id());
    if ($field_encrypt_settings) {
      foreach ($field_encrypt_settings->getBaseFields() as $field_name => $encrypted_properties) {
        if (isset($fields[$field_name])) {
          $fields[$field_name]->setSetting('field_encrypt.encrypt', TRUE);
          $fields[$field_name]->setSetting('field_encrypt.properties', $encrypted_properties);
        }
      }
    }
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Clean up any unused encrypted field storage field if necessary.
    $this->fieldEncryptStateManager->removeStorageFields();
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete().
   */
  #[Hook('encryption_profile_predelete')]
  public function encryptionProfilePredelete(EncryptionProfile $profile): void {
    // Prevent encryption profiles from being deleted if they are in use.
    if ($profile->id() === $this->config->get('field_encrypt.settings')
      ->get('encryption_profile')) {
      throw new \RuntimeException(sprintf('Cannot delete %s encryption profile because it is the default for the field_encrypt module', $profile->id()));
    }

    foreach ($this->fieldEncryptStateManager->getEncryptedEntityTypes() as $entity_type_id) {
      if (!$this->entityTypeManager->hasDefinition($entity_type_id)) {
        continue;
      }
      $query = $this->entityTypeManager->getStorage($entity_type_id)
        ->getQuery()
        ->condition(ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME . '.encryption_profile', $profile->id())
        ->accessCheck(FALSE);
      if ($this->entityTypeManager->getDefinition($entity_type_id)
        ->isRevisionable()) {
        $query->allRevisions();
      }
      if ($query->count()->execute() > 0) {
        throw new \RuntimeException(sprintf('Cannot delete %s encryption profile because it is in-use by %s entities', $profile->id(), $entity_type_id));
      }
    }
  }

  /**
   * Verify if the given entity has encrypted fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   Boolean indicating whether has encrypted fields.
   */
  private function hasEncryptedFields(EntityInterface $entity): bool {
    // We can only encrypt content entities.
    if (!$entity instanceof ContentEntityInterface) {
      return FALSE;
    }
    // @todo compare performance with
    //   $entity->hasField(static::ENCRYPTED_FIELD_STORAGE_NAME).
    return in_array($entity->getEntityTypeId(), $this->fieldEncryptStateManager->getEncryptedEntityTypes(), TRUE);
  }

}
