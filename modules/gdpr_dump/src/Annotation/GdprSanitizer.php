<?php

namespace Drupal\gdpr_dump\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for sanitizer plugins.
 *
 * Plugin namespace: Plugin\Gdpr\Sanitizer.
 *
 * @see \Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer\UsernameSanitizer
 *
 * @package Drupal\gdpr_dump\Annotation
 *
 * @Annotation
 */
class GdprSanitizer extends Plugin {

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
