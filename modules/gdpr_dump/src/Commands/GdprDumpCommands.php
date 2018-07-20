<?php

namespace Drupal\gdpr_dump\Commands;

use Drupal\gdpr_dump\Service\GdprSanitize;
use Drupal\gdpr_dump\Service\GdprSqlDump;
use Drush\Commands\DrushCommands;

/**
 * Class GdprDumpCommands.
 *
 * Drush 9 commands.
 *
 * @package Drupal\gdpr_dump\Commands
 */
class GdprDumpCommands extends DrushCommands {

  /**
   * The dump service.
   *
   * @var \Drupal\gdpr_dump\Service\GdprSqlDump
   */
  protected $dumpService;

  /**
   * The sanitize service.
   *
   * @var \Drupal\gdpr_dump\Service\GdprSanitize
   */
  protected $sanitizeService;

  /**
   * GdprDumpCommands constructor.
   *
   * @param \Drupal\gdpr_dump\Service\GdprSqlDump $dump
   *   The dump service.
   * @param \Drupal\gdpr_dump\Service\GdprSanitize $sanitize
   *   The sanitize service.
   */
  public function __construct(
    GdprSqlDump $dump,
    GdprSanitize $sanitize
  ) {
    parent::__construct();
    $this->dumpService = $dump;
    $this->sanitizeService = $sanitize;
  }

  const DEFAULT_DUMP_OPTIONS = [
    'result-file' => NULL,
    'create-db' => FALSE,
    'data-only' => FALSE,
    'ordered-dump' => FALSE,
    'gzip' => FALSE,
    'extra' => self::REQ,
    'extra-dump' => self::REQ,
  ];

  /**
   * Exports the Drupal DB as SQL using mysqldump or equivalent.
   *
   * @param array $options
   *   The options for the command.
   *
   * @command gdpr:sql:dump
   * @aliases gdpr-sql-dump
   * @optionset_sql
   * @optionset_table_selection
   * @option result-file Save to a file. The file should be relative to Drupal root.
   * @option create-db Omit DROP TABLE statements. Used by Postgres and Oracle only.
   * @option data-only Dump data without statements to create any of the schema.
   * @option ordered-dump Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.
   * @option gzip Compress the dump using the gzip program which must be in your $PATH.
   * @option extra Add custom arguments/options when connecting to database (used internally to list tables).
   * @option extra-dump Add custom arguments/options to the dumping the database (e.g. mysqldump command).
   * @usage drush gdpr:sql:dump --result-file=../18.sql
   *   Save SQL dump to the directory above Drupal root.
   * @usage drush gdpr:sql:dump --skip-tables-key=common
   *   Skip standard tables. @see example.drush.yml
   * @usage drush gdpr:sql:dump --extra-dump=--no-data
   *   Pass extra option to mysqldump command.
   * @hidden-options create-db
   * @bootstrap max configuration
   *
   * @notes
   *   The createdb command is used by sql-sync, since including the
   *   DROP TABLE statements interfere with the import when the
   *   database is created.
   *
   * @see \Drush\Commands\sql\SqlCommands::dump()
   *
   * @return bool|\Drupal\gdpr_dump\Sql\GdprSqlBase
   *   The result of the dump.
   *
   * @throws \Exception
   */
  public function dump(array $options = self::DEFAULT_DUMP_OPTIONS) {
    try {
      return $this->dumpService->dump($options);
    }
    catch (\Exception $e) {
      return drush_set_error('DRUSH_DUMP_ERROR', $e->getMessage());
    }
  }

  /**
   * Sanitizes the current environment.
   *
   * @command gdpr:sanitize
   * @aliases gdpr-sanitize
   * @bootstrap max configuration
   *
   * @throws \Exception
   */
  public function sanitize() {
    $this->sanitizeService->sanitize();
  }

}
