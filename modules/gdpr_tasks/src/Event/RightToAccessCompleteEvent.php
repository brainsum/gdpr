<?php

namespace Drupal\gdpr_tasks\Event;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event fired when a right to access request is completed.
 *
 * @package Drupal\gdpr_tasks\Event
 */
class RightToAccessCompleteEvent extends Event {

  const EVENT_NAME = 'gdpr_tasks.rules_rta_complete';

  /**
   * The user who for whom the request was made.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  public $user;

  /**
   * The link to the data export.
   *
   * @var string
   */
  public $link;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user.
   * @param string $link
   *   The link to the download.
   */
  public function __construct(AccountInterface $user, $link) {
    $this->user = $user;
    $this->link = $link;
  }

}
