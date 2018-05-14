<?php

namespace Drupal\gdpr_dump\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Class GdprDatabaseManager.
 *
 * @package Drupal\gdpr_dump\Service
 */
class GdprDatabaseManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * GdprDatabaseManager constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    Connection $database
  ) {
    $this->database = $database;
  }

  /**
   * Fetch the tables with their columns.
   *
   * @return array
   *   The tables with their columns.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function getTableColumns() {
    $tables = $this->database->schema()->findTables('%');
    $columns = [];
    foreach ($tables as $table) {
      $result = $this->getColumns($table);
      if (NULL === $result) {
        continue;
      }
      $columns[$table] = $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    return $columns;
  }

  /**
   * Get the columns for a table.
   *
   * @param string $table
   *   The table name.
   *
   * @return \Drupal\Core\Database\StatementInterface|null
   *   An executed DB statement, or NULL.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  protected function getColumns($table) {
    // @todo: How cross-driver is this?
    $query = $this->database->select('information_schema.columns', 'columns');
    $query->fields('columns', ['COLUMN_NAME', 'DATA_TYPE', 'COLUMN_COMMENT']);
    $query->condition('TABLE_SCHEMA', $this->database->getConnectionOptions()['database']);
    $query->condition('TABLE_NAME', $table);
    return $query->execute();
  }

  /**
   * Get the column names for a table as an array.
   *
   * @param string $table
   *   The table name.
   *
   * @return array
   *   The columns.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function fetchColumnNames($table) {
    $query = $this->database->select('information_schema.columns', 'columns');
    $query->fields('columns', ['COLUMN_NAME']);
    $query->condition('TABLE_SCHEMA', $this->database->getConnectionOptions()['database']);
    $query->condition('TABLE_NAME', $table);
    $result = $query->execute();
    if (NULL === $result) {
      throw new InvalidQueryException("Columns for '$table' not available.");
    }

    return \array_keys($result->fetchAllAssoc('COLUMN_NAME', \PDO::FETCH_ASSOC));
  }

}
