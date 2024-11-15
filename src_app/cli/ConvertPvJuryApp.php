<?php
namespace app\cli;

use app\config\bootstrap;
use app\CsvPvBuilder;
use app\CsvPvModel1Builder;
use app\CsvPvModel2Builder;
use app\PvDataExtractor;
use app\PvJuryXlsxBuilder;
use nur\sery\app\cli\Application;
use nur\sery\ext\json;
use nur\sery\ext\yaml;
use nur\sery\StateException;

class ConvertPvJuryApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "convertir une extraction de PV de jury",
    "usage" => "INPUT.csv [-o OUTPUT.csv]",

    ["-1", "--model1", "name" => "model", "value" => 1,
      "help" => "Sélectionner le modèle n°1",
    ],
    ["-2", "--model2", "name" => "model", "value" => 2,
      "help" => "Sélectionner le modèle n°1",
    ],
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

  const CSV_BUILDERS = [
    1 => CsvPvModel1Builder::class,
    2 => CsvPvModel2Builder::class,
  ];

  protected int $model = 2;
  protected bool $dumpYaml = false;
  protected ?string $jsonOutput = null;
  protected ?string $csvOutput = null;
  protected ?string $xlsxOutput = null;
  protected ?array $args = null;

  function main() {
    $args = $this->args;
    $csvInput = $args[0] ?? null;
    if (!$args || !$csvInput) self::die("Vous devez spécifier le fichier en entrée");

    $extractor = new PvDataExtractor();
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
      $class = self::CSV_BUILDERS[$this->model] ?? null;
      if ($class === null) throw StateException::unexpected_state();
      /** @var CsvPvBuilder $builder */
      $builder = new $class();
      $builder->build($data, $csvOutput)->write();
    }
    if($xlsxOutput !== null){
      $builder = new PvJuryXlsxBuilder();
      $builder->build($data, $xlsxOutput)->write();
    }
  }
}
