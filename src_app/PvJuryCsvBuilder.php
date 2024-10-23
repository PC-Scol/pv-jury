<?php
namespace app;

use nur\sery\cl;
use nur\sery\ext\spreadsheet\SsBuilder;
use nur\sery\file;
use nur\sery\file\csv\IBuilder;

class PvJuryCsvBuilder {
  private ?IBuilder $builder = null;

  private $output = null;

  function build(array $data, $output): self {
    $builder = $this->builder = SsBuilder::with($output, [
      "use_headers" => false,
    ]);
    $this->output = $output;
    foreach ($data["document"]["title"] as $line) {
      $builder->write([$line]);
    }

    $builder->write([]);
    foreach ($data["promo"]["headers"] as $row) {
      $builder->write($row);
    }
    foreach ($data["promo"]["body"] as $row) {
      $builder->write($row);
    }

    $builder->write([]);
    $prefix = [null];
    foreach ($data["stats"]["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($data["stats"]["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }

    $builder->write([]);
    $prefix = [null];
    if ($data["config"]["have_gpt"]) $prefix[] = null;
    foreach ($data["totals"]["headers"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    foreach ($data["totals"]["body"] as $row) {
      $builder->write(cl::merge($prefix, $row));
    }
    $builder->build();
    return $this;
  }

  function write(): void {
    $this->builder->copyTo(file::writer($this->output, "w+b"), true);
    $this->builder = null;
    $this->output = null;
  }

  function send(bool $exit=true): void {
    $this->builder->sendFile();
    if ($exit) exit();
  }
}
