<?php

/**
 * @file
 * Page callbacks for the GDPR Tasks module.
 */

/**
 * Request page for user.
 */
function gdpr_task_user_request($user) {
  return array(
    'message' => array(
      '#markup' => 'Make data access requests.'
    ),
  );
}

/**
 * Request page for user.
 */
function gdpr_tasks_request($account, $gdpr_task_type) {
  $tasks = gdpr_tasks_get_user_tasks($account, $gdpr_task_type);

  if (!empty($tasks)) {
    drupal_set_message('You already have a pending task.', 'warning');
  }
  else {
    global $user;

    $values = array(
      'type' => $gdpr_task_type,
      'user_id' => $account->uid,
      'requested_by' => $user->uid,
    );
    $task = entity_create('gdpr_task', $values);
    $task->save();

    // Send confirmation email to user.
    if ($task->requested_by == $task->user_id) {
      gdpr_tasks_send_mail('task_requested_self', $task);
    }
    else {
      gdpr_tasks_send_mail('task_requested_other', $task);
    }
    drupal_set_message('Your request has been logged.');
  }

  drupal_goto("user/{$account->uid}/gdpr/requests");
}