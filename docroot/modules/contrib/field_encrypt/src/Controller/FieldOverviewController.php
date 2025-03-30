<?php

declare(strict_types=1);

namespace Drupal\field_encrypt\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders encrypted fields overview.
 */
class FieldOverviewController extends ControllerBase {

  /**
   * Creates a new FieldOverviewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(
    protected $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Renders overview page of encrypted fields.
   */
  public function overview(): array {
    $encrypted_fields = $this->getEncryptedFields();
    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        'field_name' => $this->t('Field'),
        'entity_type' => $this->t('Entity type'),
        'properties' => $this->t('Properties'),
        'operations' => $this->t('Operations'),
      ],
      '#title' => 'Overview of encrypted fields',
      '#rows' => [],
      '#empty' => $this->t('There are no encrypted fields.'),
    ];

    /** @var \Drupal\field\FieldStorageConfigInterface $encrypted_field */
    foreach ($encrypted_fields as $encrypted_field) {
      if ($encrypted_field->isBaseField()) {
        $properties = $encrypted_field->getSetting('field_encrypt.properties') ?? [];
      }
      else {
        $properties = $encrypted_field->getThirdPartySetting('field_encrypt', 'properties', []);
      }
      $entity_type = $encrypted_field->getTargetEntityTypeId();
      $field_name = $encrypted_field->getName();

      $row = [
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'properties' => [
          'data' => [
            '#theme' => 'item_list',
            '#items' => array_filter($properties),
          ],
        ],
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'decrypt' => [
                'title' => $this->t('Decrypt'),
                'url' => Url::fromRoute('field_encrypt.field_decrypt_confirm', [
                  'entity_type' => $entity_type,
                  'field_name' => $field_name,
                  'base_field' => $encrypted_field->isBaseField(),
                ]),
              ],
            ],
          ],
        ],
      ];
      $build['table']['#rows'][$encrypted_field->getName()] = $row;
    }
    return $build;
  }

  /**
   * Get a list of encrypted fields' storage entities.
   *
   * @return \Drupal\Core\Field\FieldStorageDefinitionInterface[]
   *   An array of FieldStorageConfig entities and base fields for encrypted
   *   fields.
   */
  protected function getEncryptedFields(): array {
    $encrypted_fields = [];
    $storage = $this->entityTypeManager->getStorage('field_storage_config');
    $fields = $storage->loadMultiple();
    foreach ($fields as $field) {
      if ($field->getThirdPartySetting('field_encrypt', 'encrypt', FALSE) == TRUE) {
        $encrypted_fields[] = $field;
      }
    }
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      // Only content entity types support encryption.
      if ($entity_type instanceof ContentEntityTypeInterface) {
        /** @var \Drupal\Core\Field\BaseFieldDefinition $base_field */
        foreach ($this->entityFieldManager->getBaseFieldDefinitions($entity_type->id()) as $base_field) {
          if ($base_field->getSetting('field_encrypt.encrypt')) {
            $encrypted_fields[] = $base_field;
          }
        }
      }
    }

    return $encrypted_fields;
  }

}
