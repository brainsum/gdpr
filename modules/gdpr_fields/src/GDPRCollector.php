<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeManager;
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
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;


  /**
   * Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $bundleInfo;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Bundle info.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->bundleInfo = $bundle_info;
  }

  /**
   * List fields on entity including their GDPR values.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param array $filters
   *   Array of filters with following keys:
   *   'empty' => filter out entities where all fields are not configured.
   *   'rtf' => only include fields where RTF is configured.
   *   'rta' => only include fields where RTA is configured.
   *   'search' => only include fields whose name match.
   *
   * @return array
   *   GDPR entity field list.
   */
  public function listFields(EntityTypeInterface $entity_type, $bundle_id, array $filters) {
    $bundle_type = $entity_type->getBundleEntityType();
    $gdpr_settings = GdprFieldConfigEntity::load($entity_type->id());

    // @todo explicitly skip commerce_order_item for now as they break bundles
    if ($entity_type->id() == 'commerce_order_item') {
      return [];
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type->id(), $bundle_id);

    // Get fields for entity.
    $fields = [];

    // If the 'Filter out entities where all fields are not configured' option
    // is set, return an empty array if GDPR is not configured for the entity.
    if ($filters['empty'] && $gdpr_settings == NULL) {
      return $fields;
    }

    $has_at_least_one_configured_field = FALSE;

    foreach ($field_definitions as $field_id => $field_definition) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field_definition */
      $key = "{$entity_type->id()}.$bundle_id.$field_id";
      $route_name = 'gdpr_fields.edit_field';
      $route_params = [
        'entity_type' => $entity_type->id(),
        'bundle_name' => $bundle_id,
        'field_name' => $field_id,
      ];

      if (isset($bundle_type)) {
        $route_params[$bundle_type] = $bundle_id;
      }

      $rta = '0';
      $rtf = '0';

      $label = $field_definition->getLabel();

      // If we're searching by name, check if the label matches search.
      if ($filters['search'] && (!stripos($label, $filters['search']) || !stripos($field_definition->getName(), $filters['search']))) {
        continue;
      }

      $is_id = $entity_type->getKey('id') == $field_id;

      $fields[$key] = [
        'title' => $label,
        'type' => $is_id ? 'primary_key' : $field_definition->getType(),
        'rta' => 'Not Configured',
        'rtf' => 'Not Configured',
        'notes' => '',
        'edit' => '',
        'is_id' => $is_id,
      ];

      if ($entity_type->get('field_ui_base_route')) {
        $fields[$key]['edit'] = Link::createFromRoute('edit', $route_name, $route_params);
      }

      if ($gdpr_settings != NULL) {
        /* @var \Drupal\gdpr_fields\Entity\GdprField $field_settings */
        $field_settings = $gdpr_settings->getField($bundle_id, $field_id);
        if ($field_settings->enabled) {
          $has_at_least_one_configured_field = TRUE;
          $rta = $field_settings->rta;
          $rtf = $field_settings->rtf;

          $fields[$key]['rta'] = $field_settings->rtaDescription();
          $fields[$key]['rtf'] = $field_settings->rtfDescription();
          $fields[$key]['notes'] = $field_settings->notes;
        }
      }

      // Apply filters.
      if (!empty($filters['rtf']) && !in_array($rtf, $filters['rtf'])) {
        unset($fields[$key]);
      }

      if (!empty($filters['rta']) && !in_array($rta, $filters['rta'])) {
        unset($fields[$key]);
      }
    }

    // Handle the 'Filter out Entities where all fields are not configured'
    // checkbox.
    if ($filters['empty'] && !$has_at_least_one_configured_field) {
      return [];
    }

    return $fields;
  }

  /**
   * Gets bundles belonging to an entity type.
   *
   * @param string $entity_type_id
   *   The entity type for which bundles should be located.
   *
   * @return array
   *   Array of bundles.
   */
  public function getBundles($entity_type_id) {
    $all_bundles = $this->bundleInfo->getAllBundleInfo();
    $bundles = isset($all_bundles[$entity_type_id]) ? $all_bundles[$entity_type_id] : [$entity_type_id => []];
    return $bundles;
  }

}
