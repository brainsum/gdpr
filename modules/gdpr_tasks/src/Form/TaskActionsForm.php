<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_tasks\Anonymizer;
use Drupal\gdpr_tasks\Event\RightToAccessCompleteEvent;
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
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, EventDispatcherInterface $event_dispatcher, Anonymizer $anonymizer, TaskManager $task_manager) {
    parent::__construct($entity_manager, $entity_type_bundle_info, $time);
    $this->eventDispatcher = $event_dispatcher;
    $this->anonymizer = $anonymizer;
    $this->taskManager = $task_manager;
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
      $container->get('gdpr_tasks.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;

    if ($entity->status->value == 'closed') {
      $form['manual_data']['widget']['#disabled'] = TRUE;
      $form['actions']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * Performs the SAR export.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function doSarExport(FormStateInterface $form_state) {
    $entity = $this->entity;
    $manual = $form_state->getValue(['manual_data', 0, 'value']);

    $data = gdpr_tasks_generate_sar_report($entity->getOwner());

    $inc = [];
    foreach ($data as $key => $values) {
      $rta = $values['gdpr_rta'];
      unset($values['gdpr_rta']);
      if ($rta == 'inc') {
        $inc[$key] = $values;
      }
    }

    $file_name = $entity->sar_export->entity->getFilename();
    $file_uri = $entity->sar_export->entity->getFileUri();
    $dirname = str_replace($file_name, '', $file_uri);

    /* @var \Drupal\gdpr_tasks\TaskManager $task_manager */
    $destination = $this->taskManager->toCsv($inc, $dirname);
    $export = \file_get_contents($destination);

    $export .= $manual;

    // @todo Add headers to csv export.
    file_save_data($export, $file_uri, FILE_EXISTS_REPLACE);

    $this->eventDispatcher->dispatch(RightToAccessCompleteEvent::EVENT_NAME, new RightToAccessCompleteEvent($entity->getOwner(), $file_uri));
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
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;

    if ($entity->bundle() == 'gdpr_remove') {
      $errors = $this->doRemoval($form_state);
      // Removals may have generated errors.
      // If this happens, combine the error messages and display them.
      if (\count($errors) > 0) {
        $should_save = FALSE;
        $this->messenger()->addError(implode(' ', $errors));
        $form_state->setRebuild();
      }
      else {
        $should_save = TRUE;
      }
    }
    else {
      $this->doSarExport($form_state);
      $should_save = TRUE;
    }

    if ($should_save) {
      $entity->status = 'closed';
      $entity->setProcessedById($this->currentUser()->id());
      $this->messenger()->addStatus('Task has been processed.');
      parent::save($form, $form_state);
    }
  }

}
