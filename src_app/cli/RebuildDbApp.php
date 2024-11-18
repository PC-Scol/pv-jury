<?php
namespace app\cli;

use app\config\bootstrap;
use app\pvs;
use nur\sery\app\cli\Application;
use nur\sery\os\sh;

class RebuildDbApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "reconstruire la base de donnée des PVs de jury",

    ["-k", "--clean", "value" => true,
      "help" => "supprimer le fichier de base de données avant la reconstruction"
    ],
  ];

  private bool $clean = false;

  function main() {
    $storageFile = pvs::storage_file();
    if ($this->clean && file_exists($storageFile)) {
      unlink($storageFile);
    }

    $channel = pvs::channel_rebuilder();
    $files = sh::ls_pfiles(pvs::file(null), "*.json");
    foreach ($files as $file) {
      $channel->charge($file);
    }
  }
}
