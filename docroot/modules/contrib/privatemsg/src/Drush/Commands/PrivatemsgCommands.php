<?php

namespace Drupal\privatemsg\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\privatemsg\Entity\PrivatemsgMessage;
use Drupal\privatemsg\PrivateMsgService;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush command file.
 */
class PrivatemsgCommands extends DrushCommands {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Constructs a PrivatemsgCommands object.
   *
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(PrivateMsgService $privatemsg_service, EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    parent::__construct();
    $this->privateMsgService = $privatemsg_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('privatemsg.common'),
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * Migrate data from 1.x to 2.x.
   *
   * @command privatemsg:1to2
   * @aliases pmsg1to2
   */
  public function privatemsg1to2() {
    if ($this->database->schema()->tableExists('pm_index_old') && $this->database->schema()->tableExists('pm_message_old')) {
      $this->output()->writeln('1.x database tables found.');

      $query = $this->database->select('pm_message_old', 'm');
      $query->innerJoin('pm_index_old', 'i', 'i.mid = m.mid');
      $query->fields('m', ['mid', 'author', 'subject', 'body', 'format', 'timestamp']);
      $query->fields('i', ['thread_id', 'recipient']);
      $messages_all = $query->execute()->fetchAll();
      $messages = [];
      foreach ($messages_all as $message) {
        if ($message->author !== $message->recipient) {
          $messages[$message->thread_id][$message->mid] = $message;
        }
      }

      foreach ($messages as $thread) {
        $messages_for_thread = [];
        $thread_members = [];
        $subject = '';
        $updated_custom = NULL;
        foreach ($thread as $thread_message) {
          $message_entity = PrivatemsgMessage::create([
            'id' => $thread_message->mid,
            'owner' => $thread_message->author,
            'message' => [
              'value' => $thread_message->body,
              'format' => 'basic_html',
            ],
            'created' => $thread_message->timestamp,
          ]);
          $message_entity->save();
          $messages_for_thread[] = $message_entity->id();
          $thread_members[$thread_message->author] = $thread_message->author;
          $thread_members[$thread_message->recipient] = $thread_message->recipient;
          $subject = $thread_message->subject;
          $updated_custom = $thread_message->timestamp;
        }

        $group_id = $this->privateMsgService->getLastThreadGroupNumber();
        ++$group_id;
        foreach ($thread_members as $thread_member) {
          $data = [
            'subject' => $subject,
            'members' => $thread_members,
            'owner' => $thread_member,
            'group' => $group_id,
            'private_messages' => $messages_for_thread,
            'updated_custom' => $updated_custom,
          ];
          $thread_entity = $this->entityTypeManager
            ->getStorage('privatemsg_thread')
            ->create($data);
          $thread_entity->save();
        }
      }
      $this->output()->writeln('Migration completed.');
    }
    else {
      $this->output()->writeln('1.x database tables not found.');
    }
  }

}
