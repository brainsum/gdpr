<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for traversing entities.
 *
 * @package Drupal\gdpr_fields
 */
abstract class EntityTraversal implements EntityTraversalInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity storage for GDPR config entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $configStorage;

  /**
   * Reverse relationship information.
   *
   * @var \Drupal\gdpr_fields\Entity\GdprField[]
   */
  private $reverseRelationshipFields = NULL;

  /**
   * The starting entity for the traversal.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $baseEntity;

  /**
   * Whether or not the traversal has happened successfully.
   *
   * @var bool
   */
  protected $success = NULL;

  /**
   * The processed entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $entities = [];

  /**
   * The results of the traversal.
   *
   * @var array
   */
  protected $results = [];

  /**
   * EntityTraversal constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityInterface $base_entity
   *   The starting entity for the traversal.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityInterface $base_entity) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->configStorage = $this->entityTypeManager->getStorage('gdpr_fields_config');
    $this->baseEntity = $base_entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, EntityInterface $base_entity) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $base_entity
    );
  }

  /**
   * {@inheritdoc}
   */
  public function traverse() {
    if (is_null($this->success)) {
      try {
        $this->traverseEntity($this->baseEntity);
        $this->success = TRUE;
      }
      catch (\Exception $e) {
        $this->success = FALSE;
      }
    }

    return $this->success;
  }

  /**
   * Traverses the entity relationship tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function traverseEntity(EntityInterface $entity) {
    $this->doTraversalRecursive($entity);
  }

  /**
   * Traverses the entity relationship tree.
   *
   * Calls the handleEntity method for every entity found.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The root entity to traverse.
   * @param \Drupal\gdpr_fields\Entity\GdprField|null $parent_config
   *   (Optional) The parent config field settings.
   * @param int|null $row_id
   *   (Optional) The row to place the information in.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function doTraversalRecursive(EntityInterface $entity, GdprField $parent_config = NULL, $row_id = NULL) {
    // If the entity is not fieldable, don't continue.
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    $entity_type = $entity->getEntityTypeId();

    // Explicitly make sure we don't traverse any links to excluded entities.
    $definition = $this->entityTypeManager->getDefinition($entity_type);
    if ($definition->get('gdpr_entity_traversal_exclude')) {
      return;
    }

    // Check for infinite loop.
    if (isset($this->entities[$entity_type][$entity->id()])) {
      return;
    }

    if (!isset($row_id)) {
      $row_id = $entity->id();
    }

    // Store the entity in progress to make sure we don't get stuck
    // in an infinite loop by processing the same entity again.
    $this->entities[$entity_type][$entity->id()] = $entity;

    // GDPR config for this entity.
    /* @var \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config */
    $config = $this->configStorage->load($entity_type);
    if (NULL === $config) {
      return;
    }

    // Let subclasses do with the entity. They will add to the $results array.
    $this->processEntity($entity, $config, $row_id, $parent_config);

    // Find relationships from this entity.
    $fields = $config->getFieldsForBundle($entity->bundle());

    foreach ($fields as $field_config) {
      // Only include fields explicitly enabled for entity traversal.
      if ($field_config->includeRelatedEntities() && $entity->hasField($field_config->name)) {
        // If there is no value, we don't need to proceed.
        $referenced_entities = $entity->get($field_config->name)->referencedEntities();

        if (empty($referenced_entities)) {
          continue;
        }

        $single_cardinality = $entity->get($field_config->name)->getFieldDefinition()
          ->getFieldStorageDefinition()->getCardinality() == 1;

        $passed_row_id = $single_cardinality ? $row_id : NULL;
        // Loop through each child entity and traverse their relationships too.
        foreach ($referenced_entities as $child_entity) {
          $this->doTraversalRecursive($child_entity, $field_config, $passed_row_id);
        }
      }
    }

    // Now we want to look up any reverse relationships that have been marked
    // as owner.
    foreach ($this->getAllReverseRelationships() as $relationship) {
      if ($relationship['target_type'] == $entity_type) {
        // Load all instances of this entity where the field value is the same
        // as our entity's ID.
        $storage = $this->entityTypeManager->getStorage($relationship['entity_type']);

        $ids = $storage->getQuery()
          ->condition($relationship['field'], $entity->id())
          ->execute();

        foreach ($storage->loadMultiple($ids) as $related_entity) {
          $this->doTraversalRecursive($related_entity, $relationship['config']);
        }
      }
    }
  }

  /**
   * Handles the entity.
   *
   * By default this just returns the entity instance, but derived classes
   * should override this method if they need to collect additional data on the
   * instance.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to handle.
   * @param \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config
   *   GDPR config for this entity.
   * @param string $row_id
   *   Row identifier used in SARs.
   * @param \Drupal\gdpr_fields\Entity\GdprField|null $parent_config
   *   Parent's config.
   */
  abstract protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, GdprField $parent_config = NULL);

  /**
   * Gets all reverse relationships configured in the system.
   *
   * @return array
   *   Information about reversible relationships.
   */
  protected function getAllReverseRelationships() {
    if ($this->reverseRelationshipFields !== NULL) {
      // Make sure reverse relationships are cached.
      // as this is called many times in the recursion loop.
      return $this->reverseRelationshipFields;
    }

    $this->reverseRelationshipFields = [];
    /* @var \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config  */
    foreach ($this->configStorage->loadMultiple() as $config) {
      foreach ($config->getAllFields() as $field) {
        if ($field->enabled && $field->isOwner()) {
          foreach ($this->entityFieldManager->getFieldDefinitions($config->id(), $field->bundle) as $field_definition) {
            if ($field_definition->getName() == $field->name && $field_definition->getType() == 'entity_reference') {
              $this->reverseRelationshipFields[] = [
                'entity_type' => $config->id(),
                'bundle' => $field->bundle,
                'field' => $field->name,
                'config' => $field,
                'target_type' => $field_definition->getSetting('target_type'),
              ];
            }
          }
        }
      }
    }

    return $this->reverseRelationshipFields;
  }

  /**
   * Gets the entity bundle label. Useful for display traversal.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the bundle label for.
   *
   * @return string
   *   Bundle label
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getBundleLabel(EntityInterface $entity) {
    $entity_definition = $entity->getEntityType();
    $bundle_type = $entity_definition->getBundleEntityType();

    if ($bundle_type) {
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
      $bundle_entity = $bundle_storage->load($entity->bundle());
      $bundle_label = $bundle_entity == NULL ? '' : $bundle_entity->label();
    }
    else {
      $bundle_label = $entity_definition->getLabel();
    }
    return $bundle_label;
  }

  /**
   * Get the calculated actual calling points.
   *
   * This will calculate them if they haven't been calculated exist.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|null
   *   Either an array of entities or NULL if we couldn't find them.
   */
  public function getEntities() {
    try {
      if ($this->traverse()) {
        return $this->entities;
      }
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getResults() {
    try {
      if ($this->traverse()) {
        return $this->results;
      }
    }
    catch (\Exception $exception) {
      return NULL;
    }
  }

}
