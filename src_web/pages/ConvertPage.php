<?php
namespace web\pages;

use app\CsvPvModel1Builder;
use app\CsvPvModel2Builder;
use app\PvData;
use app\pvs;
use Exception;
use nur\sery\cl;
use nur\sery\file;
use nur\sery\os\path;
use nur\sery\web\params\F;
use nur\v\al;
use nur\v\bs3\fo\Form;
use nur\v\bs3\fo\FormBasic;
use nur\v\page;
use nur\v\v;
use nur\v\vo;
use web\init\ANavigablePage;

class ConvertPage extends ANavigablePage {
  const TITLE = "PV Jury";
  const CONTAINER_OPTIONS = [
    "container" => "fluid",
  ];
  function NAVBAR_OPTIONS(): ?array {
    return cl::merge(parent::NAVBAR_OPTIONS(), [
      "container" => "fluid",
    ]);
  }

  function setup(): void {
    $valid = false;
    $name = F::get("n");
    if ($name) {
      $file = pvs::json_file($name);
      if (file_exists($file)) {
        $data = file::reader($file)->decodeJson();
        if ($data) $valid = true;
      }
    }
    if (!$valid) page::redirect(IndexPage::class);
    $pvData = $this->pvData = new PvData($data);
    $builder = $this->builder = new CsvPvModel1Builder($pvData);

    $convertfo = $this->convertfo = new FormBasic([
      "method" => "post",
      "schema" => [
        "ises" => ["int", null, "Session"],
      ],
      "params" => [
        "convert" => ["control" => "hidden", "value" => 1],
        "ises" => [
          "control" => "select",
          "items" => $builder->getSessions(),
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
    if ($convertfo->isSubmitted()) {
      al::reset();
    } else {
      $tbuilder = new CsvPvModel2Builder();
      $tbuilder->compute($pvData);
      $this->tbuilder = $tbuilder;
    }
  }

  private PvData $pvData;

  private CsvPvModel1Builder $builder;

  protected Form $convertfo;

  const VALID_ACTIONS = ["convert"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $builder = $this->builder;
    $builder->setIses($this->convertfo["ises"]);
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

    al::print();
    $this->convertfo->print();

    $this->tbuilder->print();
  }
}
