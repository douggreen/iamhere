<?php

namespace Drupal\privatemsg\Entity\Builder;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\Registry;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Build handler for private messages.
 */
class PrivatemsgMessageViewBuilder extends EntityViewBuilder {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * Constructs a PrivateMessageThreadViewBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Theme\Registry $themeRegistry
   *   The theme register.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   */
  public function __construct(
    EntityTypeInterface $entityType,
    EntityRepositoryInterface $entityRepository,
    LanguageManagerInterface $languageManager,
    Registry $themeRegistry,
    AccountProxyInterface $current_user,
    EntityDisplayRepositoryInterface $entity_display_repository,
    PrivateMsgService $privatemsg_service,
  ) {
    parent::__construct($entityType, $entityRepository, $languageManager, $themeRegistry, $entity_display_repository);
    $this->currentUser = $current_user;
    $this->privateMsgService = $privatemsg_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entityType) {
    return new static(
      $entityType,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('current_user'),
      $container->get('entity_display.repository'),
      $container->get('privatemsg.common'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'default', $langcode = NULL) {
    $build = parent::view($entity, $view_mode, $langcode);

    /** @var \Drupal\privatemsg\Entity\PrivatemsgThread $thread */
    $thread = $this->privateMsgService->getThreadFromMessage($entity->id(), $this->currentUser->id());
    if ($thread) {
      $last_access_time = $this->privateMsgService->getThreadGroupLastAccessTime($this->currentUser->id(), $thread->getGroup());
      /** @var \Drupal\privatemsg\Entity\PrivatemsgMessage $entity */
      if ($last_access_time <= $entity->getCreatedTime() && $entity->getOwnerId() !== $this->currentUser->id()) {
        $build['#attributes']['class'][] = 'privatemsg-message-unread';
      }

      $members = $thread->getMembers();
      if (count($members) < 3) {
        $recipient = NULL;
        foreach ($members as $member) {
          if ($member->id() !== $this->currentUser->id()) {
            $recipient = $member;
          }
        }
        if ($recipient) {
          $last_access_time = $this->privateMsgService->getThreadGroupLastAccessTime($recipient->id(), $thread->getGroup());
          /** @var \Drupal\privatemsg\Entity\PrivatemsgMessage $entity */
          if ($last_access_time <= $entity->getCreatedTime() && $entity->getOwnerId() === $this->currentUser->id()) {
            $build['#attributes']['class'][] = 'privatemsg-message-recipient-unread';
          }
        }
      }
    }

    return $build;
  }

}
