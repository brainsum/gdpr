<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a common interface for dependency container injection.
 *
 * This interface gives classes who need services a factory method for
 * instantiation as well as the entity to be traversed.
 */
interface EntityTraversalContainerInjectionInterface {

  /**
   * Creates an instance of the traversal for this specific entity.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   * @param \Drupal\Core\Entity\EntityInterface $base_entity
   *   The entity to be traversed.
   */
  public static function create(ContainerInterface $container, EntityInterface $base_entity);

}
