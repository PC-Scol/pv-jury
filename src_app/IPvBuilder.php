<?php
namespace app;

interface IPvBuilder {
  function build(array $data, $output): self;

  function write(): void;

  function send(bool $exit=true): void;
}
