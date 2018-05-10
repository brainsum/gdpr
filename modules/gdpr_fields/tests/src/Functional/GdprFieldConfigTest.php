<?php

namespace Drupal\Tests\gdpr_fields\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests GDPR fields and configuration.
 *
 * @group gdpr
 */
class GdprFieldConfigTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['gdpr', 'gdpr_fields', 'anonymizer', 'ctools'];

  /**
   * Testing admin user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->createUser([], NULL, TRUE);
    // @TODO update page permission requirements.
    $this->adminUser->addRole('administrator');
    $this->adminUser->save();
  }

  /**
   * Test installing gdpr_fields module and field config list works.
   */
  public function testViewFieldsList() {
    // Check the site has installed successfully.
    $this->drupalGet('');
    $this->assertSession()->statusCodeEquals(200);

    // Check that prior to logging in, we can't access the fields list.
    $this->drupalGet('admin/reports/fields/gdpr-fields');
    $this->assertSession()->statusCodeEquals(403);

    // Gain access to the fields list.
    $this->drupalLogin($this->adminUser);

    // Check that the fields list has the expected content.
    $this->drupalGet('admin/reports/fields/gdpr-fields');
    $session = $this->assertSession();
    $session->statusCodeEquals(200);
    $session->elementTextContains('css', '.page-title', 'Used in GDPR');
    // @todo Check that some user fields are present.
  }

}
