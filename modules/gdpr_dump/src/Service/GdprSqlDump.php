<?php

namespace Drupal\gdpr_dump\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\gdpr_dump\Form\SettingsForm;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerFactory;
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
  protected $gdprOptions = [];

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
   * @var \Drupal\gdpr_dump\Sanitizer\GdprSanitizerFactory
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
   * @param \Drupal\gdpr_dump\Sanitizer\GdprSanitizerFactory $pluginFactory
   *   The GDPR plugin factory.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    Connection $database,
    GdprDatabaseManager $gdprDatabaseManager,
    GdprSanitizerFactory $pluginFactory
  ) {
    $this->gdprOptions = $configFactory->get(SettingsForm::GDPR_DUMP_CONF_KEY)->get('mapping');
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
    $tables = \array_keys($this->gdprOptions);
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
    /** @var array $sanitationOptions */
    foreach ($this->gdprOptions as $table => $sanitationOptions) {
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

      while ($row = $oldRows->fetchAssoc()) {
        foreach ($sanitationOptions as $column => $pluginId) {
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
          $row[$column] = $this->pluginFactory->get($pluginId)->sanitize($row[$column]);
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
    $this->createTableClones();
    $this->sanitizeData();
  }

  /**
   * Cleanup the database after the dump.
   *
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   */
  protected function cleanup() {
    $transaction = $this->database->startTransaction('gdpr_drop_table');
    foreach (\array_keys($this->gdprOptions) as $table) {
      $gdprTable = self::GDPR_TABLE_PREFIX . $table;
      $this->database->schema()->dropTable($gdprTable);
    }
    $this->database->popTransaction($transaction->name());
  }

}
