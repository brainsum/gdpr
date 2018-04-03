<?php

/**
 * @file
 * Ctools plugin for text sanitizaion.
 */

$plugin = [
  'title' => t('Text Sanitizer'),
  'sanitize callback' => 'gdpr_text_sanitize',
];

/**
 * Text sanitize callback.
 */
function gdpr_text_sanitize($input) {
  if (empty($input)) {
    return $input;
  }

  $output = gdpr_text_remote_generator($input);

  if ($output === $input) {
    $output = gdpr_text_local_generator($input);
  }

  return $output;
}

/**
 * Remote generator for text sanitize.
 */
function gdpr_text_remote_generator($input) {
  $result = NULL;
  try {
    $result = drupal_http_request('https://loripsum.net/api/1/short/plaintext');
  }
  catch (\Exception $e) {
    // @todo: Log?
    return $input;
  }

  if (NULL !== $result && 200 == $result->code) {
    $data = $result->data;
    if (NULL === $data) {
      return $input;
    }

    return substr(trim($data), 0, strlen($input));
  }

  return $input;
}

/**
 * Local generator for text sanitize.
 */
function gdpr_text_local_generator($input) {
  return gdpr_random_sentence(str_word_count($input));
}
