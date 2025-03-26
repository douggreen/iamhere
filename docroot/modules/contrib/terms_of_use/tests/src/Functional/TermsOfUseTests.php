<?php

namespace Drupal\Tests\terms_of_use\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Class TermsOfUseTests. The base class for testing terms of use.
 */
class TermsOfUseTests extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'terms_of_use'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test terms of use.
   */
  public function testTermsOfUse() {
    // Log in as an admin user with permission to manage settings.
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    // Enable private fields for node content type.
    $config = \Drupal::configFactory()->getEditable('terms_of_use.settings');
    $config->set('terms_of_use_label_checkbox', 'Agree to my terms');
    $config->save();

    $this->drupalLogout();

    // Check the status of the page.
    $this->drupalGet('user/register');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->pageTextContains('Agree to my terms');
  }

}
