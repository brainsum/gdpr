<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;

/**
 * Class LongTextSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gpdr_long_text_sanitizer",
 *   label = @Translation("Long text sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be used for longer text.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class LongTextSanitizer extends GdprSanitizerBase {

  const MAX_PARAGRAPHS_COUNT = 5;

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function sanitize($input) {
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

    $paragraphCount = \str_word_count($input) % self::MAX_PARAGRAPHS_COUNT;
    return $this->random->paragraphs($paragraphCount);
  }

  /**
   * Generate a random string via a remote service.
   *
   * @param string $input
   *   The name.
   *
   * @return string
   *   A random string or the original input (in case of an error).
   *
   * @see https://loripsum.net/
   *
   * @throws \RuntimeException
   */
  protected function remoteGenerator($input) {
    /** @var \GuzzleHttp\Client $httpClient */
    $httpClient = \Drupal::httpClient();

    $result = NULL;
    try {
      $result = $httpClient->get('https://loripsum.net/api/3/medium/plaintext');
    }
    catch (\Exception $e) {
      // @todo: Log?
      return $input;
    }

    if (NULL !== $result && 200 === $result->getStatusCode()) {
      $data = $result->getBody()->getContents();
      if (NULL === $data) {
        return $input;
      }

      return substr(\trim($data), 0, \strlen($input));
    }

    return $input;
  }

}
