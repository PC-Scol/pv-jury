<?php
namespace app;

use nur\authz;
use nur\sery\db\CapacitorChannel;
use nur\sery\file;
use nur\sery\file\web\Upload;
use nur\sery\os\path;

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

  function onCreate($item, array $values, ?array $alwaysNull): ?array {
    $name = $values["name"];
    file::writer(pvs::json_file($name))->encodeJson($values["data"]);
    return ["data" => null];
  }

  function onUpdate($item, array $values, array $pvalues): ?array {
    return $this->onCreate($item, $values, $pvalues);
  }
}
