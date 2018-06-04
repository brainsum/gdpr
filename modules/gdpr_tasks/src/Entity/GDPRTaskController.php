<?php

/**
 * The Task entity controller class.
 */
class GDPRTaskController extends EntityAPIController {

  /**
   * {@inheritdoc}
   */
  public function create(array $values = array()) {
    $values += array('status' => 'requested');
    $values += array('created' => REQUEST_TIME);

    $task = parent::create($values);
    return $task;
  }

  public function save($entity, DatabaseTransaction $transaction = NULL) {
    $entity->changed = REQUEST_TIME;
    return parent::save($entity, $transaction);
  }


}
