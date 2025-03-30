<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\encrypt\Functional\EncryptTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;

/**
 * Base test class for field_encrypt.
 */
abstract class FieldEncryptTestBase extends EncryptTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'field_ui',
    'text',
    'locale',
    'content_translation',
    'key',
    'encrypt',
    'encrypt_test',
    'field_encrypt',
  ];

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * A test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $testNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer encrypt',
      'administer keys',
      'administer field encryption',
    ], NULL, TRUE);
    $this->drupalLogin($this->adminUser);

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Create content type to test.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create test fields.
    $single_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_single',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ]);
    $single_field_storage->save();
    $single_field = FieldConfig::create([
      'field_storage' => $single_field_storage,
      'bundle' => 'page',
      'label' => 'Single field',
    ]);
    $single_field->save();
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('node', 'page', 'default')
      ->setComponent('field_test_single')
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_test_single', [
        'type' => 'text_default',
      ])
      ->save();

    $multi_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_multi',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 3,
    ]);
    $multi_field_storage->save();
    $multi_field = FieldConfig::create([
      'field_storage' => $multi_field_storage,
      'bundle' => 'page',
      'label' => 'Multi field',
    ]);
    $multi_field->save();
    $display_repository->getFormDisplay('node', 'page', 'default')
      ->setComponent('field_test_multi')
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_test_multi', [
        'type' => 'string',
      ])
      ->save();

    $decimal_field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_decimal',
      'entity_type' => 'node',
      'type' => 'decimal',
      'cardinality' => 1,
    ]);
    $decimal_field_storage->save();
    $decimal_field = FieldConfig::create([
      'field_storage' => $decimal_field_storage,
      'bundle' => 'page',
      'label' => 'Decimal field',
    ]);
    $decimal_field->save();
    $display_repository->getFormDisplay('node', 'page', 'default')
      ->setComponent('field_test_decimal')
      ->save();
    $display_repository->getViewDisplay('node', 'page', 'default')
      ->setComponent('field_test_decimal')
      ->save();

    $this->config('field_encrypt.settings')
      ->set('encryption_profile', 'encryption_profile_1')
      ->save();
  }

  /**
   * Creates a test node.
   */
  protected function createTestNode(): void {
    $node = $this->createNode([
      'field_test_single' => [
        [
          'value' => "Lorem ipsum dolor sit amet.",
          'summary' => "Lorem ipsum",
          'format' => filter_default_format(),
        ],
      ],
      'field_test_multi' => [
        ['value' => "one"],
        ['value' => "two"],
        ['value' => "three"],
      ],
      'field_test_decimal' => 12.45,
    ]);
    $this->testNode = $node;
  }

  /**
   * Set up storage settings for test fields.
   *
   * @param bool $encryption
   *   Whether or not the fields should be encrypted. Defaults to TRUE.
   */
  protected function setFieldStorageSettings(bool $encryption = TRUE): void {
    // Set up storage settings for first field.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_test_single');
    // Encrypt field found.
    $this->assertSession()->fieldExists('field_storage[field_encrypt][field_encrypt][encrypt]');

    $edit = [
      'field_storage[field_encrypt][field_encrypt][encrypt]' => $encryption,
      'field_storage[field_encrypt][field_encrypt][properties][value]' => 'value',
      'field_storage[field_encrypt][field_encrypt][properties][summary]' => 'summary',
    ];
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->pageTextContains('Saved Single field configuration.');

    // Set up storage settings for second field.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_test_multi');
    // Encrypt field found.
    $this->assertSession()->fieldExists('field_storage[field_encrypt][field_encrypt][encrypt]');

    $edit = [
      'field_storage[field_encrypt][field_encrypt][encrypt]' => $encryption,
      'field_storage[field_encrypt][field_encrypt][properties][value]' => 'value',
    ];
    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->pageTextContains('Saved Multi field configuration.');

    // Set up storage settings for decimal field.
    $this->drupalGet('admin/structure/types/manage/page/fields/node.page.field_test_decimal');
    // Encrypt field found.
    $this->assertSession()->fieldExists('field_storage[field_encrypt][field_encrypt][encrypt]');

    $this->submitForm($edit, 'Save settings');
    $this->assertSession()->pageTextContains('Saved Decimal field configuration.');
    $this->rebuildAll();
  }

  /**
   * Set up translation settings for content translation test.
   */
  protected function setTranslationSettings(): void {
    // Set up extra language.
    ConfigurableLanguage::createFromLangcode('fr')->save();
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);
    drupal_static_reset();
    $this->entityTypeManager->clearCachedDefinitions();
    \Drupal::service('router.builder')->rebuild();
    $this->rebuildContainer();
  }

  /**
   * {@inheritdoc}
   */
  protected function resetAll(): void {
    parent::resetAll();
    // Update the entity type manager to same service as in the container.
    $this->entityTypeManager = \Drupal::entityTypeManager();

    if (isset($this->testNode)) {
      // Reload node after rebuilding.
      $controller = $this->entityTypeManager->getStorage($this->testNode->getEntityTypeId());
      $controller->resetCache([$this->testNode->id()]);
      $node = $controller->load($this->testNode->id());
      assert($node instanceof NodeInterface);
      $this->testNode = $node;
    }

  }

}
