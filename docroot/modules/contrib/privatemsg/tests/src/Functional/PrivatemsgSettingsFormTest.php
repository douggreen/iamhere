<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'privatemsg',
  ];

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $adminUser;

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * Settings form url.
   */
  protected Url $settingsRoute;

  /**
   * SetUp the test class.
   */
  public function setUp(): void {
    parent::setUp();
    $this->settingsRoute = Url::fromRoute('privatemsg.settings');
    $this->user = $this->DrupalCreateUser();
    $this->adminUser = $this->DrupalCreateUser([
      'administer privatemsg',
    ]);
    $this->drupalCreateRole([], 'llamalovers', 'Llama Lovers');
    $this->drupalCreateRole([], 'catcuddlers', 'Cat Cuddlers');
  }

  /**
   * Tests that the settings page can be reached and saved.
   */
  public function testSettingsPageExists() {
    $this->drupalLogin($this->user);
    $this->drupalGet($this->settingsRoute);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($this->settingsRoute);
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'remove_after' => 25,
      'allowed_roles[llamalovers]' => 'llamalovers',
      'allowed_roles[catcuddlers]' => 'catcuddlers',
      'moderator_role' => 'llamalovers',
      'unblockable_roles[llamalovers]' => 'llamalovers',
      'unblockable_roles[catcuddlers]' => 'catcuddlers',
    ];
    $expected_values = [
      'remove_after' => 25,
      'allowed_roles' => ['llamalovers', 'catcuddlers'],
      'moderator_role' => 'llamalovers',
      'unblockable_roles' => ['llamalovers', 'catcuddlers'],
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    foreach ($expected_values as $field => $expected_value) {
      $actual_value = $this->config('privatemsg.settings')->get($field);
      $this->assertEquals($expected_value, $actual_value);
    }
  }

}
