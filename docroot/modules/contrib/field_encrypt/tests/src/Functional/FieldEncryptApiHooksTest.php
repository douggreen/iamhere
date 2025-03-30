<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_encrypt\ProcessEntities;

/**
 * Tests Field Encrypt's API hooks.
 *
 * @group field_encrypt
 * @see hook_field_encrypt_unencrypted_storage_value_alter()
 * @see hook_field_encrypt_allow_encryption()
 */
class FieldEncryptApiHooksTest extends FieldEncryptTestBase {

  use CronRunTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'key',
    'encrypt',
    'encrypt_test',
    'field_encrypt',
    'field_encrypt_test',
  ];

  /**
   * Set up base fields for test.
   *
   * @param bool $encryption
   *   Whether or not the fields should be encrypted. Defaults to TRUE.
   */
  protected function setBaseFieldStorageSettings(bool $encryption = TRUE): void {
    // Set up storage settings for first field.
    $this->drupalGet('admin/config/system/field-encrypt/entity-types');
    $this->assertSession()->fieldExists('entity_type')->selectOption('Content');
    $this->submitForm([], 'Save configuration');
    if ($encryption) {
      $this->assertSession()->fieldExists('base_fields[title]')->check();
    }
    else {
      $this->assertSession()->fieldExists('base_fields[title]')->uncheck();
    }
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Updated encryption settings for Content base fields.');
    $this->rebuildAll();
  }

  /**
   * Tests field_encrypt hooks.
   */
  public function testHooks(): void {
    FieldStorageConfig::loadByName('node', 'field_test_single')
      ->setThirdPartySetting('field_encrypt', 'placeholders', ['value' => '✨'])
      ->save();
    \Drupal::keyValue('field_encrypt_test')->set('hook_field_encrypt_unencrypted_storage_value_alter', TRUE);
    $this->setBaseFieldStorageSettings(TRUE);
    $this->setFieldStorageSettings(TRUE);

    // Save test entity.
    $this->drupalGet('node/add/page');
    $this->assertSession()->fieldExists('title[0][value]')->setValue('Test title');
    $this->assertSession()->fieldExists('field_test_single[0][value]')->setValue('Test single value field');
    $this->assertSession()->buttonExists('Save')->press();

    // Check the node title is displayed unencrypted.
    $this->assertSession()->elementTextContains('css', 'h1', "Test title");

    $result = \Drupal::database()->query("SELECT title FROM {node_field_data} WHERE nid = 1")->fetchField();
    $this->assertEquals('🐒', $result);
    $result = \Drupal::database()->query("SELECT field_test_single_value FROM {node__field_test_single} WHERE entity_id = 1")->fetchField();
    $this->assertEquals('✨', $result);

    \Drupal::keyValue('field_encrypt_test')->set('hook_field_encrypt_unencrypted_storage_value_alter', FALSE);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    \Drupal::keyValue('field_encrypt_test')->set('hook_field_encrypt_allow_encryption', TRUE);
    $this->drupalGet('node/1/edit');
    $this->assertSession()->buttonExists('Save')->press();
    $result = \Drupal::database()->query("SELECT title FROM {node_field_data} WHERE nid = 1")->fetchField();
    $this->assertEquals('Test title', $result);
    // Check the node title is displayed unencrypted.
    $this->assertSession()->elementTextContains('css', 'h1', "Test title");
    // Assert the arguments passed to hook are as expected.
    $this->assertSession()->pageTextContains("Allow encryption hook: Entity title: Test title");

    $this->drupalGet('node/1/edit');
    $this->assertSession()->fieldExists('status[value]')->uncheck();
    $this->assertSession()->buttonExists('Save')->press();
    $result = \Drupal::database()->query("SELECT title FROM {node_field_data} WHERE nid = 1")->fetchField();
    $this->assertEquals(ProcessEntities::ENCRYPTED_VALUE, $result);
    // Check the node title is displayed unencrypted.
    $this->assertSession()->elementTextContains('css', 'h1', "Test title");
    // Assert the arguments passed to hook are as expected.
    $this->assertSession()->pageTextContains("Allow encryption hook: Entity title: Test title");
  }

}
