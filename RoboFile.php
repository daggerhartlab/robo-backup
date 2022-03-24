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
   * Version number.
   */
  const VERSION = '1.0.0';

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
    '.*\/node_modules\/.*',
    '.*\/.git\/.*',
  ];

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    $this->cli = new \DagLab\RoboBackups\CliAdapter(
      $this->requireConfigVal('cli.executable'),
      $this->requireConfigVal('cli.package'),
      $this->requireConfigVal('cli.version'),
      $this->requireConfigVal('cli.backup_db_command')
    );
    $this->date = date('Y-m-d');
  }

  /**
   * Show version number.
   */
  public function version() {
    $this->writeln(static::VERSION);
  }

  /**
   * Backup database and send to S3.
   *
   * @throws \Robo\Exception\TaskException
   */
  public function backupDatabase() {
    $this->ensureCli();

    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-db.sql";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";

    $this->ensureDir($this->requireConfigVal('backups.destination'));
    $this->taskExecStack()
      ->exec("{$this->cli->executable()} {$this->cli->backupDbCommand($this->requireConfigVal('backups.code_root'), $file)}")
      ->run();
    $this->taskPack("{$file}.zip")
      ->addFile($filename, $file)
      ->run();

    $this->sendToS3("{$file}.zip", "{$filename}.zip");
    $this->removeFile("{$file}.zip");
  }

  /**
   * Backup non-code site files and send to S3.
   */
  public function backupFiles() {
    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-files.zip";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";

    $this->ensureDir($this->requireConfigVal('backups.destination'));
    $this->taskPack($file)
      ->addDir('files', $this->requireConfigVal('backups.files_root'))
      ->exclude($this->archiveExclude)
      ->run();

    $this->sendToS3($file, $filename);
    $this->removeFile($file);
  }

  /**
   * Sync files to s3 bucket in case of large file systems.
   *
   * @link https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-envvars.html
   * @link https://awscli.amazonaws.com/v2/documentation/api/latest/reference/s3/sync.html
   */
  public function backupFilesSync() {
    $this->ensureAwsCli();
    $this->taskExecStack()
      ->stopOnFail()
      ->envVars([
        'AWS_ACCESS_KEY_ID' => $this->requireConfigVal('aws.key'),
        'AWS_SECRET_ACCESS_KEY' => $this->requireConfigVal('aws.secret'),
        'AWS_DEFAULT_REGION' => $this->requireConfigVal('aws.region'),
      ])
      ->exec("aws s3 sync {$this->requireConfigVal('backups.files_root')} s3://{$this->requireConfigVal('aws.bucket')}/files_sync")
      ->run();
  }

  /**
   * Backup the code and send to S3.
   */
  public function backupCode() {
    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-code.zip";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";

    $relative_files_root = str_replace(
      $this->requireConfigVal('backups.code_root'),
      '',
      $this->requireConfigVal('backups.files_root')
    );
    // Exclude files from code backup.
    $this->archiveExclude[] = str_replace(
      '/',
      '\/',
      trim($relative_files_root, '/')
    );
    $this->ensureDir($this->requireConfigVal('backups.destination'));
    $this->taskPack($file)
      ->addDir('code', $this->requireConfigVal('backups.code_root'))
      ->exclude($this->archiveExclude)
      ->run();

    $this->sendToS3($file, $filename);
    $this->removeFile($file);
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
   * Ensure the aws cli is installed.
   *
   * @link https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html
   *
   * @throws \Robo\Exception\TaskException
   */
  public function ensureAwsCli() {
    $result = $this->taskExecStack()
      ->stopOnFail(false)
      ->exec("which aws")
      ->run();

    if ($result->getExitCode()) {
      $this->taskExecStack()
        ->stopOnFail(true)
        ->exec('curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"')
        ->exec('unzip awscliv2.zip -o')
        ->exec('./aws/install')
        ->exec('rm -fr ./aws')
        ->run();
    }
  }

  /**
   * Ensure a directory exists.
   *
   * @param string $filepath
   */
  protected function ensureDir(string $filepath) {
    $this->taskFilesystemStack()
      ->mkdir($filepath)
      ->run();
  }

  /**
   * Remove given filename.
   *
   * @param string $filepath
   */
  protected function removeFile(string $filepath) {
    $this->taskFilesystemStack()
      ->remove($filepath)
      ->run();
  }

  /**
   * Send file to S3.
   *
   * @link https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-PutObject.php.html
   *
   * @param string $source
   *   Local absolute filepath.
   * @param string $destination
   *   Name of file in S3.
   */
  protected function sendToS3(string $source, string $destination) {
    $client = $this->createS3Client();
    $client->putObject([
      'Bucket' => $this->requireConfigVal('aws.bucket'),
      'SourceFile' => $source,
      'Key' => $destination,
    ]);
  }

  /**
   * Create instance of S3 client.
   *
   * @link https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-CreateClient.php.html
   *
   * @return \Aws\S3\S3Client
   */
  protected function createS3Client() {
    return new S3Client([
      'credentials' => [
        'key'    => $this->requireConfigVal('aws.key'),
        'secret' => $this->requireConfigVal('aws.secret'),
      ],
      'region' => $this->requireConfigVal('aws.region'),
      'version' => $this->requireConfigVal('aws.version'),
    ]);
  }

}
