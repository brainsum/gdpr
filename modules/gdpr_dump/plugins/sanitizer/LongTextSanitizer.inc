<?php

/**
 * @file
 * Ctools plugin for long text sanitizaion.
 */

$plugin = [
  'title' => t('Long Text Sanitizer'),
  'sanitize callback' => 'gdpr_long_text_sanitize',
];

/**
 * Long text sanitize callback.
 */
function gdpr_long_text_sanitize($input) {
  if (empty($input)) {
    return $input;
  }

  $output = gdpr_remote_generator($input);

  if ($output === $input) {
    $output = gdpr_local_generator($input);
  }

  return $output;
}

/**
 * Remote generator for long text sanitizer.
 */
function gdpr_long_text_remote_generator($input) {
  $result = NULL;
  try {
    $result = drupal_http_request('https://loripsum.net/api/3/medium/plaintext');
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
 * Local generator for long text sanitizer.
 */
function gdpr_long_text_local_generator($input) {
  $paragraphCount = str_word_count($input) % 5;
  return gdpr_random_paragraphs($paragraphCount);
}
