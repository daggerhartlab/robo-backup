<?php

namespace DagLab\RoboBackups;

/**
 * Class DbDumperConfig.
 *
 * @link https://github.com/spatie/db-dumper
 *
 * @package DagLab\RoboBackups
 */
class DbDumperConfig implements DbDumperConfigInterface {

  /**
   * Database name.
   *
   * @var string|null
   */
  protected $dbName;

  /**
   * Database username.
   *
   * @var string|null
   */
  protected $username;

  /**
   * Database password.
   *
   * @var string|null
   */
  protected $password;

  /**
   * Database host.
   *
   * @var string|null
   */
  protected $host;

  /**
   * Database host port number.
   *
   * @var int|null
   */
  protected $port;

  /**
   * Array of table names to include.
   *
   * @var array
   */
  protected $includeTables = [];

  /**
   * Array of table names to exclude.
   *
   * @var array
   */
  protected $excludeTables = [];

  /**
   * Extra command like utility options.
   *
   * @var array
   */
  protected $extraOptions = [];

  /**
   *
   * @var string|null
   */
  protected $binaryPath;

  /**
   * DbDumperConfig constructor.
   *
   * @param string|null $db_name
   * @param string|null $username
   * @param string|null $password
   * @param string|null $host
   * @param int|null $port
   * @param array $include_tables
   * @param array $exclude_tables
   * @param array $extra_options
   * @param string|null $binary_path
   */
  public function __construct(
    string $db_name = NULL,
    string $username = NULL,
    string $password = NULL,
    string $host = NULL,
    int $port = NULL,
    array $include_tables = [],
    array $exclude_tables = [],
    array $extra_options = [],
    string $binary_path = NULL
  ) {
    $this->dbName = $db_name;
    $this->username = $username;
    $this->password = $password;
    $this->host = $host;
    $this->port = $port;
    $this->includeTables = $include_tables;
    $this->excludeTables = $exclude_tables;
    $this->extraOptions = $extra_options;
    $this->binaryPath = $binary_path;
  }

  /**
   * @inheritDoc
   */
  public function getDbName() {
    return $this->dbName;
  }

  /**
   * @inheritDoc
   */
  public function setDbName(string $dbName) {
    $this->dbName = $dbName;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getUsername() {
    return $this->username;
  }

  /**
   * @inheritDoc
   */
  public function setUsername(string $username) {
    $this->username = $username;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getPassword() {
    return $this->password;
  }

  /**
   * @inheritDoc
   */
  public function setPassword(string $password) {
    $this->password = $password;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getHost() {
    return $this->host;
  }

  /**
   * @inheritDoc
   */
  public function setHost(string $host) {
    $this->host = $host;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getPort() {
    return $this->port;
  }

  /**
   * @inheritDoc
   */
  public function setPort(int $port) {
    $this->port = $port;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getIncludeTables() {
    return $this->includeTables ?: NULL;
  }

  /**
   * @inheritDoc
   */
  public function setIncludeTables(array $includeTables) {
    $this->includeTables = $includeTables;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getExcludeTables() {
    return $this->excludeTables;
  }

  /**
   * @inheritDoc
   */
  public function setExcludeTables(array $excludeTables) {
    $this->excludeTables = $excludeTables;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getExtraOptions() {
    return $this->extraOptions;
  }

  /**
   * @inheritDoc
   */
  public function setExtraOptions(array $extraOptions) {
    $this->extraOptions = $extraOptions;
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getBinaryPath() {
    return $this->binaryPath;
  }

  /**
   * @inheritDoc
   */
  public function setBinaryPath(string $binaryPath) {
    $this->binaryPath = $binaryPath;
    return $this;
  }

}