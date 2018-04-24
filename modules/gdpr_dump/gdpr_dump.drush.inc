<?php

/**
 * @file
 * This file contains the GDPR database dump command.
 */

/**
 * Implements hook_drush_command().
 */
function gdpr_dump_drush_command() {
  $commands = [];

  $options['database'] = [
    'description' => 'The DB connection key if using multiple connections in settings.php.',
    'example-value' => 'key',
  ];
  $dbUrl['db-url'] = [
    'description' => 'A Drupal 6 style database URL.',
    'example-value' => 'mysql://root:pass@127.0.0.1/db',
  ];

  $commands['gdpr-sql-dump'] = [
    'description' => 'Exports the Drupal DB as SQL using mysqldump or equivalent.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_gdpr_sql_dump',
    'drupal dependencies' => [
      'gdpr',
      'gdpr_dump',
    ],
    'examples' => [
      'drush gdpr-sql-dump --result-file=../18.sql' => 'Save SQL dump to the directory above Drupal root.',
      'drush gdpr-sql-dump --skip-tables-key=common' => 'Skip standard tables. @see example.drushrc.php',
      'drush gdpr-sql-dump --extra=--no-data' => 'Pass extra option to dump command.',
    ],
    'options' => _gdpr_dump_sql_get_table_selection_options() + [
      'result-file' => [
        'description' => 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with no value, then date based filename will be created under ~/drush-backups directory.',
        'example-value' => '/path/to/file',
        'value' => 'optional',
      ],
      'create-db' => ['hidden' => TRUE, 'description' => 'Omit DROP TABLE statements. Postgres and Oracle only.  Used by sql-sync, since including the DROP TABLE statements interfere with the import when the database is created.'],
      'data-only' => 'Dump data without statements to create any of the schema.',
      'ordered-dump' => 'Order by primary key and add line breaks for efficient diff in revision control. Slows down the dump. Mysql only.',
      'gzip' => 'Compress the dump using the gzip program which must be in your $PATH.',
      'extra' => 'Add custom options to the dump command.',
    ] + $options + $dbUrl,
  ];

  $commands['gdpr-sanitize'] = [
    'description' => 'Sanitize the environment.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'callback' => 'drush_gdpr_sanitize',
    'drupal dependencies' => [
      'gdpr',
      'gdpr_dump',
    ],
    'examples' => [
      'drush gdpr-sanitize' => 'Sanitize the environment',
    ],
  ];

  return $commands;
}

/**
 * Command callback for gdpr-sql-dump.
 *
 * Outputs the entire Drupal database in SQL format
 * using mysqldump or equivalent.
 */
function drush_gdpr_sql_dump() {
  /** @var \Drupal\gdpr_dump\Service\GdprSqlDump $service */
  $service = \Drupal::service('gdpr_dump.sql_dump');
  try {
    return $service->dump();
  }
  catch (\Exception $e) {
    return drush_set_error('DRUSH_DUMP_ERROR', $e->getMessage());
  }
}

/**
 * Command callback for gdpr-sanitize.
 *
 * Sanitizes the current environment.
 */
function drush_gdpr_sanitize() {
  /** @var \Drupal\gdpr_dump\Service\GdprSanitize $service */
  $service = \Drupal::service('gdpr_dump.sanitize');
  try {
    $service->sanitize();
  }
  catch (\Exception $e) {
    return drush_set_error('DRUSH_DUMP_ERROR', $e->getMessage());
  }
}

/**
 * Helper function.
 *
 * Mirrored from Drush.
 *
 * @see \drush_sql_get_table_selection_options()
 *
 * @return array
 *   The table selection options.
 */
function _gdpr_dump_sql_get_table_selection_options() {
  return [
    'skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
    'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
    'tables-key' => 'A key in the $tables array. Optional.',
    'skip-tables-list' => 'A comma-separated list of tables to exclude completely. Optional.',
    'structure-tables-list' => 'A comma-separated list of tables to include for structure, but not data. Optional.',
    'tables-list' => 'A comma-separated list of tables to transfer. Optional.',
  ];
}
