<?php

namespace Drupal\anonymizer\Service;

use Faker\Factory;

/**
 * Class FakerService.
 *
 * @package Drupal\anonymizer\Service
 */
class FakerService implements FakerServiceInterface {

  /**
   * The faker generator.
   *
   * @var \Faker\Generator
   */
  protected $generator;

  /**
   * FakerService constructor.
   */
  public function __construct() {
    $this->generator = Factory::create();
  }

  /**
   * Return the generator.
   *
   * @return \Faker\Generator
   *   The generator.
   */
  public function generator() {
    return $this->generator;
  }

}
