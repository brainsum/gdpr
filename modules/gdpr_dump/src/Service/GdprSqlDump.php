<?php

namespace Drupal\gdpr_dump\Service;

use Drupal\anonymizer\Anonymizer\AnonymizerFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\gdpr_dump\Exception\GdprDumpAnonymizationException;
use Drupal\gdpr_dump\Form\SettingsForm;
use Drush\Sql\SqlException;

/* @todo:
 *  - Prepare and set the GDPR settings (tables and columns to be sanitized)
 *  - Create shadow-tables of the set ones with gdpr_tmp_ prefix.
 *  - Use db connection to sanitize the tables.
 *  - Force the exclusion of these tables for all commands
 *  - Run initial command and save output in memory (?)
 *  - Run second command, and in-memory replace the 'CREATE TABLE' commands
 *    based on the GDPR settings.
 *  - Dump them into a file (if needed), gzip them (if needed).
 *
 * Basically:
 *   - get settings
 *   - create clones
 *   - apply sanitation
 *   - create command:
 *     - base dump (exclude both original and gdpr tables)
 *     - && string replaced secondary dump
 *   - use file and gzip like the default
 */

/**
 * Class GdprSqlDump.
 *
 * @package Drupal\gdpr_dump\Service
 */
class GdprSqlDump {

  const GDPR_TABLE_PREFIX = 'gdpr_clone_';

  /**
   * The GDPR table settings.
   *
   * @var array
   */
  protected $tablesToAnonymize = [];

  /**
   * The list of tables needed to be skipped.
   *
   * @var array
   */
  protected $tablesToSkip = [];

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * GDPR database manager.
   *
   * @var \Drupal\gdpr_dump\Service\GdprDatabaseManager
   */
  protected $databaseManager;

  /**
   * The Sanitizer plugin factory.
   *
   * @var \Drupal\anonymizer\Anonymizer\AnonymizerFactory
   */
  protected $pluginFactory;

  /**
   * The database driver.
   *
   * E.g mysql, pgsql, sqlite.
   *
   * @var string
   */
  protected $driver;

  /**
   * GdprSqlDump constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\gdpr_dump\Service\GdprDatabaseManager $gdprDatabaseManager
   *   The GDPR database manager.
   * @param \Drupal\anonymizer\Anonymizer\AnonymizerFactory $pluginFactory
   *   The anonymizer plugin factory.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    Connection $database,
    GdprDatabaseManager $gdprDatabaseManager,
    AnonymizerFactory $pluginFactory
  ) {
    $this->tablesToAnonymize = $configFactory->get(SettingsForm::GDPR_DUMP_CONF_KEY)->get('mapping');
    $this->tablesToSkip = $configFactory->get(SettingsForm::GDPR_DUMP_CONF_KEY)->get('empty_tables');
    $this->database = $database;
    $this->driver = $this->database->driver();
    $this->databaseManager = $gdprDatabaseManager;
    $this->pluginFactory = $pluginFactory;
  }

  /**
   * Dump command.
   *
   * @throws \Drush\Sql\SqlException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   * @throws \Exception
   */
  public function dump() {
    drush_sql_bootstrap_further();
    $sql = $this->getInstance();
    $this->prepare();
    $result = $sql->dump(drush_get_option('result-file', FALSE));
    $this->cleanup();

    return $result;
  }

  /**
   * Get a SqlBase instance according to dbSpecs.
   *
   * @param array $dbSpec
   *   If known, specify a $dbSpec that the class can operate with.
   *
   * @throws \Drush\Sql\SqlException
   *
   * @return \Drush\Sql\SqlBase
   *   The Sql instance.
   *
   * @see \drush_sql_get_class()
   */
  protected function getInstance(array $dbSpec = NULL) {
    $database = drush_get_option('database', 'default');
    $target = drush_get_option('target', 'default');

    // Try a few times to quickly get $dbSpec.
    if (!empty($dbSpec)) {
      if (!empty($dbSpec['driver'])) {
        // Try loading our implementation first.
        $instance = drush_get_class(
          '\Drupal\gdpr_dump\Sql\GdprSql',
          [$dbSpec],
          [\ucfirst($dbSpec['driver'])]
        );

        if (!empty($instance)) {
          return $instance;
        }
      }
    }
    elseif ($url = drush_get_option('db-url')) {
      $url = \is_array($url) ? $url[$database] : $url;
      $dbSpec = drush_convert_db_from_db_url($url);
      $dbSpec['db_prefix'] = drush_get_option('db-prefix');
      return $this->getInstance($dbSpec);
    }
    elseif (
      ($databases = drush_get_option('databases'))
      && \array_key_exists($database, $databases)
      && \array_key_exists($target, $databases[$database])
    ) {
      $dbSpec = $databases[$database][$target];
      return $this->getInstance($dbSpec);
    }
    else {
      // No parameter or options provided. Determine $dbSpec ourselves.
      /** @var \Drush\Sql\SqlVersion $sqlVersion */
      if ($sqlVersion = drush_sql_get_version()) {
        if ($dbSpec = $sqlVersion->get_db_spec()) {
          return $this->getInstance($dbSpec);
        }
      }
    }

    throw new SqlException('Unable to find a matching SQL Class. Drush cannot find your database connection details.');
  }

  /**
   * Creates a query string for cloning.
   *
   * @param string $originalTable
   *   The table name.
   *
   * @return string|null
   *   The query string.
   *
   * @throws \Exception
   */
  protected function createCloneQueryString($originalTable) {
    if (\array_key_exists($originalTable, $this->tablesToSkip)) {
      // No need to clone tables that are excluded.
      return NULL;
    }
    $clonedTable = self::GDPR_TABLE_PREFIX . $originalTable;
    switch ($this->driver) {
      case 'mysql':
        return "CREATE TABLE IF NOT EXISTS `$clonedTable` LIKE `$originalTable`;";

      /* @todo
       * - These seem to be the same.
       * - Test both.
       */
      case 'pgsql':
      case 'sqlite':
        // Maybe get the original SQL of the table and apply that:
        // SELECT sql FROM sqlite_master WHERE type='table' AND name='mytable'.
        return "CREATE TABLE IF NOT EXISTS `$clonedTable` AS SELECT * FROM `$originalTable` WHERE 1=2;";

      // These require a contrib module.
      case 'oracle':
        // @see: https://www.drupal.org/project/oracle
        break;

      case 'sqlsrv':
        // @see: https://www.drupal.org/project/sqlsrv
        break;
    }

    throw new SqlException("Unsupported database driver detected, can't clone table $originalTable for GDPR.");
  }

  /**
   * Creates table clones according to the config.
   *
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Exception
   */
  protected function createTableClones() {
    $tables = \array_keys($this->tablesToAnonymize);
    $transaction = $this->database->startTransaction('gdpr_clone_tables');
    foreach ($tables as $table) {
      $queryString = $this->createCloneQueryString($table);
      if (NULL === $queryString) {
        // @todo: Notify?
        continue;
      }

      try {
        if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
          drush_print("Executing: '$queryString'", 0, STDERR);
        }
        $query = $this->database->query($queryString);
        $query->execute();
      }
      catch (\Exception $e) {
        drush_print("Error while cloning the '$table' table.");
        $transaction->rollBack();
      }
    }

    $this->database->popTransaction($transaction->name());
  }

  /**
   * Go through the data and sanitize it.
   *
   * @throws \Exception
   */
  protected function sanitizeData() {
    /* @todo
     * Remote API call optimization:
     *   Prefetch the required amount of data from remote APIs.
     *   Maybe do it on a table level.
     */
    /** @var array $anonymizationOptions */
    foreach ($this->tablesToAnonymize as $table => $anonymizationOptions) {
      if (\array_key_exists($table, $this->tablesToSkip)) {
        continue;
      }
      $selectQuery = $this->database->select($table);
      $selectQuery->fields($table);
      $oldRows = $selectQuery->execute();

      if (NULL === $oldRows) {
        // @todo: notify
        continue;
      }

      $clonedTable = self::GDPR_TABLE_PREFIX . $table;
      $tableColumns = $this->databaseManager->fetchColumnNames($table);
      $insertQuery = $this->database->insert($clonedTable);
      $insertQuery->fields($tableColumns);

      $query = $this->database->select('information_schema.columns', 'columns');
      $query->fields('columns', ['COLUMN_NAME', 'CHARACTER_MAXIMUM_LENGTH']);
      $query->condition('TABLE_SCHEMA', $this->database->getConnectionOptions()['database']);
      $query->condition('TABLE_NAME', $table);

      $columnDetails = $query->execute()->fetchAllAssoc('COLUMN_NAME');

      while ($row = $oldRows->fetchAssoc()) {
        foreach ($anonymizationOptions as $column => $pluginId) {
          /* @todo
           * Maybe it would be better to use 'per table' sanitation,
           * so username, email, etc can be the same.
           * E.g myuser could have myuser@example.com as a mail, not
           * somethingelse@example.com
           *
           * @todo:
           * Also add a way to make exceptions
           * e.g option for 'don't alter uid 1 name', etc.
           */

          $tries = 0;

          do {
            $isValid = TRUE;
            $value = $this->pluginFactory->get($pluginId)->anonymize($row[$column]);
            if (
              !empty($columnDetails[$column]->CHARACTER_MAXIMUM_LENGTH)
              && strlen($value) > $columnDetails[$column]->CHARACTER_MAXIMUM_LENGTH
              ) {
              $isValid = FALSE;
            }
          } while (!$isValid && $tries++ < 50);

          if ($tries > 50) {
            throw new GdprDumpAnonymizationException("Too many retries for column '$column'.");
          }

          $row[$column] = $value;
        }
        $insertQuery->values($row);
      }
      $insertQuery->execute();
    }
  }

  /**
   * Prepare the database for the dump.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   * @throws \Exception
   */
  protected function prepare() {
    $this->cleanup();
    $this->buildTablesToSkip();
    $this->createTableClones();
    $this->sanitizeData();
  }

  /**
   * Builds tablesToSkip array.
   */
  protected function buildTablesToSkip() {
    // Get table expanded selection.
    $sql = $this->getInstance();
    $table_selection = $sql->get_expanded_table_selection();
    $tablesToSkip = \array_merge($table_selection['skip'], $table_selection['structure']);
    $tablesToSkip = \array_flip($tablesToSkip);
    $tablesToSkip = $tablesToSkip + $this->tablesToSkip;

    $this->tablesToSkip = $tablesToSkip;
  }

  /**
   * Cleanup the database after the dump.
   *
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   */
  protected function cleanup() {
    $transaction = $this->database->startTransaction('gdpr_drop_table');
    foreach (\array_keys($this->tablesToAnonymize) as $table) {
      $gdprTable = self::GDPR_TABLE_PREFIX . $table;
      $this->database->schema()->dropTable($gdprTable);
    }
    $this->database->popTransaction($transaction->name());
  }

}
