<?php

namespace DagLab\RoboBackups;

/**
 * Class CliWp
 *
 * @package DagLab\RoboBackups
 */
class CliAdapter implements CliAdapterInterface
{
  protected $executable;
  protected $package;
  protected $version;

  /**
   * CliAdapter constructor.
   *
   * @param string $executable
   * @param string $package
   * @param string $version
   */
  public function __construct(string $executable, string $package, string $version) {
    $this->executable = $executable;
    $this->package = $package;
    $this->version = $version;
  }

  /**
   * @inheritDoc
   */
  public function executable() {
    return $this->executable;
  }

  /**
   * @inheritDoc
   */
  public function package() {
    return $this->package;
  }

  /**
   * @inheritDoc
   */
  public function version() {
    return $this->version;
  }

}