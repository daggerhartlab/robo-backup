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
  protected $backupDbCommand;

  /**
   * CliAdapter constructor.
   *
   * @param string $executable
   * @param string $package
   * @param string $version
   * @param string $backupDbCommand
   */
  public function __construct(string $executable, string $package, string $version, string $backupDbCommand) {
    $this->executable = $executable;
    $this->package = $package;
    $this->version = $version;
    $this->backupDbCommand = $backupDbCommand;
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

  /**
   * @inheritDoc
   */
  public function backupDbCommand(string $app_root, string $destination) {
    return strtr($this->backupDbCommand, [
      '[app_root]' => $app_root,
      '[destination]' => $destination,
    ]);
  }

}