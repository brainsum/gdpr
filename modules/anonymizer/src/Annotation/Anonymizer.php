<?php

namespace Drupal\anonymizer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for anonymizer plugins.
 *
 * Plugin namespace: Plugin\Anonymizer\Anonymizer.
 *
 * @package Drupal\anonymizer\Annotation
 *
 * @Annotation
 */
class Anonymizer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * Human-readable of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * Description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
