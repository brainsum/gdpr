<?php

namespace Drupal\gdpr_dump\Sql;

use Drush\Sql\Sqloracle;

/**
 * Class GdprSqlOracle.
 *
 * @package Drupal\gdpr_dump\Sql
 */
class GdprSqlOracle extends Sqloracle {

  /**
   * {@inheritdoc}
   */
  public function dumpCmd($table_selection) {
    $exec = 'exp ' . $this->creds();
    // Change variable $file by reference in order to get drush_log() to report.
    $file = $this->db_spec['username'] . '.dmp';
    $exec .= ' file=' . $file;

    $exec .= ' owner=' . $this->db_spec['username'];
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $exec .= " $option";
    }
    return [$exec, $file];
  }

}
