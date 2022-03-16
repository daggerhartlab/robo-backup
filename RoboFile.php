<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
  protected $cli;

  public function __construct() {
    $this->cli = new \DagLab\RoboBackups\CliWp();
  }

  public function ensureCli() {
    $result = $this->taskExecStack()
      ->stopOnFail(false)
      ->exec("which {$this->cli->command()}")
      ->run();

    if ($result->getExitCode()) {
      $this->taskExecStack()
        ->stopOnFail()
        ->exec("composer global require {$this->cli->package()}:{$this->cli->version()}")
        ->run();
    }
  }

}