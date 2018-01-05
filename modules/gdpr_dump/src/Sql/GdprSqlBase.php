<?php

namespace Drupal\gdpr_dump\Sql;

use Drush\Drush;
use Drush\Sql\SqlBase;

/**
 * Class GdprSqlBase.
 *
 * @see \Drush\Sql\SqlBase
 *
 * @package Drupal\gdpr_dump\Sql
 */
class GdprSqlBase extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public static function getInstance($dbSpec, $options) {
    $driver = $dbSpec['driver'];
    $className = 'Drupal\gdpr_dump\Sql\GdprSql' . ucfirst($driver);
    // @todo: Maybe add an interface, for now it's ok.
    /** @var \Drupal\gdpr_dump\Sql\GdprSqlBase $instance */
    // @todo: Maybe use classResolver.
    $instance = new $className($dbSpec, $options);
    // Inject config.
    $instance->setConfig(Drush::config());
    return $instance;
  }

}
