<?php

namespace Drupal\gdpr_tasks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
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
   * The gdpr_tasks_process_gdpr_sar queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('gdpr_tasks.manager'),
      $container->get('queue')
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
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    MessengerInterface $messenger,
    TaskManager $task_manager,
    QueueFactory $queue
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->taskManager = $task_manager;
    $this->queue = $queue->get('gdpr_tasks_process_gdpr_sar');
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
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function requestPage(AccountInterface $user, $gdpr_task_type) {
    $tasks = $this->taskManager->getUserTasks($user, $gdpr_task_type);

    $pending = FALSE;
    $statuses = ['requested', 'processed', 'reviewing'];
    foreach ($tasks as $task) {
      if (in_array($task->status->getString(), $statuses, TRUE)) {
        $pending = TRUE;
      }
    }

    if ($pending) {
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
      $newTask = $this->entityTypeManager->getStorage('gdpr_task')
        ->create($values);
      try {
        $newTask->save();
        $this->messenger->addStatus($this->t('Your request has been logged.'));

        if ($gdpr_task_type === 'gdpr_sar') {
          $this->queue->createQueue();
          $this->queue->createItem($newTask->id());
        }
      }
      catch (EntityStorageException $exception) {
        $this->messenger->addError($this->t('There was an error while logging your request.'));
        $this->loggerFactory->get('gdpr_tasks')->error($this->t('Error while trying to create a(n) "@taskType" GDPR task for user "@userName (@userId)."', [
          '@taskType' => $gdpr_task_type,
          '@userName' => $user->getDisplayName(),
          '@userId' => $user->id(),
        ]));
      }
    }

    return $this->redirect('view.gdpr_tasks_my_data_requests.page_1', ['user' => $user->id()]);
  }

}
