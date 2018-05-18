<?php

namespace Drupal\gdpr\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;

/**
 * Class UserController.
 *
 * @package Drupal\gdpr\Controller
 */
class UserController extends ControllerBase {

  /**
   * Access check for the route.
   *
   * @param \Drupal\user\UserInterface $user
   *   The given user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The result.
   */
  public function accessCollectedData(UserInterface $user) {
    // Must have the appropriate permission to access the page.
    // This allows site admins to completely disable the View My Data page.
    if (!$this->currentUser()->hasPermission('view gdpr data summary')) {
      return AccessResult::forbidden();
    }

    // If access to the page is enabled, only show the tab if we're viewing our
    // OWN profile or we're a GDPR admin.
    if ($this->currentUser()->hasPermission('administer gdpr settings')) {
      return AccessResult::allowed();
    }

    if ((int) $user->id() === (int) $this->currentUser()->id()) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Return the collected data for a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return array
   *   Render array.
   */
  public function collectedData(UserInterface $user) {
    $rows = [];
    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    foreach ($user as $key => $field) {
      // Don't show the hash.
      if ('pass' === $key) {
        $rows[] = [
          'type' => $field->getName(),
          'value' => '******',
        ];
        continue;
      }

      $fieldValue = $field->getString();

      // @todo: Maybe display as N/A, or as empty?
      if (empty($fieldValue)) {
        continue;
      }

      // @todo: Maybe turn to human readable values?
      $rows[] = [
        'type' => $key,
        'value' => $fieldValue,
      ];
    }

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Stored user data'),
      '#header' => [
        $this->t('Type'),
        $this->t('Value'),
      ],
      '#rows' => $rows,
    ];

    return $table;
  }

}
