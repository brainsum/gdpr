<?php

namespace Drupal\gdpr_tasks\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Task entities.
 */
class TaskViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
