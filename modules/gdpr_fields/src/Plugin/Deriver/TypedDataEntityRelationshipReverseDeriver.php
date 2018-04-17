<?php

namespace Drupal\gdpr_fields\Plugin\Deriver;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\ctools\Plugin\Deriver\TypedDataEntityRelationshipDeriver;

class TypedDataEntityRelationshipReverseDeriver extends TypedDataEntityRelationshipDeriver {

  /**
   * {@inheritdoc}
   */
  protected $label = '@property Entity referencing @base';

  /**
   * {@inheritdoc}
   */
  protected function generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, DataDefinitionInterface $base_definition, $property_name, DataDefinitionInterface $property_definition) {
    if (method_exists($property_definition, 'getType') && $property_definition->getType() == 'entity_reference') {
      parent::generateDerivativeDefinition($base_plugin_definition, $data_type_id, $data_type_definition, $base_definition, $property_name, $property_definition);

      // Provide the entity type.
      $derivative_id = $data_type_id . ':' . $property_name;
      if (isset($this->derivatives[$derivative_id])) {
        if ($base_definition->getConstraint('EntityType')) {
          $this->derivatives[$derivative_id]['source_entity_type'] = $base_definition->getConstraint('EntityType');
        }

        $target_data_type =  'entity:' . $this->derivatives[$derivative_id]['target_entity_type'];
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
