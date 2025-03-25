<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

final class FieldInfoCommands extends DrushCommands
{
    use AutowireTrait;
    use EntityTypeBundleAskTrait;
    use EntityTypeBundleValidationTrait;
    use FieldDefinitionRowsOfFieldsTrait;

    const INFO = 'field:info';

    public function __construct(
        protected EntityTypeManagerInterface $entityTypeManager,
        protected EntityTypeBundleInfoInterface $entityTypeBundleInfo
    ) {
    }

    /**
     * List all configurable fields of an entity bundle
     */
    #[CLI\Command(name: self::INFO, aliases: ['field-info', 'fi'])]
    #[CLI\Argument(name: 'entityType', description: 'The machine name of the entity type.')]
    #[CLI\Argument(name: 'bundle', description: 'The machine name of the bundle.')]
    #[CLI\Option(name: 'show-machine-names', description: 'Show machine names instead of labels in option lists.')]
    #[CLI\DefaultTableFields(fields: [
        'field_name',
        'required',
        'field_type',
        'cardinality',
    ])]
    #[CLI\FieldLabels(labels: [
        'label' => 'Label',
        'description' => 'Description',
        'field_name' => 'Field name',
        'field_type' => 'Field type',
        'required' => 'Required',
        'translatable' => 'Translatable',
        'cardinality' => 'Cardinality',
        'default_value' => 'Default value',
        'default_value_callback' => 'Default value callback',
        'allowed_values' => 'Allowed values',
        'allowed_values_function' => 'Allowed values function',
        'handler' => 'Selection handler',
        'target_bundles' => 'Target bundles',
    ])]
    #[CLI\FilterDefaultField(field: 'field_name')]
    #[CLI\Usage(name: 'field:info taxonomy_term tag', description: 'List all fields.')]
    #[CLI\Usage(name: self::INFO, description: 'List all fields and fill in the remaining information through prompts.')]
    #[CLI\Complete(method_name_or_callable: 'complete')]
    #[CLI\Version(version: '11.0')]
    public function info(?string $entityType = null, ?string $bundle = null, array $options = [
        'format' => 'table',
    ]): RowsOfFields
    {
        $this->input->setArgument('entityType', $entityType = $entityType ?? $this->askEntityType());
        $this->validateEntityType($entityType);

        $this->input->setArgument('bundle', $bundle = $bundle ?? $this->askBundle());
        $this->validateBundle($entityType, $bundle);

        $fieldDefinitions = $this->entityTypeManager
            ->getStorage('field_config')
            ->loadByProperties([
                'entity_type' => $entityType,
                'bundle' => $bundle,
            ]);

        return $this->getRowsOfFieldsByFieldDefinitions($fieldDefinitions);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->getCompletionName() === 'entityType') {
            $suggestions->suggestValues(array_keys($this->getFieldableEntityTypes()));
        }

        if ($input->getCompletionName() === 'bundle') {
            $entityTypeId = $input->getArgument('entityType');
            $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entityTypeId);

            $suggestions->suggestValues(array_keys($bundleInfo));
        }
    }
}
