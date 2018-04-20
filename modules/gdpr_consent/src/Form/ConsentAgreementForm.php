<?php

namespace Drupal\gdpr_consent\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_consent\Entity\ConsentAgreement;

/**
 * Form controller for Consent Agreement edit forms.
 *
 * @ingroup gdpr_consent
 */
class ConsentAgreementForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\gdpr_consent\Entity\ConsentAgreement */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

//    $form['title'] = [
//      '#type' => 'textfield',
//      '#required' => TRUE,
//      '#title' => 'Title',
//      '#default_value' => $entity->title->value,
//    ];

//    $form['mode'] = [
//      '#type' => 'select',
//      '#title' => 'Agreement Type',
//      '#required' => TRUE,
//      '#options' => ConsentAgreement::getModes(),
//      '#default_value' => $entity->mode->value,
//      '#description' => 'Set to "Explicit" if the user needs to explicitly agree, otherwise "Implicit"',
//    ];

//    $form['description'] = [
//      '#type' => 'textfield',
//      '#title' => 'Description',
//      '#required' => TRUE,
//      '#description' => 'Text displayed to the user on the form',
//      '#default_value' => $entity->description->value,
//    ];
//
//    $form['long_description'] = [
//      '#type' => 'textarea',
//      '#title' => 'Long Description',
//      '#description' => 'Text shown when the user clicks for more details',
//      '#default_value' => $entity->long_description->value,
//    ];

    if (!$this->entity->isNew()) {
      $form['new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => TRUE,
        '#weight' => 10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('new_revision') && $form_state->getValue('new_revision') != FALSE) {
      $entity->setNewRevision();

      // If a new revision is created, save the current user as revision author.
      $entity->setRevisionCreationTime(REQUEST_TIME);
      $entity->setRevisionUserId(\Drupal::currentUser()->id());
    }
    else {
      $entity->setNewRevision(FALSE);
    }

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Consent Agreement.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Consent Agreement.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.gdpr_consent_agreement.canonical', ['gdpr_consent_agreement' => $entity->id()]);
  }

  protected function showRevisionUi() {
    return FALSE;
  }
}
