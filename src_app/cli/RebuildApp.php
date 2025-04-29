<?php
namespace app\cli;

use app\config\bootstrap;
use app\pvs;
use nulib\app\cli\Application;
use nulib\os\sh;
use nulib\output\msg;

class RebuildApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "reconstruire la base de donnée des PVs de jury",

    ["-c", "--verifix", "value" => true,
      "help" => "vérifier la version, et reconstruire le cas échéant. implique --uploads --no-clean",
    ],
    ["-u", "--uploads", "value" => true,
      "help" => "reconstruire depuis les fichiers du répertoire uploads",
    ],
    ["-k", "--clean", "value" => true,
      "help" => "supprimer le fichier de base de données avant la reconstruction",
    ],
  ];

  private bool $verifix = false;
  private bool $uploads = false;
  private bool $clean = false;

  function main() {
    $check = $this->verifix;
    $uploads = $this->uploads;
    $clean = $this->clean;
    if ($check) {
      if (pvs::get_version() === pvs::EXPECTED_VERSION) return;
      msg::info("Reconstruction des imports... Veuillez patienter");
      $uploads = true;
      $clean = false;
    }

    $storageFile = pvs::storage_file();
    if ($clean && file_exists($storageFile)) {
      msg::info("suppression de la base de données");
      unlink($storageFile);
    }

    if ($uploads) {
      $files = sh::ls_pfiles(pvs::upload_file(null), "*.csv");
      $channel = pvs::channel();
      $channel->setRebuilder(true);
      foreach ($files as $file) {
        $filename = basename($file);
        msg::action("chargement de $filename", function() use ($channel, $file) {
          $channel->charge($file);
          return true;
        });
      }
    } else {
      $files = sh::ls_pfiles(pvs::file(null), "*.json");
      $channel = pvs::channel_rebuilder();
      foreach ($files as $file) {
        $filename = basename($file);
        msg::action("chargement de $filename", function() use ($channel, $file) {
          $channel->charge($file);
          return true;
        });
      }
    }

    if ($check) {
      # mettre à jour la version
      pvs::set_version(pvs::EXPECTED_VERSION);
    }
  }
}
