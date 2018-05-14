<?php

namespace Drupal\gdpr_fields\Plugin\Relationship;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ctools\Plugin\Relationship\TypedDataEntityRelationship;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextInterface;

/**
 * Reverse entity relationship.
 *
 * @Relationship(
 *   id = "typed_data_entity_relationship_reverse",
 *   deriver = "\Drupal\gdpr_fields\Plugin\Deriver\TypedDataEntityRelationshipReverseDeriver"
 * )
 */
class TypedDataEntityRelationshipReverse extends TypedDataEntityRelationship implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRelationship() {
    $plugin_definition = $this->getPluginDefinition();

    if (!isset($plugin_definition['source_entity_type'])) {
      return parent::getRelationship();
    }
    $source_entity_type = $plugin_definition['source_entity_type'];
    $context_definition = new ContextDefinition("entity:{$source_entity_type}", $plugin_definition['label']);
    $context_value = NULL;

    // If the 'base' context has a value, then get the property value to put on
    // the context (otherwise, mapping hasn't occurred yet and we just want to
    // return the context with the right definition and no value).
    if ($this->getContext('base')->hasContextValue()) {
      $data = $this->getData($this->getContext('base'), $source_entity_type);
      if ($data) {
        $context_value = $data;
      }
    }

    $context_definition->setDefaultValue($context_value);
    return new Context($context_definition, $context_value);
  }

  /**
   * {@inheritdoc}
   */
  protected function getData(ContextInterface $context, $source_entity_type = NULL) {
    if (!$source_entity_type) {
      return FALSE;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $base */
    $base = $context->getContextValue();
    $name = $this->getPluginDefinition()['property_name'];

    $query = \Drupal::entityQuery($source_entity_type)
      ->condition($name, $base->id(), '=')
      ->range(0, 1)
      ->execute();

    $data = \reset($query);
    if ($data) {
      $data = $this->entityTypeManager->getStorage($source_entity_type)->load($data);
    }

    return $data;
  }

}
