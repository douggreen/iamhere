<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\EventSubscriber;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionEvent;
use Drupal\Core\Field\FieldStorageDefinitionEvents;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_encrypt\StateManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates existing data when field encryption settings are updated.
 */
class ConfigSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * Constructs a new ConfigSubscriber object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected QueueFactory $queueFactory,
    protected StateManager $stateManager,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', 0];
    $events[ConfigEvents::DELETE][] = ['onConfigDelete', 0];
    $events[FieldStorageDefinitionEvents::DELETE] = ['onBaseFieldDelete', 0];
    return $events;
  }

  /**
   * React on the configuration save event.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   *
   * @todo why is this not just using hook_field_storage_update?
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    assert($config instanceof Config);
    if (str_starts_with($config->getName(), 'field.storage.')) {
      $this->onFieldStorageChange($config);
    }

    if (!$config->isNew() && $config->getName() === 'field_encrypt.settings') {
      $this->onFieldEncryptSettingsChange($config);
    }
  }

  /**
   * Reacts to changes in field.storage.*.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The field storage config.
   */
  protected function onFieldStorageChange(Config $config): void {
    // Get the original field_encrypt configuration.
    $original_config = $config->getOriginal('third_party_settings.field_encrypt');

    if ($config->get('third_party_settings.field_encrypt') === $original_config) {
      return;
    }
    // Update existing entities, if data encryption settings changed.
    // Get the entity type and field from the changed config key.
    $storage_name = substr($config->getName(), 14);
    [$entity_type, $field_name] = explode('.', $storage_name, 2);

    // Load the FieldStorageConfig entity that was updated.
    $field_storage_config = FieldStorageConfig::loadByName($entity_type, $field_name);
    if ($field_storage_config) {
      // Phpstan needs this because hasData() is not part of the interface.
      /** @var \Drupal\field\Entity\FieldStorageConfig $field_storage_config */
      if ($field_storage_config->hasData()) {
        // Get entities that need updating, because they contain the field
        // that has its field encryption settings updated.
        $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();
        $query->accessCheck(FALSE);
        // Check if the field is present.
        $query->exists($field_name);
        // Make sure to get all revisions for revisionable entities.
        if ($this->entityTypeManager->getDefinition($entity_type)->isRevisionable()) {
          $query->allRevisions();
        }
        $entity_ids = $query->execute();

        if (!empty($entity_ids)) {
          // Call the Queue API and add items for processing.
          $queue = $this->queueFactory->get('field_encrypt_update_entity_encryption');

          $data = [
            'entity_type' => $entity_type,
          ];
          foreach (array_keys($entity_ids) as $entity_id) {
            $data['entity_id'] = $entity_id;
            $queue->createItem($data);
          }
        }

        $this->messenger->addMessage($this->t('Updates to entities with existing data for this field have been queued to be processed. You should immediately <a href=":url">run this process manually</a>. Alternatively, the updates will be performed automatically by cron.', [
          ':url' => Url::fromRoute('field_encrypt.process_queue')
            ->toString(),
        ]));
      }
    }

    // Update the field_encrypt module's state.
    $this->stateManager->update();
  }

  /**
   * Reacts to changes in field_encrypt.settings.
   *
   * @param \Drupal\Core\Config\Config $config
   *   The field_encrypt.settings config object.
   */
  protected function onFieldEncryptSettingsChange(Config $config): void {
    if ($config->getOriginal('make_entities_uncacheable') !== $config->get('make_entities_uncacheable')) {
      $this->stateManager->onFieldEncryptSettingsCacheChange();
    }
  }

  /**
   * React on the configuration delete event.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigDelete(ConfigCrudEvent $event): void {
    $config = $event->getConfig();
    if (str_starts_with($config->getName(), 'field.storage.')) {
      $this->stateManager->update();
    }
  }

  /**
   * React to a base field being deleted.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionEvent $event
   *   The field storage event.
   */
  public function onBaseFieldDelete(FieldStorageDefinitionEvent $event): void {
    // @todo as this makes a configuration change should we disable during
    //   configuration import?
    $field = $event->getFieldStorageDefinition();
    if ($event->getFieldStorageDefinition()->isBaseField()) {
      /** @var \Drupal\field_encrypt\Entity\FieldEncryptEntityType $field_encrypt_settings */
      $field_encrypt_settings = $this->entityTypeManager->getStorage('field_encrypt_entity_type')->load($field->getTargetEntityTypeId());
      if ($field_encrypt_settings && $field_encrypt_settings->hasBaseField($field->getName())) {
        $field_encrypt_settings->removeBaseField($field->getName());
        empty($field_encrypt_settings->getBaseFields()) ? $field_encrypt_settings->delete() : $field_encrypt_settings->save();
      }
    }
  }

}
