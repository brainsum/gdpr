<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UsernameSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_username_sanitizer",
 *   label = @Translation("Username sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be used for usernames.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class UsernameSanitizer extends GdprSanitizerBase {

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    $output = $this->remoteGenerator($input);

    // If the old and new names are the same, we likely encountered an error.
    // Fallback to local generation.
    if ($output === $input) {
      $output = $this->localGenerator($input);
    }

    return $output;
  }

  /**
   * Locally generate a random string of the same length as the input.
   *
   * @param string $input
   *   The name.
   *
   * @return string
   *   The random string.
   */
  protected function localGenerator($input) {
    if (NULL === $this->random) {
      $this->random = new Random();
    }

    return $this->random->word(\strlen($input));
  }

  /**
   * Generate a name via a remote service.
   *
   * @param string $input
   *   The name.
   *
   * @return string
   *   A random username or the original input (in case of an error).
   *
   * @see https://randomuser.me/
   *
   * @throws \RuntimeException
   */
  protected function remoteGenerator($input) {
    /** @var \GuzzleHttp\Client $httpClient */
    $httpClient = \Drupal::httpClient();

    $result = NULL;
    try {
      $result = $httpClient->get('https://randomuser.me/api/?format=pretty&results=1&inc=name&noinfo&nat=us,gb');
    }
    catch (\Exception $e) {
      // @todo: Log?
      return $input;
    }

    if (NULL !== $result && 200 === $result->getStatusCode()) {
      $data = $result->getBody()->getContents();
      $data = \json_decode($data, TRUE);

      $name = \reset($data['results'])['name'];
      return $name['first'] . '.' . $name['last'];
    }

    return $input;
  }

}
