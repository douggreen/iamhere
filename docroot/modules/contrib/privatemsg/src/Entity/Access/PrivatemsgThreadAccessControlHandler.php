<?php

namespace Drupal\privatemsg\Entity\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access control handler for private message entities.
 */
class PrivatemsgThreadAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

  /**
   * Constructs the block access control handler instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(EntityTypeInterface $entity_type, UserDataInterface $user_data) {
    parent::__construct($entity_type);
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('user.data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $user = User::load($account->id());
    if ($user) {
      $is_messages_enabled = $this->userData->get('privatemsg', $user->id(), 'enable');
      if ($is_messages_enabled) {
        switch ($operation) {
          case 'view':
            if ($account->hasPermission('administer site configuration')) {
              return AccessResult::allowed();
            }
            if ($account->hasPermission('privatemsg write messages') && $entity->getOwnerId() === $account->id()) {
              return AccessResult::allowed();
            }
            break;

          case 'change_tags':
          case 'mark_unread':
          case 'mark_read':
            if ($account->hasPermission('privatemsg use messages actions')) {
              return AccessResult::allowed();
            }
            break;
        }
      }
    }
    return AccessResult::forbidden();
  }

}
