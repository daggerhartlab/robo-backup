<?php

namespace DagLab\RoboBackups;

use Spatie\DbDumper\DbDumper;

/**
 * Class DbDumpAdapter
 *
 * @package DagLab\RoboBackups
 */
class DbDumperAdapter implements DbDumperAdapterInterface {

  /**
   * Database type, named after the dump utility.
   * Options: mysqldump, pg_dump, sqlite3 and mongodump
   *
   * @var string
   */
  protected $dbType;

  /**
   * @var \DagLab\RoboBackups\DbDumperConfigInterface
   */
  protected $config;

  /**
   * @var \Spatie\DbDumper\DbDumper
   */
  protected $dumper;

  /**
   * @var string[]
   */
  protected $typeDriverMap = [
    'mysqldump' => 'MySql',
    'pg_dump' => 'PostgreSql',
    'sqlite3' => 'Sqlite',
    'mongodump' => 'MongoDb',
  ];

  /**
   * DbDumperAdapter constructor.
   *
   * @param string $db_type
   * @param \DagLab\RoboBackups\DbDumperConfigInterface|null $config
   */
  public function __construct(string $db_type, DbDumperConfigInterface $config = NULL) {
    $this->dbType = $db_type;
    $this->config = $config;
    $this->dumper = $this->createDumper($this->dbType, $config);
  }

  /**
   * @inheritDoc
   */
  public function isAllowedType(string $db_type) {
    return in_array($db_type, array_keys($this->typeDriverMap));
  }

  /**
   * @inheritDoc
   */
  public function createDumper(string $db_type, DbDumperConfigInterface $config = NULL) {
    if (!$this->isAllowedType($db_type)) {
      throw new \RuntimeException("Database type is now allowed: {$db_type}");
    }

    /** @var \Spatie\DbDumper\DbDumper $class */
    $class = "\\Spatie\\DbDumper\\Databases\\{$this->typeDriverMap[$db_type]}";
    $db_dumper = $class::create();
    if ($config) {
      $db_dumper = $this->setDumperConfig($db_dumper, $config);
    }
    return $db_dumper;
  }

  /**
   * @inheritDoc
   */
  public function setDumperConfig(DbDumper $db_dumper, DbDumperConfigInterface $config) {
    if ($config->getDbName()) {
      $db_dumper->setDbName($config->getDbName());
    }
    if ($config->getUsername()) {
      $db_dumper->setUserName($config->getUsername());
    }
    if ($config->getPassword()) {
      $db_dumper->setPassword($config->getPassword());
    }
    if ($config->getHost()) {
      $db_dumper->setHost($config->getHost());
    }
    if ($config->getPort()) {
      $db_dumper->setPort($config->getPort());
    }
    if ($config->getBinaryPath()) {
      $db_dumper->setDumpBinaryPath($config->getBinaryPath());
    }

    // Special cases depending on the dumper.
    if (method_exists($db_dumper, 'includeTables') && !empty($config->getIncludeTables())) {
      $db_dumper->includeTables($config->getIncludeTables());
    }
    if (method_exists($db_dumper, 'excludeTables') && !empty($config->getExcludeTables())) {
      $db_dumper->excludeTables($config->getExcludeTables());
    }
    if (method_exists($db_dumper, 'addExtraOption') && !empty($config->getExtraOptions())) {
      foreach ($config->getExtraOptions() as $extra_option) {
        $db_dumper->addExtraOption($extra_option);
      }
    }

    return $db_dumper;
  }

  /**
   * @inheritDoc
   */
  public function dumpToFile(string $file) {
    $this->dumper->dumpToFile($file);
  }

}