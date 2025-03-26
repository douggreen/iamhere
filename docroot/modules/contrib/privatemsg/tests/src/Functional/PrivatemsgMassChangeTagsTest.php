<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgMassChangeTagsTest extends BrowserTestBase {

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
   * Tests VBO mass change tags.
   */
  public function testMassChangeTags() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($this->user2);
    $data = ['action' => 0];
    $selected = [0];

    /* Create new tag */
    $this->executeAction('/messages', 'Apply to selected items', $selected, $data);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_tags' => 'NewTag',
    ], 'Apply');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'td.views-field-tags-target-id a', 'NewTag');

    /* Clear tags */
    $this->executeAction('/messages', 'Apply to selected items', $selected, $data);
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_tags' => NULL,
    ], 'Apply');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'td.views-field-tags-target-id', '');
  }

}
