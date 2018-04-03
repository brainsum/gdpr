<?php

/**
 * @file
 * Ctools plugin for username sanitizaion.
 */

$plugin = [
  'title' => t('Username Sanitizer'),
  'sanitize callback' => 'gdpr_username_sanitize',
];

/**
 * Username sanitize callback.
 */
function gdpr_username_sanitize($input) {
  if (empty($input)) {
    return $input;
  }

  $output = gdpr_username_remote_generator($input);

  if ($output === $input) {
    $output = gdpr_username_local_generator($input);
  }

  return $output;
}

/**
 * Remote generator for username sanitizer.
 */
function gdpr_username_remote_generator($input) {
  $result = NULL;
  try {
    $result = drupal_http_request('https://randomuser.me/api/?format=pretty&results=1&inc=name&noinfo&nat=us,gb');
  }
  catch (\Exception $e) {
    // @todo: Log?
    return $input;
  }

  if (NULL !== $result && 200 == $result->code) {
    $data = $result->data;
    $data = json_decode($data, TRUE);

    $name = reset($data['results'])['name'];
    return $name['first'] . '.' . $name['last'];
  }

  return $input;
}

/**
 * Local generator for username sanitizer.
 */
function gdpr_username_local_generator($input) {
  return gdpr_random_word(strlen($input));
}
