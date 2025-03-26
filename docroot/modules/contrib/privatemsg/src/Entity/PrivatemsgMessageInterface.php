<?php

namespace Drupal\privatemsg\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining a privatemsg message entity type.
 */
interface PrivatemsgMessageInterface extends ContentEntityInterface {

  /**
   * Get the time at which the private message was created.
   *
   * @return int
   *   A Unix timestamp indicating the time at which the private message was
   *   created.
   */
  public function getCreatedTime();

  /**
   * Get owner id.
   *
   * @return int
   *   Integer value.
   */
  public function getOwnerId();

  /**
   * Mark message as deleted.
   *
   * @return $this
   */
  public function markMessageAsDeleted();

  /**
   * Get mark as deleted time.
   *
   * @return int
   *   The timestamp at which message was mark as deleted.
   */
  public function getMarkAsDeletedTime();

}
