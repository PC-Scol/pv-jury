<?php
namespace web\pages;

use app\CsvPvModel1Builder;
use app\CsvPvModel2Builder;
use Exception;
use nur\sery\cl;
use nur\sery\os\path;
use nur\sery\text\words;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\APvPage;

class ConvertPage extends APvPage {
  const TITLE = "PV Jury";

  function setup(): void {
    parent::setup();
    $pvData = $this->pvData;
    $this->count = count($pvData->rows);

    $builder = $this->builder = new CsvPvModel1Builder($pvData);
    $sessions = $builder->getSessions();
    $convertfo = $this->convertfo = new FormBasic([
      "method" => "post",
      "schema" => [
        "ises" => ["?int", null, "Session"],
        "order" => ["string", null, "Ordre"],
      ],
      "params" => [
        "convert" => ["control" => "hidden", "value" => 1],
        "ises" => cl::merge([
          "control" => "select",
          "items" => $sessions,
        ], count($sessions) > 1? [
          "no_item_value" => "",
          "no_item_text" => "-- Veuillez choisir la session --",
        ]: null),
        "order" => [
          "control" => "select",
          "items" => [
            [CsvPvModel1Builder::ORDER_NOTE, "Classer par mérite (note)"],
            [CsvPvModel1Builder::ORDER_NOM, "Classer par ordre alphabétique (nom)"],
          ],
        ],
      ],
      "submit" => [
        "Editer le PV",
        "name" => "action",
        "value" => "convert",
        "accesskey" => "s",
      ],
      "submitted_key" => "convert",
      "autoload_params" => true,
    ]);
    $action = false;
    if ($convertfo->isSubmitted()) {
      al::reset();
      if ($convertfo["ises"] !== null) {
        $action = true;
      } else {
        al::error("Vous devez choisir la session");
        $this->dispatchAction(false);
      }
    }
    if (!$action) {
      $tbuilder = new CsvPvModel2Builder();
      $tbuilder->compute($pvData);
      $this->tbuilder = $tbuilder;
    }
  }

  private int $count;

  private CsvPvModel1Builder $builder;

  protected Form $convertfo;

  const VALID_ACTIONS = ["convert"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $builder = $this->builder;
    $convertfo = $this->convertfo;
    $builder->setIses($convertfo["ises"]);
    $builder->setOrder($convertfo["order"]);
    $output = path::filename($this->pvData->origname);
    $output = path::ensure_ext($output, ".xlsx", ".csv");
    try {
      $builder->build($output)->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
    }
    page::redirect(true);
  }

  protected CsvPvModel2Builder $tbuilder;

  function print(): void {
    $title = null;
    foreach (array_filter($this->pvData->title) as $line) {
      if ($title === null) {
        $title = [$line];
      } else {
        $title[] = [
          "<br/>",
          v::tag("small", $line),
        ];
      }
    }
    vo::h3($title);
    vo::h4(words::q($this->count, "l'étudiant#s dans ce fichier|m"));
    vo::p([
      "Afficher ",
      v::a("le détail des dossiers étudiants", page::bu(ViewPage::class, [
        "n" => $this->name,
      ])),
      " tel que mentionnés dans le fichier importé",
    ]);

    al::print();
    $this->convertfo->print();
  }
}
