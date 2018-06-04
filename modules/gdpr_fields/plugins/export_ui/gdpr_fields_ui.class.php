<?php

/**
 * @file
 * Contains the CTools Export UI integration code.
 */

/**
 * CTools Export UI class handler for GDPR Fields UI.
 */
class gdpr_fields_ui extends ctools_export_ui {

  protected $rows = array();
  protected $items = array();
  protected $entityPlugins = array();

  /**
   * {@inheritdoc}
   */
  function hook_menu(&$items) {
    unset($this->plugin['menu']['items']['add']);
    // @todo Make sure import always overrides and never adds.
    $this->plugin['menu']['items']['import']['title'] = 'Override';
    parent::hook_menu($items);
  }

  /**
   * Get the options array for right to access field.
   *
   * @return array
   *   Right to access field options array.
   */
  protected function rtaOptions() {
    return array(
      '' => 'Not configured',
      'inc' => 'Included',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    );
  }

  /**
   * Get the options array for right to be forgotten field.
   *
   * @return array
   *   Right to be forgotten field options array.
   */
  protected function rtfOptions() {
    return array(
      '' => 'Not configured',
      'anonymise' => 'Anonymise',
      'remove' => 'Remove',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    );
  }

  /**
   * Gets all plugins for the same entity type as the one provided.
   *
   * @param GDPRFieldData $item
   *   The plugin to compare with.
   * @param bool $filter_unconfigured
   *   Exclude plugins that are not configured.
   *
   * @return GDPRFieldData[]
   *   Array of plugins found.
   */
  protected function getItemEntityPlugins($item, $filter_unconfigured = FALSE) {
    $plugins = array();

    if (empty($this->entityPlugins[$item->entity_type])) {
      $entity_type = $item->entity_type;
      $this->entityPlugins[$entity_type] = array_filter($this->items, function ($item) use ($entity_type) {
        return $item->entity_type == $entity_type;
      });
    }

    foreach ($this->entityPlugins[$item->entity_type] as $plugin_name => $plugin) {
      if ($filter_unconfigured) {
        if ($plugin->getSetting('gdpr_fields_rta', '') == '' && $plugin->getSetting('gdpr_fields_rtf', '') == '') {
          continue;
        }
      }

      $plugins[$plugin_name] = $plugin;
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   *
   * @param GDPRFieldData $item
   */
  public function list_build_row($item, &$form_state, $operations) {
    // Set up sorting
    $name = $item->{$this->plugin['export']['key']};
    $schema = ctools_export_get_schema($this->plugin['schema']);

    $is_id = Anonymizer::propertyIsEntityId($item->entity_type, $item->property_name);

    // Note: $item->{$schema['export']['export type string']} should have already been set up by export.inc so
    // we can use it safely.
    switch ($form_state['values']['order']) {
      case 'disabled':
        $this->sorts[$name] = empty($item->disabled) . $name;
        break;
      case 'title':
        $this->sorts[$name] = $item->{$this->plugin['export']['admin_title']};
        break;
      case 'name':
        $this->sorts[$name] = $name;
        break;
    }

    $row['data'] = array();
    $row['class'] = !empty($item->disabled) ? array('ctools-export-ui-disabled') : array('ctools-export-ui-enabled');

    // If we have an admin title, make it the first row.
    $row['data'][] = array('data' => check_plain($item->getSetting('label')), 'class' => array('ctools-export-ui-title'));

    $entity_info = entity_get_property_info($item->entity_type);
    if ($info = field_info_field($item->property_name)) {
      $type = $info['type'];

      // Highlight entity reference fields.
      if ($type == 'entityreference') {
        $type = '<strong>' . $type . '</strong>';
      }
    }
    else {
      $type = 'property';
      if (!empty($entity_info['properties'][$item->property_name]['type'])) {
        $type .= ':' . $entity_info['properties'][$item->property_name]['type'];
      }
      // Highlight entity ids.
      if ($is_id) {
        $type = '<strong>primary_key</strong>';
      }
    }

    $row['data'][] = array('data' => $type, 'class' => array('ctools-export-ui-field-type'));

    $rta_labels = $this->rtaOptions();
    $rtf_labels = $this->rtfOptions();

    $row['data'][] = array('data' => $rta_labels[$item->getSetting('gdpr_fields_rta', '')], 'class' => array('ctools-export-ui-rta'));

    $rtf_label = $rtf_labels[$item->getSetting('gdpr_fields_rtf', '')];

    // Label id with removal as remove entity.
    if ($is_id && $item->getSetting('gdpr_fields_rtf', '') == 'remove') {
      $rtf_label = t('Delete entire entity');
    }

    $row['data'][] = array('data' => $rtf_label, 'class' => array('ctools-export-ui-rtf'));

    $ops = theme('links__ctools_dropbutton', array('links' => $operations, 'attributes' => array('class' => array('links', 'inline'))));

    $row['data'][] = array('data' => $ops, 'class' => array('ctools-export-ui-operations'));

    // Add an automatic mouseover of the description if one exists.
    if (!empty($this->plugin['export']['admin_description'])) {
      $row['title'] = $item->{$this->plugin['export']['admin_description']};
    }

    $this->rows[$name] = $row;
  }

  /**
   * {@inheritdoc}
   */
  public function list_sort_options() {
    $options = parent::list_sort_options();
    unset($options['storage']);
    return $options;
  }


    /**
   * {@inheritdoc}
   */
  public function list_table_header() {
    $header = array();
    $header[] = array('data' => t('Label'), 'class' => array('ctools-export-ui-title'));

    $header[] = array('data' => t('Field type'), 'class' => array('ctools-export-ui-field-type'));
    $header[] = array('data' => t('Right to access'), 'class' => array('ctools-export-ui-rta'));
    $header[] = array('data' => t('Right to be forgotten'), 'class' => array('ctools-export-ui-rtf'));

    $header[] = array('data' => t('Operations'), 'class' => array('ctools-export-ui-operations'));
    return $header;

  }

  /**
   * {@inheritdoc}
   */
  public function list_render(&$form_state) {
    $tables = array();
    $table_data = '';

    foreach ($this->rows as $name => $row) {
      list($entity_type, $entity_bundle, $property_name) = explode('|', $name);
      $tables[$entity_type][$entity_bundle][$name] = $row;
    }

    foreach ($tables as $entity_type => $entities) {
      $fieldset_entity = array(
        'element' => array(
          '#title' => t('Entity: @entity', array(
            '@entity' => $entity_type,
          )),
          '#value' => '',
          '#children' => '<div>',
          '#attributes' => array (
            'class' => array(
              'collapsible',
            ),
          ),
        ),
      );

      if (count($entities) === 1) {
        $rows = reset($entities);
        $table = array(
          'header' => $this->list_table_header(),
          'rows' => $rows,
          'empty' => $this->plugin['strings']['message']['no items'],
        );
        $fieldset_entity['element']['#value'] = theme('table', $table);
      }
      else {
        foreach ($entities as $bundle => $rows) {
          $table = array(
            'header' => $this->list_table_header(),
            'rows' => $rows,
            'empty' => $this->plugin['strings']['message']['no items'],
          );

          $fieldset_bundle = array(
            'element' => array(
              '#title' => t('Bundle: @bundle', array(
                '@bundle' => $bundle,
              )),
              '#value' => theme('table', $table),
              '#children' => '<div>',
              '#attributes' => array (
                'class' => array(
                  'collapsible',
//                  'collapsed',
                ),
              ),
            ),
          );
          $fieldset_entity['element']['#value'] .= theme('fieldset', $fieldset_bundle);
        }
      }

      $table_data .= theme('fieldset', $fieldset_entity);
    }

    $content = array(
      '#type' => 'container',
      '#attributes' => array (
        'id' => 'ctools-export-ui-list-items',
      ),
      'data' => array('#markup' => !empty($table_data) ? $table_data : $this->plugin['strings']['message']['no items']),
    );

    return drupal_render($content);
  }

  /**
   * {@inheritdoc}
   */
  public function list_form(&$form, &$form_state) {
    parent::list_form($form, $form_state);

    // Remove storage filter.
    unset($form['top row']['storage']);

    // Shrink search field slightly.
    $form['top row']['search']['#size'] = 30;

    $entities = array();
    foreach (entity_get_info() as $key => $entity_info) {
      $entities[$key] = $entity_info['label'];
    }

    $form['top row']['gdpr_entity'] = array(
      '#type' => 'select',
      '#title' => t('Entity'),
      '#options' => $entities,
      '#multiple' => TRUE,
      '#default_value' => array(),
    );

    $form['top row']['rta'] = array(
      '#type' => 'select',
      '#title' => t('Right to access'),
      '#options' => $this->rtaOptions(),
      '#multiple' => TRUE,
      '#default_value' => array(),
    );

    $form['top row']['rtf'] = array(
      '#type' => 'select',
      '#title' => t('Right to be forgotten'),
      '#options' => $this->rtfOptions(),
      '#multiple' => TRUE,
      '#default_value' => array(),
    );

    $form['top row']['empty'] = array(
      '#type' => 'checkbox',
      '#title' => t('Filter out Entities where all fields are not configured'),
      '#default_value' => TRUE,
    );

    $form['bottom row']['submit']['#attributes']['class'] = array();
    $form['bottom row']['reset']['#attributes']['class'] = array();

    $form['#attached']['library'][] = array('system', 'drupal.collapse');
  }

  /**
   * {@inheritdoc}
   */
  public function list_filter($form_state, $item) {
    if (!empty($form_state['values']['gdpr_entity']) && !in_array($item->entity_type, $form_state['values']['gdpr_entity'])) {
      return TRUE;
    }

    if ($form_state['values']['disabled'] != 'all' && $form_state['values']['disabled'] != !empty($item->disabled)) {
      return TRUE;
    }

    if (!empty($form_state['values']['rtf']) && !in_array($item->getSetting('gdpr_fields_rtf', ''), $form_state['values']['rtf'])) {
      return TRUE;
    }

    if (!empty($form_state['values']['rta']) && !in_array($item->getSetting('gdpr_fields_rta', ''), $form_state['values']['rta'])) {
      return TRUE;
    }

    $plugins = $this->getItemEntityPlugins($item, TRUE);
    if (!empty($form_state['values']['empty']) && empty($plugins)) {
      return TRUE;
    }

    if ($form_state['values']['search']) {
      $search = strtolower($form_state['values']['search']);
      foreach ($this->list_search_fields() as $field) {
        if (strpos(strtolower($item->$field), $search) !== FALSE) {
          $hit = TRUE;
          break;
        }
      }
      if (empty($hit)) {
        return TRUE;
      }
    }
  }

}
