<?php
namespace app;

use nur\sery\db\CapacitorChannel;
use nur\sery\file;
use nur\sery\file\web\Upload;
use nur\sery\os\path;

class PvChannel extends CapacitorChannel {
  const COLUMN_DEFINITIONS = [
    "srcname" => "varchar",
    "name" => "varchar primary key",
    "date" => "datetime",
    "data__" => "text",
  ];

  function getItemValues($item): ?array {
    /** @var Upload $file */
    $file = $item;
    $extractor = new PvDataExtractor();
    $data = $extractor->extract($file);
    $origname = path::filename($file->fullPath);
    $name = pvs::basename($origname);
    $date = pvs::get_date($origname);
    return [
      "origname" => $origname,
      "name" => $name,
      "date" => $date,
      "data" => $data,
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
