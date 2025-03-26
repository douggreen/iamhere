<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgUserProfileLinkTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'privatemsg',
  ];

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user1;

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user2;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'access user profiles',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);
    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'access user profiles',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);
  }

  /**
   * Tests that the user profile link can be reached.
   */
  public function testUserProfileLink() {
    $this->drupalLogin($this->user1);
    $this->drupalGet("/user/{$this->user2->id()}");
    $this->assertSession()->pageTextContains('Send this user a private message');
  }

}
