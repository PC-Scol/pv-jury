<?php
namespace app\cli;

use app\PvDataExtractor;
use app\PvModelBuilder;
use app\PvModelBuilderClassicEdition;
use app\PvModelBuilderPegaseEdition;
use nulib\app\cli\Application;
use nulib\ext\json;
use nulib\ext\yaml;
use nulib\StateException;
use nulib\str;

class ConvertPvJuryApp extends Application {
  const ARGS = [
    "purpose" => "convertir une extraction de PV de jury",
    "usage" => "INPUT.csv [-o OUTPUT.csv]",

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
    ["-t", "--types", "args" => 1, "argsdesc" => "TYPES",
      "help" => "spécifier les types de colonnes séparées par des virgules pour le modèle 'édition PEGASE'",
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
    ["args" => "file", "name" => "args"],
  ];

  const CSV_BUILDERS = [
    2 => PvModelBuilderClassicEdition::class,
    3 => PvModelBuilderPegaseEdition::class,
  ];

  protected int $model = 1;
  protected $ises = null;
  protected $types = null;
  protected bool $dumpYaml = false;
  protected ?string $jsonOutput = null;
  protected ?string $csvOutput = null;

  function main() {
    $args = $this->args;
    $csvInput = $args[0] ?? null;
    if (!$args || !$csvInput) self::die("Vous devez spécifier le fichier en entrée");

    $extractor = new PvDataExtractor();
    $pvData = $extractor->extract($csvInput);

    $dumpYaml = $this->dumpYaml;
    $jsonOutput = $this->jsonOutput;
    $csvOutput = $this->csvOutput;
    if (!$dumpYaml && $jsonOutput === null && $csvOutput === null) {
      $csvOutput = "-";
    }
    $wsdump = $dumpYaml && $csvOutput !== null;

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
      $types = $this->types;
      if ($types !== null) {
        $types = preg_split('/\s*,\s*/', str::trim($types));
        $builder->setTypes($types);
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
  }
}
