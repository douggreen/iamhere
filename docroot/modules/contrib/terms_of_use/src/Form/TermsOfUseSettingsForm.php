<?php

namespace Drupal\terms_of_use\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Class TermsOfUseSettingsForm.
 *
 * @package Drupal\terms_of_use\Form
 */
class TermsOfUseSettingsForm extends ConfigFormBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\user\AccountSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'terms_of_use.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'terms_of_use_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('terms_of_use.settings');
    if (!empty($config->get('terms_of_use_node'))) {
      $default_node = $this->entityTypeManager->getStorage('node')->load($config->get('terms_of_use_node'));
    }
    else {
      $default_node = '';
    }
    $form['terms_of_use'] = [
      '#type' => 'fieldset',
    ];
    $form['terms_of_use']['terms_of_use_node'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#default_value' => $default_node,
      '#title' => $this->t('Title of the post where your Terms of Use are published'),
      '#description' => $this->t('Node <em>title</em> of the page or story (or blog entry or book page) where your Terms of Use are published.'),
      '#required' => TRUE,
    ];
    $form['terms_of_use_form'] = [
      '#type' => 'fieldset',
    ];
    $form['terms_of_use_form']['terms_of_use_label_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the fieldset'),
      '#default_value' => $config->get('terms_of_use_label_name'),
      '#description' => $this->t('The text for the Terms of Use and the [x] checkbox are contained in a fieldset. Type here the title for that fieldset. Leave empty to remove the fieldset'),
    ];
    $form['terms_of_use_form']['terms_of_use_label_checkbox'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label for the checkbox'),
      '#default_value' => $config->get('terms_of_use_label_checkbox'),
      '#description' => $this->t('Type here something like "I agree with these terms." or "I CERTIFY THAT I AM OVER THE AGE OF 18 YEARS OLD.", without quotes. You can use the token @link to insert a link to the Terms in this label. For example, the label can be: "I agree with the @link.", without quotes. You may want to link to the Terms if you prefer not to show the full text of the Terms in the registration form. If you use the token, the Terms will not be shown.'),
    ];
    $form['terms_of_use_form']['terms_of_use_open_link_in_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open link in new window'),
      '#default_value' => $config->get('terms_of_use_open_link_in_new_window'),
      '#description' => $this->t('Should any @link be opened in a new window.'),
    ];
    $form['terms_of_use_form']['terms_of_use_collapsed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Terms of Use as collapsed'),
      '#default_value' => $config->get('terms_of_use_collapsed'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('terms_of_use.settings')
      ->set('terms_of_use_node', $form_state->getValue('terms_of_use_node'))
      ->set('terms_of_use_label_name', $form_state->getValue('terms_of_use_label_name'))
      ->set('terms_of_use_label_checkbox', $form_state->getValue('terms_of_use_label_checkbox'))
      ->set('terms_of_use_open_link_in_new_window', (bool) $form_state->getValue('terms_of_use_open_link_in_new_window'))
      ->set('terms_of_use_collapsed', (bool) $form_state->getValue('terms_of_use_collapsed'))
      ->save();

    Cache::invalidateTags(['config:user.settings']);
  }

}
