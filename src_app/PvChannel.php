<?php
namespace app;

use app\config\cdefaults;
use nulib\db\CapacitorChannel;
use nulib\file;
use nulib\file\web\Upload;
use nulib\os\path;
use nur\authz;

class PvChannel extends CapacitorChannel {
  const TABLE_NAME = "pv";

  const COLUMN_DEFINITIONS = [
    "origname" => "varchar",
    "name" => "varchar primary key",
    "cod_usr" => "varchar",
    "lib_usr" => "varchar",
    "title" => "varchar",
    "date" => "datetime",
    "data__" => "text",
  ];

  private bool $rebuilder = false;

  function setRebuilder(bool $rebuilder) {
    $this->rebuilder = $rebuilder;
  }

  function getItemValues($item): ?array {
    if ($this->rebuilder) {
      $codUsr = null;
      $libUsr = "(rebuilder)";
    } else {
      $usr = authz::get();
      $codUsr = $usr->getUsername();
      $libUsr = $usr->getDisplayName();
    }
    $file = $item;
    if ($file instanceof Upload) {
      # faire une copie du fichier
      $origname = path::filename($file->fullPath);
      $file->moveTo(pvs::upload_file($origname));
    }
    # puis extraire les données
    $extractor = new PvDataExtractor();
    $pvData = $extractor->extract($file);
    $title = $pvData->data["title"][0] ?? null;
    return [
      "origname" => $pvData->origname,
      "name" => $pvData->name,
      "cod_usr" => $codUsr,
      "lib_usr" => $libUsr,
      "title" => $title,
      "date" => $pvData->date,
      "data" => $pvData->data,
      "item" => null,
    ];
  }

  function onCreate($item, array $row, ?array $alwaysNull): ?array {
    $name = $row["name"];
    file::writer(pvs::json_file($name))->encodeJson($row["data"]);
    return ["data" => null];
  }

  function onUpdate($item, array $row, array $prow): ?array {
    $updates = $this->onCreate($item, $row, $prow);
    if ($this->rebuilder && $row["cod_usr"] === null && $prow["cod_usr"] !== null) {
      # si rebuilder, essayer de garder le même nom d'utilisateur
      $updates["cod_usr"] = $prow["cod_usr"];
      $updates["lib_usr"] = $prow["lib_usr"];
    }
    return $updates;
  }

  function onDelete(array $row): bool {
    if (!cdefaults::KEEP_FILES_ON_DELETE) {
      $jsonName = "{$row["name"]}.json";
      @unlink(pvs::json_file($jsonName));
      $uploadName = $row["origname"];
      @unlink(pvs::upload_file($uploadName));
    }
    return true;
  }
}
