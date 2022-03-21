<?php

namespace DagLab\RoboBackups;

interface CliAdapterInterface
{

  /**
   * @return string
   *   Cli command.
   */
  public function executable();

  /**
   * @return string
   *   Composer package name.
   */
  public function package();

  /**
   * @return string
   *   Package version.
   */
  public function version();

  /**
   * @param string $app_root
   *   Location of the web app/website.
   * @param string $destination
   *   Backup destination folder.
   *
   * @return string
   */
  public function backupDbCommand(string $app_root, string $destination);

}
