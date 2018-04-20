<?php

namespace Drupal\gdpr_view_export_log\Plugin\views\display_extender;

use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_view_export_log\Entity\ExportAudit;
use Drupal\views\Plugin\views\display_extender\DisplayExtenderPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Head metadata display extender plugin.
 *
 * @ingroup views_display_extender_plugins
 *
 * @ViewsDisplayExtender(
 *   id = "gdpr_view_export_logging",
 *   title = @Translation("GDPR Logging for Views"),
 *   no_ui = FALSE
 * )
 */
class GdprExportLogDisplayExtender extends DisplayExtenderPluginBase {

  /**
   * Checks whether this is a data export.
   *
   * @return bool
   *   Whether this view is a data export.
   */
  private function isExportView() {
    return get_class($this->displayHandler) == ExportAudit::exportDisplayHandler();
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptionsAlter(&$options) {
    $options['gdpr_log'] = [
      'default' => FALSE,
      'contains' => [
        'log_exports' => ['default' => 0],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    if ($this->isExportView()) {
      $categories['gdpr_log'] = [
        'title' => 'GDPR',
        'column' => 'second',
      ];

      $options['gdpr_log'] = [
        'category' => 'gdpr_log',
        'title' => 'Log Exports',
        'value' => $this->loggingEnabled() ? 'Yes' : 'No',
      ];
    }
  }

  /**
   * Whether logging is enabled for this view.
   *
   * @return bool
   *   Whether logging is enabled for this view.
   */
  public function loggingEnabled() {
    return array_key_exists('gdpr_log', $this->options) && $this->options['gdpr_log']['log_exports'] == TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') == 'gdpr_log') {
      $form['#title'] .= 'The GDPR Log settings';

      $form['gdpr_log']['#type'] = 'container';
      $form['gdpr_log']['#tree'] = TRUE;
      $form['gdpr_log']['log_exports'] = [
        '#title' => $this->t('Log Exports'),
        '#description' => $this->t('Whether to log exports of this view'),
        '#type' => 'checkbox',
        '#default_value' => $this->loggingEnabled(),
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('section') == 'gdpr_log') {
      $this->options['gdpr_log'] = $form_state->getValue('gdpr_log');
    }
  }

  /**
   * Whether logging is enabled for a particular view.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to check.
   *
   * @return bool
   *   Whether logging is enabled.
   */
  public static function isLoggingEnabled(ViewExecutable $view) {
    if (get_class($view->display_handler) == ExportAudit::exportDisplayHandler()) {
      $extenders = $view->getDisplay()->getExtenders();
      if (array_key_exists('gdpr_view_export_logging', $extenders)) {
        return $extenders['gdpr_view_export_logging']->options['gdpr_log']['log_exports'] == 1;
      }
    }

    return FALSE;
  }

}
