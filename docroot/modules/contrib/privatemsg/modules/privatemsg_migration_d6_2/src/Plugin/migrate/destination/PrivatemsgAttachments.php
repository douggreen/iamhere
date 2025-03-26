<?php

namespace Drupal\privatemsg_migration_d6_2\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination plugin for creating attachments.
 *
 * @MigrateDestination(
 *   id = "privatemsg_attachments",
 * )
 */
class PrivatemsgAttachments extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a PrivatemsgThread object.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The current migration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
      ],
      'mid' => [
        'type' => 'integer',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $fid = $row->getDestinationProperty('fid');
    $mid = $row->getDestinationProperty('mid');

    $message = $this->entityTypeManager->getStorage('privatemsg_message')->load($mid);
    if ($message) {
      $attachments = $message->get('field_attachments')->getValue();
      $attachments[] = [
        'target_id' => $fid,
      ];
      $message->set('field_attachments', $attachments);
      $message->save();
    }

    return ['fid' => $fid, 'mid' => $mid];
  }

  /**
   * We add support to rollback.
   */
  public function supportsRollback() {
    return TRUE;
  }

  /**
   * Rollback process.
   */
  public function rollback(array $destination_identifier) {
    $mid = $destination_identifier['mid'];
    $message = $this->entityTypeManager->getStorage('privatemsg_message')->load($mid);
    if ($message) {
      $message->set('field_attachments', []);
      $message->save();
    }
  }

}
