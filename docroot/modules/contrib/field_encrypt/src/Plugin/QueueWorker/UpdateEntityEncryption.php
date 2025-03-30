<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Queue Worker that updates an entity's encryption on cron run.
 *
 * This re-saves the entity causing it to use the current field encryption
 * settings. This can:
 * - encrypt fields that have become encrypted after the entity was last saved
 * - decrypt fields that no longer are set to be encrypted
 * - change the encryption profile that is used.
 */
#[QueueWorker(
  id: "field_encrypt_update_entity_encryption",
  title: new TranslatableMarkup("Field encrypt: update encryption profile."),
  cron: ["time" => 15]
)]
class UpdateEntityEncryption extends QueueWorkerBase implements ContainerFactoryPluginInterface, FieldEncryptQueueWorkerInterface {
  use StringTranslationTrait;

  /**
   * Creates a new UpdateEntityEncryption object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem(mixed $data): void {
    $entity_type = $this->entityTypeManager->getDefinition($data['entity_type']);
    // @todo Remove the type hint below when support for Drupal 10 is dropped.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($data['entity_type']);
    $is_revisionable = $entity_type->isRevisionable();
    if ($is_revisionable) {
      $entity = $storage->loadRevision($data['entity_id']);
    }
    else {
      $entity = $storage->load($data['entity_id']);
    }
    // If the entity no longer exists then there is nothing to do.
    if (empty($entity)) {
      return;
    }
    // Don't create unnecessary revisions.
    if ($is_revisionable) {
      $entity->setNewRevision(FALSE);
    }
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function batchMessage(array $data): TranslatableMarkup {
    return $this->t('Updating @entity_type with ID @entity_id to use the latest field encryption settings', [
      '@entity_type' => $this->entityTypeManager->getDefinition($data['entity_type'])->getSingularLabel(),
      '@entity_id' => $data['entity_id'],
    ]);
  }

}
