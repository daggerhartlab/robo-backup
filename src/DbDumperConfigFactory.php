<?php

namespace DagLab\RoboBackups;

class DbDumperConfigFactory {

  use \Kerasai\Robo\Config\ConfigHelperTrait;

  /**
   * @var string[]
   */
  protected $credentialPropertyNames = [
    'db_name',
    'username',
    'password',
    'host',
    'port',
  ];

  /**
   * @return array
   */
  public function resolveCredentials() {
    $credential_values = [];

    // Attempt credentials file parsing.
    if (
      $this->requireConfigVal('dump.strategy') === 'file' &&
      \file_exists($this->requireConfigVal('dump.credentials_file'))
    ) {
      $file_values = \parse_ini_file($this->requireConfigVal('dump.credentials_file'));
      foreach ($this->credentialPropertyNames as $property) {
        if (isset($file_values[$this->getConfigVal("dump.credentials.{$property}")])) {
          $credential_values[$property] = $file_values[$this->getConfigVal("dump.credentials.{$property}")];
        }
        // Fallback to literal value if the property doesn't exist in the file values.
        else {
          $credential_values[$property] = $this->getConfigVal("dump.credentials.{$property}");
        }
      }
    }
    else if ($this->requireConfigVal('dump.strategy') === 'env') {
      foreach ($this->credentialPropertyNames as $property) {
        $credential_values[$property] = getenv($this->getConfigVal("dump.credentials.{$property}"));
        // Fallback to literal value if getenv results in false, meaning the environment variable doesn't exist.
        if ($credential_values[$property] === FALSE) {
          $credential_values[$property] = $this->getConfigVal("dump.credentials.{$property}");
        }
      }
    }

    // Parse credential data types.
    foreach ($credential_values as $property => $value) {
      // Some values are expected numeric, such as "port".
      if (is_numeric($value)) {
        $credential_values[$property] = intval($value);
      }
      // Array values may be in json format.
      else if (\json_decode($value)) {
        $credential_values[$property] = \json_decode($value, TRUE);
      }
    }

    return $credential_values;
  }

  /**
   * @param array $credential_values
   *
   * @return \DagLab\RoboBackups\DbDumperConfigInterface
   */
  public function createConfig(array $credential_values) {
    return new DbDumperConfig(
      $credential_values['db_name'] ?? NULL,
      $credential_values['username'] ?? NULL,
      $credential_values['password'] ?? NULL,
      $credential_values['host'] ?? NULL,
      $credential_values['port'] ?? NULL,
      $this->getConfigVal('dump.options.include_tables') ?? [],
      $this->getConfigVal('dump.options.exclude_tables') ?? [],
      $this->getConfigVal('dump.options.extra_options') ?? []
    );
  }

}