<?php

namespace Drupal\privatemsg\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a privatemsg thread entity type.
 */
interface PrivatemsgThreadInterface extends ContentEntityInterface {

  /**
   * Retrieve the ids of the members of the private message thread.
   */
  public function getMembersId();

  /**
   * Check if the user with the given ID is a member of the thread.
   *
   * @param int $id
   *   The User ID of the user to check.
   *
   * @return bool
   *   - TRUE if the user is a member of the thread
   *   - FALSE if they are not
   */
  public function isMember($id);

  /**
   * Get messages count in the thread.
   */
  public function getMessagesCount();

  /**
   * Get owner id.
   *
   * @return int
   *   Integer value.
   */
  public function getOwnerId();

  /**
   * Add a history record to the current thread for the given user.
   */
  public function addHistoryRecord(int $user_id);

  /**
   * Retrieve the members of the private message thread.
   */
  public function getMembers();

  /**
   * Retrieve all private messages attached to this thread.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface
   *   A list of private messages attached to this thread
   */
  public function getMessages();

  /**
   * Remove message from thread.
   *
   * @param string $message_id
   *   Message id.
   *
   * @return $this
   */
  public function removeMessageFromThread(string $message_id);

  /**
   * Get thread group.
   */
  public function getGroup();

  /**
   * Get thread tags.
   */
  public function getTags();

  /**
   * Get thread subject.
   */
  public function getSubject();

}
