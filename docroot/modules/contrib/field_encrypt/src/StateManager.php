<?php

declare(strict_types=1);

namespace Drupal\field_encrypt;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages state for the module.
 *
 * The module state includes:
 * - the list of entity types with encrypted fields.
 * - the installation and removal of the encrypted_field_storage base field.
 * - the management of the entity definitions.
 */
class StateManager {

  /**
   * Constructs a new ConfigSubscriber object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueFactory $queueFactory,
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
    protected EntityLastInstalledSchemaRepositoryInterface $entitySchemaRepository,
    protected EntityDefinitionUpdateManagerInterface $entityDefinitionUpdateManager,
    protected DrupalKernelInterface $kernel,
    // Set by \Drupal\field_encrypt\FieldEncryptServiceProvider
    protected array $entityTypes = [],
  ) {
  }

  /**
   * Figure out which entity types are encrypted.
   */
  public function update(): void {
    $new_entity_types = $this->getEntityTypes();
    if ($this->entityTypes === $new_entity_types) {
      // No changes to make. Early return to do nothing and preserve caches.
      return;
    }

    // Get entities where we need to add a field.
    foreach (array_diff($new_entity_types, $this->entityTypes) as $type) {
      $definition = static::getEncryptedFieldStorageDefinition();
      $this->entityDefinitionUpdateManager->installFieldStorageDefinition(ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME, $type, 'field_encrypt', $definition);
    }

    // We can't remove the field if there are queue items to process because if
    // there is data we'll destroy it. So merge in the old entity types.
    $this->entityTypes = array_merge($this->entityTypes, $new_entity_types);
    $this->keyValueFactory->get('field_encrypt')->set('entity_types', $this->entityTypes);
    // @see field_encrypt.module
    $this->kernel->invalidateContainer();
    // @see field_encrypt_entity_type_alter()
    $this->entityTypeManager->clearCachedDefinitions();
    $this->setEntityTypeCacheInformation($new_entity_types);
  }

  /**
   * Reacts to field_encrypt.settings:make_entities_uncacheable changes.
   *
   * @return static
   */
  public function onFieldEncryptSettingsCacheChange(): static {
    $this->entityTypeManager->clearCachedDefinitions();
    $this->setEntityTypeCacheInformation($this->entityTypes);
    return $this;
  }

  /**
   * Sets the last installed entity cache information correctly.
   *
   * @param string[] $entity_type_ids
   *   The entity type IDs to set the cache information for.
   *
   * @see field_encrypt_entity_type_alter()
   */
  protected function setEntityTypeCacheInformation(array $entity_type_ids): void {
    $entity_types = $this->entityTypeManager->getDefinitions();

    // Types that have changed need to have their last installed definition
    // updated. We need to be careful to only change the settings we are
    // interested in.
    foreach ($entity_type_ids as $type) {
      $last_installed_definition = $this->entitySchemaRepository->getLastInstalledDefinition($type);
      $last_installed_definition
        ->set('render_cache', $entity_types[$type]->get('render_cache') ?? FALSE)
        ->set('persistent_cache', $entity_types[$type]->get('persistent_cache') ?? FALSE);
      $this->entitySchemaRepository->setLastInstalledDefinition($last_installed_definition);
    }
  }

  /**
   * Removes storage base fields if possible.
   */
  public function removeStorageFields(): void {
    $queue = $this->queueFactory->get('field_encrypt_update_entity_encryption');
    // We can't remove the field if there are queue items to process because if
    // there is data we'll destroy it.
    if ($queue->numberOfItems() > 0) {
      return;
    }

    $new_entity_types = $this->getEntityTypes();
    $old_entity_types = $this->entityTypes;

    if ($old_entity_types === $new_entity_types) {
      // No changes to make. Early return to do nothing and preserve caches.
      return;
    }

    $this->entityTypes = $new_entity_types;
    $this->keyValueFactory->get('field_encrypt')->set('entity_types', $this->entityTypes);
    // @see field_encrypt.module
    $this->kernel->invalidateContainer();
    foreach (array_diff($old_entity_types, $new_entity_types) as $type) {
      $field = $this->entityDefinitionUpdateManager->getFieldStorageDefinition(ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME, $type);
      if ($field) {
        $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($field);
      }
    }
  }

  /**
   * Gets the field definition for the blob where we store data.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The field definition for the blob where we store data.
   */
  public static function getEncryptedFieldStorageDefinition(): BaseFieldDefinition {
    return BaseFieldDefinition::create('encrypted_field_storage')
      ->setLabel(new TranslatableMarkup('Encrypted data'))
      ->setDescription(new TranslatableMarkup('Stores data from encrypted fields.'))
      ->setInternal(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);
  }

  /**
   * Lists entity types which have encrypted fields.
   *
   * @return string[]
   *   The list of entity types with encrypted fields. Keyed by entity type ID.
   */
  protected function getEntityTypes(): array {
    $entity_types = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $storage_class = $this->entityTypeManager->createHandlerInstance($entity_type->getStorageClass(), $entity_type);
        if ($storage_class instanceof DynamicallyFieldableEntityStorageInterface) {
          $entity_type_id = $entity_type->id();
          // Check base fields.
          if ($this->entityTypeManager->getStorage('field_encrypt_entity_type')->load($entity_type_id)) {
            $entity_types[$entity_type_id] = $entity_type_id;
            continue;
          }
          // Query by filtering on the ID as this is more efficient than
          // filtering on the entity_type property directly.
          $ids = $this->entityTypeManager->getStorage('field_storage_config')->getQuery()
            ->condition('id', $entity_type_id . '.', 'STARTS_WITH')
            ->accessCheck(FALSE)
            ->execute();
          // Fetch all fields on entity type.
          /** @var \Drupal\field\FieldStorageConfigInterface[] $field_storages */
          $field_storages = $this->entityTypeManager->getStorage('field_storage_config')->loadMultiple($ids);
          foreach ($field_storages as $storage) {
            // Check if field is encrypted.
            if ($storage->getThirdPartySetting('field_encrypt', 'encrypt', FALSE) == TRUE) {
              $entity_types[$entity_type_id] = $entity_type_id;
              continue 2;
            }
          }
        }
      }
    }
    return $entity_types;
  }

  /**
   * Gets the list of entity type IDs with encrypted fields.
   *
   * @return string[]
   *   The list of entity type IDs with encrypted fields.
   */
  public function getEncryptedEntityTypes(): array {
    return $this->entityTypes;
  }

}
