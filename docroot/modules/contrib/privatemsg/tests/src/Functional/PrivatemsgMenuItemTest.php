<?php

namespace Drupal\Tests\privatemsg\Functional;

use Drupal\block\Entity\Block;
use Drupal\system\Entity\Menu;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests for the privatemsg module.
 *
 * @group privatemsg
 */
class PrivatemsgMenuItemTest extends BrowserTestBase {

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
   * The menu block.
   *
   * @var \Drupal\block\Entity\Block
   */
  protected $menuBlock;

  /**
   * {@inheritdoc}
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

    // Place the account menu block.
    $this->placeAccountMenuBlock();
  }

  /**
   * Places the account menu block in a visible region.
   */
  protected function placeAccountMenuBlock() {
    // Make sure the account menu exists.
    $menu = Menu::load('account');
    if (!$menu) {
      $menu = Menu::create([
        'id' => 'account',
        'label' => 'Account menu',
        'description' => 'Links related to the user account',
      ]);
      $menu->save();
    }

    // Create and place the block.
    $block = Block::create([
      'id' => 'account_menu',
      'theme' => $this->defaultTheme,
      'region' => 'content',
      'plugin' => 'system_menu_block:account',
      'settings' => [
        'id' => 'system_menu_block:account',
        'label' => 'Account menu',
        'label_display' => 'visible',
        'provider' => 'system',
        'level' => 1,
        'depth' => 0,
      ],
      'visibility' => [],
    ]);
    $block->save();

    $this->menuBlock = $block;
  }

  /**
   * Tests menu item.
   */
  public function testMenuItem() {
    $this->drupalLogin($this->user1);
    $this->drupalGet('/messages/new');
    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');
    $this->drupalGet('/messages/new');
    $this->submitForm([
      'thread_members' => $this->user2->getDisplayName() . ' (' . $this->user2->id() . ')',
      'message[0][value]' => $this->getRandomGenerator()->sentences(5),
    ], 'Send message', 'privatemsg-message-add-form');

    $this->drupalLogin($this->user2);
    $this->assertSession()->pageTextContains('Private Messages (2)');
  }

}
