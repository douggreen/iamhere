<?php

namespace Drupal\privatemsg\Plugin\Action;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\privatemsg\PrivateMsgService;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mark thread as unread.
 *
 * @Action(
 *   id = "privatemsg_read_thread_action",
 *   label = @Translation("Mark as read"),
 *   type = "privatemsg_thread",
 *   confirm = FALSE,
 *   requirements = {
 *     "_permission" = "privatemsg use messages actions",
 *   },
 * )
 */
class PrivatemsgReadThreadAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * Object constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin Id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrivateMsgService $privatemsg_service, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->privateMsgService = $privatemsg_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('privatemsg.common'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('mark_read', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL) {
    if ($entity) {
      $this->privateMsgService->updateThreadGroupLastAccessTime($this->currentUser->id(), $entity->getGroup());
      return 'Marked selected threads as read';
    }

    return 'You must first select one (or more) messages before you can take that action.';
  }

}
