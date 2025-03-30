<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\Tests\encrypt\Functional\EncryptTestBase;
use Drupal\Tests\system\Functional\Entity\Traits\EntityDefinitionTestTrait;
use Drupal\field_encrypt\ProcessEntities;

/**
 * Tests user field encryption.
 *
 * @group field_encrypt
 */
class EncryptUserTest extends EncryptTestBase {
  use EntityDefinitionTestTrait;
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_ui',
    'text',
    'key',
    'encrypt',
    'encrypt_test',
    'field_encrypt',
    'field_encrypt_test',
  ];

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

    // Set a value for init so it gets encrypted.
    $this->adminUser->init = 'test@example.com';
    $this->adminUser->save();
    $this->drupalLogin($this->adminUser);

    $this->config('field_encrypt.settings')
      ->set('encryption_profile', 'encryption_profile_1')
      ->save();
  }

  /**
   * Set up base fields for test.
   *
   * @param bool $encryption
   *   Whether or not the fields should be encrypted. Defaults to TRUE.
   */
  protected function setFieldStorageSettings(bool $encryption = TRUE): void {
    // Set up storage settings for first field.
    $this->drupalGet('admin/config/system/field-encrypt/entity-types');
    $this->assertSession()->fieldExists('entity_type')->selectOption('User');
    $this->submitForm([], 'Save configuration');
    if ($encryption) {
      $this->assertSession()->fieldExists('base_fields[init]')->check();
    }
    else {
      $this->assertSession()->fieldExists('base_fields[init]')->uncheck();
    }
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->pageTextContains('Updated encryption settings for User base fields.');
    $this->rebuildAll();
  }

  /**
   * Test encrypting user fields.
   *
   * This test covers encrypting non-revisionable entities as well.
   */
  public function testEncryptFieldNormal(): void {
    $this->setFieldStorageSettings(TRUE);
    $this->drupalGet('admin/config/system/field-encrypt/process-queues');
    $this->assertSession()->pageTextContains('There are 4 entities queued for updating to use the latest field encryption settings.');
    $this->cronRun();
    $this->drupalGet('admin/config/system/field-encrypt/process-queues');
    $this->assertSession()->pageTextContains('There are 0 entities queued for updating to use the latest field encryption settings.');
    $result = \Drupal::database()->query("SELECT init FROM {users_field_data} WHERE uid = :uid", [':uid' => $this->adminUser->id()])->fetchAll();
    $this->assertCount(1, $result);
    foreach ($result as $record) {
      $this->assertEquals(ProcessEntities::ENCRYPTED_VALUE, $record->init);
    }
  }

}
