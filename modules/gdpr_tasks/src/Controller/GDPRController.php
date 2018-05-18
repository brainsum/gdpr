<?php

namespace Drupal\gdpr_tasks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\gdpr_tasks\TaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gdpr_tasks\Form\CreateGdprRequestOnBehalfOfUserForm;

/**
 * Returns responses for Views UI routes.
 */
class GDPRController extends ControllerBase {

  /**
   * The task manager service.
   *
   * @var \Drupal\gdpr_tasks\TaskManager
   */
  protected $taskManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('gdpr_tasks.manager')
    );
  }

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\gdpr_tasks\TaskManager $task_manager
   *   The task manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    MessengerInterface $messenger,
    TaskManager $task_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->taskManager = $task_manager;
  }

  /**
   * Placeholder for a GDPR Dashboard.
   *
   * @return array
   *   Renderable Drupal markup.
   */
  public function summaryPage() {
    return ['#markup' => $this->t('Summary')];
  }

  /**
   * Request a GDPR Task.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user for whom the request is being made.
   * @param string $gdpr_task_type
   *   Type of task to be created.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Either the task request form or a redirect response to requests page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function requestPage(AccountInterface $user, $gdpr_task_type) {
    $tasks = $this->taskManager->getUserTasks($user, $gdpr_task_type);

    if (!empty($tasks)) {
      $this->messenger->addWarning('You already have a pending task.');
    }
    else {
      // If the current user is making a request for themselves, just create it.
      // However, if we're a member of staff making a request on behalf
      // of someone else, we need to collect further details
      // so render a form to get the notes.
      if ($this->currentUser()->id() !== $user->id()) {
        return [
          'form' => $this->formBuilder()->getForm(CreateGdprRequestOnBehalfOfUserForm::class),
        ];
      }

      $values = [
        'type' => $gdpr_task_type,
        'user_id' => $user->id(),
      ];
      $this->entityTypeManager->getStorage('gdpr_task')
        ->create($values)
        ->save();
      $this->messenger->addStatus('Your request has been logged');
    }

    return $this->redirect('view.gdpr_tasks_my_data_requests.page_1', ['user' => $user->id()]);
  }

}
