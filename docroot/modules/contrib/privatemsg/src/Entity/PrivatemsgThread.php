<?php

namespace Drupal\privatemsg\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the privatemsg thread entity class.
 *
 * @ContentEntityType(
 *   id = "privatemsg_thread",
 *   label = @Translation("PrivateMsg Thread"),
 *   label_collection = @Translation("PrivateMsg Threads"),
 *   label_singular = @Translation("privatemsg thread"),
 *   label_plural = @Translation("privatemsg threads"),
 *   label_count = @PluralTranslation(
 *     singular = "@count privatemsg threads",
 *     plural = "@count privatemsg threads",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\privatemsg\Entity\Builder\PrivatemsgThreadViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\privatemsg\Routing\PrivatemsgThreadHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\privatemsg\Entity\Access\PrivatemsgThreadAccessControlHandler",
 *   },
 *   base_table = "pm_index",
 *   admin_permission = "administer privatemsg",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "subject",
 *   },
 *   links = {
 *     "canonical" = "/messages/view/{privatemsg_thread}",
 *   },
 *   field_ui_base_route = "entity.privatemsg_thread.settings",
 * )
 */
class PrivatemsgThread extends ContentEntityBase implements PrivatemsgThreadInterface {

  /**
   * {@inheritdoc}
   */
  public function getMembersId() {
    $members = [];
    foreach ($this->get('members')->getValue() as $member_item) {
      $members[] = $member_item['target_id'];
    }
    return $members;
  }

  /**
   * {@inheritdoc}
   */
  public function isMember($id) {
    return in_array($id, $this->getMembersId());
  }

  /**
   * {@inheritdoc}
   */
  public function getMessagesCount() {
    return $this->get('private_messages')->count();
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('owner')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function addHistoryRecord(int $user_id) {
    $record = \Drupal::database()->select('pm_thread_history', 'pmth')
      ->condition('uid', $user_id)
      ->condition('thread_group', $this->getGroup())
      ->fields('pmth', ['access_timestamp'])
      ->execute()
      ->fetchField();

    if ($record === FALSE) {
      \Drupal::database()->insert('pm_thread_history')
        ->fields([
          'uid' => $user_id,
          'thread_group' => $this->getGroup(),
        ])->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers() {
    return $this->get('members')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    if (!$update) {
      $this->addHistoryRecord($this->getOwnerId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages() {
    return $this->get('private_messages')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function removeMessageFromThread(string $message_id) {
    $messages = $this->getMessages();
    foreach ($messages as $key => $message) {
      if ($message->id() === $message_id) {
        $this->get('private_messages')->removeItem($key);
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->get('group')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    return $this->get('tags')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entityType) {
    $fields = parent::baseFieldDefinitions($entityType);

    $fields['id']->setLabel(t('Private message thread ID'))
      ->setDescription(t('The private message thread ID.'));

    $fields['uuid']->setDescription(t('The custom private message thread UUID.'));

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated (core)'))
      ->setDescription(t('The most recent time at which the thread was updated'));

    $fields['updated_custom'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Updated (custom)'))
      ->setDescription(t('The most recent time at which the thread was updated'));

    $fields['group'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Group of threads'));

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Thread owner'))
      ->setDescription(t('The author of the private thread'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default');

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['members'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('To:'))
      ->setDescription(t('Enter the recipient, separate recipients with commas.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['tags'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Tags'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSettings([
        'target_type' => 'taxonomy_term',
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'privatemsg_tags' => 'privatemsg_tags',
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => 2,
        'settings' => ['link' => FALSE],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['private_messages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Messages'))
      ->setDescription(t('The private messages that belong to this thread'))
      ->setSetting('target_type', 'privatemsg_message')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['deleted'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Deleted'))
      ->setDescription(t('The time that the thread was mark as deleted.'));

    return $fields;
  }

}
