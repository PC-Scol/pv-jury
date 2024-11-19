<?php
namespace web\init;

use nur\config;
use nur\v\bs3\plugins\navbarProfilePlugin;
use nur\v\vp\NavigablePage;

class ANavigablePage extends NavigablePage {
  const CSS = ["pv_jury.css?1"];
  const CONTAINER_OPTIONS = [
    "container" => "fluid",
  ];

  const PLUGINS = [navbarProfilePlugin::class];

  function NAVBAR_OPTIONS(): ?array {
    return [
      "class" => config::get_profile(),
      "container" => "fluid",
      "brand" => "<img src='nur-v-bs3/brand.png' width='50' height='50' alt='PV Jury'/>",
      "show_brand" => "asis",
    ];
  }
}
