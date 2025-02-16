<?php
namespace web\pages;

use app\CsvPvModel2Builder;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\bs3\vc\CDatatable;
use nur\v\ly;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class ViewPage extends APvPage {
  const TITLE = "Dossier étudiant";
  const PLUGINS = [
    [CDatatable::class, null, ["dtauto" => true]],
  ];
  const MENU = [
    "brand" => ["&nbsp;Consultation PV Jury"],
  ];

  function setup(): void {
    parent::setup();

    $pvData = $this->pvData;

    $rows = [];
    foreach ($pvData->rows as $row) {
      $codApr = $row[0];
      $rows[$codApr] = [
        "Code apprenant" => $codApr,
        "Nom" => $row[1],
        "Prénom" => $row[2],
      ];
    }
    $this->rows = $rows;

    $this->searchfo = $searchfo = new FormBasic([
      "schema" => [
        "n" => ["string", null],
        "a" => ["?string", null, "Code apprenant"],
      ],
      "params" => [
        "n" => ["control" => "hidden"],
        "a" => [
          "control" => "text",
          "accesskey" => "q",
        ],
      ],
      "submit" => [
        "Afficher",
        "accesskey" => "s",
      ],
      "autoload_params" => true,
    ]);

    al::reset();
    $codApr = $searchfo["a"];
    if (isset($rows[$codApr])) {
      $builder = new CsvPvModel2Builder();
      $builder->setCodApr($codApr);
      $builder->compute($pvData);
      $this->builder = $builder;
    } elseif ($codApr) {
      al::error("Code apprenant non trouvé dans ce fichier");
    }
  }

  private array $rows;

  private Form $searchfo;

  private ?CsvPvModel2Builder $builder = null;

  function print(): void {
    ly::row(); ly::col(12);
    $pvData = $this->pvData;
    vo::h4([
      join("<br/>", array_filter($pvData->title)),
    ]);

    vo::p([
      "Saisissez un numéro d'étudiant pour lequel vous voulez afficher le dossier:"
    ]);
    al::print();
    $this->searchfo->print();

    if ($this->builder !== null) {
      ly::row(); ly::col(12);
      $this->builder->print();
    }

    ly::row(["class" => "gap-row"]); ly::col(12);
    new CDatatable($this->rows, [
      "table_class" => "table-bordered table-auto",
      "before_table" => v::unless($this->builder, v::p([
        "Vous pouvez aussi sélectionner directement l'étudiant dans la liste ci-dessous:",
      ])),
      "col_func" => function($vs, $value, $col, $index, $row) {
        return v::a($vs, page::bu(ViewPage::class, [
          "n" => $this->name,
          "a" => $row["Code apprenant"],
        ]));
      },
      "autoprint" => true,
    ]);
  }
}
