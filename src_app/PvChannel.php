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
    $pvData = $extractor->extract($file);
    return [
      "origname" => $pvData->origname,
      "name" => $pvData->name,
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
