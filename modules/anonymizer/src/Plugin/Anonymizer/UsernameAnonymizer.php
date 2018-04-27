<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UsernameAnonymizer.
 *
 * @Anonymizer(
 *   id = "username_anonymizer",
 *   label = @Translation("Username anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for user names.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class UsernameAnonymizer extends AnonymizerBase {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client')
    );
  }

  /**
   * PasswordAnonymizer constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \GuzzleHttp\Client $httpClient
   *   The http client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Client $httpClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $httpClient;
    $this->random = new Random();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
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
    $result = NULL;
    try {
      $result = $this->httpClient->get('https://randomuser.me/api/?format=pretty&results=1&inc=name&noinfo&nat=us,gb');
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
