<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgSendMessageToRolesTest extends BrowserTestBase {

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
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user4;

  /**
   * The User used for the test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user5;

  /**
   * SetUp the test class.
   */
  public function setUp(): void {
    parent::setUp();

    $this->user1 = $this->DrupalCreateUser([
      'privatemsg write messages',
      'privatemsg send to role',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user1->id(), 'enable', 1);

    $role_llamalovers = $this->drupalCreateRole([], 'llamalovers', 'Llama Lovers');
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('privatemsg.settings')
      ->set('allowed_roles', [0 => 'llamalovers'])
      ->save();
    $value = $config_factory->get('privatemsg.settings')->get('allowed_roles');
    $this->assertSame([0 => 'llamalovers'], $value);

    $this->user2 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user2->id(), 'enable', 1);
    $this->user2->addRole($role_llamalovers);
    $this->user2->save();

    $this->user3 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user3->id(), 'enable', 1);
    $this->user3->addRole($role_llamalovers);
    $this->user3->save();

    $this->user4 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user4->id(), 'enable', 1);
  }

  /**
   * Tests that the message can be sent.
   */
  public function testSendMessageToRole() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'thread_members' => 'Llama Lovers (role)',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalLogin($this->user2);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'td.views-field-privatemsg-messages-counter-views-field', 1);

    $this->drupalLogin($this->user3);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'td.views-field-privatemsg-messages-counter-views-field', 1);

    $this->drupalLogin($this->user4);
    $this->drupalGet('/messages');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.vbo-view-form', 'No messages available.');

    // Test when role has only one user and it is a sender.
    $role_catcuddlers = $this->drupalCreateRole([], 'catcuddlers', 'Cat Cuddlers');
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('privatemsg.settings')
      ->set('allowed_roles', [0 => 'catcuddlers'])
      ->save();
    $value = $config_factory->get('privatemsg.settings')->get('allowed_roles');
    $this->assertSame([0 => 'catcuddlers'], $value);
    $this->user5 = $this->DrupalCreateUser([
      'privatemsg write messages',
    ]);
    \Drupal::service('user.data')->set('privatemsg', $this->user5->id(), 'enable', 1);
    $this->user5->addRole($role_catcuddlers);
    $this->user5->save();
    $this->drupalLogin($this->user5);
    $this->drupalGet('/messages/new');
    $this->submitForm([
      'thread_members' => 'Cat Cuddlers (role)',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->assertSession()->pageTextNotContains('The website encountered an unexpected error');
  }

}
