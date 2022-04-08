<?php

namespace DagLab\RoboBackups;

interface DbDumperConfigInterface {

  /**
   * @return string|null
   */
  public function getDbName();

  /**
   * @param string|null $dbName
   *
   * @return DbDumperConfig
   */
  public function setDbName(string $dbName);

  /**
   * @return string|null
   */
  public function getUsername();

  /**
   * @param string|null $username
   *
   * @return DbDumperConfig
   */
  public function setUsername(string $username);

  /**
   * @return string|null
   */
  public function getPassword();

  /**
   * @param string|null $password
   *
   * @return DbDumperConfig
   */
  public function setPassword(string $password);

  /**
   * @return string|null
   */
  public function getHost();

  /**
   * @param string|null $host
   *
   * @return DbDumperConfig
   */
  public function setHost(string $host);

  /**
   * @return int|null
   */
  public function getPort();

  /**
   * @param int|null $port
   *
   * @return DbDumperConfig
   */
  public function setPort(int $port);

  /**
   * @return array
   */
  public function getIncludeTables();

  /**
   * @param array $includeTables
   *
   * @return DbDumperConfig
   */
  public function setIncludeTables(array $includeTables);

  /**
   * @return array
   */
  public function getExcludeTables();

  /**
   * @param array $excludeTables
   *
   * @return DbDumperConfig
   */
  public function setExcludeTables(array $excludeTables);

  /**
   * @return array
   */
  public function getExtraOptions();

  /**
   * @param array $extraOptions
   *
   * @return DbDumperConfig
   */
  public function setExtraOptions(array $extraOptions);

  /**
   * @return string|null
   */
  public function getBinaryPath();

  /**
   * @param string|null $binaryPath
   *
   * @return DbDumperConfig
   */
  public function setBinaryPath(string $binaryPath);

}