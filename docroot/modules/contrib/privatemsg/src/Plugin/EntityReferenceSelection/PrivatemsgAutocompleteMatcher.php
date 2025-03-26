<?php

namespace Drupal\privatemsg\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityAutocompleteMatcher;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\privatemsg\PrivateMsgService;
use Drupal\user\UserDataInterface;

/**
 * Custom autocomplete matcher.
 */
class PrivatemsgAutocompleteMatcher extends EntityAutocompleteMatcher {

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Common functions.
   */
  protected PrivateMsgService $privateMsgService;

  /**
   * The user data service.
   */
  protected UserDataInterface $userData;

  /**
   * Constructs an PrivatemsgAutocompleteMatcher object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\privatemsg\PrivateMsgService $privatemsg_service
   *   Common functions.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   */
  public function __construct(SelectionPluginManagerInterface $selection_manager, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, ConfigFactory $config_factory, PrivateMsgService $privatemsg_service, UserDataInterface $user_data) {
    parent::__construct($selection_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->configFactory = $config_factory;
    $this->privateMsgService = $privatemsg_service;
    $this->userData = $user_data;
  }

  /**
   * Gets matched labels based on a given search string.
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = [];
    $options = [
      'target_type' => $target_type,
      'handler' => $selection_handler,
      'handler_settings' => $selection_settings,
    ];
    /** @var \Drupal\privatemsg\Plugin\EntityReferenceSelection\PrivatemsgUserSelection $handler */
    $handler = $this->selectionManager->getInstance($options);

    if ($selection_handler === 'default:privatemsg') {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          $user = $this->entityTypeManager->getStorage($target_type)->load($entity_id);
          if ($user) {
            $is_messages_enabled = $this->userData->get('privatemsg', $user->id(), 'enable');
            $is_blocked = $this->privateMsgService->isUserBlocked($user->id(), $this->currentUser->id());
            if ($is_messages_enabled && !$is_blocked && $user->id() !== $this->currentUser->id()) {
              $key = "{$label} ({$entity_id})";
              // Strip things like starting/trailing white spaces, line breaks
              // and tags.
              $key = preg_replace('/\\s\\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
              // Names containing commas or quotes must be wrapped in quotes.
              $key = Tags::encode($key);
              $label .= ' (' . $entity_id . ')';
              $matches[] = [
                'value' => $key,
                'label' => $label,
              ];
            }
          }
        }
      }
      if ($this->currentUser->hasPermission('privatemsg send to role')) {
        $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
        $config = $this->configFactory->get('privatemsg.settings');
        $allowed_roles = $config->get('allowed_roles');
        foreach ($roles as $role) {
          if (!in_array($role->id(), $allowed_roles, TRUE)) {
            continue;
          }
          if (str_contains(strtolower($role->label()), strtolower($string))) {
            $matches[] = [
              'value' => $role->label() . ' (role)',
              'label' => $role->label() . ' (role)',
            ];
          }
        }
      }
    }
    elseif ($selection_handler === 'default:privatemsg_tag') {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $this->entityTypeManager->getStorage($target_type)->load($entity_id);
          if ($term) {
            $author = $term->get('field_privatemsg_tag_author')->referencedEntities();
            $author = reset($author);
            if ($author->id() === $this->currentUser->id()) {
              $key = "{$label} ({$entity_id})";
              // Strip things like starting/trailing white spaces, line breaks
              // and tags.
              $key = preg_replace('/\\s\\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
              // Names containing commas or quotes must be wrapped in quotes.
              $key = Tags::encode($key);
              $label .= ' (' . $entity_id . ')';
              $matches[] = [
                'value' => $key,
                'label' => $label,
              ];
            }
          }
        }
      }
    }
    elseif ($selection_handler === 'default:privatemsg_views') {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);
      $user_threads = $this->privateMsgService->getThreadsForUser($this->currentUser->id());
      $members_ids = [];
      foreach ($user_threads as $thread) {
        $members_ids[] = $thread->getMembersId();
      }
      if (!empty($members_ids)) {
        // Flat array.
        $members_ids = array_merge(...$members_ids);
        // Remove duplicates.
        $members_ids = array_unique($members_ids);
        // Remove current user id from array.
        $members_ids = array_filter($members_ids, fn ($m) => $m !== $this->currentUser->id());
      }
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          if (in_array((string) $entity_id, $members_ids, TRUE)) {
            $key = "{$label} ({$entity_id})";
            // Strip things like starting/trailing white spaces, line breaks
            // and tags.
            $key = preg_replace('/\\s\\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
            // Names containing commas or quotes must be wrapped in quotes.
            $key = Tags::encode($key);
            $label .= ' (' . $entity_id . ')';
            $matches[] = [
              'value' => $key,
              'label' => $label,
            ];
          }
        }
      }
    }
    elseif ($selection_handler === 'default:privatemsg_blocked') {
      // Get an array of matching entities.
      $match_operator = !empty($selection_settings['match_operator']) ? $selection_settings['match_operator'] : 'CONTAINS';
      $entity_labels = $handler->getReferenceableEntities($string, $match_operator, 10);
      // Loop through the entities and convert them into autocomplete output.
      foreach ($entity_labels as $values) {
        foreach ($values as $entity_id => $label) {
          $user = $this->entityTypeManager->getStorage($target_type)->load($entity_id);
          if ($user) {
            $is_messages_enabled = $this->userData->get('privatemsg', $user->id(), 'enable');
            $is_blocked = $this->privateMsgService->isUserBlocked($this->currentUser->id(), $user->id());
            $can_be_blocked = $this->privateMsgService->canBeBlocked($user->id());
            if ($is_messages_enabled && !$is_blocked && $can_be_blocked && $user->id() !== $this->currentUser->id()) {
              $key = "{$label} ({$entity_id})";
              // Strip things like starting/trailing white spaces, line breaks
              // and tags.
              $key = preg_replace('/\\s\\s+/', ' ', str_replace("\n", '', trim(Html::decodeEntities(strip_tags($key)))));
              // Names containing commas or quotes must be wrapped in quotes.
              $key = Tags::encode($key);
              $label .= ' (' . $entity_id . ')';
              $matches[] = [
                'value' => $key,
                'label' => $label,
              ];
            }
          }
        }
      }
    }
    else {
      $matches = parent::getMatches($target_type, $selection_handler, $selection_settings, $string);
    }

    return $matches;
  }

}
