<?php

namespace Drupal\gdpr\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserController.
 *
 * @package Drupal\gdpr\Controller
 */
class UserController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * UserController constructor.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $dateFormatter) {
    $this->dateFormatter = $dateFormatter;
  }

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

      if ($key === 'created' || $key === 'changed' || $key === 'login' || $key === 'access') {
        $fieldValue = $this->dateFormatter->format($fieldValue, 'medium');
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
