<?php
namespace web\pages;

use nur\v\vp\AInitAuthzPage;
use nur\v\vp\TAuthzLoginPage;

class LoginPage extends AInitAuthzPage {
  use TAuthzLoginPage;

  const TITLE = "Connexion PV Jury";
}
