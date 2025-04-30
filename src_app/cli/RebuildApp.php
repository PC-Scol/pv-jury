<?php
namespace app\cli;

use app\config\bootstrap;
use app\pvs;
use nulib\app\cli\Application;
use nulib\cl;
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
    ["-x", "--clean-db", "value" => true,
      "help" => "supprimer la base de données avant la reconstruction",
    ],
    ["--no-import", "name" => "import", "value" => false,
      "help" => "ne pas réimporter les fichiers",
    ],
    ["-u", "--uploads", "value" => true,
      "help" => "reconstruire depuis les fichiers du répertoire uploads",
    ],
    ["-k", "--clean-orphans", "value" => true,
      "help" => "supprimer les fichiers qui ne sont associés à aucun utilisateur",
    ],
  ];

  private bool $verifix = false;
  private bool $cleanDb = false;
  private bool $import = true;
  private bool $uploads = false;
  private bool $cleanOrphans = false;

  function main() {
    $verifix = $this->verifix;
    $import = $this->import;
    $uploads = $this->uploads;
    $cleanDb = $this->cleanDb;
    if ($verifix) {
      if (pvs::get_version() === pvs::EXPECTED_VERSION) return;
      msg::info("Reconstruction des imports... Veuillez patienter");
      $import = true;
      $uploads = true;
      $cleanDb = false;
    }

    $storageFile = pvs::storage_file();
    if ($cleanDb && file_exists($storageFile)) {
      msg::info("suppression de la base de données");
      unlink($storageFile);
    }

    if ($import) {
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
    }

    if ($this->cleanOrphans) {
      msg::info("Suppression des fichiers orphelins");
      $channel = pvs::channel();
      $channel->delete([
        "cod_usr" => null,
      ]);
      $orphanJsons = sh::ls_files(pvs::file(null), "*.json");
      $orphanUploads = sh::ls_files(pvs::upload_file(null), "*.csv");
      $channel->each(null, function($item, array $values) use (&$orphanUploads, &$orphanJsons) {
        $jsonName = $values["name"];
        $key = array_search($jsonName, $orphanJsons);
        if ($key !== false) unset($orphanJsons[$key]);
        $uploadName = $values["origname"];
        $key = array_search($uploadName, $orphanUploads);
        if ($key !== false) unset($orphanUploads[$key]);
      });
      foreach ($orphanJsons as $jsonName) {
        msg::action("suppression de $jsonName", function() use ($jsonName) {
          @unlink(pvs::json_file($jsonName));
          return true;
        });
      }
      foreach ($orphanUploads as $uploadName) {
        msg::action("suppression de $uploadName", function() use ($uploadName) {
          @unlink(pvs::upload_file($uploadName));
        });
      }
    }

    if ($verifix) {
      # mettre à jour la version
      pvs::set_version(pvs::EXPECTED_VERSION);
    }
  }
}
