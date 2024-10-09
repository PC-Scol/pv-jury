<?php
namespace app\init;

use nur\config;
use nur\sery\db\mysql\Mysql;
use nur\sery\db\mysql\MysqlStorage;

class pv_jurydb {
  private static $mysql;

  static function mysql(?array $params=null): Mysql {
    return self::$mysql ??= new Mysql(config::db("pv_jury"), $params);
  }

  static function storage(?array $params=null): MysqlStorage {
    return new MysqlStorage(self::mysql($params));
  }
}
