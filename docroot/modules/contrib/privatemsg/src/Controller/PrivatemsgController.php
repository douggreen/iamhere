<?php

namespace Drupal\privatemsg\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Private messages routes.
 */
class PrivatemsgController extends ControllerBase {

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * Constructs a PrivatemsgController.
   *
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   */
  public function __construct(PrivateMsgService $privatemsg_service) {
    $this->privateMsgService = $privatemsg_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('privatemsg.common'),
    );
  }

  /**
   * Remove message.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function removeMessage(Request $request) {
    $thread_id = $request->attributes->get('thread_id');
    $mid = $request->attributes->get('mid');

    $incorrect_response = new Response();
    $incorrect_response->setContent('Incorrect request.');
    $incorrect_response->setStatusCode(400);

    if (!$thread_id || !$mid) {
      return $incorrect_response;
    }

    /** @var \Drupal\privatemsg\Entity\PrivatemsgMessage $message */
    $message = $this->entityTypeManager()->getStorage('privatemsg_message')->load($mid);
    if ($message && $this->currentUser()->hasPermission('privatemsg delete own messages') && $message->getOwnerId() === $this->currentUser()->id()) {
      $message->markMessageAsDeleted();
      $message->save();

      $response = new AjaxResponse();
      $url = Url::fromRoute('entity.privatemsg_thread.canonical', [
        'privatemsg_thread' => $thread_id,
      ]);
      $response->addCommand(new RedirectCommand($url->toString()));
      return $response;
    }

    return $incorrect_response;
  }

  /**
   * Block user.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\Response
   *   Response.
   */
  public function blockUser(Request $request) {
    $user_id = $request->attributes->get('user');

    $incorrect_response = new Response();
    $incorrect_response->setContent('Incorrect request.');
    $incorrect_response->setStatusCode(400);

    if (!$user_id) {
      return $incorrect_response;
    }

    $isBlocked = $this->privateMsgService->isUserBlocked($this->currentUser()->id(), $user_id);
    $response = new AjaxResponse();
    $selector = '#privatemsg-block-user-' . $user_id;

    $url_object = Url::fromRoute('privatemsg.block_user', [
      'user' => $user_id,
    ]);
    $url = $url_object->toString();

    if ($isBlocked) {
      $this->privateMsgService->unblockUser($user_id);
      $text = $this->t('block');
      $content = "<a id=\"privatemsg-block-user-$user_id\" class=\"privatemsg-block-user use-ajax\" href=\"$url\">$text</a>";
      $response->addCommand(new ReplaceCommand($selector, $content));
      return $response;
    }

    $this->privateMsgService->blockUser($user_id);
    $text = $this->t('unblock');
    $content = "<a id=\"privatemsg-block-user-$user_id\" class=\"privatemsg-block-user use-ajax\" href=\"$url\">$text</a>";
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

}
