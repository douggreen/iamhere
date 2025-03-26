<?php

namespace Drupal\Tests\privatemsg\FunctionalJavascript;

use Drupal\Core\Language\LanguageInterface;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgChangeTagsTest extends WebDriverTestBase {

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
   * Term for referencing.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * Current thread.
   *
   * @var \Drupal\privatemsg\Entity\PrivatemsgThread
   */
  protected $thread;

  /**
   * SetUp the test class.
   */
  public function setUp(): void {
    parent::setUp();

    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);

    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);

    $this->term = Term::create([
      'name' => 'My test tag',
      'vid' => 'privatemsg_tags',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'field_privatemsg_tag_author' => $this->user1->id(),
    ]);
    $this->term->save();
  }

  /**
   * Tests that the thread tags can be changed.
   */
  public function testChangeTags() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');

    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');

    $this->drupalGet('/messages/view/2');
    $page = $this->getSession()->getPage();

    $this->click('details.privatemsg-add-tag-form');

    $tag_input = $page->findField('edit-thread-tags');
    $this->assertNotEmpty($tag_input);
    $page->fillField('edit-thread-tags', 'My test tag');

    $page->findButton('edit-submit--2')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->drupalGet('/messages/view/2');
    $this->assertSession()->pageTextContains('My test tag');
  }

}
