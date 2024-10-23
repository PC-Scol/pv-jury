<?php
namespace web\pages;

use app\PvJuryCsvBuilder;
use app\PvJuryExtractor;
use Exception;
use nur\sery\file\web\Upload;
use nur\sery\web\uploads;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormInline;
use nur\v\bs3\plugins\formfilePlugin;
use nur\v\page;
use nur\v\vo;
use web\init\ANavigablePage;

class IndexPage extends ANavigablePage {
  const TITLE = "Conversion PV Jury";

  function setup(): void {
    $convertfo = $this->convertfo = new FormInline([
      "upload" => true,
      "params" => [
        "convert" => ["control" => "hidden", "value" => 1],
        "action" => ["control" => "hidden", "value" => "convert"],
        "file" => ["control" => "file",
          "label" => [],
          "btn_label" => "Convertir un fichier",
          "accept" => ".csv",
        ],
      ],
      "autoadd_submit" => false,
      "submitted_key" => "convert",
      "autoload_params" => true,
    ]);
    $this->addPlugin(new formfilePlugin("Conversion de '", "'", formfilePlugin::AUTOSUBMIT_ON_CHANGE));

    if ($convertfo->isSubmitted()) {
      al::reset();
      try {
        /** @var Upload[] $files */
        $this->file = uploads::get("file");
      } catch (Exception $e) {
        $this->dispatchAction(false);
        al::error($e->getMessage());
      }
    }
  }

  /** @var Form */
  protected $convertfo;

  /** @var Upload */
  protected $file;

  const VALID_ACTIONS = ["convert"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    /** @var Upload $file */
    $file = $this->file;
    $extractor = new PvJuryExtractor();
    $builder = new PvJuryCsvBuilder();
    try {
      $data = $extractor->extract($file);
      $builder->build($data, "exemple-pv-de-jury.csv")->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
      page::redirect(true);
    }
    page::redirect(true);
  }

  function print(): void {
    vo::h1(self::TITLE);
    vo::p("Veuillez déposer le fichier édité dans PEGASE. Vous obtiendrez en retour un fichier Excel mis en forme");

    al::print();
    $this->convertfo->print();
  }
}
