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

  protected ?IBuilder $builder = null;

  protected $output = null;

  protected abstract function compute(PvData $pvData): void;
  protected abstract function writeRows(PvData $pvData): void;

  function build($output, ?PvData $pvData=null): self {
    $pvData ??= $this->pvData;
    ValueException::check_null($pvData, "pvData");

    $this->output = $output;
    $this->builder = SsBuilder::with($output, [
      "use_headers" => false,
    ]);

    $this->compute($pvData);
    $this->writeRows($pvData);

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
