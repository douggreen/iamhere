<?php

declare(strict_types=1);

namespace Drupal\field_encrypt;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Service class to process entities and fields for encryption.
 */
class ProcessEntities {

  /**
   * The name of the field that stores encrypted data.
   */
  const ENCRYPTED_FIELD_STORAGE_NAME = 'encrypted_field_storage';

  /**
   * This value is used in place of the real value in the database.
   */
  const ENCRYPTED_VALUE = '🔒';

  /**
   * Constructs a ProcessEntities object.
   */
  public function __construct(protected ModuleHandlerInterface $moduleHandler) {
  }

  /**
   * Encrypts an entity's encrypted fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to encrypt.
   *
   * @see field_encrypt_entity_presave()
   * @see field_encrypt_entity_update()
   * @see field_encrypt_entity_insert()
   * @see field_encrypt_module_implements_alter()
   */
  public function encryptEntity(ContentEntityInterface $entity): void {
    // Make sure there is a base field to store encrypted data.
    if (!$entity->hasField(static::ENCRYPTED_FIELD_STORAGE_NAME)) {
      return;
    }

    // Check if encryption is prevented due to implementations of
    // hook_field_encrypt_allow_encryption().
    $allowed = TRUE;
    $this->moduleHandler->invokeAllWith('field_encrypt_allow_encryption', function (callable $hook) use (&$allowed, $entity) {
      if ($allowed && $hook($entity) === FALSE) {
        $allowed = FALSE;
      }
    });

    // If any implementation returns a FALSE boolean value, disable encryption.
    if (!$allowed) {
      return;
    }

    // Process all language variants of the entity.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language) {
      $translated_entity = $entity->getTranslation($language->getId());
      $field = $translated_entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME);
      // Before encrypting the entity ensure the encrypted field storage is
      // empty so that any changes to encryption settings are processed as
      // expected.
      if (!$field->isEmpty()) {
        $field->removeItem(0);
        $field->appendItem();
      }
      foreach ($this->getEncryptedFields($translated_entity) as $field) {
        $this->encryptField($translated_entity, $field);
      }

      // The entity storage handler has clever logic to ensure that configurable
      // fields are only saved if necessary. If entity->original is set we need
      // to ensure the field values are the values in the database and not the
      // unencrypted values so that they are saved if necessary. This is
      // particularly important when a previously encrypted field is set to be
      // unencrypted.
      // @see \Drupal\Core\Entity\Sql\SqlContentEntityStorage::saveToDedicatedTables()
      // @see \Drupal\Core\Entity\ContentEntityStorageBase::hasFieldValueChanged()
      if (isset($translated_entity->original) && $translated_entity->original instanceof ContentEntityInterface) {
        if ($translated_entity->original->hasTranslation($language->getId())) {
          $translated_original = $translated_entity->original->getTranslation($language->getId());
          $this->setEncryptedFieldValues($translated_original, 'getUnencryptedPlaceholderValue');
        }
      }
      // All the encrypted fields have now being processed and their values
      // moved to encrypted field storage. It's time to encrypt that field.
      /** @var \Drupal\field_encrypt\Plugin\Field\FieldType\EncryptedFieldStorageItem $encrypted_field */
      $encrypted_field = $translated_entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME)[0];
      $encrypted_field->encrypt();
    }
  }

  /**
   * Decrypts an entity's encrypted fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to decrypt.
   *
   * @see field_encrypt_entity_storage_load()
   */
  public function decryptEntity(ContentEntityInterface $entity): void {
    // Make sure there is a base field to store encrypted data.
    if (!$entity->hasField(static::ENCRYPTED_FIELD_STORAGE_NAME)) {
      return;
    }

    // Process all language variants of the entity.
    $languages = $entity->getTranslationLanguages();
    foreach ($languages as $language) {
      $translated_entity = $entity->getTranslation($language->getId());
      $this->setEncryptedFieldValues($translated_entity);
    }
  }

  /**
   * Encrypts a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being encrypted.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to encrypt.
   */
  protected function encryptField(ContentEntityInterface $entity, FieldItemListInterface $field): void {
    $definition = $field->getFieldDefinition();
    $storage = $definition->getFieldStorageDefinition();

    $field_value = $field->getValue();
    // Get encryption settings from storage.
    if ($storage->isBaseField()) {
      $properties = $storage->getSetting('field_encrypt.properties') ?? [];
    }
    else {
      /** @var \Drupal\field\FieldStorageConfigInterface $storage */
      $properties = $storage->getThirdPartySetting('field_encrypt', 'properties', []);
    }
    // Process the field with the given encryption provider.
    foreach ($field_value as $delta => &$value) {
      // Process each of the field properties that exist.
      foreach ($properties as $property_name) {
        if (isset($value[$property_name])) {
          $value[$property_name] = $this->encryptFieldValue($entity, $field, $delta, $property_name, $value[$property_name]);
        }
      }
    }
    // Set the new value. Calling setValue() updates the entity too.
    $field->setValue($field_value);
  }

  /**
   * Sets an entity's encrypted fields to a value.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to set the values on.
   * @param string|null $method
   *   (optional) The method call to set the value. By default, this code sets
   *   the encrypted field to the decrypted value. If $method is set then it is
   *   called with the entity, the field and the property name.
   */
  protected function setEncryptedFieldValues(ContentEntityInterface $entity, ?string $method = NULL): void {
    $storage = $entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME)->decrypted_value ?? [];

    foreach ($storage as $field_name => $decrypted_field) {
      if (!$entity->hasField($field_name)) {
        continue;
      }
      $field = $entity->get($field_name);
      $field_value = $field->getValue();
      // Process each of the field properties that exist.
      foreach ($field_value as $delta => &$value) {
        if (!isset($storage[$field_name][$delta])) {
          continue;
        }
        // Process each of the field properties that exist.
        foreach ($decrypted_field[$delta] as $property_name => $decrypted_value) {
          if ($method) {
            // @see \Drupal\field_encrypt\ProcessEntities::getUnencryptedPlaceholderValue()
            $value[$property_name] = $this->$method($entity, $field, $property_name);
          }
          else {
            $value[$property_name] = $decrypted_value;
          }
        }
      }
      // Set the new value. Calling setValue() updates the entity too.
      $field->setValue($field_value);
    }
  }

  /**
   * Gets the encrypted fields from the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity with encrypted fields.
   *
   * @return iterable
   *   An iterator over the fields which are configured to be encrypted.
   */
  protected function getEncryptedFields(ContentEntityInterface $entity): iterable {
    foreach ($entity->getFields() as $field) {
      /** @var \Drupal\field\FieldStorageConfigInterface $storage */
      $storage = $field->getFieldDefinition()->getFieldStorageDefinition();

      $is_base_field = $storage->isBaseField();
      // Check if the field is encrypted.
      if (
        ($is_base_field && $storage->getSetting('field_encrypt.encrypt')) ||
        (!$is_base_field && $storage->getThirdPartySetting('field_encrypt', 'encrypt', FALSE))
      ) {
        yield $field;
      }
    }
  }

  /**
   * Moves the unencrypted value to the encrypted field storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to process.
   * @param int $delta
   *   The field delta.
   * @param string $property_name
   *   The name of the property.
   * @param mixed $value
   *   The value to decrypt.
   *
   * @return mixed
   *   The encrypted field database value.
   */
  protected function encryptFieldValue(ContentEntityInterface $entity, FieldItemListInterface $field, int $delta, string $property_name, mixed $value = ''): mixed {
    // Do not modify empty strings.
    if ($value === '') {
      return '';
    }

    $storage = $entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME)->decrypted_value ?? [];

    // Return value to store for unencrypted property.
    // We can't set this to NULL, because then the field values are not
    // saved, so we can't replace them with their unencrypted value on load.
    $placeholder_value = $this->getUnencryptedPlaceholderValue($entity, $field, $property_name);
    if ($placeholder_value !== $value) {
      $storage[$field->getName()][$delta][$property_name] = $value;
      /** @var \Drupal\field_encrypt\Plugin\Field\FieldType\EncryptedFieldStorageItem $encrypted_field */
      $encrypted_field = $entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME);
      $encrypted_field->decrypted_value = $storage;
      return $placeholder_value;
    }

    // If not allowed, but we still have an encrypted value remove it.
    if (isset($storage[$field->getName()][$delta][$property_name])) {
      unset($storage[$field->getName()][$delta][$property_name]);
      /** @var \Drupal\field_encrypt\Plugin\Field\FieldType\EncryptedFieldStorageItem $encrypted_field */
      $encrypted_field = $entity->get(static::ENCRYPTED_FIELD_STORAGE_NAME);
      $encrypted_field->decrypted_value = $storage;
    }
    return $value;
  }

  /**
   * Render a placeholder value to be stored in the unencrypted field storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to encrypt fields on.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to encrypt.
   * @param string $property_name
   *   The property to encrypt.
   *
   * @return mixed
   *   The unencrypted placeholder value.
   */
  protected function getUnencryptedPlaceholderValue(ContentEntityInterface $entity, FieldItemListInterface $field, string $property_name): mixed {
    $unencrypted_storage_value = NULL;

    $property_definitions = $field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    $data_type = $property_definitions[$property_name]->getDataType();

    switch ($data_type) {
      case "string":
      case "email":
      case "datetime_iso8601":
      case "duration_iso8601":
      case "uri":
      case "filter_format":
        // On Drupal versions < 10.2, Decimal fields are string data type, but
        // get stored as number.
        if ($field->getFieldDefinition()->getType() == "decimal") {
          $unencrypted_storage_value = 0;
        }
        else {
          $unencrypted_storage_value = static::ENCRYPTED_VALUE;
        }
        break;

      case "integer":
      case "boolean":
      case "float":
      case "decimal":
        $unencrypted_storage_value = 0;
        break;
    }

    // Allow field storages to override the placeholders.
    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = $field->getFieldDefinition()->getFieldStorageDefinition();
    if ($field_storage->isBaseField()) {
      $placeholder_overrides = $field_storage->getSetting('field_encrypt.placeholders') ?? [];
    }
    else {
      $placeholder_overrides = $field_storage->getThirdPartySetting('field_encrypt', 'placeholders') ?? [];
    }

    return $placeholder_overrides[$property_name] ?? $unencrypted_storage_value;
  }

  /**
   * Sets an entity's encrypted field's cache tags appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being viewed.
   * @param array $build
   *   A renderable array representing the entity content.
   *
   * @see field_encrypt_entity_view()
   */
  public function entitySetCacheTags(ContentEntityInterface $entity, array &$build): void {
    foreach ($this->getEncryptedFields($entity) as $field) {
      $build[$field->getName()]['#cache']['max-age'] = 0;
    }
  }

}
