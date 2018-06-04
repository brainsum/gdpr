<?php

/**
 * Base class for export UI.
 */
class gdpr_entity_property {
  var $plugin;
  var $name;
  var $options = array();

  /**
   * Fake constructor.
   */
  function init($plugin) {
    $this->plugin = $plugin;
  }

}
