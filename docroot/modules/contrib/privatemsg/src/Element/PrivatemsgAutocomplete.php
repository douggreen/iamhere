<?php

namespace Drupal\privatemsg\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Form\FormStateInterface;

/**
 * Extended default autocomplete.
 *
 * Included entity selection by id.
 *
 * The #default_value accepted by this element is either an entity object or an
 * array of entity objects.
 *
 * @see \Drupal\Core\Render\Annotation\FormElement
 * @see \Drupal\Core\Entity\Element\EntityAutocomplete
 * @see plugin_api
 *
 * @FormElement("privatemsg_autocomplete")
 */
class PrivatemsgAutocomplete extends EntityAutocomplete {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = static::class;

    $info['#element_validate'] = [[$class, 'validateEntityIdAutocomplete']];

    return $info;
  }

  /**
   * {@inheritdoc}
   *
   * Just change autocomplete route name.
   */
  public static function processEntityAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element = parent::processEntityAutocomplete($element, $form_state, $complete_form);
    return $element;
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateEntityIdAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {
      $options = $element['#selection_settings'] + [
        'target_type' => $element['#target_type'],
        'handler' => $element['#selection_handler'],
      ];

      /** @var /Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface $handler */
      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance($options);
      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = $element['#tags'] ? Tags::explode($element['#value']) : [$element['#value']];

        foreach ($input_values as $input) {
          $match = static::extractEntityIdFromAutocompleteInput($input);
          if ($match === 'role') {
            $role = \Drupal::entityTypeManager()->getStorage('user_role')->loadByProperties([
              'label' => trim(strtok($input, '(')),
            ]);
            $role = reset($role);
            if ($role) {
              $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
                'roles' => $role->id(),
              ]);
              if ($users) {
                foreach ($users as $user) {
                  $value[] = [
                    'target_id' => $user->id(),
                  ];
                }
              }
            }
            continue;
          }

          if ($match !== NULL) {
            $value[] = [
              'target_id' => $match,
            ];
          }
        }
      }

      // Check that the referenced entities are valid, if needed.
      if ($element['#validate_reference'] && !empty($value)) {
        // Validate existing entities.
        $ids = array_reduce($value, function ($return, $item) {
          if (isset($item['target_id'])) {
            $return[] = $item['target_id'];
          }
          return $return;
        });

        if ($ids) {
          $valid_ids = $handler->validateReferenceableEntities($ids);

          foreach ($valid_ids as $user_id) {
            $user = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
            if ($user) {
              /** @var \Drupal\privatemsg\PrivateMsgServiceInterface $privatemsg_service */
              $privatemsg_service = \Drupal::service('privatemsg.common');
              $is_blocked = $privatemsg_service->isUserBlocked($user->id(), \Drupal::currentUser()->id());
              if ($is_blocked) {
                $form_state->setError($element, t('You are not permitted to send messages to user %name', [
                  '%name' => $user->getDisplayName(),
                ]));
              }
            }
          }

          if ($invalid_ids = array_diff($ids, $valid_ids)) {
            foreach ($invalid_ids as $invalid_id) {
              $form_state->setError($element, t('The referenced entity (%type: %id) does not exist.', [
                '%type' => $element['#target_type'],
                '%id' => $invalid_id,
              ]));
            }
          }
        }
      }

      // Use only the last value if the form element does not support multiple
      // matches (tags).
      if (!$element['#tags'] && !empty($value)) {
        $last_value = $value[count($value) - 1];
        $value = $last_value['target_id'] ?? $last_value;
      }
    }

    $form_state->setValueForElement($element, $value);
  }

}
