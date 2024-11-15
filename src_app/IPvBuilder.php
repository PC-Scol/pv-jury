<?php
namespace app;

interface IPvBuilder {
  function build(PvData $pvData, $output): self;

  function write(): void;

  function send(bool $exit=true): void;
}
