<?php
namespace app\init;

use nulib\db\mysql\Mysql;
use nulib\db\mysql\MysqlCapacitor;
use nur\config;

class pv_jurydb {
  private static $mysql;

  static function mysql(?array $params=null): Mysql {
    return self::$mysql ??= new Mysql(config::db("pv_jury"), $params);
  }

  static function storage(?array $params=null): MysqlCapacitor {
    return new MysqlCapacitor(self::mysql($params));
  }
}
