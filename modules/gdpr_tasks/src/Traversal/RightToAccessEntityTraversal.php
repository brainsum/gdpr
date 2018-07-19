<?php

namespace Drupal\gdpr_tasks\Traversal;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\EntityTraversal;

/**
 * Entity traversal for performing Right to Access requests.
 *
 * @package Drupal\gdpr_tasks
 */
class RightToAccessEntityTraversal extends EntityTraversal {

  private $assets = [];

  /**
   * {@inheritdoc}
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, GdprField $parent_config = NULL) {
    $entity_type = $entity->getEntityTypeId();

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    $field_configs = $config->getFieldsForBundle($entity->bundle());

    foreach ($fields as $field_id => $field) {
      $field_config = isset($field_configs[$field_id]) ? $field_configs[$field_id] : NULL;

      // If the field is not configured, not enabled,
      // or not enabled for RTA, then skip it.
      if ($field_config === NULL || !$field_config->enabled || !in_array($field_config->rta, ['inc', 'maybe'])) {
        continue;
      }

      $plugin_name = "{$entity_type}|{$entity->bundle()}|{$field_id}";

      $filename = 'main';
      if ($parent_config) {
        $filename = !empty($parent_config->sarsFilename) ? $parent_config->sarsFilename : $filename;
      }

      $field_value = $this->getFieldValue($entity, $field, $field_id);

      $data = [
        'plugin_name' => $plugin_name,
        'entity_type' => $entity_type,
        'entity_id' => $entity->id(),
        'file' => $filename,
        'row_id' => $row_id,
        'label' => $field->getLabel(),
        'value' => $field_value,
        'notes' => $field_config->notes,
        'rta' => $field_config->rta,
      ];

      $this->results["{$plugin_name}|{$entity->id()}"] = $data;
    }
  }

  /**
   * Gets the field value, taking into account file references.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The current entity being processed.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   Field definition.
   * @param string $field_id
   *   Field ID.
   *
   * @return string
   *   Field value
   */
  protected function getFieldValue(FieldableEntityInterface $entity, FieldDefinitionInterface $field, $field_id) {
    // Special handling for file references.
    // For files, we want to add to the assets collection.
    $labels = [];
    if ($entity->{$field_id} instanceof EntityReferenceFieldItemList && $field->getSetting('target_type') == 'file') {
      /* @var \Drupal\file\Entity\File $file */
      foreach ($entity->{$field_id}->referencedEntities() as $file) {
        $this->assets[] = ['target_id' => $file->id(), 'display' => 1];
        $labels[] = "assets/{$file->id()}." . pathinfo($file->getFileUri(), PATHINFO_EXTENSION);
      }
    }
    elseif ($entity->{$field_id} instanceof EntityReferenceFieldItemList) {
      /* @var \Drupal\Core\Entity\EntityInterface $referenced_entity */
      foreach ($entity->{$field_id}->referencedEntities() as $referenced_entity) {
        if ($referenced_entity->label()) {
          $labels[] = "{$referenced_entity->label()} [{$referenced_entity->id()}]";
        }
        else {
          $labels[] = $referenced_entity->id();
        }
      }
    }
    else {
      $labels[] = $entity->get($field_id)->getString();
    }
    $field_value = implode(', ', $labels);
    return $field_value;
  }

}
