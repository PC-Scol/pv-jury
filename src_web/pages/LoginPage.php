<?php
namespace web\pages;

use nur\v\vo;
use nur\v\vp\AInitAuthzPage;
use nur\v\vp\TCasLoginPage;

class LoginPage extends AInitAuthzPage {
  use TCasLoginPage;

  const TITLE = "Connexion PV Jury";

  function printTitle(): void {
    vo::h1(["class" => "text-center", q($this->getTitle())]);
  }
}
