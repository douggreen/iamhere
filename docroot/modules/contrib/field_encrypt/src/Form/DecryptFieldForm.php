<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for removing encryption on field.
 */
class DecryptFieldForm extends ConfirmFormBase {

  /**
   * The entity type.
   *
   * @var string
   */
  protected string $entityType;

  /**
   * The field name to decrypt.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * Whether the field is a base field.
   *
   * @var bool
   */
  protected bool $baseField;

  /**
   * Constructs a new FieldEncryptDecryptForm.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'field_encrypt_decrypt_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t(
      'Are you sure you want to remove encryption for field %field on %entity_type?',
      ['%field' => $this->fieldName, '%entity_type' => $this->entityType]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('field_encrypt.field_overview');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Remove field encryption');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return $this->t('This action removes field encryption from the specified field. Existing field data will be decrypted through a batch process.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $entity_type = NULL, ?string $field_name = NULL, bool $base_field = FALSE): array {
    $this->entityType = $entity_type;
    $this->fieldName = $field_name;
    $this->baseField = $base_field;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->baseField) {
      /** @var \Drupal\field_encrypt\Entity\FieldEncryptEntityType $field_encrypt_settings */
      $field_encrypt_settings = $this->entityTypeManager->getStorage('field_encrypt_entity_type')->load($this->entityType);
      $field_encrypt_settings
        ->removeBaseField($this->fieldName)
        ->save();
    }
    else {
      $storage = $this->entityTypeManager->getStorage('field_storage_config');
      $field_storage_config = $storage->load($this->entityType . '.' . $this->fieldName);
      $field_storage_config->unsetThirdPartySetting('field_encrypt', 'encrypt');
      $field_storage_config->unsetThirdPartySetting('field_encrypt', 'properties');
      $field_storage_config->save();
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
