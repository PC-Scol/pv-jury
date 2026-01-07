<?php
# Lancer une application en ligne de commande
require __DIR__.'/../vendor/autoload.php';

const NULIB_APP_app_params = [
  "projdir" => __DIR__.'/..',
  "projcode" => \app\config\bootstrap::PROJCODE,
];
require __DIR__.'/../vendor/nulib/base/php/src/app/cli/include-launcher.php';
