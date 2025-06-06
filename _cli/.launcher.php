<?php
require __DIR__.'/../vendor/autoload.php';
# Lancer une application en ligne de commande

const NULIB_APP_app_params = [
  "projdir" => __DIR__.'/..',
  "appcode" => \app\config\bootstrap::APPCODE,
];
require __DIR__.'/../vendor/nulib/base/php/src/app/cli/include-launcher.php';
