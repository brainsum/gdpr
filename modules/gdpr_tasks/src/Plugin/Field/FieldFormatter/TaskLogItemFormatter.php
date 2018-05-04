<?php

namespace Drupal\gdpr_tasks\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the TaskLogItem formatter.
 *
 * @FieldFormatter(
 *   id = "gdpr_task_item",
 *   label = @Translation("GDPR Removal Task Item"),
 *   field_types = {
 *    "gdpr_task_item"
 *   }
 * )
 */
class TaskLogItemFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $key => $item) {
      $elements[$key] = [
        '#type' => 'markup',
        '#markup' => "Entity type: {$item->entity_type} ID: {$item->entity_id} Field name: {$item->field_name} Action: {$item->action} Anonymizer: {$item->anonymizer}",
      ];
    }

    return $elements;
  }

}
