<?php
namespace app;

use nur\sery\ext\spreadsheet\SsBuilder;
use nur\sery\file;
use nur\sery\file\csv\IBuilder;
use nur\sery\ValueException;

abstract class CsvPvBuilder implements IPvBuilder {
  function __construct(?PvData $pvData=null) {
    $this->pvData = $pvData;
  }

  protected ?PvData $pvData;

  function setPvData(PvData $pvData) {
    $this->pvData = $pvData;
  }

  protected ?IBuilder $builder = null;

  protected $output = null;

  abstract function compute(?PvData $pvData=null): static;

  protected abstract function writeRows(?PvData $pvData=null): void;

  protected function ensurePvData(?PvData &$pvData): void {
    $pvData ??= $this->pvData;
    ValueException::check_null($pvData, "pvData");
    $this->pvData = $pvData;
  }

  function build($output, ?PvData $pvData=null): self {
    $this->ensurePvData($pvData);
    $this->compute();

    $this->output = $output;
    $this->builder = SsBuilder::with($output, [
      "use_headers" => false,
    ]);
    $this->writeRows();
    $this->builder->build();
    return $this;
  }

  function write(): void {
    $this->builder->copyTo(file::writer($this->output), true);
    $this->builder = null;
    $this->output = null;
  }

  function send(bool $exit=true): void {
    $this->builder->sendFile();
    if ($exit) exit();
  }
}
