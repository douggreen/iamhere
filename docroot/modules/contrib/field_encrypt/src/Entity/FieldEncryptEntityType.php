<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Defines the Field Encrypt entity type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "field_encrypt_entity_type",
 *   label = @Translation("Field Encrypt entity type settings"),
 *   label_collection = @Translation("Field Encrypt entity type settings"),
 *   label_singular = @Translation("Field Encrypt entity type settings"),
 *   label_plural = @Translation("Field Encrypt entity type settings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Field Encrypt entity type settings",
 *     plural = "@count Field Encrypt entity type settings",
 *   ),
 *   admin_permission = "administer field encryption",
 *   config_prefix = "entity_type",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "base_fields",
 *   }
 * )
 *
 * @see \Drupal\field_encrypt\Form\EntityTypeForm
 */
class FieldEncryptEntityType extends ConfigEntityBase {

  /**
   * The machine name for the configuration entity.
   *
   * @var string
   */
  protected string $id;

  /**
   * The base fields.
   *
   * @var array
   */
  protected array $base_fields = [];

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): FieldEncryptEntityType {
    parent::calculateDependencies();
    $target_entity_type = \Drupal::entityTypeManager()->getDefinition($this->id);
    $this->addDependency('module', $target_entity_type->getProvider());
    return $this;
  }

  /**
   * Determines if the base field is set to be encrypted.
   *
   * @param string $field_name
   *   The base field name.
   *
   * @return bool
   *   TRUE if the base field is set to be encrypted, FALSE if not.
   */
  public function hasBaseField(string $field_name): bool {
    return isset($this->base_fields[$field_name]);
  }

  /**
   * Gets the base fields that are encrypted and their property settings.
   *
   * @return string[][]
   *   An array of arrays encrypted base field properties. Keyed by base field
   *   name.
   */
  public function getBaseFields(): array {
    return $this->base_fields;
  }

  /**
   * Sets the base fields that are encrypted and their property settings.
   *
   * @param string[][] $base_fields
   *   An array of arrays encrypted base field properties. Keyed by base field
   *   name.
   *
   * @return $this
   */
  public function setBaseFields(array $base_fields): FieldEncryptEntityType {
    $this->base_fields = $base_fields;
    return $this;
  }

  /**
   * Removes a base field from the configuration.
   *
   * @param string $field_name
   *   The base field name.
   *
   * @return $this
   */
  public function removeBaseField(string $field_name): FieldEncryptEntityType {
    unset($this->base_fields[$field_name]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    if (!$update || $this->getBaseFields() !== $this->original->getBaseFields()) {
      self::queueEntityUpdates($this->id());
    }
    // Update the field_encrypt module's state.
    \Drupal::service('field_encrypt.state_manager')->update();
    // Ensure base field definitions are rebuilt.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      self::queueEntityUpdates($entity->id());
    }
    // Update the field_encrypt module's state.
    \Drupal::service('field_encrypt.state_manager')->update();
    // Ensure base field definitions are rebuilt.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
  }

  /**
   * Queues entity updates when entity is updated or deleted.
   *
   * @param string $entity_type_id
   *   The ID of the entity being updated or deleted. This is the same as the
   *   entity type the config entity is configures.
   */
  private static function queueEntityUpdates(string $entity_type_id): void {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::service('queue')->get('field_encrypt_update_entity_encryption');
    $entity_type_manager = \Drupal::entityTypeManager();

    // Skip entity types that do not exist. This is defensive coding.
    if ($entity_type_manager->hasDefinition($entity_type_id) && $entity_type_manager->getStorage($entity_type_id)->hasData()) {
      $entity_type = $entity_type_manager->getDefinition($entity_type_id);
      // Call the Queue API and add items for processing.
      // Get entities that need updating, because they contain the field
      // that has its field encryption settings updated.
      $query = $entity_type_manager->getStorage($entity_type_id)->getQuery()->accessCheck(FALSE);
      // Make sure to get all revisions for revisionable entities.
      if ($entity_type->isRevisionable()) {
        $query->allRevisions();
      }
      $entity_ids = $query->execute();
      $data = ['entity_type' => $entity_type_id];
      foreach (array_keys($entity_ids) as $entity_id) {
        $data['entity_id'] = $entity_id;
        $queue->createItem($data);
      }
      \Drupal::messenger()->addMessage(new TranslatableMarkup('Updates to @entity_type with existing data been queued to be processed. You should immediately <a href=":url">run this process manually</a>. Alternatively, the updates will be performed automatically by cron.', [
        '@entity_type' => $entity_type->getPluralLabel(),
        ':url' => Url::fromRoute('field_encrypt.process_queue')->toString(),
      ]));
    }
  }

}
