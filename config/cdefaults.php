<?php
namespace app\config;

use nur\v\bs3\Bs3IconManager;

class cdefaults {
  const APP = [
    "debug" => false,
    "trace_sql" => false,
    "dev_devauth" => false,
    "dev_username" => "jclain",

    "users" => [
      "jclain" => [null, "jephte.clain@univ-reunion.fr", "root"],
    ],
    "default_role" => "user",
    "role_perms" => [
      "user" => ["connect"],
      "admin" => ["connect", "admin"],
      "root" => ["*"],
    ],

    "menu" => [
      "brand" => ["&nbsp;PV Jury"],
      "items" => [
        [[Bs3IconManager::REFRESH[0]." Actualiser"], "", "accesskey" => "a"],
      ],
    ],
  ];

  const DBS = [
    "pv_jury" => [
      "type" => "mysql",
      "name" => "mysql:host=pv-jurydb;dbname=pv_jury;charset=utf8",
      "user" => "pv_jury_int",
      "pass" => "pv_jury",
    ],
  ];
}
