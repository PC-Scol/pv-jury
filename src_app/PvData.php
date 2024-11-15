<?php
namespace app;

class PvData {
  function __construct(array $data) {
    $this->data = $data;
  }

  public array $data;

  public ?array $ws = [];
}
