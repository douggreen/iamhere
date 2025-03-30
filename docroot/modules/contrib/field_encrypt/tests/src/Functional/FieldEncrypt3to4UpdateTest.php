<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\field_encrypt\ProcessEntities;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the update path from field encrypt 3 to 4.
 *
 * @group Update
 */
class FieldEncrypt3to4UpdateTest extends UpdatePathTestBase {

  /**
   * Text that appears when updating and settings.php can be updated afterwards.
   */
  private const SETTINGS_UPDATE_TEXT = 'The setting "field_encrypt.use_eval_for_entity_hooks" can be removed from settings.php.';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update_test_schema'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles[] = __DIR__ . '/../../fixtures/field-encrypt-3.2.x-drupal-11.0.x-update.php.gz';
  }

  /**
   * Tests that updates are properly run.
   *
   * @testWith [true]
   *           [false]
   */
  public function testUpdates(bool $add_setting): void {
    $this->assertSame(['node' => 'node'], \Drupal::state()->get('field_encrypt.entity_types'));
    if ($add_setting) {
      $this->writeSettings(['settings' => ['field_encrypt.use_eval_for_entity_hooks' => (object) ['value' => FALSE, 'required' => TRUE]]]);
    }
    $this->runUpdates();
    if ($add_setting) {
      $this->assertSession()->pageTextContains(self::SETTINGS_UPDATE_TEXT);
    }
    else {
      $this->assertSession()->pageTextNotContains(self::SETTINGS_UPDATE_TEXT);
    }

    $this->assertNull(\Drupal::state()->get('field_encrypt.entity_types', NULL));

    // Ensure encrypt content still works.
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains('This data should be encrypted!');
    $result = \Drupal::database()->query("SELECT body_value FROM {node__body} WHERE entity_id = 1")->fetchField();
    $this->assertEquals(ProcessEntities::ENCRYPTED_VALUE, $result);
  }

}
