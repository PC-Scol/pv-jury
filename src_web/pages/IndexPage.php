<?php
namespace web\pages;

use nur\v\vo;
use web\init\ANavigablePage;

class IndexPage extends ANavigablePage {
  const TITLE = "PV Jury";

  function print(): void {
    vo::h1(self::TITLE);
  }
}
