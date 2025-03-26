<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgEmailTest extends BrowserTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

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
  private $user1;

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user2;

  /**
   * SetUp the test class.
   */
  public function setUp(): void {
    parent::setUp();
    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'notify', 0);
    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'notify', 1);
  }

  /**
   * Tests that the email can be sent.
   */
  public function testMailSend() {
    // Before we send the email, drupalGetMails should return an empty array.
    $captured_emails = $this->drupalGetMails();
    $this->assertCount(0, $captured_emails, 'The captured emails queue is empty.');

    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user1->getDisplayName() . ' (' . $this->user1->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    // Ensure that there is one email in the captured emails array.
    $captured_emails = $this->drupalGetMails(['key' => 'privatemsg']);
    $this->assertCount(0, $captured_emails, 'The captured emails queue is empty.');

    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    // Ensure that there is one email in the captured emails array.
    $captured_emails = $this->drupalGetMails(['key' => 'privatemsg']);
    $this->assertCount(1, $captured_emails, 'One email was captured.');
  }

}
