<?php

namespace DagLab\RoboBackups;

/**
 * Configuration resolver and object factory.
 */
class DbDumperConfigFactory
{
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
   * Resolve credentials based on configuration file strategy.
   *
   * @return array
   *   Resolved and parsed credentials.
   */
  public function resolveCredentials() {
    $credential_values = [];

    // Credentials file strategy.
    if ($this->requireConfigVal('dump.strategy') === 'file') {
      if (!\file_exists($this->requireConfigVal('dump.credentials_file'))) {
        throw new \RuntimeException("Credentials file missing: {$this->requireConfigVal('dump.credentials_file')}");
      }

      $file_values = $this->parseCredentialsFile($this->requireConfigVal('dump.credentials_file'));
      foreach ($this->credentialPropertyNames as $property) {
        // Default to literal value if the property.
        $credential_values[$property] = $this->getConfigVal("dump.credentials.{$property}");
        // Look for property name as value key from file.
        if (isset($file_values[$this->getConfigVal("dump.credentials.{$property}")])) {
          $credential_values[$property] = $file_values[$this->getConfigVal("dump.credentials.{$property}")];
        }
      }
    }

    // Environment value strategy.
    else if ($this->requireConfigVal('dump.strategy') === 'env') {
      foreach ($this->credentialPropertyNames as $property) {
        // Default to environment value.
        $credential_values[$property] = getenv($this->getConfigVal("dump.credentials.{$property}"));
        // Fallback to literal value if getenv results in false, meaning the environment variable doesn't exist.
        if ($credential_values[$property] === FALSE) {
          $credential_values[$property] = $this->getConfigVal("dump.credentials.{$property}");
        }
      }
    }

    return $this->typeValues($credential_values);
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

  /**
   * Parse given ini/env file.
   *
   * @param string $file
   *   File name.
   *
   * @return array
   *   Values order of precedence:
   *     1 - interpolated
   *     2 - typed
   *     3 - normal
   */
  protected function parseCredentialsFile(string $file) {
    $result = [];
    $raw_values = \parse_ini_file($file, false, INI_SCANNER_RAW);
    $interpolated_values = $this->interpolateValues($raw_values);
    $normal_values = \parse_ini_file($file);
    $typed_values = \parse_ini_file($file, false, INI_SCANNER_TYPED);

    foreach ($raw_values as $key => $value) {
      // Default to normal values
      $result[$key] = $normal_values[$key];

      // Interpolated values take precedence.
      if ($interpolated_values[$key] != $value) {
        $result[$key] = $interpolated_values[$key];
      }
      // Typed values proceed over normal values.
      else if ($typed_values[$key] !== $value) {
        $result[$key] = $typed_values[$key];
      }
    }

    return $result;
  }

  /**
   * Interpolate variable replacement in values.
   *
   * @param array $values
   *   Values from resolution.
   *
   * @return array
   *   Interpolated values.
   */
  protected function interpolateValues(array $values) {
    $interpolated = $values;
    foreach ($values as $key => $value) {
      if (is_string($value) && stripos($value, '${') !== FALSE) {
        $interpolated[$key] = preg_replace_callback('/\$\{(\w+)\}/', function($matches) use ($values) {
          if (isset($matches[1], $values[$matches[1]])) {
            return $values[$matches[1]];
          }
          return $matches[0];
        }, $value);
      }
    }
    return $interpolated;
  }

  /**
   * Convert values to simple types.
   *
   * @param array $values
   *   Values from resolution.
   *
   * @return array
   *   Typed values.
   */
  protected function typeValues(array $values) {
    foreach ($values as $key => $value) {
      // Some values are expected numeric, such as "port".
      if (is_numeric($value)) {
        $values[$key] = intval($value);
      }
      // Array values may be in json format.
      elseif (\json_decode($value)) {
        $values[$key] = \json_decode($value, TRUE);
      }
    }
    return $values;
  }

}