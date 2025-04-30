<?php
namespace app\cli;

use app\config\bootstrap;
use app\PvModelBuilder;
use app\PvModelBuilderClassicEdition;
use app\PvModelBuilderDisplay;
use app\PvDataExtractor;
use app\PvModelBuilderTemplateEdition;
use app\PvModelBuilderPegaseEdition;
use nulib\app\cli\Application;
use nulib\ext\json;
use nulib\ext\yaml;
use nulib\StateException;
use nulib\str;

class ConvertPvJuryApp extends Application {
  const PROJDIR = __DIR__.'/../..';
  const APPCODE = bootstrap::APPCODE;

  const ARGS = [
    "purpose" => "convertir une extraction de PV de jury",
    "usage" => "INPUT.csv [-o OUTPUT.csv]",

    ["-1", "--model-display", "name" => "model", "value" => 1,
      "help" => "Sélectionner le modèle 'affichage individuel' (c'est la valeur par défaut)",
    ],
    ["-2", "--model-classic-edition", "name" => "model", "value" => 2,
      "help" => "Sélectionner le modèle 'édition classique'",
    ],
    ["-3", "--model-pegase-edition", "name" => "model", "value" => 3,
      "help" => "Sélectionner le modèle 'édition PEGASE'",
    ],
    ["-s", "--ises", "args" => 1, "argsdesc" => "ISES",
      "help" => "spécifier l'identifiant de session pour le modèle 'édition classique'
ou les identifiants séparés par des virgules pour le modèle 'édition PEGASE'",
    ],
    ["-c", "--icols", "args" => 1, "argsdesc" => "ICOLS",
      "help" => "spécifier les colonnes séparées par des virgules pour le modèle 'édition PEGASE'",
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
    1 => PvModelBuilderDisplay::class,
    2 => PvModelBuilderClassicEdition::class,
    3 => PvModelBuilderPegaseEdition::class,
  ];

  protected int $model = 1;
  protected $ises = null;
  protected $icols = null;
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
    $pvData = $extractor->extract($csvInput);

    $dumpYaml = $this->dumpYaml;
    $jsonOutput = $this->jsonOutput;
    $csvOutput = $this->csvOutput;
    $xlsxOutput = $this ->xlsxOutput;
    if (!$dumpYaml && $jsonOutput === null && $csvOutput === null && $xlsxOutput === null) {
      $csvOutput = "-";
    }
    $wsdump = $dumpYaml && $csvOutput !== null || $xlsxOutput !== null;

    if ($dumpYaml && !$wsdump) {
      yaml::dump($pvData->data);
    }
    if ($jsonOutput !== null) {
      json::dump($pvData->data, $jsonOutput);
    }
    if ($csvOutput !== null) {
      $class = self::CSV_BUILDERS[$this->model] ?? null;
      if ($class === null) throw StateException::unexpected_state();
      /** @var PvModelBuilder $builder */
      $builder = new $class($pvData);
      $ises = $this->ises;
      if ($ises !== null) {
        $ises = preg_split('/\s*,\s*/', str::trim($ises));
        $builder->setIses($ises);
      }
      $icols = $this->icols;
      if ($icols !== null) {
        $icols = preg_split('/\s*,\s*/', str::trim($icols));
        $builder->setIcols($icols);
      }
      $builder->build($csvOutput);
      if ($dumpYaml) {
        yaml::dump([
          "data" => $pvData->data,
          "ws" => $pvData->ws,
        ]);
      } else {
        $builder->write();
      }
    }
    if($xlsxOutput !== null){
      $builder = new PvModelBuilderTemplateEdition();
      $builder->build($pvData->data, $xlsxOutput)->write();
    }
  }
}
