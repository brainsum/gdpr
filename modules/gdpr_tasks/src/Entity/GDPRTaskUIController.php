<?php

/**
 * The Task type entity controller class.
 */
class GDPRTaskUIController extends EntityBundleableUIController {

  /**
   * {@inheritdoc}
   */
  public function hook_menu() {
    $items = parent::hook_menu();
    // Set this on the object so classes that extend hook_menu() can use it.
    $plural_label = isset($this->entityInfo['plural label']) ? $this->entityInfo['plural label'] : $this->entityInfo['label'] . 's';

    $items['admin/config/gdpr/task-list'] = array(
      'title' => $plural_label,
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_overview_form', $this->entityType),
      'description' => 'Manage ' . $plural_label . '.',
      'access callback' => 'entity_access',
      'access arguments' => array('view', $this->entityType),
      'file' => 'includes/entity.ui.inc',
      'weight' => 10,
    );

    return $items;
  }

  /**
   * Generates the render array for a overview tables for different statuses.
   *
   * {@inheritdoc}
   */
  public function overviewTable($conditions = array()) {
    $query = new EntityFieldQuery();
    $query->entityCondition('entity_type', $this->entityType);
    $query->propertyOrderBy('created');

    // Add all conditions to query.
    foreach ($conditions as $key => $value) {
      $query->propertyCondition($key, $value);
    }

    if ($this->overviewPagerLimit) {
      $query->pager($this->overviewPagerLimit);
    }

    $results = $query->execute();

    $ids = isset($results[$this->entityType]) ? array_keys($results[$this->entityType]) : array();
    $entities = $ids ? entity_load($this->entityType, $ids) : array();
    ksort($entities);

    // Always show at least requested and complete tables.
    $rows = array(
      'requested' => array(),
      'closed' => array(),
    );
    foreach ($entities as $entity) {
      $rows[$entity->status][] = $this->overviewTableRow($conditions, entity_id($this->entityType, $entity), $entity);
    }

    $render = array();
    foreach ($rows as $status => $status_rows) {
      $render[$status] = array(
        '#theme' => 'table',
        '#header' => $this->overviewTableHeaders($conditions, $status_rows),
        '#rows' => $status_rows,
        '#caption' => t('Tasks with status - @status', array('@status' => ucfirst($status))),
        '#empty' => t('No tasks.'),
        '#weight' => 3,
      );

      // @todo Find a better way to order statuses.
      if ($status == 'requested') {
        $render[$status]['#weight'] = 0;
      }
    }

    return $render;
  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableHeaders($conditions, $rows, $additional_header = array()) {
    $additional_header = array(
      t('Type'),
      t('Status'),
      t('User'),
      t('Requested'),
    );
    return parent::overviewTableHeaders($conditions, $rows, $additional_header);
  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableRow($conditions, $id, $entity, $additional_cols = array()) {
    /* @var GDPRTask $entity */

    $time_diff = REQUEST_TIME - $entity->created;
    $created_ago = t('%time ago', array('%time' => format_interval($time_diff, 1)));

    $additional_cols = array(
      $entity->bundleLabel(),
      $entity->status,
      theme('username', array('account' => user_load($entity->user_id))),
      format_date($entity->created, 'short') . ' - ' . $created_ago,
    );
    $row = parent::overviewTableRow($conditions, $id, $entity, $additional_cols);
    // @todo Fix hardcoded links.
    $row[0] = l($entity->label(), $this->path . '/' . $id . '/view', array('query' => drupal_get_destination()));
    $row[5] = l(t('edit'), $this->path . '/' . $id . '/edit', array('query' => drupal_get_destination()));
    $row[6] = l(t('delete'), $this->path . '/' . $id . '/delete', array('query' => drupal_get_destination()));

    return $row;
  }

}
