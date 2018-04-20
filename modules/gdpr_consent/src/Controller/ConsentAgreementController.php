<?php

namespace Drupal\gdpr_consent\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\gdpr_consent\Entity\ConsentAgreement;
use Drupal\gdpr_consent\Entity\ConsentAgreementInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConsentAgreementController.
 *
 *  Returns responses for Consent Agreement routes.
 */
class ConsentAgreementController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity field manager for metadata.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  //private $entityTypeManager;

  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->entityFieldManager = $entity_field_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * Displays a Consent Agreement  revision.
   *
   * @param int $consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($gdpr_consent_agreement_revision) {
    $gdpr_consent_agreement = $this->entityManager()
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    $view_builder = $this->entityManager()
      ->getViewBuilder('gdpr_consent_agreement');

    return $view_builder->view($gdpr_consent_agreement);
  }

  /**
   * Page title callback for a Consent Agreement  revision.
   *
   * @param int $gdpr_consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($gdpr_consent_agreement_revision) {
    $gdpr_consent_agreement = $this->entityManager()
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $gdpr_consent_agreement->label(),
      '%date' => format_date($gdpr_consent_agreement->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Consent Agreement .
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreement $agreement
   *   A Consent Agreement  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview($gdpr_consent_agreement) {
    $agreement = ConsentAgreement::load($gdpr_consent_agreement);
    $account = $this->currentUser();
    $storage = $this->entityManager()->getStorage('gdpr_consent_agreement');

    $build['#title'] = $this->t('Revisions for %title', ['%title' => $agreement->title->value]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = $account->hasPermission('create gdpr agreements');
    $delete_permission = $account->hasPermission('create gdpr agreements');

    $rows = [];

    $vids = $storage->revisionIds($agreement);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\gdpr_consent\Entity\ConsentAgreement $revision */
      $revision = $storage->loadRevision($vid);

      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];

      // Use revision link to link to revisions that are not active.
      $date = \Drupal::service('date.formatter')
        ->format($revision->getRevisionCreationTime(), 'short');
      if ($vid != $agreement->getRevisionId()) {
        $link = $this->l($date, new Url('entity.gdpr_consent_agreement.revision', [
          'gdpr_consent_agreement' => $agreement->id(),
          'gdpr_consent_agreement_revision' => $vid,
        ]));
      }
      else {
        $link = $agreement->link($date);
      }

      $row = [];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
          '#context' => [
            'date' => $link,
            'username' => \Drupal::service('renderer')->renderPlain($username),
            'message' => [
              '#markup' => $revision->getRevisionLogMessage(),
              '#allowed_tags' => Xss::getHtmlTagList(),
            ],
          ],
        ],
      ];
      $row[] = $column;

      if ($latest_revision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];
        foreach ($row as &$current) {
          $current['class'] = ['revision-current'];
        }
        $latest_revision = FALSE;
      }
      else {
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $this->t('Revert'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_revert', [
              'gdpr_consent_agreement' => $agreement->id(),
              'gdpr_consent_agreement_revision' => $vid,
            ]),
          ];
        }

        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_delete', [
              'gdpr_consent_agreement' => $agreement->id(),
              'gdpr_consent_agreement_revision' => $vid,
            ]),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $build['gdpr_consent_agreement_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

  function myAgreements($user) {
    $map = $this->entityFieldManager->getFieldMapByFieldType('gdpr_user_consent');
    $agreement_storage = $this->entityTypeManager()->getStorage('gdpr_consent_agreement');
    $rows = [];

    foreach ($map as $entity_type => $fields) {
      $field_names = array_keys($fields);

      foreach ($field_names as $field_name) {

        $ids = \Drupal::entityQuery($entity_type)
          ->condition($field_name . '.user_id', $user)
          ->execute();

        $entities = $this->entityTypeManager()->getStorage($entity_type)
          ->loadMultiple($ids);

        foreach ($entities as $entity) {
          $agreement = $agreement_storage->loadRevision($entity->{$field_name}->target_revision_id);

          $row = [];

          $row[] = [
            'data' => [
              '#markup' => $agreement->toLink($agreement->title->value, 'revision')->toString()
            ],
          ];

          $row[] = [
            'data' => [
              '#markup' => $entity->{$field_name}->date
            ],
          ];

          $rows[] = $row;
        }
      }
    }


    $header = ['Agreement', 'Date Agreed'];

    $build = [
      '#title' => 'Consent Agreements',
      'table' => [
        '#theme' => 'table',
        '#rows' => $rows,
        '#header' => $header,
      ],
    ];
    return $build;
  }

}
