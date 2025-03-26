<?php

namespace Drupal\privatemsg\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\privatemsg\PrivateMsgService;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form controller for the privatemsg message entity edit forms.
 */
class PrivatemsgMessageForm extends ContentEntityForm {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

  /**
   * Constructs a PrivatemsgMessageForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, PrivateMsgService $privatemsg_service, MailManagerInterface $mail_manager, UserDataInterface $user_data) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->privateMsgService = $privatemsg_service;
    $this->mailManager = $mail_manager;
    $this->userData = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('privatemsg.common'),
      $container->get('plugin.manager.mail'),
      $container->get('user.data'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#value'] = $this->t('Send message');

    $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser()->id());
    $is_messages_enabled = $this->userData->get('privatemsg', $current_user->id(), 'enable');

    if ($is_messages_enabled) {
      $route_name = $this->getRouteMatch()->getRouteName();
      if ($route_name === 'entity.privatemsg_message.add') {
        $form['thread_members'] = [
          '#type' => 'privatemsg_autocomplete',
          '#target_type' => 'user',
          '#selection_handler' => 'default:privatemsg',
          '#title' => $this->t('To:'),
          '#description' => $this->t('Enter the recipient, separate recipients with commas.'),
          '#tags' => TRUE,
          '#required' => TRUE,
          '#weight' => '-2',
        ];

        $user_parameter = $this->getRouteMatch()->getParameter('user');
        if ($user_parameter > 0) {
          $user = $this->entityTypeManager->getStorage('user')->load($user_parameter);
          if ($user) {
            $is_messages_enabled = $this->userData->get('privatemsg', $user->id(), 'enable');
            if ($is_messages_enabled) {
              $form['thread_members']['#default_value'] = $user;
            }
            else {
              throw new NotFoundHttpException();
            }
          }
        }

        $form['thread_subject'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Subject'),
          '#weight' => '-1',
        ];
      }
    }
    else {
      throw new NotFoundHttpException();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $route_name = $this->routeMatch->getRouteName();
    if ($route_name === 'entity.privatemsg_message.add') {
      $thread_members = $form_state->getValue('thread_members');
      if (is_null($thread_members)) {
        $form_state->setErrorByName('thread_members', $this->t('You must include at least one valid recipient.'));
        return;
      }
      // Remove duplicates.
      $thread_members = array_map('unserialize', array_unique(array_map('serialize', $thread_members)));
      $form_state->setValue('thread_members', $thread_members);
    }

    if (($route_name === 'entity.privatemsg_thread.canonical')) {
      /** @var \Drupal\privatemsg\Entity\PrivatemsgThread $thread */
      $thread = $this->getRouteMatch()->getParameter('privatemsg_thread');
      $thread_members = $thread->getMembers();
      if (count($thread_members) < 3) {
        foreach ($thread_members as $thread_member) {
          if ($thread_member->id() !== $this->currentUser()->id()) {
            $is_blocked = $this->privateMsgService->isUserBlocked($thread_member->id(), $this->currentUser()->id());
            if ($is_blocked) {
              /** @var \Drupal\user\Entity\User $user */
              $user = $this->entityTypeManager->getStorage('user')->load($thread_member->id());
              if ($user) {
                $form_state->setError($form['message'], $this->t('You are not permitted to send messages to user %name', [
                  '%name' => $user->getDisplayName(),
                ]));
              }
            }
          }
        }
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    if ($result === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('The message has been sent.'));
      $this->logger('privatemsg')->notice('Created new privatemsg message');

      $route_name = $this->getRouteMatch()->getRouteName();
      $message_entity = NULL;
      $thread = NULL;

      if ($route_name === 'entity.privatemsg_message.add') {
        $thread_subject = $form_state->getValue('thread_subject');
        $body = $form_state->getValue('message');
        $body_value = $body[0]['value'];

        if (empty($thread_subject)) {
          $thread_subject = strip_tags($body_value);
          $thread_subject = mb_substr($thread_subject, 0, 30);
        }

        $already_member = FALSE;
        $thread_members = $form_state->getValue('thread_members');
        foreach ($thread_members as $thread_member) {
          if ((int) $thread_member['target_id'] === $this->currentUser()->id()) {
            $already_member = TRUE;
          }
        }
        if (!$already_member) {
          $thread_members[]['target_id'] = $this->currentUser()->id();
        }

        if ($form_state->getFormObject() instanceof EntityForm) {
          $form_object = $form_state->getFormObject();
          $message_entity = $form_object->getEntity();
          if ($message_entity) {
            $group_id = $this->privateMsgService->getLastThreadGroupNumber();
            ++$group_id;

            foreach ($thread_members as $thread_member) {
              $data = [
                'subject' => $thread_subject,
                'members' => $thread_members,
                'owner' => $thread_member,
                'group' => $group_id,
                'private_messages' => $message_entity->id(),
                'updated_custom' => $this->time->getRequestTime(),
              ];
              $thread = $this->entityTypeManager
                ->getStorage('privatemsg_thread')
                ->create($data);
              $thread->save();
            }
            $thread = $this->entityTypeManager
              ->getStorage('privatemsg_thread')
              ->loadByProperties([
                'private_messages' => $message_entity->id(),
                'owner' => $this->currentUser()->id(),
              ]);
            $thread = reset($thread);
            if ($thread) {
              $form_state->setRedirect('entity.privatemsg_thread.canonical', ['privatemsg_thread' => $thread->id()]);
            }
          }
        }
      }
      elseif ($route_name === 'entity.privatemsg_thread.canonical') {
        /** @var \Drupal\privatemsg\Entity\PrivatemsgThread $thread */
        $thread = $this->getRouteMatch()->getParameter('privatemsg_thread');
        if ($thread && $form_state->getFormObject() instanceof EntityForm) {
          $form_object = $form_state->getFormObject();
          $message_entity = $form_object->getEntity();
          if ($message_entity) {
            $group_id = $thread->getGroup();
            $threads = $this->entityTypeManager
              ->getStorage('privatemsg_thread')
              ->loadByProperties([
                'group' => $group_id,
              ]);
            foreach ($threads as $thread) {
              $thread->private_messages[] = ['target_id' => $message_entity->id()];
              $thread->updated_custom = $this->time->getRequestTime();
              $thread->save();
            }
          }
        }
      }

      $members = $thread->getMembers();
      $message_owner_id = $message_entity->getOwnerId();
      foreach ($members as $member) {
        if ($member->id() !== $message_owner_id) {
          $is_notify_enabled = $this->userData->get('privatemsg', $member->id(), 'notify');
          if ($is_notify_enabled) {
            $thread = $this->privateMsgService->getThreadFromMessage($message_entity->id(), $member->id());
            if ($thread) {
              $params['subject'] = $this->t('New private message');
              $params['message'] = $this->t('You have received a new private message. To read your message, follow this link: http://%host/messages/view/%thread_id', [
                '%host' => $this->getRequest()->getHost(),
                '%thread_id' => $thread->id(),
              ]);
              if (!empty($member->getEmail())) {
                $this->mailManager->mail('privatemsg', 'privatemsg', $member->getEmail(), 'ru', $params);
              }
            }
          }
        }
      }
    }
    return $result;
  }

}
