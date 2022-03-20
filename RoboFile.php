<?php

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
  use \Kerasai\Robo\Config\ConfigHelperTrait;

  /**
   * @var \DagLab\RoboBackups\CliAdapter
   */
  protected $cli;

  /**
   * @var string
   */
  protected $date;

  public function __construct() {
    $this->cli = new \DagLab\RoboBackups\CliAdapter(
      $this->getConfigVal('cli.executable'),
      $this->getConfigVal('cli.package'),
      $this->getConfigVal('cli.version')
    );
    $this->date = date('Y-m-d');
    $this->stopOnFail();
  }

  public function backupDatabase() {
    $this->ensureCli();

  }

  /**
   * Backup non-code site files and send to S3.
   *
   * @throws \League\Flysystem\FilesystemException
   */
  public function backupFiles() {
    $filename = "{$this->getConfigVal('backups.prefix')}-{$this->date}-files.zip";
    $file = "{$this->getConfigVal('backups.destination')}/{$filename}";
    $this->taskPack($file)
      ->add($this->getConfigVal('backups.files_root'))
      ->run();

    $this->sendToS3($file, $filename);
  }

  /**
   * Backup the code and send to S3.
   */
  public function backupCode() {
    $filename = "{$this->getConfigVal('backups.prefix')}-{$this->date}-code.zip";
    $file = "{$this->getConfigVal('backups.destination')}/{$filename}";
    $this->taskPack($file)
      ->add($this->getConfigVal('backups.code_root'))
      ->exclude($this->getConfigVal('backups.files_root'))
      ->run();

    $this->sendToS3($file, $filename);
  }

  /**
   * @throws \Robo\Exception\TaskException
   */
  protected function ensureCli() {
    $result = $this->taskExecStack()
      ->stopOnFail(false)
      ->exec("which {$this->cli->executable()}")
      ->run();

    if ($result->getExitCode()) {
      $this->taskExecStack()
        ->stopOnFail(true)
        ->exec("composer global require {$this->cli->package()}:{$this->cli->version()}")
        ->run();
    }
  }

  /**
   * Send file to S3.
   *
   * @param string $source
   * @param string $destination
   *
   * @throws \League\Flysystem\FilesystemException
   */
  protected function sendToS3(string $source, string $destination) {
    $client = new S3Client([
      'credentials' => [
        'key'    => $this->getConfigVal('aws.key'),
        'secret' => $this->getConfigVal('aws.secret'),
      ],
      'region' => $this->getConfigVal('aws.region'),
      'version' => $this->getConfigVal('aws.version'),
    ]);
    $client->putObject([
      'Bucket' => $this->getConfigVal('aws.bucket'),
      'SourceFile' => $source,
      'Key' => $destination,
    ]);
  }

}
