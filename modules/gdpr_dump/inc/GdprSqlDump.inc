<?php

/**
 * Class GdprSqlDump.
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
   * The empty tables array.
   *
   * @var array
   */
  protected $emptyTables = [];

  /**
   * The list of tables needed to be skipped.
   *
   * @var array
   */
  protected $skipTables = [];

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Sanitizer plugins.
   *
   * @var array
   */
  protected $plugins;

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
   */
  public function __construct() {
    $this->driver = db_driver();
    $this->plugins = ctools_get_plugins('gdpr_dump', 'sanitizer');
    $this->gdprOptions = variable_get('gdpr_dump_table_map', []);
    $this->emptyTables = variable_get('gdpr_dump_empty_tables', []);
  }

  /**
   * Dump command.
   */
  public function dump() {
    $sql = $this->getInstance();
    $this->prepare();
    $results = $sql->dump(drush_get_option('result-file', FALSE));
    $this->cleanup();
    return $results;
  }

  /**
   * Get a SqlBase instance according to dbSpecs.
   */
  protected function getInstance(array $dbSpec = NULL) {
    $database = drush_get_option('database', 'default');
    $target = drush_get_option('target', 'default');

    if (!empty($dbSpec)) {
      if (!empty($dbSpec['driver'])) {
        // Try loading our implementation first.
        $className = 'GdprSql' . ucfirst($dbSpec['driver']);
        module_load_include('inc', 'gdpr_dump', 'inc/' . $className);

        $instance = drush_get_class($className, [$dbSpec]);

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
  }

  /**
   * Creates a query string for cloning.
   */
  protected function createCloneQueryString($originalTable) {
    if (\array_key_exists($originalTable, $this->skipTables)) {
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
   */
  protected function createTableClones() {
    $tables = \array_keys($this->gdprOptions);
    $transaction = db_transaction('gdpr_drop_table');

    try {
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
          db_query($queryString);
        }
        catch (\Exception $e) {
          drush_print("Error while cloning the '$table' table.");
          $transaction->rollBack();
        }
      }
    }
    catch (Exception $e) {
      $transaction->rollback();
      drush_set_error('DRUSH_SQL_DUMP_FAIL', 'Table clone failed');
    }
  }

  /**
   * Go through the data and sanitize it.
   */
  protected function sanitizeData() {
    /* @todo
     * Remote API call optimization:
     *   Prefetch the required amount of data from remote APIs.
     *   Maybe do it on a table level.
     */
    /** @var array $sanitationOptions */
    foreach ($this->gdprOptions as $table => $sanitationOptions) {
      if (\array_key_exists($table, $this->skipTables)) {
        continue;
      }
      $selectQuery = db_select($table);
      $selectQuery->fields($table);
      $oldRows = $selectQuery->execute();

      if (NULL === $oldRows) {
        // @todo: notify
        continue;
      }

      $clonedTable = self::GDPR_TABLE_PREFIX . $table;

      $describeQueryString = "DESCRIBE  `$table`";
      $describeQuery = db_query($describeQueryString);
      $tableColumns = $describeQuery->fetchCol('Field');
      $insertQuery = db_insert($clonedTable);
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

          $plugin = gdpr_dump_get_sanitizer_plugins($pluginId);

          if (function_exists($plugin['sanitize callback'])) {
            $row[$column] = call_user_func($plugin['sanitize callback'], $row[$column]);
          }
        }
        $insertQuery->values($row);
      }
      $insertQuery->execute();
    }
  }

  /**
   * Prepare the database for the dump.
   */
  protected function prepare() {
    $this->cleanup();
    $this->buildSkipTables();
    $this->createTableClones();
    $this->sanitizeData();
  }

  /**
   * Builds skipTables array.
   */
  protected function buildSkipTables() {
    // Get table expanded selection.
    $sql = $this->getInstance();
    $table_selection = $sql->get_expanded_table_selection();
    $skipTables = \array_merge($table_selection['skip'], $table_selection['structure']);
    $skipTables = \array_flip($skipTables);
    $skipTables = $skipTables + $this->emptyTables;

    $this->skipTables = $skipTables;
  }

  /**
   * Cleanup the database after the dump.
   */
  protected function cleanup() {
    $transaction = db_transaction('gdpr_drop_table');

    try {
      foreach (\array_keys($this->gdprOptions) as $table) {
        $gdprTable = self::GDPR_TABLE_PREFIX . $table;
        db_drop_table($gdprTable);
      }
    }
    catch (Eexception $e) {
      $transaction->rollback();
      drush_set_error('DRUSH_SQL_DUMP_FAIL', 'Database Cleanup failed');
    }
  }

}
