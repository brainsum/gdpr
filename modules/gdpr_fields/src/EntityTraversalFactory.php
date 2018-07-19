<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Factory class for traversing entities.
 *
 * @package Drupal\gdpr_fields
 */
class EntityTraversalFactory {

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The traversal class name to be instantiated.
   *
   * @var string
   */
  protected $traverser;

  /**
   * EntityTraversal constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param string $traverser
   *   The traversal class name.
   */
  public function __construct(ContainerInterface $container, $traverser) {
    $this->container = $container;
    $this->traverser = $traverser;
  }

  /**
   * Instantiates the traversal class.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Base entity to start recursive traversal.
   *
   * @return \Drupal\gdpr_fields\EntityTraversalInterface
   *   Traversal class.
   */
  public function getTraversal(EntityInterface $entity) {
    $traversal_class = $this->traverser;
    $class = new \ReflectionClass($traversal_class);
    if ($class->implementsInterface(EntityTraversalInterface::class)) {
      /* @var \Drupal\gdpr_fields\EntityTraversalInterface $instance */
      $instance = $traversal_class::create($this->container, $entity);
      return $instance;
    }

    // @todo Handle exceptions?
  }

}
