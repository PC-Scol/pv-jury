<?php
namespace app;

use nulib\db\CapacitorChannel;
use nulib\file;
use nulib\output\msg;

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

  function onUpdate($item, array $values, array $pvalues): ?array {
    $updates = null;
    if ($values["cod_usr"] === null && $pvalues["cod_usr"] !== null) {
      # si rebuilder, essayer de garder le même nom d'utilisateur
      $updates["cod_usr"] = $pvalues["cod_usr"];
      $updates["lib_usr"] = $pvalues["lib_usr"];
    }
    return $updates;
  }
}
