<?php

namespace Drupal\gdpr_tasks\Traversal;

use Drupal\anonymizer\Anonymizer\AnonymizerFactory;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\EntityTraversal;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity traversal used for Right to be Forgotten requests.
 *
 * @package Drupal\gdpr_tasks\Traversal
 */
class RightToBeForgottenEntityTraversal extends EntityTraversal {

  /**
   * Factory used to retrieve anonymizer to use on a particular field.
   *
   * @var \Drupal\anonymizer\Anonymizer\AnonymizerFactory
   */
  private $anonymizerFactory;

  /**
   * Drupal module handler for hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, EntityInterface $base_entity) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $base_entity,
      $container->get('module_handler'),
      $container->get('anonymizer.anonymizer_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager,
    $base_entity,
    ModuleHandlerInterface $module_handler,
    AnonymizerFactory $anonymizer_factory
  ) {
    parent::__construct($entityTypeManager, $entityFieldManager, $base_entity);
    $this->moduleHandler = $module_handler;
    $this->anonymizerFactory = $anonymizer_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function traverseEntity(EntityInterface $entity) {
    $this->results = [
      'errors' => [],
      'successes' => [],
      'failures' => [],
      'log' => [],
      'to_delete' => [],
    ];

    $this->doTraversalRecursive($entity);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, GdprField $parent_config = NULL) {
    $entity_success = TRUE;
    $entity_type = $entity->getEntityTypeId();

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    $field_configs = $config->getFieldsForBundle($entity->bundle());

    // Re-load a fresh copy of the entity from storage so we don't
    // end up modifying any other references to the entity in memory.
    /* @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)
      ->loadUnchanged($entity->id());

    foreach ($fields as $field_id => $field_definition) {
      $field_config = isset($field_configs[$field_id]) ? $field_configs[$field_id] : NULL;

      // If the field is not configured, not enabled,
      // or not enabled for RTF, then skip it.
      if ($field_config === NULL
        || !$field_config->enabled
        || !in_array($field_config->rtf, ['anonymize', 'remove', 'maybe'])) {
        continue;
      }

      $mode = $field_config->rtf;
      $field = $entity->get($field_id);

      $success = TRUE;
      $msg = NULL;
      $anonymizer = '';

      if ($mode == 'anonymize') {
        list($success, $msg, $anonymizer) = $this->anonymize($field, $field_definition, $field_config);
      }
      elseif ($mode == 'remove') {
        list($success, $msg, $should_delete) = $this->remove($field, $field_config, $entity);
      }

      if ($success === TRUE) {
        $this->results['log'][] = [
          'entity_id' => $entity->id(),
          'entity_type' => $entity_type . '.' . $entity->bundle(),
          'field_name' => $field->getName(),
          'action' => $mode,
          'anonymizer' => $anonymizer,
        ];
      }
      else {
        // Could not anonymize/remove field. Record to errors list.
        // Prevent entity from being saved.
        $entity_success = FALSE;
        $this->results['errors'][] = $msg;
      }
    }

    if ($entity_success) {
      if (isset($should_delete) && $should_delete) {
        $this->results['to_delete'][] = $entity;
      }
      else {
        $this->results['successes'][] = $entity;
      }
    }
    else {
      $this->results['failures'][] = $entity;
    }
  }

  /**
   * Removes the field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The current field to process.
   * @param \Drupal\gdpr_fields\Entity\GdprField $field_config
   *   The current field config.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove.
   *
   * @return array
   *   First element is success boolean, second element is the error message,
   *   third element is boolean indicating whether the whole
   *   entity should be deleted.
   */
  private function remove(FieldItemListInterface $field, GdprField $field_config, EntityInterface $entity) {
    try {
      $should_delete = FALSE;
      // If this is the entity's ID, treat the removal as remove the entire
      // entity.
      $entity_type = $entity->getEntityType();
      $error_message = NULL;
      if ($entity_type->getKey('id') == $field->getName()) {
        $should_delete = TRUE;
        return [TRUE, NULL, $should_delete];
      }
      // Check if the property can be removed.
      elseif (!$field_config->propertyCanBeRemoved($field->getFieldDefinition(), $error_message)) {
        return [FALSE, $error_message, $should_delete];
      }

      // Otherwise assume we can simply clear the field.
      $field->setValue(NULL);
      return [TRUE, NULL, $should_delete];
    }
    catch (ReadOnlyException $e) {
      return [FALSE, $e->getMessage(), $should_delete];
    }
  }

  /**
   * Runs anonymize functionality against a field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to anonymize.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\gdpr_fields\Entity\GdprField $field_config
   *   GDPR field configuration.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function anonymize(FieldItemListInterface $field, FieldDefinitionInterface $field_definition, GdprField $field_config) {
    $anonymizer_id = $this->getAnonymizerId($field_definition, $field_config);

    if (!$anonymizer_id) {
      return [
        FALSE,
        "Could not anonymize field {$field->getName()}. Please consider changing this field from 'anonymize' to 'remove', or register a custom anonymizer.",
        NULL,
      ];
    }

    try {
      $anonymizer = $this->anonymizerFactory->get($anonymizer_id);
      $field->setValue($anonymizer->anonymize($field->value, $field));
      return [TRUE, NULL, $anonymizer_id];
    }
    catch (\Exception $e) {
      return [FALSE, $e->getMessage(), NULL];
    }
  }

  /**
   * Gets the ID of the anonymizer plugin to use on this field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\gdpr_fields\Entity\GdprField $field_config
   *   GDPR field configuration.
   *
   * @return string
   *   The anonymizer ID or null.
   */
  private function getAnonymizerId(FieldDefinitionInterface $field_definition, GdprField $field_config) {
    $anonymizer = $field_config->anonymizer;
    $type = $field_definition->getType();

    if (!$anonymizer) {
      // No anonymizer defined directly on the field.
      // Instead try and get one for the datatype.
      $anonymizers = [
        'string' => 'gdpr_text_anonymizer',
        'datetime' => 'gdpr_date_anonymizer',
      ];

      $this->moduleHandler->alter('gdpr_type_anonymizers', $anonymizers);
      $anonymizer = $anonymizers[$type];
    }
    return $anonymizer;
  }

}
