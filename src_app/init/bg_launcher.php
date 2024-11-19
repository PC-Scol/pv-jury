<?php
namespace app\init;

use nur\sery\app\args;
use nur\sery\os\path;
use nur\sery\os\proc\Cmd;

class bg_launcher {
  const PHP_BINARY = "/usr/bin/php";

  static function launch(string $appClass, ?array $args=null): int {
    $cmd = new Cmd([
      static::PHP_BINARY,
      path::abspath(__DIR__.'/../../sbin/_bg_launcher.php'),
      $appClass, "--", ...args::from_array($args),
    ]);
    $cmd->addRedir("both", "/tmp/NULIB_APP_app_launcher.log");
    $cmd->passthru($exitcode);

    usleep(500000);
    return $exitcode;
  }
}
