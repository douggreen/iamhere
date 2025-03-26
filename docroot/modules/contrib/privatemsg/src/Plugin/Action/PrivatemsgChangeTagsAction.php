<?php

namespace Drupal\privatemsg\Plugin\Action;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Change tags of thread.
 *
 * @Action(
 *   id = "privatemsg_change_tags_action",
 *   label = @Translation("Change tags"),
 *   type = "privatemsg_thread",
 *   confirm = FALSE,
 *   requirements = {
 *     "_permission" = "privatemsg use messages actions",
 *   },
 * )
 */
class PrivatemsgChangeTagsAction extends ViewsBulkOperationsActionBase implements PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('change_tags', $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['thread_tags'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:privatemsg_tag',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Enter tags separate with commas.'),
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['privatemsg_tags'],
      ],
      '#autocreate' => [
        'bundle' => 'privatemsg_tags',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['thread_tags'] = $form_state->getValue('thread_tags');
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?ContentEntityInterface $entity = NULL) {
    if ($entity) {
      $flat_tags = [];
      $thread_tags = $this->configuration['thread_tags'];
      if ($thread_tags) {
        foreach ($thread_tags as $new_tag) {
          $new_tag = reset($new_tag);
          if ($new_tag instanceof TermInterface) {
            $new_tag->save();
            $flat_tags[] = $new_tag->id();
          }
          else {
            $flat_tags[] = $new_tag;
          }
        }
        $new_tags = [];
        foreach ($flat_tags as $flat_tag) {
          $new_tags[] = ['target_id' => $flat_tag];
        }
        $entity->set('tags', $new_tags);
      }
      else {
        $entity->set('tags', NULL);
      }
      $entity->save();
      return 'Tags for selected threads were updated';
    }
    return 'You must first select one (or more) messages before you can take that action.';
  }

}
