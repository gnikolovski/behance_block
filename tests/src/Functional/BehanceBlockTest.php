<?php

namespace Drupal\Tests\behance_block\Functional;

use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Behance Block.
 *
 * @group behance_block
 */
class BehanceBlockTest extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'block',
    'behance_block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'administer blocks',
      'administer site configuration',
      'access administration pages',
    ]);

    $this->drupalLogin($admin_user);
  }

  /**
   * Test that the block is available.
   */
  public function testBlockAvailability() {
    $this->drupalGet('/admin/structure/block');
    $this->clickLink('Place block');
    $this->assertSession()->pageTextContains('Behance Block');
    $this->assertSession()->linkByHrefExists('admin/structure/block/add/behance_block/', 0);
  }

  /**
   * Test that the block can be placed.
   */
  public function testBlockPlacement() {
    $this->drupalPlaceBlock('behance_block', [
      'region' => 'content',
      'label' => 'Behance Block',
      'id' => 'behanceblock',
    ]);

    $this->drupalGet('admin/structure/block');
    $this->assertSession()->pageTextContains('Behance Block');

    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Behance Block');
  }

}
