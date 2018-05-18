<?php

/**
 * @file
 * Contains gdpr_task.page.inc.
 *
 * Page callback for Task entities.
 */

use Drupal\Core\Render\Element;
use Drupal\gdpr_fields\Controller\GDPRController;

/**
 * Prepares variables for Task templates.
 *
 * Default template: gdpr_task.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityMalformedException
 */
function template_preprocess_gdpr_task(array &$variables) {
  // Fetch Task Entity Object.
  /* @var \Drupal\gdpr_tasks\Entity\TaskInterface $gdpr_task */
  $gdpr_task = $variables['elements']['#gdpr_task'];

  $bundle_storage = \Drupal::entityTypeManager()->getStorage($gdpr_task->getEntityType()->getBundleEntityType());
  $variables['type'] = $bundle_storage->load($gdpr_task->bundle())->label();;

  /* @var \Drupal\gdpr_fields\Controller\GDPRController $controller */
  $controller = \Drupal::classResolver()->getInstanceFromDefinition(GDPRController::class);

  /* @var \Drupal\gdpr_tasks\Entity\TaskInterface $entity */
  switch ($gdpr_task->bundle()) {
    case 'gdpr_remove':
      $rows = $controller->rtfData($gdpr_task->getOwner());

      $variables['data'] = [
        '#type' => 'table',
        '#header' => [
          t('Name'),
          t('Type'),
          t('Entity'),
          t('Bundle'),
          t('Notes'),
          t('Right to be forgotten'),
          '',
        ],
        '#rows' => $rows,
        '#sticky' => TRUE,
        '#empty' => t('There are no GDPR fields.'),
      ];

      $variables['actions'] = \Drupal::service('entity.form_builder')->getForm($gdpr_task, 'process');
      break;

    case 'gdpr_sar':
      $rows = $controller->rtaData($gdpr_task->getOwner());

      $variables['data'] = [
        '#type' => 'table',
        '#header' => [
          t('Name'),
          t('Type'),
          t('Entity'),
          t('Bundle'),
          t('Notes'),
          t('Right to access'),
        ],
        '#rows' => $rows,
        '#sticky' => TRUE,
        '#empty' => t('There are no GDPR fields.'),
      ];
      $variables['actions'] = \Drupal::service('entity.form_builder')->getForm($gdpr_task, 'process');
      break;
  }

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
