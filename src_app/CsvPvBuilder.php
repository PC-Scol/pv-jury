<?php
namespace app;

use nur\sery\ext\spreadsheet\SsBuilder;
use nur\sery\file;
use nur\sery\file\csv\IBuilder;

abstract class CsvPvBuilder implements IPvBuilder {
  protected ?IBuilder $builder = null;

  protected $output = null;

  protected abstract function compute(array &$data): void;
  protected abstract function writeRows(array $data): void;

  function build(array $data, $output): self {
    $this->output = $output;
    $this->builder = SsBuilder::with($output, [
      "use_headers" => false,
    ]);

    $this->compute($data);
    $this->writeRows($data);

    $this->builder->build();
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
