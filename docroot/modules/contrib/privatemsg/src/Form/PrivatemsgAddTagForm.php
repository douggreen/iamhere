<?php

namespace Drupal\privatemsg\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Private Messages form.
 */
class PrivatemsgAddTagForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'privatemsg_privatemsg_add_tag';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $thread = $this->getRouteMatch()->getParameter('privatemsg_thread');

    $form['thread_tags'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:privatemsg_tag',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Enter tags separate with commas.'),
      '#tags' => TRUE,
      '#weight' => '-2',
      '#default_value' => $thread->get('tags')->referencedEntities(),
      '#selection_settings' => [
        'target_bundles' => ['privatemsg_tags'],
      ],
      '#autocreate' => [
        'bundle' => 'privatemsg_tags',
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Tag this thread'),
      '#ajax' => [
        'callback' => '::addTagToThread',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing here.
  }

  /**
   * {@inheritdoc}
   */
  public function addTagToThread(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $thread = $this->getRouteMatch()->getParameter('privatemsg_thread');
    if ($thread) {
      $tags = $form_state->getValue('thread_tags');
      $thread->set('tags', $tags);
      $thread->save();

      if (empty($tags)) {
        $response->addCommand(new InvokeCommand('.privatemsg-thread-tags-title', 'addClass', ['hidden']));
        $response->addCommand(new HtmlCommand('.privatemsg-thread-tag-list', ''));
      }
      else {
        $response->addCommand(new InvokeCommand('.privatemsg-thread-tags-title', 'removeClass', ['hidden']));
        $content = '';
        foreach ($tags as $tag) {
          $tag = reset($tag);
          if ($tag instanceof TermInterface) {
            $tag_term = $tag;
          }
          else {
            $tag_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tag);
          }
          if ($tag_term instanceof TermInterface) {
            $content .= '<a href="/messages?tags_target_id=' . $tag_term->id() . '">' . $tag_term->getName() . '</a>, ';
          }
        }
        $response->addCommand(new HtmlCommand('.privatemsg-thread-tag-list', $content));
      }

      $response->addCommand(new InvokeCommand('.privatemsg-add-tag-form', 'removeAttr', ['open']));
    }
    return $response;
  }

}
