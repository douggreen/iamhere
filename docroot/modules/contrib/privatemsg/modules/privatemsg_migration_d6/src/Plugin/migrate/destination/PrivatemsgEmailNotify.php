<?php

namespace Drupal\privatemsg_migration_d6\Plugin\migrate\destination;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination plugin for creating email notify.
 *
 * @MigrateDestination(
 *   id = "privatemsg_email_notify",
 * )
 */
class PrivatemsgEmailNotify extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

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
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    UserDataInterface $user_data,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->userData = $user_data;
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
      $container->get('user.data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'user_id' => [
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
    $user_id = $row->getDestinationProperty('user_id');
    $email_notify_is_enabled = $row->getDestinationProperty('email_notify_is_enabled');
    $this->userData->set('privatemsg', $user_id, 'enable', 1);
    $this->userData->set('privatemsg', $user_id, 'notify', $email_notify_is_enabled);
    return ['user_id' => $user_id];
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
    $user_id = $destination_identifier['user_id'];
    $this->userData->set('privatemsg', $user_id, 'enable', 0);
    $this->userData->set('privatemsg', $user_id, 'notify', 0);
  }

}
