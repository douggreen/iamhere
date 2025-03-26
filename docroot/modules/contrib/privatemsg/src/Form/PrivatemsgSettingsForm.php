<?php

namespace Drupal\privatemsg\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Private Messages settings for this site.
 */
class PrivatemsgSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'privatemsg_privatemsg_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['privatemsg.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('privatemsg.settings');

    $form['remove_after'] = [
      '#type' => 'number',
      '#title' => $this->t('Remove deleted messages from DB after (days)'),
      '#default_value' => $config->get('remove_after') ?: 30,
      '#required' => TRUE,
      '#min' => 1,
    ];

    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $roles_options = [];
    foreach ($roles as $role) {
      $role_id = $role->id();
      $role_label = $role->label();
      $roles_options[$role_id] = $role_label;
    }

    $form['allowed_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allow send private messages for these roles'),
      '#options' => $roles_options,
      '#default_value' => $config->get('allowed_roles') ?: [],
    ];

    $form['moderator_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderator role for claims'),
      '#options' => $roles_options,
      '#default_value' => $config->get('moderator_role') ?: NULL,
    ];

    $form['unblockable_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Users cannot block another users with these roles'),
      '#options' => $roles_options,
      '#default_value' => $config->get('unblockable_roles') ?: [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('privatemsg.settings')
      ->set('remove_after', $form_state->getValue('remove_after'))
      ->set('allowed_roles', array_values(array_filter($form_state->getValue('allowed_roles'))))
      ->set('moderator_role', $form_state->getValue('moderator_role'))
      ->set('unblockable_roles', array_values(array_filter($form_state->getValue('unblockable_roles'))))
      ->save();
    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
    parent::submitForm($form, $form_state);
  }

}
