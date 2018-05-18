<?php

namespace Drupal\gdpr_consent\ConsentUserResolver;

use Drupal\Component\Annotation\Plugin;

/**
 * Annotation for Consent Resolver plugins.
 *
 * Plugin namespace: Plugin\Gdpr\ConsentUserResolver.
 *
 * @package Drupal\gdpr_consent\Annotation
 *
 * @Annotation
 */
class GdprConsentUserResolver extends Plugin {

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


  /**
   * The entity type to which this resolver applies.
   *
   * @var string
   */
  public $entityType;


  /**
   * The bundle that this should act on.
   *
   * @var string
   */
  public $bundle;

}
