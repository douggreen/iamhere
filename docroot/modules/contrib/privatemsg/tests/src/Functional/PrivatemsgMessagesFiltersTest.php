<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\privatemsg\Entity\PrivatemsgThread;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgMessagesFiltersTest extends BrowserTestBase {

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
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user3;

  /**
   * Current thread.
   *
   * @var \Drupal\privatemsg\Entity\PrivatemsgThread
   */
  protected $thread;

  /**
   * Term for referencing.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);

    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);

    $this->user3 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user3->id(), 'enable', 1);

    $this->term = Term::create([
      'name' => 'Favorites',
      'vid' => 'privatemsg_tags',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'field_privatemsg_tag_author' => $this->user1->id(),
    ]);
    $this->term->save();
  }

  /**
   * Tests messages filters.
   */
  public function testMessagesFilters() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_subject' => 'Test subject',
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user3->getDisplayName() . ' (' . $this->user3->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user3->getDisplayName() . ' (' . $this->user3->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');

    // Test subject filter.
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'subject' => 'Test subject',
    ], 'Apply', 'views-exposed-form-all-privatemsg-threads-page-1');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 1);

    // Test members filter.
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'uid' => $this->user3->getDisplayName() . ' (' . $this->user3->id() . ')',
    ], 'Apply', 'views-exposed-form-all-privatemsg-threads-page-1');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 2);

    // Test tag filter.
    $this->thread = PrivatemsgThread::load(6);
    $this->thread->set('tags', $this->term->id());
    $this->thread->save();
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'tags_target_id' => $this->term->id(),
    ], 'Apply', 'views-exposed-form-all-privatemsg-threads-page-1');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 1);
  }

}
