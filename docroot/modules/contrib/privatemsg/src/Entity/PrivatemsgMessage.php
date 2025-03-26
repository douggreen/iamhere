<?php

namespace Drupal\privatemsg\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the privatemsg message entity class.
 *
 * @ContentEntityType(
 *   id = "privatemsg_message",
 *   label = @Translation("Privatemsg Message"),
 *   label_collection = @Translation("Privatemsg Messages"),
 *   label_singular = @Translation("privatemsg message"),
 *   label_plural = @Translation("privatemsg messages"),
 *   label_count = @PluralTranslation(
 *     singular = "@count privatemsg messages",
 *     plural = "@count privatemsg messages",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\privatemsg\Entity\Builder\PrivatemsgMessageViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\privatemsg\Form\PrivatemsgMessageForm",
 *     },
 *     "access" = "Drupal\privatemsg\Entity\Access\PrivatemsgMessageAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\privatemsg\Routing\PrivatemsgMessageHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "pm_message",
 *   admin_permission = "administer privatemsg",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/privatemsg_message/{privatemsg_message}",
 *     "add-form" = "/messages/new",
 *   },
 *   field_ui_base_route = "entity.privatemsg_message.settings",
 * )
 */
class PrivatemsgMessage extends ContentEntityBase implements PrivatemsgMessageInterface {

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   *
   * When a new private message is created, set the owner entity reference to
   * the current user as the creator of the instance.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'owner' => \Drupal::currentUser()->id(),
    ];
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
  public function markMessageAsDeleted() {
    $this->set('deleted', \Drupal::time()->getRequestTime());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMarkAsDeletedTime() {
    return $this->get('deleted')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id']->setLabel(t('Private message ID'))
      ->setDescription(t('The private message ID.'));

    $fields['uuid']->setDescription(t('The custom private message UUID.'));

    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('From'))
      ->setDescription(t('The author of the private message'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'label' => 'hidden',
        'settings' => [
          'placeholder' => 'Message',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the private message was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['deleted'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Deleted'))
      ->setDescription(t('The time that the message was mark as deleted.'));

    return $fields;
  }

}
