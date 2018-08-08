<?php

namespace Drupal\gdpr_tasks\Traversal;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\EntityTraversal;

/**
 * Entity traversal for the right to be forgotten preview display.
 *
 * @package Drupal\gdpr_tasks
 */
class RightToBeForgottenDisplayTraversal extends EntityTraversal {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, GdprField $parent_config = NULL) {
    $entity_type = $entity->getEntityTypeId();
    $entity_definition = $entity->getEntityType();

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    $field_configs = $config->getFieldsForBundle($entity->bundle());

    foreach ($fields as $field_id => $field) {
      $field_config = isset($field_configs[$field_id]) ? $field_configs[$field_id] : NULL;

      // If the field is not configured, not enabled,
      // or not enabled for RTF, then skip it.
      if ($field_config === NULL
        || !$field_config->enabled
        || !in_array($field_config->rtf, ['anonymize', 'remove', 'maybe'])) {
        continue;
      }

      $key = "$entity_type.{$entity->id()}.$field_id";

      $this->results[$key] = [
        'title' => $field->getLabel(),
        'value' => $entity->get($field_id)->getString(),
        'entity' => $entity_definition->getLabel(),
        'bundle' => $this->getBundleLabel($entity),
        'notes' => $field_config->notes,
        'gdpr_rtf' => $field_config->rtf,
        'link' => $field_config->rtf == 'maybe' ? $entity->toLink('Edit', 'edit-form') : '',
      ];
    }
  }

}
