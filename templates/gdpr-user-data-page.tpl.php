<?php

/**
 * @file
 * Renders a user's collected data.
 */

$header = [t('Type'), t('Value')];
$rows = [];
foreach ($user_data as $field => $value) {
  $rows[] = [
    'data' => [$field, $value],
  ];
}
print theme('table', [
  'header' => $header,
  'rows' => $rows,
]);
