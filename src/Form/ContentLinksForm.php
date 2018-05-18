<?php

namespace Drupal\gdpr\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ContentLinksForm.
 *
 * @see \Drupal\link\Plugin\Field\FieldType\LinkItem
 * @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget
 *
 * @package Drupal\gdpr\Form
 */
class ContentLinksForm extends ConfigFormBase {

  const GDPR_CONTENT_CONF_KEY = 'gdpr.content_mapping';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * ContentLinksForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    LanguageManagerInterface $languageManager
  ) {
    parent::__construct($configFactory);
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::GDPR_CONTENT_CONF_KEY,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_content_links_form';
  }

  /**
   * Return an array of the required content labels keyed by machine name.
   *
   * @return array
   *   The required content.
   */
  public static function requiredContentList() {
    return [
      'privacy_policy' => t('Privacy policy'),
      'terms_of_use' => t('Terms of use'),
      'about_us' => t('About us'),
      'impressum' => t('Impressum'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['description'] = [
      '#markup' => $this->t('Enter internal paths e.g <strong>@internal_path</strong> or full URLs e.g <strong>@full_url</strong>', [
        '@internal_path' => '/privacy-policy',
        '@full_url' => 'https://www.example.com/termsofservice.pdf',
      ]),
    ];

    $form['links'] = [
      '#type' => 'container',
    ];

    $urls = $this->loadUrls();

    /** @var \Drupal\Core\Language\LanguageInterface $language */
    foreach ($this->languageManager->getLanguages() as $langCode => $language) {
      $form['links'][$langCode] = [
        '#type' => 'details',
        '#title' => $language->getName(),
      ];

      foreach (static::requiredContentList() as $key => $label) {
        $form['links'][$langCode][$key] = [
          '#type' => 'textfield',
          '#title' => $label,
          '#process_default_value' => FALSE,
          '#element_validate' => [[static::class, 'validateUriElement']],
          '#default_value' => isset($urls[$langCode][$key]) ? $urls[$langCode][$key] : NULL,
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasValue('links')) {
      /** @var array $links */
      $links = $form_state->getValue('links', []);
      $config = $this->configFactory->getEditable(static::GDPR_CONTENT_CONF_KEY);
      $config
        ->set('links', $links)
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Load the stored URLs as displayable strings.
   *
   * @return array
   *   The loaded URLs.
   */
  protected function loadUrls() {
    $config = $this->config(static::GDPR_CONTENT_CONF_KEY)->get('links');
    if (NULL === $config || !\is_array($config)) {
      $config = [];
    }

    foreach ($config as $langCode => $links) {
      foreach ($links as $key => $link) {
        $config[$langCode][$key] = static::getUriAsDisplayableString($link);
      }
    }

    return $config;
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The displayable URI.
   *
   * @see static::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = \parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayableString = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uriReference = \explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = \parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uriReference = '<front>' . \substr($uriReference, 1);
      }

      $displayableString = $uriReference;
    }

    return $displayableString;
  }

  /**
   * Form element validation handler for the 'uri' element.
   *
   * Disallows saving inaccessible or untrusted URLs.
   */
  public static function validateUriElement($element, FormStateInterface $form_state, $form) {
    $uri = static::getUserEnteredStringAsUri($element['#value']);
    $form_state->setValueForElement($element, $uri);

    // If getUserEnteredStringAsUri() mapped the entered value to a 'internal:'
    // URI , ensure the raw value begins with '/', '?' or '#'.
    // @todo '<front>' is valid input for BC reasons, may be removed by
    //   https://www.drupal.org/node/2421941
    if (
      \parse_url($uri, PHP_URL_SCHEME) === 'internal'
      && 0 !== strpos($element['#value'], '<front>')
      && !\in_array($element['#value'][0], ['/', '?', '#'], TRUE)
    ) {
      $form_state->setError($element, t('Manually entered paths should start with /, ? or #.'));
      return;
    }
  }

  /**
   * Gets the user-entered string as a URI.
   *
   * The following two forms of input are mapped to URIs:
   * - entity autocomplete ("label (entity id)") strings: to 'entity:' URIs;
   * - strings without a detectable scheme: to 'internal:' URIs.
   *
   * This method is the inverse of ::getUriAsDisplayableString().
   *
   * @param string $string
   *   The user-entered string.
   *
   * @return string
   *   The URI, if a non-empty $uri was passed.
   *
   * @see static::getUriAsDisplayableString()
   */
  protected static function getUserEnteredStringAsUri($string) {
    // By default, assume the entered string is an URI.
    $uri = $string;

    if (!empty($string) && \parse_url($string, PHP_URL_SCHEME) === NULL) {
      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      // - '<front>' -> '/'
      // - '<front>#foo' -> '/#foo'
      if (\strpos($string, '<front>') === 0) {
        $string = '/' . \substr($string, \strlen('<front>'));
      }
      $uri = 'internal:' . $string;
    }

    return $uri;
  }

}
