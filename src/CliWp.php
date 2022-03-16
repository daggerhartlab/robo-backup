<?php

namespace DagLab\RoboBackups;

/**
 * Class CliWp
 *
 * @package DagLab\RoboBackups
 */
class CliWp implements CliAdapterInterface
{
  /**
   * @inheritDoc
   */
  public function command() {
    return 'wp';
  }

  /**
   * @inheritDoc
   */
  public function package() {
    return 'wp-cli/wp-cli-bundle';
  }

  /**
   * @inheritDoc
   */
  public function version() {
    return '*';
  }

}