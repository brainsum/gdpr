<?php

namespace Drupal\gdpr_dump\Service;

/**
 * Class GdprSqlDump.
 *
 * @package Drupal\gdpr_dump\Service
 */
class GdprSanitize extends GdprSqlDump {

  /**
   * Go through the data and sanitize it.
   *
   * @throws \Exception
   */
  public function sanitize() {
    $this->prepare();
    $this->rename();
  }

  /**
   * Rename the cloned tables to the original tables.
   */
  protected function rename() {
    $transaction = $this->database->startTransaction('gdpr_rename_table');

    foreach (\array_keys($this->tablesToAnonymize) as $table) {
      $gdprTable = self::GDPR_TABLE_PREFIX . $table;
      $this->database->schema()->dropTable($table);
      $this->database->schema()->renameTable($gdprTable, $table);
    }

    $this->database->popTransaction($transaction->name());
  }

}
