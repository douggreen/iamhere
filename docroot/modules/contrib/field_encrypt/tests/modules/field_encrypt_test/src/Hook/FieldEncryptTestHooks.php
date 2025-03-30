<?php

declare(strict_types=1);

namespace Drupal\field_encrypt_test\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements hooks for the field_encrypt_test module.
 */
class FieldEncryptTestHooks {
  use StringTranslationTrait;

  private KeyValueStoreInterface $keyValueStoreFieldEncryptTest;

  public function __construct(
    protected MessengerInterface $messenger,
    #[Autowire(service: 'keyvalue')]
    protected KeyValueFactoryInterface $keyValueFactory,
  ) {
    $this->keyValueStoreFieldEncryptTest = $this->keyValueFactory->get('field_encrypt_test');
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->messenger->addMessage(
        $this->t('Field encrypt test hook_entity_update: @label', ['@label' => $entity->label()])
      );
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $entity): void {
    $this->messenger->addMessage(
      $this->t('Field encrypt test hook_ENTITY_TYPE_update: @label', ['@label' => $entity->label()])
    );
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->messenger->addMessage(
        $this->t('Field encrypt test hook_entity_insert: @label', ['@label' => $entity->label()])
      );
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $entity): void {
    $this->messenger->addMessage(
      $this->t('Field encrypt test hook_ENTITY_TYPE_insert: @label', ['@label' => $entity->label()])
    );
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity instanceof NodeInterface) {
      $this->messenger->addMessage(
        $this->t('Field encrypt test hook_entity_delete: @label', ['@label' => $entity->label()])
      );
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('node_delete')]
  public function nodeDelete(EntityInterface $entity): void {
    $this->messenger->addMessage(
      $this->t('Field encrypt test hook_ENTITY_TYPE_delete: @label', ['@label' => $entity->label()])
    );
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'node' && $this->keyValueStoreFieldEncryptTest->get('create_base_field', FALSE)) {
      $fields['field_encrypt_test_base_field'] = BaseFieldDefinition::create('string')->setLabel('Field Encrypt test base field');
    }
    return $fields;
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if (
      $entity_type->id() === 'node' &&
      $this->keyValueStoreFieldEncryptTest->get('hook_field_encrypt_unencrypted_storage_value_alter', FALSE)
    ) {
      /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
      $fields['title']->setSetting('field_encrypt.placeholders', ['value' => '🐒']);
    }
  }

  /**
   * Implements hook_field_encrypt_unencrypted_storage_value_alter().
   */
  #[Hook('field_encrypt_unencrypted_storage_value_alter')]
  public function fieldEncryptUnencryptedStorageValueAlter(array &$unencrypted_storage_value, array $context): void {
    if ($this->keyValueStoreFieldEncryptTest->get('hook_field_encrypt_unencrypted_storage_value_alter', FALSE)) {
      $entity = $context['entity'];
      $field = $context['field'];
      $property = $context['property'];
      $this->messenger->addMessage("Value alter hook: Entity title: {$entity->label()}");
      $this->messenger->addMessage("Value alter hook: Field name: {$field->getName()}");
      $this->messenger->addMessage("Value alter hook: Property: {$property}");
      $unencrypted_storage_value = ['🐒'];
    }
  }

  /**
   * Implements hook_field_encrypt_allow_encryption().
   */
  #[Hook('field_encrypt_allow_encryption')]
  public function fieldEncryptAllowEncryption(ContentEntityInterface $entity): bool {
    if ($this->keyValueStoreFieldEncryptTest->get('hook_field_encrypt_allow_encryption', FALSE)) {
      $this->messenger->addMessage("Allow encryption hook: Entity title: {$entity->label()}");
      // Only encrypt fields on unpublished nodes.
      if ($entity->getEntityTypeId() === 'node') {
        /** @var \Drupal\node\NodeInterface $entity */
        if ($entity->isPublished()) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

}
