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
    "usage" => "-f INPUT.csv",

    ["-f", "--csv-input", "args" => "file",
      "help" => "Spécifier le fichier CSV en entrée",
    ],
    ["-d", "--dump-yaml", "value" => true,
      "help" => "Afficher les données au format YAML",
    ],
    ["-o", "--csv-output", "args" => "file",
      "help" => "Spécifier le fichier CSV en sortie",
    ],
    ["-x", "--xlsx-output", "args" => "file",
      "help" => "Spécifier le fichier XLSX en sortie",
    ],
    ["-j", "--json-output", "args" => "file",
      "help" => "Spécifier le fichier JSON en sortie",
    ],
  ];

  protected ?string $csvInput = null;
  protected bool $dumpYaml = false;
  protected ?string $csvOutput = null;
  protected ?string $xlsxOutput = null;
  protected ?string $jsonOutput = null;

  function main() {
    $csvInput = $this->csvInput;
    if ($csvInput === null) {
      self::die("Vous devez spécifier le fichier en entrée");
    }

    $extractor = new PvJuryExtractor();
    $data = $extractor->extract($csvInput);
    
    $csvOutput = $this->csvOutput;
    $jsonOutput = $this->jsonOutput;
    $xlsxOutput = $this ->xlsxOutput;
    $dumpYaml = $this->dumpYaml;
    if ($csvOutput === null && $jsonOutput === null && !$dumpYaml && $xlsxOutput === null) {
      $csvOutput = "-";
    }

    if ($csvOutput !== null) {
      $builder = new PvJuryCsvBuilder();
      $builder->build($data, $csvOutput)->write();
    }
    if($xlsxOutput !== null){
      $builder = new PvJuryXlsxBuilder();
      $builder->build($data, $xlsxOutput)->write();
    }
    if ($jsonOutput !== null) {
      json::dump($data, $jsonOutput);
    }
    if ($dumpYaml) {
      yaml::dump($data);
    }
  }
}
