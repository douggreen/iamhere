<?php

namespace Drupal\privatemsg\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for private message entities.
 */
class PrivatemsgMessageAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * Constructs a PrivatemsgMessageAccessControlHandler entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   */
  public function __construct(EntityTypeInterface $entity_type, PrivateMsgService $privatemsg_service) {
    parent::__construct($entity_type);
    $this->privateMsgService = $privatemsg_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('privatemsg.common'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        if ($account->hasPermission('administer site configuration')) {
          return AccessResult::allowed();
        }
        if ($account->hasPermission('privatemsg write messages')) {
          if ($entity->getOwnerId() === $account->id()) {
            return AccessResult::allowed();
          }
          $thread = $this->privateMsgService->getThreadFromMessage($entity->id(), $account->id());
          if ($thread && $thread->isMember($account->id())) {
            return AccessResult::allowed();
          }
        }
    }
    return AccessResult::forbidden();
  }

}
