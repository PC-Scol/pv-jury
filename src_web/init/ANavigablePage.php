<?php
namespace web\init;

use app\config\cdefaults;
use nur\config;
use nur\session;
use nur\v\bs3\plugins\navbarProfilePlugin;
use nur\v\vp\NavigablePage;

class ANavigablePage extends NavigablePage {
  const CSS = ["pv_jury.css?2"];
  const CONTAINER_OPTIONS = [
    "container" => "fluid",
  ];

  const REQUIRE_AUTH = cdefaults::AUTH_ANY;
  const PLUGINS = [navbarProfilePlugin::class];

  function NAVBAR_OPTIONS(): ?array {
    return [
      "class" => config::get_profile(),
      "container" => "fluid",
      "brand" => "<img src='logo.png' height='50' alt='PV Jury'/>",
      "show_brand" => "asis",
    ];
  }

  function afterConfig(): void {
    # il faut TOUJOURS avoir une session. si pas d'authentification, démarrer la
    # session ici
    parent::afterConfig();
    if (!self::REQUIRE_AUTH) session::start();
  }
}
