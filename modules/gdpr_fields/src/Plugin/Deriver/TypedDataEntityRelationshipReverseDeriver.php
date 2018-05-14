<?php

namespace Drupal\gdpr_fields\Plugin\Deriver;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataManagerInterface;
use Drupal\ctools\Plugin\Deriver\TypedDataEntityRelationshipDeriver;

/**
 * Derives reverse entity relationships.
 */
class TypedDataEntityRelationshipReverseDeriver extends TypedDataEntityRelationshipDeriver {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    TypedDataManagerInterface $typed_data_manager,
    TranslationInterface $string_translation
  ) {
    parent::__construct($typed_data_manager, $string_translation);
    $this->label = '@property Entity referencing @base';
  }

  /**
   * {@inheritdoc}
   */
  protected function generateDerivativeDefinition(
    $base_plugin_definition,
    $data_type_id,
    $data_type_definition,
    DataDefinitionInterface $base_definition,
    $property_name,
    DataDefinitionInterface $property_definition
  ) {
    if (\method_exists($property_definition, 'getType') && 'entity_reference' === $property_definition->getType()) {
      parent::generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, $base_definition, $property_name, $property_definition);

      // @todo Handle entity revision relationships.
      list($data_entity_type) = \explode(':', $data_type_id);
      if ('entity_revision' === $data_entity_type) {
        return;
      }

      $bundle_info = $base_definition->getConstraint('Bundle');
      if ($bundle_info && \array_filter($bundle_info) && $base_definition->getConstraint('EntityType')) {
        $base_data_type = 'entity:' . $base_definition->getConstraint('EntityType');
      }
      // Otherwise, just use the raw data type identifier.
      else {
        $base_data_type = $data_type_id;
      }

      // Provide the entity type.
      $derivative_id = $base_data_type . ':' . $property_name;
      if (isset($this->derivatives[$derivative_id])) {
        if ($base_definition->getConstraint('EntityType')) {
          $this->derivatives[$derivative_id]['source_entity_type'] = $base_definition->getConstraint('EntityType');
        }

        // @todo: Proper error handling when target_entity_type is not set.
        $target_data_type = 'entity:' . $this->derivatives[$derivative_id]['target_entity_type'];
        $context_definition = new ContextDefinition($target_data_type, $this->typedDataManager->createDataDefinition($target_data_type));
        // Add the constraints of the base definition to the context definition.
        if ($property_definition->getFieldStorageDefinition()->getPropertyDefinition('entity')->getConstraint('Bundle')) {
          $context_definition->addConstraint('Bundle', $property_definition->getFieldStorageDefinition()->getPropertyDefinition('entity')->getConstraint('Bundle'));
        }
        $this->derivatives[$derivative_id]['context']['base'] = $context_definition;
      }
    }
  }

}
