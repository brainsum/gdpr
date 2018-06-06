<?php

/**
 * @file
 * Contains the gdpr_entity_property plugin definition.
 */

$plugin = array(
  // As this is the base class plugin, it shouldn't declare any menu items.
  'has menu' => FALSE,
  'handler' => array(
    'class' => 'gdpr_entity_property',
  ),
  'get child' => 'gdpr_fields_gdpr_entity_property_get_child',
  'get children' => 'gdpr_fields_gdpr_entity_property_get_children',
);

/**
 * Gets a specific child for a plugin type.
 *
 * @param array $plugin
 *   The plugin to get child for.
 * @param string $parent
 *   The parent plugin id.
 * @param string $child
 *   The child plugin id.
 *
 * @return array
 *   An child plugin definition.
 */
function gdpr_fields_gdpr_entity_property_get_child(array $plugin, $parent, $child) {
  $plugins = gdpr_fields_gdpr_entity_property_get_children($plugin, $parent);
  return $plugins[$parent . ':' . $child];
}

/**
 * Gets all plugin children for a plugin type.
 *
 * @param array $plugin
 *   The plugin to get children for.
 * @param string $parent
 *   The parent plugin id.
 *
 * @return array
 *   An array of child plugin definitions.
 */
function gdpr_fields_gdpr_entity_property_get_children(array $plugin, $parent) {
  $plugins = array();
  foreach (entity_get_property_info() as $entity_type => $property_info) {
    // Get a list of bundles.
    if (isset($property_info['bundles'])) {
      $bundles = array_keys($property_info['bundles']);
    }
    // Otherwise just use the entity type.
    else {
      $bundles = array($entity_type);
    }

    // Get the plugins for each bundle.
    foreach ($bundles as $bundle) {
      $properties = array();

      // Default properties.
      foreach ($property_info['properties'] as $property_name => $property) {
        $name = "{$entity_type}|{$bundle}|{$property_name}";
        $properties[$name] = $property;
      }

      // Bundle properties.
      if (isset($property_info['bundles'][$bundle])) {
        foreach ($property_info['bundles'][$bundle]['properties'] as $property_name => $property) {
          $name = "{$entity_type}|{$bundle}|{$property_name}";
          $properties[$name] = $property;
        }
      }

      // Add plugins for properties.
      foreach ($properties as $name => $property) {
        $child_plugin = $plugin;
        $child_plugin['name'] = $name;
        $child_plugin['label'] = $property['label'];

        $child_plugin['description'] = isset($property['description']) ? $property['description'] : '';

        // @todo Should computed properties be removed instead or disabled?
        if (!empty($property['computed'])) {
          $child_plugin['computed'] = TRUE;
        }
        $plugins[$name] = $child_plugin;
      }
    }
  }

  return $plugins;
}
