<?php

/**
 * Base class for export UI.
 */
class gdpr_entity_property {

  public $plugin;
  public $name;
  public $options = array();

  /**
   * Fake constructor.
   */
  public function init($plugin) {
    $this->plugin = $plugin;
  }

}
