<?php

namespace Drupal\privatemsg;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\privatemsg\Entity\PrivatemsgMessage;
use Drupal\privatemsg\Entity\PrivatemsgThread;

/**
 * Common functions service.
 */
class PrivateMsgService implements PrivateMsgServiceInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The time interface.
   */
  protected TimeInterface $time;

  /**
   * Configuration Factory.
   */
  protected ConfigFactory $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Connection $database, TimeInterface $time, ConfigFactory $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->database = $database;
    $this->time = $time;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadFromMessage(int $message_id, int $user_id) {
    $thread = $this->entityTypeManager->getStorage('privatemsg_thread')->loadByProperties([
      'private_messages' => $message_id,
      'owner' => $user_id,
    ]);
    return reset($thread);
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadThreadCount() {
    $unread_count = 0;
    $threads = $this->getThreadsForUser($this->currentUser->id());
    foreach ($threads as $thread) {
      $last_access_time = $this->getThreadGroupLastAccessTime($this->currentUser->id(), $thread->getGroup());
      $messages = $thread->get('private_messages')->referencedEntities();
      $is_unread_message = FALSE;
      foreach ($messages as $message) {
        if ($last_access_time <= $message->getCreatedTime() && $message->getOwnerId() != $this->currentUser->id()) {
          $is_unread_message = TRUE;
        }
      }
      if ($is_unread_message) {
        $unread_count++;
      }
    }
    return $unread_count;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadsForUser(int $user_id) {
    return $this->entityTypeManager->getStorage('privatemsg_thread')->loadByProperties([
      'owner' => $user_id,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastThreadGroupNumber() {
    $query = $this->database->select('pm_index');
    $query->addExpression('MAX("group")', 'group');
    return $query->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function updateThreadGroupLastAccessTime(int $user_id, int $thread_group) {
    $this->database->update('pm_thread_history')
      ->condition('uid', $user_id)
      ->condition('thread_group', $thread_group)
      ->fields(['access_timestamp' => $this->time->getRequestTime()])
      ->execute();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadGroupLastAccessTime(int $user_id, int $thread_group) {
    return $this->database->select('pm_thread_history', 'pmth')
      ->condition('uid', $user_id)
      ->condition('thread_group', $thread_group)
      ->fields('pmth', ['access_timestamp'])
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function markThreadGroupAsUnread(int $user_id, int $thread_group) {
    $this->database->update('pm_thread_history')
      ->condition('uid', $user_id)
      ->condition('thread_group', $thread_group)
      ->fields(['access_timestamp' => 0])
      ->execute();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isUserBlocked(int $user_id_who, int $user_id_blocked) {
    return $this->database->select('pm_block_user', 'pmbu')
      ->condition('who', $user_id_who)
      ->condition('blocked', $user_id_blocked)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function blockUser(int $user_id) {
    $this->database
      ->insert('pm_block_user')
      ->fields([
        'who' => $this->currentUser->id(),
        'blocked' => $user_id,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function unblockUser(int $user_id) {
    $this->database->delete('pm_block_user')
      ->condition('who', $this->currentUser->id())
      ->condition('blocked', $user_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function canBeBlocked(int $user_id) {
    $config = $this->configFactory->get('privatemsg.settings');
    $unblockable_roles = $config->get('unblockable_roles');
    if (empty($unblockable_roles)) {
      return TRUE;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    if ($user) {
      $user_roles = $user->getRoles();
      foreach ($unblockable_roles as $role) {
        if (in_array($role, $user_roles, TRUE)) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockedByUserId(int $user_id) {
    return $this->database->select('pm_block_user', 'pmbu')
      ->fields('pmbu', ['blocked'])
      ->condition('who', $user_id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * {@inheritdoc}
   */
  public function createNewMessage(int $author_id, string $text, string $format): PrivatemsgMessage {
    $message = PrivatemsgMessage::create([
      'owner' => $author_id,
      'message' => [
        'value' => $text,
        'format' => $format,
      ],
    ]);
    $message->save();
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewThreads(string $subject, array $members): array {
    $threads_group = $this->getLastThreadGroupNumber() + 1;
    $ids = [];
    foreach ($members as $member) {
      $thread = PrivatemsgThread::create([
        'subject' => $subject,
        'owner' => $member,
        'members' => $members,
        'group' => $threads_group,
        'updated_custom' => $this->time->getRequestTime(),
      ]);
      $thread->save();
      $ids[] = $thread->id();
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewMessageAndThreads(int $author_id, string $text, string $format, string $subject, array $members): array {
    $message = $this->createNewMessage($author_id, $text, $format);
    $ids = $this->createNewThreads($subject, $members);
    $this->attachMessageToExistingThreadsByIds($ids, $message->id());
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function attachMessageToExistingThreadsByIds(array $threads_ids, int $message_id) {
    foreach ($threads_ids as $threads_id) {
      /** @var \Drupal\privatemsg\Entity\PrivatemsgThread $thread */
      $thread = $this->entityTypeManager->getStorage('privatemsg_thread')->load($threads_id);
      if ($thread) {
        $thread->private_messages[] = ['target_id' => $message_id];
        $thread->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function attachMessageToExistingThreadsByProperties(array $properties, int $message_id) {
    $threads = $this->entityTypeManager->getStorage('privatemsg_thread')->loadByProperties($properties);
    if ($threads) {
      /** @var \Drupal\privatemsg\Entity\PrivatemsgThread $thread */
      foreach ($threads as $thread) {
        $thread->private_messages[] = ['target_id' => $message_id];
        $thread->save();
      }
    }
  }

}
