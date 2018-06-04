<?php

/**
 * The Task entity class.
 */
interface GDPRTaskInterface extends EntityInterface {

  /**
   * Gets the human readable label of the tasks bundle.
   */
  public function bundleLabel();

  /**
   * Gets the user that the task belongs to.
   */
  public function getOwner();

}