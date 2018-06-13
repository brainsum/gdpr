<?php

/**
 * @file
 * Renders a user's collected data.
 */

$header = array(t('Type'), t('Value'));
$rows = array();
foreach ($user_data as $field => $value) {
  $rows[] = array(
    'data' => array($field, $value),
  );
}
print theme('table', array(
  'header' => $header,
  'rows' => $rows,
));
