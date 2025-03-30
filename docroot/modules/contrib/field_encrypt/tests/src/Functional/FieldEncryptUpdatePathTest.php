<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\Core\Url;
use Drupal\Tests\RequirementsPageTrait;

/**
 * Tests that updating from older versions of field encrypt is not supported.
 *
 * @group field_encrypt
 * @group legacy
 */
class FieldEncryptUpdatePathTest extends FieldEncryptTestBase {
  use RequirementsPageTrait;

  /**
   * URL to the update.php script.
   *
   * @var string
   */
  private string $updateUrl;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->updateUrl = Url::fromRoute('system.db_update')->toString();
  }

  /**
   * Tests field_encrypt_requirements().
   */
  public function testUpdate(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet($this->updateUrl, ['external' => TRUE]);
    $this->updateRequirementsProblem();
    $this->clickLink('Continue');
    $this->assertSession()->pageTextContains('No pending updates.');

    // Simulate having an old version of field_encrypt.
    \Drupal::service('update.update_hook_registry')->setInstalledVersion('field_encrypt', 8000);
    $this->drupalGet($this->updateUrl, ['external' => TRUE]);
    $this->assertSession()->pageTextContains('Updating to field_encrypt version 4 is not supported.');

    $this->drupalGet('admin/reports/status');
    $this->assertSession()->pageTextContains('Updating to field_encrypt version 4 is not supported.');
  }

}
