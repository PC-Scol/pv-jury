<?php
namespace app;

/**
 * Class PvData
 *
 * @property-read string origname
 * @property-read string name
 * @property-read string date
 * @property-read array title
 */
class PvData {
  function __construct(array $data) {
    $this->data = $data;
  }

  public array $data;

  function __get($name) {
    switch ($name) {
    default:
      return $this->data[$name];
    }
  }

  public ?array $ws = [];
}
