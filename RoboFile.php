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

  protected $name = 'robo-backup';

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

  protected $awsBucket;
  protected $awsFolder;
  private $awsKey;
  private $awsSecret;
  protected $awsRegion;
  protected $awsVersion;

  protected $backupDestination;
  protected $backupPrefix;
  /**
   * Array of file folders.
   *
   * @var array
   */
  protected $backupFilesRoot = [];

  /**
   * Code folder.
   *
   * @var array
   */
  protected $backupCodeRoot;

  protected $isCli = FALSE;
  protected $cliExecutable;
  protected $cliPackage;
  protected $cliVersion;
  protected $cliBackupDbCommand;
  protected $cliRestoreDbCommand;

  protected $isDump = FALSE;
  protected $dumpType;
  protected $dumpStrategy;
  private $dumpCredentials;
  protected $dumpCredentialsFile;
  protected $dumpOptions = [];

  /**
   * RoboFile constructor.
   */
  public function __construct() {
    $this->date = date('Y-m-d');

    $this->awsBucket = $this->requireConfigVal("{$this->name}.aws.bucket");
    $this->awsFolder = $this->getConfigVal("{$this->name}.aws.folder") ?: "";
    $this->awsKey = $this->requireConfigVal("{$this->name}.aws.key");
    $this->awsSecret = $this->requireConfigVal("{$this->name}.aws.secret");
    $this->awsRegion = $this->requireConfigVal("{$this->name}.aws.region");
    $this->awsVersion = $this->getConfigVal("{$this->name}.aws.version") ?: "latest";

    $this->backupDestination = $this->requireConfigVal("{$this->name}.backups.destination");
    $this->backupPrefix = $this->requireConfigVal("{$this->name}.backups.prefix");
    $this->backupFilesRoot = (array) $this->requireConfigVal("{$this->name}.backups.files_root");
    $this->backupCodeRoot = $this->requireConfigVal("{$this->name}.backups.code_root");

    if ($this->getConfigVal("{$this->name}.cli")) {
      $this->cliExecutable = $this->requireConfigVal("{$this->name}.cli.executable");
      $this->cliPackage = $this->requireConfigVal("{$this->name}.cli.package");
      $this->cliVersion = $this->getConfigVal("{$this->name}.cli.version") ?: "*";
      $this->cliBackupDbCommand = $this->requireConfigVal("{$this->name}.cli.backup_db_command");
      $this->cliRestoreDbCommand = $this->getConfigVal("{$this->name}.cli.restore_db_command") ?: NULL;
      $this->isCli = TRUE;
    }

    if ($this->getConfigVal("{$this->name}.dump")) {
      $this->dumpType = $this->requireConfigVal("{$this->name}.dump.type");
      $this->dumpStrategy = $this->requireConfigVal("{$this->name}.dump.strategy");
      $this->dumpCredentials = $this->getConfigVal("{$this->name}.dump.credentials") ?: [];
      $this->dumpCredentialsFile = $this->getConfigVal("{$this->name}.dump.credentials_file");
      $this->dumpOptions = $this->getConfigVal("{$this->name}.dump.options") ?: [];
      $this->isDump = TRUE;
    }
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
    //$this->stopOnFail(TRUE);

    // Validate AWS credentials.
    $client = $this->createS3Client();
    $client->listObjectsV2([
      'Bucket' => $this->awsBucket,
      'max-keys' => 1,
    ]);
    $this->say("S3 bucket '{$this->awsBucket}' connected.");

    // Validate CLI or Dumper.
    if ($this->isCli) {
      $cli_adapter = $this->getCliAdapter();
      $this->ensureCli($cli_adapter);
      $this->say("CLI command '{$cli_adapter->executable()}' found.");
    }
    elseif ($this->isDump) {
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
    $filename = "{$this->backupPrefix}-{$this->date}-db.sql";
    $file = "{$this->backupDestination}/{$filename}";

    $this->ensureDir($this->backupDestination);

    // Project specific cli utility for db dump.
    if ($this->isCli) {
      $cli_adapter = $this->getCliAdapter();
      $this->ensureCli($cli_adapter);
      $this->taskExecStack()
        ->exec("{$cli_adapter->executable()} {$cli_adapter->backupDbCommand($this->backupCodeRoot, $file)}")
        ->run();
    }
    // Generic dump wrapper around technology cli command.
    elseif ($this->isDump) {
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
   * Restore latest database backup. **CLI only**
   *
   * @return void
   * @throws \Robo\Exception\TaskException
   */
  public function restoreDatabase() {
    // **CLI only** for now.
    if (!$this->isCli) {
      throw new \RuntimeException("CLI required for database restore and  not configured.");
    }

    $archive = $this->downloadLatestFromS3('sql');
    $destination = rtrim($this->backupDestination, '/') . '/tmp';
    $file = $destination . '/' . basename($archive, '.zip');

    $this->taskExtract($archive)
      ->to($destination)
      ->run();

    if (!file_exists($file)) {
      throw new \RuntimeException("Failed to extract {$file}");
    }

    if ($this->isCli) {
      $cli_adapter = $this->getCliAdapter();
      $this->taskExecStack()
        ->exec("{$cli_adapter->executable()} {$cli_adapter->restoreDbCommand($this->backupCodeRoot, $file)}")
        ->run();

      $this->removeFile($file);
      $this->removeFile($destination);
      $this->removeFile($archive);
    }
  }

  /**
   * Backup non-code site files and send to S3.
   */
  public function backupFiles() {
    $filename = "{$this->backupPrefix}-{$this->date}-files.zip";
    $file = "{$this->backupDestination}/{$filename}";
    $this->ensureDir($this->backupDestination);

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
    $folder = trim($this->awsFolder ?? '', '/');
    $sync = $this->taskExecStack()
      ->stopOnFail()
      ->envVars([
        'AWS_ACCESS_KEY_ID' => $this->awsKey,
        'AWS_SECRET_ACCESS_KEY' => $this->awsSecret,
        'AWS_DEFAULT_REGION' => $this->awsRegion,
      ]);

    foreach ($this->backupFilesRoot as $i => $files_root) {
      $destination = basename($files_root) . "_sync_{$i}";
      if ($folder) {
        $destination = "{$folder}/{$destination}";
      }
      $sync->exec("aws s3 sync {$files_root} s3://{$this->awsBucket}/{$destination}");
    }
    $sync->run();
  }

  /**
   * Sync files from s3 to the file system.
   *
   * @return void
   * @throws \Robo\Exception\TaskException
   */
  public function restoreFilesSync() {
    $this->ensureAwsCli();
    $folder = trim($this->awsFolder ?? '', '/');
    $sync = $this->taskExecStack()
      ->stopOnFail()
      ->envVars([
        'AWS_ACCESS_KEY_ID' => $this->awsKey,
        'AWS_SECRET_ACCESS_KEY' => $this->awsSecret,
        'AWS_DEFAULT_REGION' => $this->awsRegion,
      ]);

    foreach ($this->backupFilesRoot as $i => $files_root) {
      $destination = basename($files_root) . "_sync_{$i}";
      if ($folder) {
        $destination = "{$folder}/{$destination}";
      }
      $sync->exec("aws s3 sync s3://{$this->awsBucket}/{$destination} {$files_root}");
    }
    $sync->run();
  }

  /**
   * Backup the code and send to S3.
   */
  public function backupCode() {
    $filename = "{$this->backupPrefix}-{$this->date}-code.zip";
    $file = "{$this->backupDestination}/{$filename}";
    $this->ensureDir($this->backupDestination);

    // Exclude files from code backup.
    foreach ($this->backupFilesRoot as $files_root) {
      foreach ($this->backupCodeRoot as $code_root) {
        $relative_files_root = str_replace($code_root, '', $files_root);
        $this->archiveExclude[] = str_replace('/', '\/', trim($relative_files_root, '/'));
      }
    }
    $archive = $this->taskPack($file);
    $archive->addDir("code", $this->backupCodeRoot);
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
      $this->cliExecutable,
      $this->cliPackage,
      $this->cliVersion,
      $this->cliBackupDbCommand,
      $this->cliRestoreDbCommand
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
      $this->dumpType,
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
    return $factory->createConfig(
      $factory->resolveCredentials(
        $this->dumpStrategy,
        $this->dumpCredentials,
        $this->dumpCredentialsFile
      ),
      $this->dumpOptions
    );
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
      $this->say("Attempting to install {$cli->package()}:{$cli->version()}");

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
    $folder = trim($this->awsFolder ?? '', '/');
    if ($folder) {
      $destination = "{$folder}/{$destination}";
    }

    $client = $this->createS3Client();
    $client->putObject([
      'Bucket' => $this->awsBucket,
      'SourceFile' => $source,
      'Key' => $destination,
    ]);
  }

  /**
   * Download the latest of a backup type from S3.
   *
   * @param string $backup_type
   *   Backup type of: sql, files, or code.
   *
   * @return string
   *   Resulting file name.
   */
  protected function downloadLatestFromS3(string $backup_type) {
    if (!in_array($backup_type, ['sql', 'files', 'code'])) {
      throw new \RuntimeException("Invalid backup type: {$backup_type}.");
    }

    $client = $this->createS3Client();
    // Get list of backup file objects and sort by newest first.
    $result = $client->listObjectsV2([
      'Bucket' => $this->awsBucket,
      'Prefix' => $this->backupPrefix,
    ]);
    $results_array = $result->toArray();
    if (empty($results_array['Contents'])) {
      throw new \RuntimeException("No objects found in query.");
    }

    $objects = $results_array['Contents'];
    $objects = array_filter($objects, function($object) use ($backup_type) {
      return stripos(".{$object['Key']}.zip", $backup_type) !== FALSE;
    });
    usort($objects, function($a, $b) {
      return $b['LastModified']->getTimestamp() <=> $a['LastModified']->getTimestamp();
    });
    $latest = array_shift($objects);
    $this->say("Found latest {$backup_type} backup '{$latest['Key']}' from {$latest['LastModified']}");

    $local_file = "{$this->backupDestination}/" . basename($latest['Key']);
    $client->getObject([
      'Bucket' => $this->awsBucket,
      'Key' => $latest['Key'],
      'SaveAs' => $local_file,
    ]);

    if (!file_exists($local_file)) {
      throw new \RuntimeException("Failed to download object {$latest['Key']} to {$local_file}.");
    }

    $this->say("Saved to {$local_file}");
    return $local_file;
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
        'key'    => $this->awsKey,
        'secret' => $this->awsSecret,
      ],
      'region' => $this->awsRegion,
      'version' => $this->awsVersion,
    ]);
  }

}
