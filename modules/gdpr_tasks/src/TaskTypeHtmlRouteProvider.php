<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides routes for Task type entities.
 *
 * @see \Drupal\entity\Routing\AdminHtmlRouteProvider
 * @see \Drupal\entity\Routing\DefaultHtmlRouteProvider
 */
class TaskTypeHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    // Provide your custom entity routes here.
    return $collection;
  }

}
