<?php
namespace app;

use nur\sery\db\CapacitorChannel;
use nur\sery\file;
use nur\sery\output\msg;

class PvChannelRebuilder extends CapacitorChannel {
  const TABLE_NAME = PvChannel::TABLE_NAME;

  const COLUMN_DEFINITIONS = PvChannel::COLUMN_DEFINITIONS;

  function getItemValues($item): ?array {
    $data = file::reader($item)->decodeJson();
    $origname = $data["origname"];
    $name = pvs::basename($origname);
    $date = pvs::get_date($origname);
    if ($name !== $data["name"]) {
      $data["name"] = $name;
      $data["date"] = $date;
      file::writer($item)->encodeJson($data);
      msg::info("Il faut renommer le fichier $item en $name.json");
    }

    return [
      "origname" => $origname,
      "name" => $name,
      "cod_usr" => null,
      "lib_usr" => "(rebuilder)",
      "title" => $data["title"][0] ?? null,
      "date" => $date,
      "data" => null,
      "item" => null,
    ];
  }
}
