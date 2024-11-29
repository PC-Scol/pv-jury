<?php
namespace app\cli;

use app\config\bootstrap;
use app\pvs;
use nulib\app\cli\Application;
use nulib\os\sh;

class RebuildApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "reconstruire la base de donnée des PVs de jury",

    ["-u", "--uploads", "value" => true,
      "help" => "reconstruire depuis les fichiers du répertoire uploads",
    ],
    ["-k", "--clean", "value" => true,
      "help" => "supprimer le fichier de base de données avant la reconstruction",
    ],
  ];

  private bool $uploads = false;
  private bool $clean = false;

  function main() {
    $storageFile = pvs::storage_file();
    if ($this->clean && file_exists($storageFile)) {
      unlink($storageFile);
    }

    if ($this->uploads) {
      $files = sh::ls_pfiles(pvs::upload_file(null), "*.csv");
      $channel = pvs::channel();
      $channel->setRebuilder(true);
      foreach ($files as $file) {
        $channel->charge($file);
      }
    } else {
      $files = sh::ls_pfiles(pvs::file(null), "*.json");
      $channel = pvs::channel_rebuilder();
      foreach ($files as $file) {
        $channel->charge($file);
      }
    }
  }
}
