<?php

namespace Drupal\privatemsg_migration_d7\Plugin\migrate\destination;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Row;
use Drupal\privatemsg\PrivateMsgService;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Destination plugin for creating threads.
 *
 * @MigrateDestination(
 *   id = "privatemsg_thread",
 * )
 */
class PrivatemsgThread extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The database connection.
   */
  protected Connection $database;

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
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    EntityTypeManagerInterface $entity_type_manager,
    PrivateMsgService $privatemsg_service,
    Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->entityTypeManager = $entity_type_manager;
    $this->privateMsgService = $privatemsg_service;
    $this->database = $database;
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
      $container->get('privatemsg.common'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'group_id' => [
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
    $thread_members = $row->getDestinationProperty('members');
    $messages_for_thread = $row->getDestinationProperty('messages');
    $subject = $row->getDestinationProperty('subject');
    $updated_custom = $row->getDestinationProperty('updated_custom');
    $tags = $row->getDestinationProperty('tags');
    $is_new = $row->getDestinationProperty('is_new');

    $group_id = $this->privateMsgService->getLastThreadGroupNumber();
    ++$group_id;
    foreach ($thread_members as $thread_member) {
      $tags_for_thread = [];
      foreach ($tags as $tag) {
        if ($thread_member === $tag['uid'] && $tag['tag'] !== 'Inbox') {
          $tag_exists = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
            'name' => $tag['tag'],
            'field_privatemsg_tag_author' => $tag['uid'],
          ]);
          $tag_exists = reset($tag_exists);
          /** @var \Drupal\taxonomy\Entity\Term $tag_exists */
          if (!$tag_exists) {
            $new_tag = Term::create([
              'name' => $tag['tag'],
              'vid' => 'privatemsg_tags',
              'field_privatemsg_tag_author' => $tag['uid'],
            ]);
            $new_tag->save();
            $tags_for_thread[] = $new_tag->id();
          }
          else {
            $tags_for_thread[] = $tag_exists->id();
          }
        }
      }

      $data = [
        'subject' => $subject,
        'members' => $thread_members,
        'owner' => $thread_member,
        'group' => $group_id,
        'private_messages' => $messages_for_thread,
        'updated_custom' => $updated_custom,
        'tags' => $tags_for_thread,
      ];

      $thread_entity = $this->entityTypeManager
        ->getStorage('privatemsg_thread')
        ->create($data);
      $thread_entity->save();

      // If user has unread messages.
      if (isset($is_new[$thread_member])) {
        $this->privateMsgService->markThreadGroupAsUnread($thread_member, $group_id);
      }
      else {
        $this->privateMsgService->updateThreadGroupLastAccessTime($thread_member, $group_id);
      }
    }

    return ['group_id' => $group_id];
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
    $group_id = $destination_identifier['group_id'];

    $threads = $this->entityTypeManager->getStorage('privatemsg_thread')->loadByProperties([
      'group' => $group_id,
    ]);
    foreach ($threads as $thread) {
      $thread->delete();
    }

    $this->database->delete('pm_thread_history')
      ->condition('thread_group', $group_id)
      ->execute();
  }

}
