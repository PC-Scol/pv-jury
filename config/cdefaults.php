<?php
namespace app\config;

use nur\v\bs3\Bs3IconManager;
use web\pages\IndexPage;

class cdefaults {
  const APP = [
    "debug" => false,
    "trace_sql" => false,

    "menu" => [
      "brand" => ["&nbsp;PV Jury"],
      "items" => [
        [[Bs3IconManager::UPLOAD[0]." Importer"], IndexPage::class, "accesskey" => "a"],
      ],
    ],
  ];
}
