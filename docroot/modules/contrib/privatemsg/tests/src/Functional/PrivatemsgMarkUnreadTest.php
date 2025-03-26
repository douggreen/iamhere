<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgMarkUnreadTest extends BrowserTestBase {

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
      'privatemsg use messages actions',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);
    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'privatemsg use messages actions',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);
  }

  /**
   * Helper function that executes en operation.
   *
   * @param string|null $path
   *   The path of the View page that includes VBO.
   * @param string $button_text
   *   The form submit button text.
   * @param int[] $selection
   *   The selected items' indexes.
   * @param array $data
   *   Additional parameters for the submitted form.
   */
  protected function executeAction($path, string $button_text, array $selection = [], array $data = []): void {
    foreach ($selection as $index) {
      $data["views_bulk_operations_bulk_form[$index]"] = TRUE;
    }
    if ($path !== NULL) {
      $this->drupalGet($path);
    }
    $this->submitForm($data, $button_text);
  }

  /**
   * Tests that the threads can be mark as unread.
   */
  public function testMarkUnread() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($this->user2);
    sleep(1);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    sleep(1);
    $this->drupalGet('/messages/view/3');
    $this->assertSession()->statusCodeEquals(200);
    sleep(1);

    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '.privatemsg-unread-thread');

    $selected = [0, 1];
    $data = ['action' => 2];
    $this->executeAction('/messages', 'Apply to selected items', $selected, $data);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '.privatemsg-unread-thread');
  }

}
