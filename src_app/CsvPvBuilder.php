<?php
namespace app;

use nulib\cl;
use nulib\ext\tab\SsBuilder;
use nulib\file;
use nulib\file\tab\IBuilder;
use nulib\ValueException;

abstract class CsvPvBuilder implements IPvBuilder {
  function __construct(?PvData $pvData=null) {
    $this->pvData = $pvData;
  }

  abstract protected function verifixPvData(PVData $pvData): void;

  protected ?PvData $pvData;

  function setPvData(PvData $pvData): void {
    if (!$pvData->verifixed) {
      $this->verifixPvData($pvData);
      $pvData->verifixed = true;
    }
    $this->pvData = $pvData;
  }

  protected function ensurePvData(?PvData &$pvData): void {
    $pvData ??= $this->pvData;
    ValueException::check_null($pvData, "pvData");
    $this->setPvData($pvData);
  }

  protected ?IBuilder $builder = null;

  protected $output = null;

  abstract function compute(?PvData $pvData=null): static;

  protected abstract function writeRows(?PvData $pvData=null): void;

  protected function getBuilderParams(): ?array {
    return null;
  }

  function build($output, ?PvData $pvData=null): self {
    $this->ensurePvData($pvData);
    $this->compute();

    $this->output = $output;
    $params = cl::merge($this->getBuilderParams(), [
      "use_headers" => false,
    ]);
    $this->builder = SsBuilder::with($output, $params);
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
