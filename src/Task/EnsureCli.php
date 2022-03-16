<?php

namespace DagLab\RoboBackups\Task;

use DagLab\RoboBackups\CliAdapterInterface;
use Robo\Task\CommandStack;

class EnsureCli extends CommandStack {

  protected $cli;

  public function __construct(CliAdapterInterface  $cli_adapter) {
    $this->cli = $cli_adapter;
  }

  public function run() {
//    $result = $this->exec()
//      ->stopOnFail(false)
//      ->exec("which {$this->cli->command()}")
//      ->run();
//
//    if ($result->getExitCode()) {
//      $this->taskExecStack()
//        ->stopOnFail()
//        ->exec("composer global require {$this->cli->package()}:{$this->cli->version()}")
//        ->run();
//    }
  }

}