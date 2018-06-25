<?php

namespace Drupal\gdpr_tasks\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Task entities.
 *
 * @ingroup gdpr_tasks
 */
interface TaskInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Task creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Task.
   */
  public function getCreatedTime();

  /**
   * Sets the Task creation timestamp.
   *
   * @param int $timestamp
   *   The Task creation timestamp.
   *
   * @return \Drupal\gdpr_tasks\Entity\TaskInterface
   *   The called Task entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the current status of the task.
   *
   * @return string
   *   The status of the Task entity.
   */
  public function getStatus();

  /**
   * Gets the current human readable status of the task.
   *
   * @return string
   *   The human readable status of the Task entity.
   */
  public function getStatusLabel();

}
