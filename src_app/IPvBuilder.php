<?php
namespace app;

interface IPvBuilder {
  function build($output, ?PvData $pvData=null): self;

  function write(): void;

  function send(bool $exit=true): void;
}
