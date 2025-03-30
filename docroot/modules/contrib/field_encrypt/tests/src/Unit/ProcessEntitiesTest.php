<?php

declare(strict_types=1);

namespace Drupal\Tests\field_encrypt\Unit;

// phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Tests\UnitTestCase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_encrypt\Plugin\Field\FieldType\EncryptedFieldStorageItem;
use Drupal\field_encrypt\ProcessEntities;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Runner\Version;

/**
 * Unit Tests for the ProcessEntities service.
 *
 * @group field_encrypt
 * @coversDefaultClass \Drupal\field_encrypt\ProcessEntities
 */
class ProcessEntitiesTest extends UnitTestCase {

  /**
   * A mock entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface&MockObject
   */
  protected ContentEntityInterface&MockObject $entity;

  /**
   * A mock field.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface&MockObject
   */
  protected FieldItemListInterface&MockObject $field;

  /**
   * A mock encrypted storage field.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface&MockObject
   */
  protected FieldItemListInterface&MockObject $storageField;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    if (version_compare(Version::id(), '8.0', '<')) {
      $this->markTestSkipped('This test needs PHPUnit 8');
    }

    // Set up a mock entity.
    $this->entity = $this->createMock('\Drupal\Core\Entity\ContentEntityInterface');

    // Set up language object.
    $language = $this->createMock('\Drupal\Core\Language\LanguageInterface');

    // Set up expectations for language.
    $language->expects($this->any())
      ->method('getId')
      ->willReturn('en');

    // Set up expectations for entity.
    $this->entity->expects($this->any())
      ->method('getTranslationLanguages')
      ->willReturn([$language]);
    $this->entity->expects($this->any())
      ->method('getTranslation')
      ->willReturnSelf();

    // Set up a mock field.
    $this->field = $this->createMock('\Drupal\Core\Field\FieldItemListInterface');

    // Set up a mock storage field.
    $storage_item = $this->createMock(EncryptedFieldStorageItem::class);
    $this->storageField = $this->createMock('\Drupal\Core\Field\FieldItemListInterface');
    $this->storageField->expects($this->any())
      ->method('offsetGet')
      ->with(0)
      ->willReturn($storage_item);
  }

  /**
   * Tests the encryptEntity / decryptEntity methods.
   *
   * @covers ::__construct
   * @covers ::encryptEntity
   * @covers ::decryptEntity
   * @covers ::encryptField
   * @covers ::encryptFieldValue
   * @covers ::getUnencryptedPlaceholderValue
   *
   * @dataProvider encryptDecryptEntityDataProvider
   */
  public function testEncryptDecryptEntity(string $field_type, array $property_definitions, array $properties, mixed $field_value, ?array $expected_placeholder, ?bool $encrypted): void {
    // Set up field definition.
    $definition = $this->getMockBuilder(FieldConfig::class)
      ->onlyMethods(['getName', 'getFieldStorageDefinition', 'getType'])
      ->disableOriginalConstructor()
      ->getMock();

    // Set up field storage.
    $storage = $this->getMockBuilder(FieldStorageConfig::class)
      ->onlyMethods(['getThirdPartySetting', 'getPropertyDefinitions', 'isBaseField'])
      ->disableOriginalConstructor()
      ->getMock();

    // Set up expectations for storage.
    $storage_map = [
      ['field_encrypt', 'encrypt', FALSE, $encrypted],
      ['field_encrypt', 'properties', [], $properties],
    ];
    $storage->expects($this->any())
      ->method('getThirdPartySetting')
      ->willReturnMap($storage_map);
    $storage->expects($this->any())
      ->method('getPropertyDefinitions')
      ->willReturn($property_definitions);
    $storage->expects($this->any())
      ->method('isBaseField')
      ->willReturn(FALSE);

    // Set up expectations for definition.
    $definition->expects($this->any())
      ->method('getName')
      ->willReturn('test_field');
    $definition->expects($this->any())
      ->method('getFieldStorageDefinition')
      ->willReturn($storage);

    $definition->expects($this->any())
      ->method('getType')
      ->willReturn($field_type);

    // Set up expectations for field.
    $this->field->expects($this->any())
      ->method('getFieldDefinition')
      ->willReturn($definition);
    $this->field->expects($this->any())
      ->method('getName')
      ->willReturn('test_field');

    if ($encrypted) {
      $this->field->expects($this->once())
        ->method('getValue')
        ->willReturn($field_value);
      $this->field->expects($this->once())
        ->method('setValue')
        ->with(self::identicalTo($expected_placeholder));
    }
    else {
      $this->field->expects($this->never())
        ->method('getValue');
      $this->field->expects($this->never())
        ->method('setValue');
    }

    // Set expectations for entity.
    $this->entity->expects($this->once())
      ->method('hasField')
      ->with(ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME)
      ->willReturn(TRUE);
    $this->entity->expects($this->once())
      ->method('getFields')
      ->willReturn([
        'test_field' => $this->field,
      ]);
    $this->entity->expects($this->any())
      ->method('get')
      ->with(ProcessEntities::ENCRYPTED_FIELD_STORAGE_NAME)
      ->willReturn($this->storageField);

    // Set up a mock for the EncryptionProfile class to mock some methods.
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->once())
      ->method('invokeAllWith');

    $service = new ProcessEntities($module_handler);

    $service->encryptEntity($this->entity);
  }

  /**
   * Data provider for testEncryptDecryptEntity method.
   *
   * @return array
   *   An array with data for the test method.
   */
  public static function encryptDecryptEntityDataProvider(): array {
    return [
      'encrypted_string' => [
        'string',
        [
          'value' => new DataDefinition([
            'type' => 'string',
            'required' => TRUE,
            'settings' => ['case_sensitive' => FALSE],
          ]),
        ],
        ['value' => 'value'],
        [['value' => 'unencrypted text']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_string_long' => [
        'string_long',
        [
          'value' => new DataDefinition([
            'type' => 'string',
            'required' => TRUE,
            'settings' => ['case_sensitive' => FALSE],
          ]),
        ],
        ['value' => 'value'],
        [['value' => 'unencrypted text']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_text' => [
        'text',
        [
          'value' => new DataDefinition(['type' => 'string', 'required' => TRUE]),
          'format' => new DataDefinition(['type' => 'filter_format']),
          'processed' => new DataDefinition([
            'type' => 'string',
            'computed' => TRUE,
            'class' => '\Drupal\text\TextProcessed',
            'settings' => ['text source' => 'value'],
          ]),
        ],
        ['value' => 'value', 'format' => 'format'],
        [['value' => '<p>unencrypted text</p>', 'format' => 'basic_html']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE, 'format' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_text_long' => [
        'text_long',
        [
          'value' => new DataDefinition(['type' => 'string', 'required' => TRUE]),
          'format' => new DataDefinition(['type' => 'filter_format']),
          'processed' => new DataDefinition([
            'type' => 'string',
            'computed' => TRUE,
            'class' => '\Drupal\text\TextProcessed',
            'settings' => ['text source' => 'value'],
          ]),
        ],
        ['value' => 'value', 'format' => 'format'],
        [['value' => '<p>unencrypted text</p>', 'format' => 'basic_html']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE, 'format' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_text_with_summary' => [
        'text_with_summary',
        [
          'value' => new DataDefinition(['type' => 'string', 'required' => TRUE]),
          'format' => new DataDefinition(['type' => 'filter_format']),
          'processed' => new DataDefinition([
            'type' => 'string',
            'computed' => TRUE,
            'class' => '\Drupal\text\TextProcessed',
            'settings' => ['text source' => 'value'],
          ]),
          'summary' => new DataDefinition(['type' => 'string', 'required' => TRUE]),
          'summary_processed' => new DataDefinition([
            'type' => 'string',
            'computed' => TRUE,
            'class' => '\Drupal\text\TextProcessed',
            'settings' => ['text source' => 'summary'],
          ]),
        ],
        ['value' => 'value', 'summary' => 'summary', 'format' => 'format'],
        [['value' => '<p>unencrypted text</p>', 'summary' => 'summary', 'format' => 'basic_html']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE, 'summary' => ProcessEntities::ENCRYPTED_VALUE, 'format' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_list_string' => [
        'list_string',
        [
          'value' => new DataDefinition([
            'type' => 'string',
            'required' => TRUE,
            'constraints' => ['Length' => ['max' => 255]],
          ]),
        ],
        ['value' => 'value'],
        [['value' => 'value1']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_email' => [
        'email',
        [
          'value' => new DataDefinition(['type' => 'email', 'required' => TRUE]),
        ],
        ['value' => 'value'],
        [['value' => 'test@example.com']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_date' => [
        'datetime',
        [
          'value' => new DataDefinition([
            'type' => 'datetime_iso8601',
            'required' => TRUE,
          ]),
          'date' => new DataDefinition([
            'type' => 'any',
            'computed' => TRUE,
            'class' => '\Drupal\datetime\DateTimeComputed',
            'settings' => ['date source' => 'value'],
          ]),
        ],
        ['value' => 'value'],
        [['value' => '1984-10-04T00:00:00']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_link' => [
        'link',
        [
          'uri' => new DataDefinition(['type' => 'uri']),
          'title' => new DataDefinition(['type' => 'string']),
          'options' => new DataDefinition(['type' => 'map']),
        ],
        ['uri' => 'uri', 'title' => 'title'],
        [[
          'title' => 'Drupal.org',
          'attributes' => [],
          'options' => [],
          'uri' => 'https://drupal.org',
        ],
        ],
        [[
          'title' => ProcessEntities::ENCRYPTED_VALUE,
          'attributes' => [],
          'options' => [],
          'uri' => ProcessEntities::ENCRYPTED_VALUE,
        ],
        ],
        TRUE,
      ],
      'encrypted_int' => [
        'integer',
        [
          'value' => new DataDefinition([
            'type' => 'integer',
            'required' => TRUE,
          ]
          ),
        ],
        ['value' => 'value'],
        [['value' => '42']],
        [['value' => 0]],
        TRUE,
      ],
      'encrypted_float' => [
        'float',
        [
          'value' => new DataDefinition([
            'type' => 'float',
            'required' => TRUE,
          ]
          ),
        ],
        ['value' => 'value'],
        [['value' => '3.14']],
        [['value' => 0]],
        TRUE,
      ],
      'encrypted_decimal' => [
        'decimal',
        [
          'value' => new DataDefinition([
            'type' => 'decimal',
            'required' => TRUE,
          ]
          ),
        ],
        ['value' => 'value'],
        [['value' => '3.14']],
        [['value' => 0]],
        TRUE,
      ],
      'encrypted_boolean' => [
        'boolean',
        [
          'value' => new DataDefinition([
            'type' => 'boolean',
            'required' => TRUE,
          ]
          ),
        ],
        ['value' => 'value'],
        [['value' => 1]],
        [['value' => 0]],
        TRUE,
      ],
      'encrypted_telephone' => [
        'telephone',
        [
          'value' => new DataDefinition([
            'type' => 'string',
            'required' => TRUE,
          ]
          ),
        ],
        ['value' => 'value'],
        [['value' => '+1-202-555-0161']],
        [['value' => ProcessEntities::ENCRYPTED_VALUE]],
        TRUE,
      ],
      'encrypted_entity_reference' => [
        'entity_reference',
        [
          'target_id' => new DataDefinition([
            'type' => 'integer',
            'settings' => ['unsigned' => TRUE],
            'required' => TRUE,
          ]),
          'entity' => new DataDefinition([
            'type' => 'entity_reference',
            'computed' => TRUE,
            'read-only' => FALSE,
            'constraints' => ['EntityType' => 'user'],
          ]),
        ],
        ['target_id' => 'target_id'],
        [['target_id' => 1]],
        [['target_id' => 0]],
        TRUE,
      ],
      'not_encrypted' => ['text', [], [], 'unencrypted text', NULL, FALSE],
    ];
  }

}
