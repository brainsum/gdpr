<?php

namespace Drupal\gdpr_dump\Sql;

use Drush\Sql\Sqloracle;

/**
 * Class GdprSqlOracle.
 *
 * @package Drupal\gdpr_dump\Sql
 */
class GdprSqlOracle extends Sqloracle {

  // @todo $file is no longer provided. We are supposed to return bash that can be piped to gzip.
  // Probably Oracle needs to override dump() entirely - http://stackoverflow.com/questions/2236615/oracle-can-imp-exp-go-to-stdin-stdout.
  public function dumpCmd($table_selection) {
    $create_db = drush_get_option('create-db');
    $exec = 'exp ' . $this->creds();
    // Change variable '$file' by reference in order to get drush_log() to report.
    if (!$file) {
      $file = $this->db_spec['username'] . '.dmp';
    }
    $exec .= ' file=' . $file;

    if (!empty($tables)) {
      $exec .= ' tables="(' . implode(',', $tables) . ')"';
    }
    $exec .= ' owner=' . $this->db_spec['username'];
    if ($option = drush_get_option('extra', $this->query_extra)) {
      $exec .= " $option";
    }
    return array($exec, $file);
  }

}
