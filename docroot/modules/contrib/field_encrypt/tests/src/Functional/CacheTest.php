<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Functional;

use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests field encryption caching.
 *
 * @group field_encrypt
 */
class CacheTest extends FieldEncryptTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  protected static $modules = ['dynamic_page_cache'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    // Set up fields for encryption.
    $this->setFieldStorageSettings(TRUE);

    // Create a test entity.
    $this->createTestNode();
  }

  /**
   * Test caching of encrypted fields on response level.
   */
  public function testDynamicPageCache(): void {
    // Page should be uncacheable due to max-age = 0.
    $this->drupalGet('node/' . $this->testNode->id());
    $this->assertStringStartsWith('UNCACHEABLE', $this->getSession()->getResponseHeader(DynamicPageCacheSubscriber::HEADER), 'Page with encrypted fields is uncacheable.');

    // Set encrypted field as cacheable.
    $this->drupalGet('admin/config/system/field-encrypt');
    $this->submitForm(['make_entities_uncacheable' => FALSE], 'Save configuration');

    // Page is cacheable, but currently not cached.
    $this->drupalGet('node/' . $this->testNode->id());
    $this->assertEquals('MISS', $this->getSession()->getResponseHeader(DynamicPageCacheSubscriber::HEADER), 'Dynamic Page Cache MISS.');

    // Page is cacheable, and should be cached.
    $this->drupalGet('node/' . $this->testNode->id());
    $this->assertEquals('HIT', $this->getSession()->getResponseHeader(DynamicPageCacheSubscriber::HEADER), 'Dynamic Page Cache HIT.');
  }

  /**
   * Test caching of encrypted fields on entity level.
   */
  public function testEntityCache(): void {
    // Check if entity with uncacheable fields is cached by the entity
    // storage.
    $entity_type = $this->testNode->getEntityTypeId();
    $cid = "values:$entity_type:" . $this->testNode->id();

    // Check whether node entities are marked as uncacheable.
    $definition = $this->entityTypeManager->getDefinition('node');
    $this->assertFalse($definition->isPersistentlyCacheable());
    $this->assertFalse($definition->isRenderCacheable());
    $this->assertTrue($definition->isStaticallyCacheable());

    // Check that no initial cache entry is present.
    $this->assertFalse(\Drupal::cache('entity')->get($cid), 'Entity cache: no initial cache.');

    $controller = $this->entityTypeManager->getStorage($entity_type);
    $controller->load($this->testNode->id());

    // Check if entity gets cached.
    $this->assertFalse(\Drupal::cache('entity')->get($cid), 'Entity cache: entity is not in persistent cache.');

    // Set encrypted field as cacheable.
    $this->config('field_encrypt.settings')
      ->set('make_entities_uncacheable', FALSE)
      ->save();
    // Clear memory cache so the entity will now make it to the persistent
    // cache.
    \Drupal::service('entity.memory_cache')->deleteAll();

    // Check whether node entities are marked as cacheable.
    $definition = $this->entityTypeManager->getDefinition('node');
    $this->assertTrue($definition->isPersistentlyCacheable());
    $this->assertTrue($definition->isRenderCacheable());
    $this->assertTrue($definition->isStaticallyCacheable());

    // Load the node again. It should be cached now.
    $controller = $this->entityTypeManager->getStorage($entity_type);
    $controller->load($this->testNode->id());

    /** @var false|object $cache */
    $cache = \Drupal::cache('entity')->get($cid);
    $this->assertTrue(is_object($cache), 'Entity cache: entity is in persistent cache.');
  }

  /**
   * {@inheritdoc}
   */
  protected function setFieldStorageSettings(bool $encryption = TRUE): void {
    $fields = [
      'node.field_test_single' => [
        'properties' => ['value' => 'value', 'summary' => 'summary'],
      ],
      'node.field_test_multi' => [
        'properties' => ['value' => 'value'],
      ],
    ];

    foreach ($fields as $field => $settings) {
      $field_storage = FieldStorageConfig::load($field);
      if ($encryption) {
        $field_storage->setThirdPartySetting('field_encrypt', 'encrypt', TRUE);
        $field_storage->setThirdPartySetting('field_encrypt', 'properties', $settings['properties']);
      }
      else {
        $field_storage->unsetThirdPartySetting('field_encrypt', 'encrypt');
        $field_storage->unsetThirdPartySetting('field_encrypt', 'properties');
      }
      $field_storage->save();
    }
  }

}
