<?php

/**
 * @file
 * Provide views data and handlers for GDPR Tasks module.
 */

/**
 * Implements hook_views_data_alter().
 *
 * Add to existing views data generated for entities.
 */
function gdpr_tasks_views_data_alter(&$data) {
  $data['gdpr_task']['created'] = array(
    'title' => t('Created date'),
    'help' => t('The date the task was requested.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
    ),
  );

  $data['gdpr_task']['changed'] = array(
    'title' => t('Changed date'),
    'help' => t('The date the task was last updated.'),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
    ),
  );

  $data['gdpr_task']['operations'] = array(
    'field' => array(
      'title' => t('Operations links'),
      'help' => t('Display all operations available for this task.'),
      'handler' => 'gdpr_tasks_handler_operations_field',
    ),
  );
}
