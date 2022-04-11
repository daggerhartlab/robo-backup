<?php

use Aws\S3\S3Client;
use DagLab\RoboBackups\CliAdapter;
use DagLab\RoboBackups\CliAdapterInterface;
use DagLab\RoboBackups\DbDumperAdapter;
use DagLab\RoboBackups\DbDumperConfigFactory;

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
  const VERSION = '1.2.0';

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
    '.*\.zip',
    '.*\.tar',
    '.*\.tgz',
    '.*\.tar\.gz',
    '.*\.wpress',
    '.*\/node_modules\/.*',
    '.*\/.git\/.*',
  ];

  /**
   * Array of file folders.
   *
   * @var array
   */
  protected $backupFilesRoot = [];

  /**
   * Array of code folders.
   *
   * @var array
   */
  protected $backupCodeRoot = [];

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    $this->date = date('Y-m-d');
    $this->backupFilesRoot = (array) $this->requireConfigVal('backups.files_root');
    $this->backupCodeRoot = (array) $this->requireConfigVal('backups.code_root');
  }

  /**
   * Show version number.
   */
  public function version() {
    $this->writeln(static::VERSION);
  }

  /**
   * Validate configuration.
   *
   * @link https://docs.aws.amazon.com/AmazonS3/latest/API/API_ListObjectsV2.html
   *
   * @return void
   * @throws \Robo\Exception\TaskException
   */
  public function configValidate() {
    $this->stopOnFail(TRUE);

    // Validate AWS credentials.
    $client = $this->createS3Client();
    $client->listObjectsV2([
      'Bucket' => $this->requireConfigVal('aws.bucket'),
      'max-keys' => 1,
    ]);
    $this->say("S3 bucket '{$this->getConfigVal('aws.bucket')}' connected.");

    // Validate CLI or Dumper.
    if ($this->getConfigVal('cli')) {
      $cli_adapter = $this->getCliAdapter();
      $this->ensureCli($cli_adapter);
      $this->say("CLI command '{$cli_adapter->executable()}}' found.");
    }
    elseif ($this->getConfigVal('dump')) {
      $config = $this->getDumperConfig();
      $this->say("Dumper config for database '{$config->getDbName()}' on host '{$config->getHost()}' loaded.");
    }
  }

  /**
   * Backup database and send to S3.
   *
   * @throws \Robo\Exception\TaskException
   */
  public function backupDatabase() {
    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-db.sql";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";

    $this->ensureDir($this->requireConfigVal('backups.destination'));

    // Project specific cli utility for db dump.
    if ($this->getConfigVal('cli')) {
      $cli_adapter = $this->getCliAdapter();
      $this->ensureCli($cli_adapter);
      $this->taskExecStack()
        ->exec("{$cli_adapter->executable()} {$cli_adapter->backupDbCommand($this->requireConfigVal('backups.code_root'), $file)}")
        ->run();
    }
    // Generic dump wrapper around technology cli command.
    elseif ($this->getConfigVal('dump')) {
      $dumper = $this->getDumper();
      $dumper->dumpToFile($file);
    }

    $this->taskPack("{$file}.zip")
      ->addFile($filename, $file)
      ->run();

    $this->sendToS3("{$file}.zip", "{$filename}.zip");
    $this->removeFile($file);
    $this->removeFile("{$file}.zip");
  }

  /**
   * Backup non-code site files and send to S3.
   */
  public function backupFiles() {
    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-files.zip";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";
    $this->ensureDir($this->requireConfigVal('backups.destination'));

    $archive = $this->taskPack($file);
    foreach ($this->backupFilesRoot as $i => $files_root) {
      $folder = basename($files_root) . "_{$i}";
      $archive->addDir($folder, $files_root);
    }
    $archive
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
    $folder = trim($this->getConfigVal('aws.folder') ?? '', '/');
    $sync = $this->taskExecStack()
      ->stopOnFail()
      ->envVars([
        'AWS_ACCESS_KEY_ID' => $this->requireConfigVal('aws.key'),
        'AWS_SECRET_ACCESS_KEY' => $this->requireConfigVal('aws.secret'),
        'AWS_DEFAULT_REGION' => $this->requireConfigVal('aws.region'),
      ]);

    foreach ($this->backupFilesRoot as $i => $files_root) {
      $destination = basename($files_root) . "_sync_{$i}";
      if ($folder) {
        $destination = "{$folder}/{$destination}";
      }
      $sync->exec("aws s3 sync {$files_root} s3://{$this->requireConfigVal('aws.bucket')}/{$destination}");
    }
    $sync->run();
  }

  /**
   * Backup the code and send to S3.
   */
  public function backupCode() {
    $filename = "{$this->requireConfigVal('backups.prefix')}-{$this->date}-code.zip";
    $file = "{$this->requireConfigVal('backups.destination')}/{$filename}";
    $this->ensureDir($this->requireConfigVal('backups.destination'));

    // Exclude files from code backup.
    foreach ($this->backupFilesRoot as $files_root) {
      foreach ($this->backupCodeRoot as $code_root) {
        $relative_files_root = str_replace($code_root, '', $files_root);
        $this->archiveExclude[] = str_replace('/', '\/', trim($relative_files_root, '/'));
      }
    }
    $archive = $this->taskPack($file);
    foreach ($this->backupCodeRoot as $i => $code_root) {
      $archive->addDir("code_{$i}", $this->requireConfigVal('backups.code_root'));
    }
    $archive
      ->exclude($this->archiveExclude)
      ->run();

    $this->sendToS3($file, $filename);
    $this->removeFile($file);
  }

  /**
   * Get CLI adpater instance.
   *
   * @return \DagLab\RoboBackups\CliAdapterInterface
   */
  protected function getCliAdapter() {
    return new CliAdapter(
      $this->requireConfigVal('cli.executable'),
      $this->requireConfigVal('cli.package'),
      $this->requireConfigVal('cli.version'),
      $this->requireConfigVal('cli.backup_db_command')
    );
  }

  /**
   * Get DbDumper adapter instance.
   *
   * @return \DagLab\RoboBackups\DbDumperAdapterInterface
   */
  protected function getDumper() {
    $config = $this->getDumperConfig();
    return new DbDumperAdapter(
      $this->requireConfigVal('dump.type'),
      $config
    );
  }

  /**
   * Get DbDumper config instance.
   *
   * @return \DagLab\RoboBackups\DbDumperConfigInterface
   */
  protected function getDumperConfig() {
    $factory = new DbDumperConfigFactory();
    return $factory->createConfig($factory->resolveCredentials());
  }

  /**
   * Install the cli if it doesn't exist.
   *
   * @throws \Robo\Exception\TaskException
   */
  protected function ensureCli(CliAdapterInterface $cli) {
    $result = $this->taskExecStack()
      ->stopOnFail(false)
      ->exec("which {$cli->executable()}")
      ->run();

    if ($result->getExitCode()) {
      $this->taskExecStack()
        ->stopOnFail(true)
        ->exec("composer global require {$cli->package()}:{$cli->version()}")
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
  protected function ensureAwsCli() {
    $result = $this->taskExecStack()
      ->stopOnFail(false)
      ->exec("which aws")
      ->run();

    if ($result->getExitCode()) {
      $this->taskExecStack()
        ->stopOnFail(true)
        ->exec('curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"')
        ->exec('unzip awscliv2.zip')
        ->exec('./aws/install -i ~/.local/aws-cli -b ~/.local/bin')
        ->exec('rm -fr ./aws')
        ->exec("echo 'export PATH=\$PATH:\$HOME/.local/bin' >> .bashrc")
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
   * @link https://docs.aws.amazon.com/aws-sdk-php/v2/api/class-Aws.S3.S3Client.html#_putObject
   * @link https://docs.aws.amazon.com/code-samples/latest/catalog/php-s3-PutObject.php.html
   *
   * @param string $source
   *   Local absolute filepath.
   * @param string $destination
   *   Name of file in S3.
   */
  protected function sendToS3(string $source, string $destination) {
    $folder = trim($this->getConfigVal('aws.folder') ?? '', '/');
    if ($folder) {
      $destination = "{$folder}/{$destination}";
    }

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
