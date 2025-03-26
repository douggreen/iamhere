<?php

namespace Drupal\privatemsg;

use Drupal\privatemsg\Entity\PrivatemsgMessage;

/**
 * The interface for the Private Message Service.
 */
interface PrivateMsgServiceInterface {

  /**
   * Get thread from message.
   */
  public function getThreadFromMessage(int $message_id, int $user_id);

  /**
   * Get unread threads count.
   */
  public function getUnreadThreadCount();

  /**
   * Retrieve a list of thread IDs for the user.
   */
  public function getThreadsForUser(int $user_id);

  /**
   * Get last thread group number.
   */
  public function getLastThreadGroupNumber();

  /**
   * Update the last access time of group of threads for the given user.
   */
  public function updateThreadGroupLastAccessTime(int $user_id, int $thread_group);

  /**
   * Get the last access time of group of threads for the given user.
   */
  public function getThreadGroupLastAccessTime(int $user_id, int $thread_group);

  /**
   * Mark thread group as unread.
   */
  public function markThreadGroupAsUnread(int $user_id, int $thread_group);

  /**
   * Check if user blocked by current user.
   */
  public function isUserBlocked(int $user_id_who, int $user_id_blocked);

  /**
   * Block user.
   */
  public function blockUser(int $user_id);

  /**
   * Unblock user.
   */
  public function unblockUser(int $user_id);

  /**
   * Check if user can be blocked.
   */
  public function canBeBlocked(int $user_id);

  /**
   * Get list of blocked users by user id.
   */
  public function getBlockedByUserId(int $user_id);

  /**
   * Create new message programmatically without any thread.
   */
  public function createNewMessage(int $author_id, string $text, string $format): PrivatemsgMessage;

  /**
   * Create new threads programmatically without any messages.
   */
  public function createNewThreads(string $subject, array $members): array;

  /**
   * Create new message and new threads programmatically (the most used).
   */
  public function createNewMessageAndThreads(int $author_id, string $text, string $format, string $subject, array $members): array;

  /**
   * Attach existing message to existing threads programmatically.
   */
  public function attachMessageToExistingThreadsByIds(array $threads_ids, int $message_id);

  /**
   * Attach existing message to existing threads programmatically.
   */
  public function attachMessageToExistingThreadsByProperties(array $properties, int $message_id);

}
