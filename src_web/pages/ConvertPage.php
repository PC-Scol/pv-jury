<?php
namespace web\pages;

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
use nur\v\bs3\fo\FormInline;
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
        if ($data) {
          $valid = true;
          $this->pvData = new PvData($data);
        }
      }
    }
    if (!$valid) page::redirect(IndexPage::class);

    $convertfo = $this->convertfo = new FormInline([
      "upload" => true,
      "params" => [
        "convert" => ["control" => "hidden", "value" => 1],
      ],
      "submit" => [
        "Convertir",
        "name" => "action",
        "value" => "convert",
        "accesskey" => "s",
      ],
      "submitted_key" => "convert",
      "autoload_params" => true,
    ]);
    if ($convertfo->isSubmitted()) {
      al::reset();
    }
  }

  private PvData $pvData;

  /** @var Form */
  protected $convertfo;

  const VALID_ACTIONS = ["convert"];
  const ACTION_PARAM = "action";

  function convertAction() {
    page::more_time();
    $pvData = $this->pvData;
    $output = path::filename($pvData->origname);
    $output = path::ensure_ext($output, ".xlsx", ".csv");

    $builder = new CsvPvModel2Builder();
    try {
      $builder->build($pvData, $output)->send();
    } catch (Exception $e) {
      al::error($e->getMessage());
    }
    page::redirect(true);
  }

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
  }
}
