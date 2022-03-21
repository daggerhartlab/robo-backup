<?php

use Aws\S3\S3Client;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
  use \Kerasai\Robo\Config\ConfigHelperTrait;

  /**
   * Configurable cli command.
   *
   * @var \DagLab\RoboBackups\CliAdapter
   */
  protected $cli;

  /**
   * Datestamp on the backups.
   *
   * @var string
   */
  protected $date;

  /**
   * Regex to exclude from archives.
   *
   * @link https://robo.li/tasks/Archive/
   * @var string[]
   */
  protected $archiveExclude = [
    '.*.zip',
    '.*.tar',
    '.*.tgz',
    '.*.tar.gz',
    '.*.wpress',
    '.*/node_modules/.*',
  ];

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    $this->cli = new \DagLab\RoboBackups\CliAdapter(
      $this->getConfigVal('cli.executable'),
      $this->getConfigVal('cli.package'),
      $this->getConfigVal('cli.version'),
      $this->getConfigVal('cli.backup_db_command')
    );
    $this->date = date('Y-m-d');
    $this->stopOnFail();
  }

  /**
   * Backup database and send to S3.
   *
   * @throws \Robo\Exception\TaskException
   */
  public function backupDatabase() {
    $this->ensureCli();

    $filename = "{$this->getConfigVal('backups.prefix')}-{$this->date}-db.sql";
    $file = "{$this->getConfigVal('backups.destination')}/{$filename}";

    $this->ensureDir($this->getConfigVal('backups.destination'));
    $this->taskExecStack()
      ->exec("{$this->cli->executable()} {$this->cli->backupDbCommand($this->getConfigVal('backups.code_root'), $file)}")
      ->run();
    $this->taskPack("{$file}.zip")
      ->addFile($filename, $file)
      ->run();

    $this->sendToS3("{$file}.zip", "{$filename}.zip");
  }

  /**
   * Backup non-code site files and send to S3.
   */
  public function backupFiles() {
    $filename = "{$this->getConfigVal('backups.prefix')}-{$this->date}-files.zip";
    $file = "{$this->getConfigVal('backups.destination')}/{$filename}";

    $this->ensureDir($this->getConfigVal('backups.destination'));
    $this->taskPack($file)
      ->addDir('files', $this->getConfigVal('backups.files_root'))
      ->exclude($this->archiveExclude)
      ->run();

    $this->sendToS3($file, $filename);
  }

  /**
   * Backup the code and send to S3.
   */
  public function backupCode() {
    $filename = "{$this->getConfigVal('backups.prefix')}-{$this->date}-code.zip";
    $file = "{$this->getConfigVal('backups.destination')}/{$filename}";

    // Exclude files from code backup.
    $this->archiveExclude[] = rtrim($this->getConfigVal('backups.files_root'), '/') . '/*';
    $this->ensureDir($this->getConfigVal('backups.destination'));
    $this->taskPack($file)
      ->addDir('code', $this->getConfigVal('backups.code_root'))
      ->exclude($this->archiveExclude)
      ->run();

    $this->sendToS3($file, $filename);
  }

  /**
   * Install the cli if it doesn't exist.
   *
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
   * Ensure a directory exists.
   */
  protected function ensureDir(string $filepath) {
    $this->taskFilesystemStack()
      ->mkdir($filepath)
      ->run();
  }

  /**
   * Send file to S3.
   *
   * @param string $source
   *   Local absolute filepath.
   * @param string $destination
   *   Name of file in S3.
   */
  protected function sendToS3(string $source, string $destination) {
    // https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-CreateClient.php.html
    $client = new S3Client([
      'credentials' => [
        'key'    => $this->getConfigVal('aws.key'),
        'secret' => $this->getConfigVal('aws.secret'),
      ],
      'region' => $this->getConfigVal('aws.region'),
      'version' => $this->getConfigVal('aws.version'),
    ]);
    // https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-PutObject.php.html
    $client->putObject([
      'Bucket' => $this->getConfigVal('aws.bucket'),
      'SourceFile' => $source,
      'Key' => $destination,
    ]);
  }

}
