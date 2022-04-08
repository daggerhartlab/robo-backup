<?php

namespace DagLab\RoboBackups;

use Spatie\DbDumper\DbDumper;

interface DbDumperAdapterInterface {

  /**
   * @param string $db_type
   *
   * @return bool
   */
  public function isAllowedType(string $db_type);

  /**
   * @param string $db_type
   * @param \DagLab\RoboBackups\DbDumperConfigInterface|null $config
   *
   * @return \Spatie\DbDumper\DbDumper
   */
  public function createDumper(string $db_type, DbDumperConfigInterface $config = NULL);

  /**
   * @param \Spatie\DbDumper\DbDumper $db_dumper
   * @param \DagLab\RoboBackups\DbDumperConfigInterface $config
   *
   * @return \Spatie\DbDumper\DbDumper
   * @throws \Spatie\DbDumper\Exceptions\CannotSetParameter
   */
  public function setDumperConfig(DbDumper $db_dumper, DbDumperConfigInterface $config);

  /**
   * @param string $file
   *
   * @return void
   */
  public function dumpToFile(string $file);

}