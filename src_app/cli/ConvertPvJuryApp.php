<?php
namespace app\cli;

use app\config\bootstrap;
use app\PvJuryCsvBuilder;
use app\PvJuryExtractor;
use app\PvJuryXlsxBuilder;
use nur\sery\app\cli\Application;
use nur\sery\ext\json;
use nur\sery\ext\yaml;

class ConvertPvJuryApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "convertir une extraction de PV de jury",
    "usage" => "INPUT.csv [-o OUTPUT.csv]",

    ["-d", "--dump-yaml", "value" => true,
      "help" => "Afficher les données au format YAML",
    ],
    ["-j", "--json-output", "args" => "file",
      "help" => "Spécifier le fichier JSON en sortie",
    ],
    ["-o", "--csv-output", "args" => "file",
      "help" => "Spécifier le fichier CSV en sortie",
    ],
    ["-x", "--xlsx-output", "args" => "file",
      "help" => "Spécifier le fichier XLSX mis en forme en sortie",
    ],
    ["args" => "file", "name" => "args"],
  ];

  protected bool $dumpYaml = false;
  protected ?string $jsonOutput = null;
  protected ?string $csvOutput = null;
  protected ?string $xlsxOutput = null;
  protected ?array $args = null;

  function main() {
    $args = $this->args;
    $csvInput = $args[0] ?? null;
    if (!$args || !$csvInput) self::die("Vous devez spécifier le fichier en entrée");

    $extractor = new PvJuryExtractor();
    $data = $extractor->extract($csvInput);
    
    $csvOutput = $this->csvOutput;
    $jsonOutput = $this->jsonOutput;
    $xlsxOutput = $this ->xlsxOutput;
    $dumpYaml = $this->dumpYaml;
    if ($csvOutput === null && $jsonOutput === null && !$dumpYaml && $xlsxOutput === null) {
      $csvOutput = "-";
    }

    if ($dumpYaml) {
      yaml::dump($data);
    }
    if ($jsonOutput !== null) {
      json::dump($data, $jsonOutput);
    }
    if ($csvOutput !== null) {
      $builder = new PvJuryCsvBuilder();
      $builder->build($data, $csvOutput)->write();
    }
    if($xlsxOutput !== null){
      $builder = new PvJuryXlsxBuilder();
      $builder->build($data, $xlsxOutput)->write();
    }
  }
}
