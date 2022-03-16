<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
  use \DagLab\RoboBackups\loadTasks;
  use \Kerasai\Robo\Config\ConfigHelperTrait;

  /**
   * @var \DagLab\RoboBackups\CliAdapter
   */
  protected $cli;

  public function __construct() {
    $this->cli = new \DagLab\RoboBackups\CliAdapter(
      $this->getConfigVal('cli.executable'),
      $this->getConfigVal('cli.package'),
      $this->getConfigVal('cli.version')
    );

    $this->taskEnsureCli()->run($this->cli);
  }

}