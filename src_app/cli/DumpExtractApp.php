<?php
namespace app\cli;

use app\PvDataExtractor;
use nulib\app\cli\Application;
use nulib\ext\yaml;

class DumpExtractApp extends Application {
  const ARGS = [
    "purpose" => "afficher la représentation interne après extraction",
    "usage" => "INPUT.csv",

    ["args" => "file", "name" => "args"],
  ];

  function main() {
    $input = self::shift($this->args);
    if (!$input) self::die("Il faut spécifier le fichier en entrée");

    $extractor = new PvDataExtractor();
    $pvData = $extractor->extract($input);

    yaml::dump($pvData->data);
  }
}
