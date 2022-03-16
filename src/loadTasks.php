<?php

namespace DagLab\RoboBackups;

use DagLab\RoboBackups\Task\EnsureCli;

trait loadTasks {

  protected function taskEnsureCli(CliAdapterInterface $cli_adapter) {
    return $this->task(EnsureCli::class, $cli_adapter);
  }

}