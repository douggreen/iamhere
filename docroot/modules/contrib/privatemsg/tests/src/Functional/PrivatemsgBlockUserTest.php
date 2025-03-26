<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgBlockUserTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'privatemsg block users',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);

    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'privatemsg block users',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);

    $this->user3 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'privatemsg block users',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user3->id(), 'enable', 1);
  }

  /**
   * Tests block user from thread.
   */
  public function testBlockUserFromThread() {
    /* User 1 send message to User 2 */
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');

    /* User 2 is blocking User 1 */
    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('block');
    $this->clickLink('block');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('unblock');

    /* User 1 trying to send message again */
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->pageTextContains('You are not permitted to send messages to user ' . $this->user2->getAccountName());

    /* User 2 is unblocking User 1 */
    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('unblock');
    $this->clickLink('unblock');
    $this->assertSession()->pageTextContains('block');

    /* User 1 has unblockable role now */
    $role_llamalovers = $this->drupalCreateRole([], 'llamalovers', 'Llama Lovers');
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('privatemsg.settings')
      ->set('unblockable_roles', [0 => 'llamalovers'])
      ->save();
    $this->user1->addRole($role_llamalovers);
    $this->user1->save();

    /* User 2 can not block User 1 now */
    $this->drupalGet('/messages/view/1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('block');
    $this->assertSession()->pageTextNotContains('unblock');
  }

  /**
   * Tests block user from form.
   */
  public function testBlockUserFromForm() {
    $this->drupalLogin($this->user1);
    $url = Url::fromRoute('privatemsg.block_user_form');
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('There are no items yet');

    /* Ban some users */
    $this->submitForm([
      'blocked' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
    ], 'Block user', 'privatemsg-block-user-form');
    $this->assertSession()->pageTextNotContains('There are no items yet');
    $this->submitForm([
      'blocked' => $this->user3->getDisplayName() . ' (' . $this->user3->id() . ')',
    ], 'Block user', 'privatemsg-block-user-form');
    $this->assertSession()->pageTextNotContains('There are no items yet');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 2);

    /* Unban one user */
    $this->drupalGet('/messages/block/' . $this->user3->id());
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('There are no items yet');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 1);

    /* User 3 has unblockable role now */
    $role_llamalovers = $this->drupalCreateRole([], 'llamalovers', 'Llama Lovers');
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('privatemsg.settings')
      ->set('unblockable_roles', [0 => 'llamalovers'])
      ->save();
    $this->user3->addRole($role_llamalovers);
    $this->user3->save();

    /* User 1 can not block User 3 now */
    $this->submitForm([
      'blocked' => $this->user3->getDisplayName() . ' (' . $this->user3->id() . ')',
    ], 'Block user', 'privatemsg-block-user-form');
    $this->assertSession()->pageTextNotContains('There are no items yet');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 1);

    /* Trying to bal already banned user */
    $this->submitForm([
      'blocked' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
    ], 'Block user', 'privatemsg-block-user-form');
    $this->assertSession()->pageTextNotContains('There are no items yet');
    $this->assertSession()->elementsCount('xpath', '//table/tbody/tr', 1);
  }

}
