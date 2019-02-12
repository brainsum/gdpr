<?php

/**
 * @file
 * Bulk export of views_default objects for GDPR Tasks module.
 */

/**
 * Implements hook_views_default_views().
 */
function gdpr_tasks_views_default_views() {
  $view = new view();
  $view->name = 'gdpr_tasks_my_data_requests';
  $view->description = '';
  $view->tag = 'default';
  $view->base_table = 'gdpr_task';
  $view->human_name = 'GDPR User Data Requests';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'GDPR User Data Requests';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['access']['perm'] = 'view gdpr tasks';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'none';
  $handler->display->display_options['style_plugin'] = 'table';
  /* Field: Task: Created date */
  $handler->display->display_options['fields']['created']['id'] = 'created';
  $handler->display->display_options['fields']['created']['table'] = 'gdpr_task';
  $handler->display->display_options['fields']['created']['field'] = 'created';
  $handler->display->display_options['fields']['created']['label'] = 'Requested date';
  $handler->display->display_options['fields']['created']['date_format'] = 'short';
  $handler->display->display_options['fields']['created']['second_date_format'] = 'panopoly_time';
  /* Contextual filter: Broken/missing handler */
  $handler->display->display_options['arguments']['user_id']['id'] = 'user_id';
  $handler->display->display_options['arguments']['user_id']['table'] = 'gdpr_task';
  $handler->display->display_options['arguments']['user_id']['field'] = 'user_id';
  $handler->display->display_options['arguments']['user_id']['default_action'] = 'default';
  $handler->display->display_options['arguments']['user_id']['default_argument_type'] = 'user';
  $handler->display->display_options['arguments']['user_id']['default_argument_options']['user'] = FALSE;
  $handler->display->display_options['arguments']['user_id']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['user_id']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['user_id']['summary_options']['items_per_page'] = '25';
  /* Filter criterion: Task: Task type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'gdpr_task';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = 'gdpr_sar';
  /* Filter criterion: Task: Status */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'gdpr_task';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = array(
    'closed' => 'closed',
  );

  /* Display: Page */
  $handler = $view->new_display('page', 'Page', 'page');
  $handler->display->display_options['path'] = 'user/%/gdpr/requests';
  $handler->display->display_options['menu']['type'] = 'tab';
  $handler->display->display_options['menu']['title'] = 'My data requests';
  $handler->display->display_options['menu']['weight'] = '0';
  $handler->display->display_options['menu']['context'] = 0;
  $handler->display->display_options['menu']['context_only_inline'] = 0;
  $translatables['gdpr_tasks_my_data_requests'] = array(
    t('Master'),
    t('GDPR User Data Requests'),
    t('more'),
    t('Apply'),
    t('Reset'),
    t('Sort by'),
    t('Asc'),
    t('Desc'),
    t('Requested date'),
    t('All'),
    t('Page'),
  );

  $views['gdpr_tasks_my_data_requests'] = $view;

  return $views;
}
