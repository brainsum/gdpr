<?php

/**
 * @file
 * Ctools plugin for password sanitizaion.
 */

$plugin = [
  'title' => t('Password Sanitizer'),
  'sanitize callback' => 'gdpr_password_sanitize',
];

/**
 * Password sanitize callback.
 */
function gdpr_password_sanitize($input) {
  require_once DRUPAL_ROOT . '/' . variable_get('password_inc', 'includes/password.inc');
  return user_hash_password(user_password(8));
}
