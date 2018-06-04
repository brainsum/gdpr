<?php

/**
 * The Task entity class.
 */
class GDPRTask extends Entity implements GDPRTaskInterface {

  /**
   * The internal numeric id of the task.
   *
   * @var integer
   */
  public $id;

  /**
   * @var string
   */
  public $type;

  /**
   * @var string
   */
  public $language;

  /**
   * The users id that requested this task.
   *
   * @var integer
   */
  public $user_id;

  /**
   * The status of the task.
   *
   * @var string
   */
  public $status;

  /**
   * The Unix timestamp when the task was created.
   *
   * @var integer
   */
  public $created;

  /**
   * The Unix timestamp when the task was most recently saved.
   *
   * @var integer
   */
  public $changed;

  /**
   * The Unix timestamp when the task was completed.
   *
   * @var integer
   */
  public $complete;

  /**
   * The users id that requested this task.
   *
   * @var integer
   */
  public $requested_by;

  /**
   * The users id that processed this task.
   *
   * @var integer
   */
  public $processed_by;

  /**
   * {@inheritdoc}
   */
  protected $defaultLabel = TRUE;


  /**
   * {@inheritdoc}
   */
  public function __construct($values = array()) {
    parent::__construct($values, 'gdpr_task');
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return user_load($this->user_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultLabel() {
    return "Task {$this->id}";
  }

  /**
   * {@inheritdoc}
   */
  public function bundleLabel() {
    // Add in the translated specified label property.
    return $this->entityInfo['bundles'][$this->bundle()]['label'];
  }
}