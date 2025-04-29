<?php
namespace app;

use nulib\str;

/**
 * Class PvData
 *
 * @property-read string origname
 * @property-read string name
 * @property-read string date
 * @property-read array gpts
 * @property-read array objs
 * @property-read array sesCols
 * @property-read array|null ctlCols
 * @property-read array title
 * @property-read array headers
 * @property-read array rows
 */
class PvData {
  function __construct(array $data) {
    $this->data = $data;
  }

  public array $data;

  function __get($name) {
    switch ($name) {
    default:
      $name = str::camel2us($name);
      return $this->data[$name] ?? null;
    }
  }

  public ?array $ws = [];
}
