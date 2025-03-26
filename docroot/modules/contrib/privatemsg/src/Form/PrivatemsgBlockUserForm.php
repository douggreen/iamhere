<?php

namespace Drupal\privatemsg\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Private Messages form.
 */
class PrivatemsgBlockUserForm extends FormBase {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The current user account.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'privatemsg_block_user_form';
  }

  /**
   * Constructs a Form object.
   *
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(PrivateMsgService $privatemsg_service, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->privateMsgService = $privatemsg_service;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('privatemsg.common'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['blocked'] = [
      '#type' => 'privatemsg_autocomplete',
      '#target_type' => 'user',
      '#selection_handler' => 'default:privatemsg_blocked',
      '#title' => $this->t('Block a user'),
      '#description' => $this->t('Separate multiple names with commas.'),
      '#tags' => TRUE,
      '#required' => TRUE,
      '#weight' => 1,
    ];

    $blocked_users_array = $this->privateMsgService->getBlockedByUserId($this->currentUser->id());
    $rows = NULL;
    if ($blocked_users_array) {
      foreach ($blocked_users_array as $blocked_user) {
        $uid = $blocked_user['blocked'];
        /** @var \Drupal\user\Entity\User $user */
        $user = $this->entityTypeManager->getStorage('user')->load($uid);
        if ($user) {
          $user_url = Url::fromRoute('entity.user.canonical', [
            'user' => $uid,
          ]);
          $user_link = Link::fromTextAndUrl($user->getAccountName(), $user_url);
          $unblock_url = Url::fromRoute(
            'privatemsg.block_user',
            ['user' => $uid],
            [
              'attributes' => [
                'class' => ['privatemsg-block-user use-ajax'],
                'id' => ['privatemsg-block-user-' . $uid],
              ],
            ],
          );
          $unblock_link = Link::fromTextAndUrl($this->t('unblock'), $unblock_url);
          $rows[] = [$user_link->toString(), $unblock_link->toString()];
        }
      }
    }

    $form['blocked_users_table'] = [
      '#type' => 'table',
      '#header' => [$this->t('Username'), $this->t('Operations')],
      '#rows' => $rows,
      '#empty' => $this->t('There are no items yet.'),
      '#weight' => 3,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 2,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Block user'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $users = $form_state->getValue('blocked');
    if ($users) {
      foreach ($users as $user) {
        $user_id = $user['target_id'];
        $is_blocked = $this->privateMsgService->isUserBlocked($this->currentUser->id(), $user_id);
        $can_be_blocked = $this->privateMsgService->canBeBlocked($user_id);
        if (!$is_blocked && $can_be_blocked) {
          $this->privateMsgService->blockUser($user_id);
        }
      }
    }
  }

}
