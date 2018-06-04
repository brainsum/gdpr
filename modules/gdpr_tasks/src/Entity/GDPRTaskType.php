<?php

/**
 * The Task type entity class.
 */
class GDPRTaskType extends Entity {

  /**
   * @var string
   */
  public $type;

  public $label;
  public $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct($values = array()) {
    parent::__construct($values, 'gdpr_task_type');
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultLabel() {
    return $this->label;
  }

}