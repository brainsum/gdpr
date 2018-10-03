<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\gdpr_tasks\Anonymizer;
use Drupal\gdpr_tasks\Event\RightToBeForgottenCompleteEvent;
use Drupal\gdpr_tasks\TaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form controller for Task edit forms.
 *
 * @ingroup gdpr_tasks
 */
class TaskActionsForm extends ContentEntityForm {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $eventDispatcher;

  /**
   * The GDPR Task anonymizer.
   *
   * @var \Drupal\gdpr_tasks\Anonymizer
   */
  protected $anonymizer;

  /**
   * The GDPR Task manager.
   *
   * @var \Drupal\gdpr_tasks\TaskManager
   */
  protected $taskManager;

  /**
   * The GDPR Task queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs a TaskActionsForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\gdpr_tasks\Anonymizer $anonymizer
   *   The GDPR Task anonymizer.
   * @param \Drupal\gdpr_tasks\TaskManager $task_manager
   *   The GDPR Task manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory service.
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EventDispatcherInterface $event_dispatcher, Anonymizer $anonymizer, TaskManager $task_manager, QueueFactory $queue) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    $this->eventDispatcher = $event_dispatcher;
    $this->anonymizer = $anonymizer;
    $this->taskManager = $task_manager;
    $this->queue = $queue->get('gdpr_tasks_process_gdpr_sar');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $container->get('gdpr_tasks.anonymizer'),
      $container->get('gdpr_tasks.manager'),
      $container->get('queue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;

    if (in_array($entity->getStatus(), ['processed', 'closed'])) {
      $form['manual_data']['#access'] = FALSE;
      $form['field_sar_export']['#access'] = FALSE;
      $form['sar_export_assets']['#access'] = FALSE;
      $form['sar_export_parts']['#access'] = FALSE;
      $form['actions']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * Performs the removal request.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   Errors array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  private function doRemoval(FormStateInterface $form_state) {
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;
    $email = $entity->getOwner()->getEmail();
    $errors = $this->anonymizer->run($entity);

    if (\count($errors) === 0) {
      $this->eventDispatcher->dispatch(RightToBeForgottenCompleteEvent::EVENT_NAME, new RightToBeForgottenCompleteEvent($email));
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if (isset($actions['delete'])) {
      unset($actions['delete']);
    }

    if (isset($actions['submit'])) {
      if ($this->entity->bundle() == 'gdpr_remove') {
        $actions['submit']['#value'] = 'Remove and Anonymize Data';
        $actions['submit']['#name'] = 'remove';
      }
      else {
        $actions['submit']['#value'] = 'Process';
        $actions['submit']['#name'] = 'export';
      }
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\gdpr_tasks\Entity\TaskInterface */
    $entity = $this->entity;

    if ($entity->bundle() == 'gdpr_remove') {
      $errors = $this->doRemoval($form_state);
      // Removals may have generated errors.
      // If this happens, combine the error messages and display them.
      if (\count($errors) > 0) {
        $should_save = FALSE;
        \array_map(function ($error) {
          $this->messenger()->addError($error);
        }, $errors);
        $form_state->setRebuild();
      }
      else {
        $should_save = TRUE;
        $status = 'closed';
      }
    }
    else {
      // Queue task for completion.
      $this->queue->createQueue();
      $this->queue->createItem($entity->id());
      $should_save = TRUE;
      $status = 'processed';
    }

    if ($should_save) {
      $entity->status = $status;
      $entity->setProcessedById($this->currentUser()->id());
      $this->messenger()->addStatus('Task has been processed.');
      parent::save($form, $form_state);
    }
  }

}
