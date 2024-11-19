<?php
namespace web\init;

use app\PvData;
use app\pvs;
use nur\sery\cl;
use nur\sery\web\params\F;
use nur\v\page;
use web\pages\IndexPage;

class APvPage extends ANavigablePage {
  const CONTAINER_OPTIONS = [
    "container" => "fluid",
  ];
  function NAVBAR_OPTIONS(): ?array {
    return cl::merge(parent::NAVBAR_OPTIONS(), [
      "container" => "fluid",
    ]);
  }

  function setup(): void {
    $name = F::get("n");
    $data = pvs::json_data($name);
    if ($data === null) page::redirect(IndexPage::class);
    $this->name = $name;
    $this->pvData = new PvData($data);
  }

  protected string $name;

  protected PvData $pvData;
}
