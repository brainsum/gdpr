<?php

/**
 * @file
 * Ctools plugin for email sanitizaion.
 */

$plugin = [
  'title' => t('Email Sanitizer'),
  'sanitize callback' => 'gdpr_email_sanitize',
];

/**
 * Email sanitize callback.
 */
function gdpr_email_sanitize($input) {
  if (empty($input)) {
    return $input;
  }

  return gdpr_random_word(12) . '@example.com';
}
