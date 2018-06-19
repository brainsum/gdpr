<?php

/**
 * @file
 * Operations handler for gdpr tasks.
 */

/**
 * This field handler aggregates operations that can be done on a model.
 */
class gdpr_tasks_handler_operations_field extends views_handler_field {

  /**
   * {@inheritdoc}
   */
  public function construct() {
    parent::construct();

    $this->additional_fields['id'] = 'id';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensure_my_table();
    $this->add_additional_fields();
  }

  /**
   * {@inheritdoc}
   */
  public function render($values) {
    $links = menu_contextual_links('model', 'admin/structure/gdpr-tasks', array(
      $this->get_value($values, 'id'),
    ));

    if (!empty($links)) {
      return theme('links', array(
        'links' => $links,
        'attributes' => array(
          'class' => array('links', 'inline', 'operations'),
        ),
      ));
    }
  }

}
