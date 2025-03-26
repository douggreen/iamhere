<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Core\Render\Markup;
use Drupal\Tests\BrowserTestBase;
use Drupal\filter\Entity\FilterFormat;
use Drupal\privatemsg\Entity\PrivatemsgMessage;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgThreadPagerTest extends BrowserTestBase {

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

    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);

    // Create Full HTML text format.
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
    ]);
    $full_html_format->save();
  }

  /**
   * Generate thread messages.
   */
  public function generateThreadMessages(int $num) {
    $sender_id = $this->user1->id();
    $recipient_id = $this->user2->id();

    $messages = [];
    for ($i = 1; $i <= $num; $i++) {
      $message = PrivatemsgMessage::create([
        'owner' => $sender_id,
        'message' => [
          'value' => Markup::create($this->getRandomGenerator()->sentences(random_int(4, 10), TRUE)),
          'format' => 'full_html',
        ],
      ]);
      $message->save();
      $messages[] = $message->id();
    }

    $thread_members = [$sender_id, $recipient_id];

    $group_id = \Drupal::service('privatemsg.common')->getLastThreadGroupNumber();
    ++$group_id;

    $subject = $this->getRandomGenerator()->sentences(random_int(4, 10), TRUE);

    foreach ($thread_members as $thread_member) {
      $data = [
        'subject' => $subject,
        'members' => $thread_members,
        'owner' => $thread_member,
        'group' => $group_id,
        'private_messages' => $messages,
        'updated_custom' => \Drupal::time()->getRequestTime(),
      ];
      $thread = \Drupal::entityTypeManager()
        ->getStorage('privatemsg_thread')
        ->create($data);
      $thread->save();
    }
  }

  /**
   * Tests the thread pager correct page.
   */
  public function testThreadPager() {
    $this->drupalLogin($this->user2);

    // Test one-page thread.
    $this->generateThreadMessages(49);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();
    $path = $page->find('xpath', '//tr[1]/td[@class="views-field views-field-subject"]/a');
    $href = str_replace('/web', '', $path->getAttribute('href'));
    $this->assertEquals('/messages/view/2', $href);
    // Read messages.
    $this->drupalGet('/messages/view/2');
    $this->assertSession()->statusCodeEquals(200);

    // Test two-page thread. Send 2 new messages.
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->submitForm([
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    // Now we have two pages, but the first unread message is on 1 page.
    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();
    $path = $page->find('xpath', '//tr[1]/td[@class="views-field views-field-subject"]/a');
    $href = str_replace('/web', '', $path->getAttribute('href'));
    $this->assertEquals('/messages/view/2', $href);
    // Read messages.
    $this->drupalGet('/messages/view/2');
    $this->assertSession()->statusCodeEquals(200);

    // Test two-page thread. Send new message.
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    // Now the first unread message is on 2 page.
    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();
    $path = $page->find('xpath', '//tr[1]/td[@class="views-field views-field-subject"]/a');
    $href = str_replace('/web', '', $path->getAttribute('href'));
    $this->assertEquals('/messages/view/2?page=1', $href);
    // Read messages.
    $this->drupalGet('/messages/view/2');
    $this->assertSession()->statusCodeEquals(200);

    // Test two-page thread without new messages.
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $page = $this->getSession()->getPage();
    $path = $page->find('xpath', '//tr[1]/td[@class="views-field views-field-subject"]/a');
    $href = str_replace('/web', '', $path->getAttribute('href'));
    $this->assertEquals('/messages/view/2?page=1', $href);
    // Read messages.
    $this->drupalGet('/messages/view/2');
    $this->assertSession()->statusCodeEquals(200);
  }

}
