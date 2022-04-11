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
  protected $restoreDbCommand;

  /**
   * CliAdapter constructor.
   *
   * @param string $executable
   * @param string $package
   * @param string $version
   * @param string $backupDbCommand
   * @param string|null $restoreDbCommand
   */
  public function __construct(string $executable, string $package, string $version, string $backupDbCommand, string $restoreDbCommand = NULL) {
    $this->executable = $executable;
    $this->package = $package;
    $this->version = $version;
    $this->backupDbCommand = $backupDbCommand;
    $this->restoreDbCommand = $restoreDbCommand;
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

  /**
   * @inheritDoc
   */
  public function restoreDbCommand(string $app_root, string $target_file) {
    return strtr($this->restoreDbCommand, [
      '[app_root]' => $app_root,
      '[target_file]' => $target_file,
    ]);
  }

}