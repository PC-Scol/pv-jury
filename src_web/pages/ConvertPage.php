<?php
namespace web\pages;

use app\pvs;
use nur\config;
use nur\sery\cl;
use nur\sery\file;
use nur\sery\web\params\F;
use nur\v\page;
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
    $name = F::get("n");
    if (!$name) page::redirect(IndexPage::class);

    $file = pvs::file("$name.json");
    if (!file_exists($file)) page::redirect(IndexPage::class);

    $data = file::reader($file)->decodeJson();
    if (!$data) page::redirect(IndexPage::class);

    $this->data = $data;
    $this->title = $data["document"]["header"];
  }

  private array $data;

  private ?string $title;

  function print(): void {
    vo::h1($this->title);

    $data = $this->data;
  }
}
