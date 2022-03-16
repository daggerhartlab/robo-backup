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

}
