<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form builder for the field_encrypt settings admin page.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Constructs a new FieldEncryptSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypePluginManager
   *   The field type plugin manager.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryptionProfileManager
   *   The encryption profile manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
    protected EncryptionProfileManagerInterface $encryptionProfileManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('encrypt.encryption_profile.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'field_encrypt_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['field_encrypt.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('field_encrypt.settings');
    $default_properties = $config->get('default_properties');

    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption profile'),
      '#description' => $this->t(
        'Select the encryption profile to use for encrypting fields. New entities and revisions will always use this profile. Existing entities and revisions can be updated via <a href=":link">Encrypted fields entity type settings</a>',
        [':link' => Url::fromRoute('field_encrypt.settings.entity_type')->toString()]
      ),
      '#options' => $this->encryptionProfileManager->getEncryptionProfileNamesAsOptions(),
      '#default_value' => $config->get('encryption_profile'),
      '#required' => TRUE,
      '#empty_value' => '',
    ];

    $form['make_entities_uncacheable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude entities from persistent caches'),
      '#description' => $this->t('This ensures unencrypted data is not exposed in the cache. However, it negatively impacts performance because it prevents caching of all entity types for which field encryption is enabled, even entity bundles that do not have any encrypted fields.'),
      '#default_value' => $config->get('make_entities_uncacheable'),
    ];

    $form['default_properties'] = [
      '#type' => 'details',
      '#title' => $this->t('Default properties'),
      '#description' => $this->t('Select which field properties will be checked by default on the field encryption settings form, per field type. Note that this does not change existing field settings, but merely sets sensible defaults.'),
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    // Gather valid field types.
    foreach ($this->fieldTypePluginManager->getGroupedDefinitions($this->fieldTypePluginManager->getUiDefinitions()) as $category => $field_types) {

      $form['default_properties'][$category] = [
        '#type' => 'details',
        '#title' => $category,
        '#open' => FALSE,
      ];

      foreach ($field_types as $name => $field_type) {
        // Special handling for preconfigured definitions.
        // @see \Drupal\Core\Field\FieldTypePluginManager::getUiDefinitions()
        $type = str_starts_with($name, 'field_ui:') ? $field_type['id'] : $name;
        $field_definition = BaseFieldDefinition::create($type);
        $definitions = $field_definition->getPropertyDefinitions();
        $properties = [];
        foreach ($definitions as $property => $definition) {
          $properties[$property] = $property . ' (' . $definition->getLabel() . ' - ' . $definition->getDataType() . ')';
        }

        $form['default_properties'][$category][$name] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('@field_type properties', ['@field_type' => $field_type['label']]),
          '#description' => $this->t('Specify the default properties to encrypt for this field type.'),
          '#options' => $properties,
          '#default_value' => $default_properties[$name] ?? [],
        ];
      }

      $form['batch_update'] = [
        '#type' => 'details',
        '#title' => $this->t('Batch update settings'),
        '#description' => $this->t('Configure behavior of the batch field update feature. When changing field encryption settings for fields that already contain data, a batch process will be started that updates the existing field values according to the new settings.'),
        '#open' => TRUE,
      ];

      $form['batch_update']['batch_size'] = [
        '#type' => 'number',
        '#title' => $this->t('Batch size'),
        '#default_value' => $config->get('batch_size'),
        '#description' => $this->t('Specify the number of entities to process on each field update batch execution. It is recommended to keep this number low, to avoid timeouts.'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $default_properties = [];
    $form_state->getValue('default_properties');
    $values = $form_state->getValue('default_properties');
    foreach ($values as $field_types) {
      foreach ($field_types as $field_type => $properties) {
        $default_properties[$field_type] = array_keys(array_filter($properties));
      }
    }

    $this->config('field_encrypt.settings')
      ->set('encryption_profile', $form_state->getValue('encryption_profile'))
      ->set('make_entities_uncacheable', $form_state->getValue('make_entities_uncacheable'))
      ->set('default_properties', $default_properties)
      ->set('batch_size', $form_state->getValue('batch_size'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
