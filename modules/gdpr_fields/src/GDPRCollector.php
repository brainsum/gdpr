<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\RelationshipManager;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;

/**
 * Defines a helper class for stuff related to views data.
 */
class GDPRCollector {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The ctools relationship manager.
   *
   * @var \Drupal\ctools\Plugin\RelationshipManager
   */
  protected $relationshipManager;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityType_manager
   *   The entity type manager.
   * @param \Drupal\ctools\Plugin\RelationshipManager $relationship_manager
   *   The ctools relationship manager.
   */
  public function __construct(EntityTypeManager $entityType_manager, RelationshipManager $relationship_manager) {
    $this->entityTypeManager = $entityType_manager;
    $this->relationshipManager = $relationship_manager;
  }

  /**
   * Get entity tree for GDPR.
   *
   * @param array $entityList
   *   List of all gotten entities keyed by entity type and bundle id.
   * @param string $entityType
   *   The entity type id.
   * @param string|null $bundleId
   *   The entity bundle id, NULL if bundles should be loaded.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getEntities(array &$entityList, $entityType = 'user', $bundleId = NULL) {
    $definition = $this->entityTypeManager->getDefinition($entityType);

    // @todo Add way of excluding irrelevant entity types.
    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    if (NULL === $bundleId) {
      if ($definition->getBundleEntityType()) {
        $bundleStorage = $this->entityTypeManager->getStorage($definition->getBundleEntityType());
        foreach (\array_keys($bundleStorage->loadMultiple()) as $loadedBundleId) {
          $this->getEntities($entityList, $entityType, $loadedBundleId);
        }
      }
      else {
        $this->getEntities($entityList, $entityType, $entityType);
      }

      return;
    }

    // Check for recursion.
    if (isset($entityList[$entityType][$bundleId])) {
      return;
    }

    // Set entity.
    $entityList[$entityType][$bundleId] = $bundleId;

    // Find relationships.
    $context = new Context(new ContextDefinition("entity:{$entityType}"));
    $definitions = $this->relationshipManager->getDefinitionsForContexts([$context]);

    foreach ($definitions as $definitionId => $definition) {
      list($type, , ,) = \explode(':', $definitionId);

      if ('typed_data_entity_relationship' === $type) {
        if (isset($definition['target_entity_type'])) {
          $this->getEntities($entityList, $definition['target_entity_type']);
        }
      }
      elseif ('typed_data_entity_relationship_reverse' === $type) {
        if (isset($definition['source_entity_type'])) {
          $this->getEntities($entityList, $definition['source_entity_type']);
        }
      }
      else {
        continue;
      }
    }
  }

  /**
   * Get entity value tree for GDPR entities.
   *
   * @param array $entityList
   *   List of all gotten entities keyed by entity type and bundle id.
   * @param string $entityType
   *   The entity type id.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The fully loaded entity for which values are gotten.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getValueEntities(array &$entityList, $entityType, EntityInterface $entity) {
    $definition = $this->entityTypeManager->getDefinition($entityType);

    // @todo Add way of excluding irrelevant entity types.
    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    // Check for recursion.
    if (isset($entityList[$entityType][$entity->id()])) {
      return;
    }

    // Set entity.
    $entityList[$entityType][$entity->id()] = $entity;

    // Find relationships.
    $contextDefinition = new ContextDefinition("entity:{$entityType}");

    // @todo Error handling for broken bundles. (Eg. file module).
    if ('undefined' !== $entity->bundle()) {
      $contextDefinition->addConstraint('Bundle', [$entity->bundle()]);
    }
    $context = new Context($contextDefinition);
    $definitions = $this->relationshipManager->getDefinitionsForContexts([$context]);

    foreach ($definitions as $definitionId => $definition) {
      list($type, $definitionEntity, $relatedEntityType,) = \explode(':', $definitionId);

      // Ignore entity revisions for now.
      if ('entity_revision' === $definitionEntity) {
        continue;
      }

      // Ignore links back to gdpr_task.
      // @todo Remove this once we have solved how to deal with ignored/excluded relationships
      if ('gdpr_task' === $relatedEntityType || 'message' === $relatedEntityType) {
        continue;
      }

      if ('typed_data_entity_relationship' === $type) {
        /* @var \Drupal\ctools\Plugin\Relationship\TypedDataEntityRelationship $plugin */
        $plugin = $this->relationshipManager->createInstance($definitionId);
        $plugin->setContextValue('base', $entity);

        $relationship = $plugin->getRelationship();
        if ($relationship->hasContextValue()) {
          $relationshipEntity = $relationship->getContextValue();
          $this->getValueEntities($entityList, $relationshipEntity->getEntityTypeId(), $relationshipEntity);
        }
      }
      elseif ('typed_data_entity_relationship_reverse' === $type) {
        /* @var \Drupal\gdpr_fields\Plugin\Relationship\TypedDataEntityRelationshipReverse $plugin */
        $plugin = $this->relationshipManager->createInstance($definitionId);
        $plugin->setContextValue('base', $entity);

        $relationship = $plugin->getRelationship();
        if ($relationship->hasContextValue()) {
          $relationshipEntity = $relationship->getContextValue();
          $this->getValueEntities($entityList, $relationshipEntity->getEntityTypeId(), $relationshipEntity);
        }
      }
      else {
        continue;
      }
    }
  }

  /**
   * List fields on entity including their GDPR values.
   *
   * @param string $bundleId
   *   The entity bundle id.
   * @param string $entityType
   *   The entity type id.
   * @param bool $include_not_configured
   *   Include fields for entities that have not yet been configured.
   *
   * @return array
   *   GDPR entity field list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function listFields($bundleId, $entityType = 'user', $include_not_configured = FALSE) {
    $storage = $this->entityTypeManager->getStorage($entityType);
    $entity_definition = $this->entityTypeManager->getDefinition($entityType);
    $bundle_type = $entity_definition->getBundleEntityType();
    $gdpr_settings = GdprFieldConfigEntity::load($entityType);

    // Create a blank entity.
    $values = [];
    $bundle_key = NULL;
    if ($entity_definition->hasKey('bundle')) {
      $bundle_key = $entity_definition->getKey('bundle');
      $values[$bundle_key] = $bundleId;
    }
    $entity = $storage->create($values);

    // Get fields for entity.
    $fields = [];

    if (!$include_not_configured && NULL === $gdpr_settings) {
      return $fields;
    }

    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();
      $key = "$entityType.$bundleId.$field_id";
      $route_name = 'gdpr_fields.edit_field';
      $route_params = [
        'entity_type' => $entityType,
        'bundle_name' => $bundleId,
        'field_name' => $field_id,
      ];

      if (NULL === $bundle_key) {
        $route_params[$bundle_type] = $bundleId;
      }

      $fields[$key] = [
        'title' => $field_definition->getLabel(),
        'type' => $field_definition->getType(),
        'gdpr_rta' => 'Not Configured',
        'gdpr_rtf' => 'Not Configured',
        'notes' => '',
        'edit' => '',
      ];

      if ($entity_definition->get('field_ui_base_route')) {
        $url = Url::fromRoute($route_name, $route_params);

        if ($url->access()) {
          $fields[$key]['edit'] = Link::fromTextAndUrl('edit', $url);
        }
      }

      if (NULL !== $gdpr_settings) {
        $field_settings = $gdpr_settings->getField($bundleId, $field_id);
        if ($field_settings->configured) {
          $fields[$key]['gdpr_rta'] = $field_settings->rtaDescription();
          $fields[$key]['gdpr_rtf'] = $field_settings->rtfDescription();
          $fields[$key]['notes'] = $field_settings->notes;
        }
        elseif (!$field_settings->configured && !$include_not_configured) {
          unset($fields[$key]);
        }
      }
    }

    return $fields;
  }

  /**
   * List field values on an entity including their GDPR values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The fully loaded entity for which values are listed.
   * @param string $entityType
   *   The entity type id.
   * @param array $extra_fields
   *   Add extra fields if required.
   *
   * @return array
   *   GDPR entity field value list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function fieldValues(EntityInterface $entity, $entityType = 'user', array $extra_fields = []) {
    $entity_definition = $this->entityTypeManager->getDefinition($entityType);
    $bundle_type = $entity_definition->getBundleEntityType();
    $bundleId = $entity->bundle();
    if ($bundle_type) {
      $bundleStorage = $this->entityTypeManager->getStorage($bundle_type);
      $bundle_entity = $bundleStorage->load($bundleId);
      $bundle_label = NULL === $bundle_entity ? '' : $bundle_entity->label();
    }
    else {
      $bundle_label = $entity->getEntityType()->getLabel();
    }

    // Get fields for entity.
    $fields = [];

    $gdpr_config = GdprFieldConfigEntity::load($entityType);

    if (NULL === $gdpr_config) {
      // No fields have been configured on this entity for GDPR.
      return $fields;
    }

    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();

      $field_config = $gdpr_config->getField($bundleId, $field->getName());

      if (!$field_config->enabled) {
        continue;
      }

      $key = "$entityType.{$entity->id()}.$field_id";

      $fieldValue = $field->getString();
      $fields[$key] = [
        'title' => $field_definition->getLabel(),
        'value' => $fieldValue,
        'entity' => $entity->getEntityType()->getLabel(),
        'bundle' => $bundle_label,
        'notes' => $field_config->notes,
      ];

      if (empty($extra_fields)) {
        continue;
      }

      // Fetch and validate based on field settings.
      if (isset($extra_fields['rta'])) {
        $rta_value = $field_config->rta;

        if ($rta_value && 'no' !== $rta_value) {
          $fields[$key]['gdpr_rta'] = $rta_value;
        }
        else {
          unset($fields[$key]);
        }
      }
      if (isset($extra_fields['rtf'])) {
        $rtf_value = $field_config->rtf;

        if ($rtf_value && $rtf_value !== 'no') {
          $fields[$key]['gdpr_rtf'] = $rtf_value;

          // For 'maybes', provide a link to edit the entity.
          if ('maybe' === $rtf_value) {
            $fields[$key]['link'] = $entity->toLink('Edit', 'edit-form');
          }
          else {
            $fields[$key]['link'] = '';
          }
        }
        else {
          unset($fields[$key]);
        }
      }
    }

    return $fields;
  }

}
