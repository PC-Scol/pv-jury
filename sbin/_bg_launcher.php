<?php
require __DIR__.'/../vendor/autoload.php';
# Lancer une application en tâche de fond

use nulib\app;
use nulib\tools\BgLauncherApp;

# chemin vers le lanceur PHP
const NULIB_APP_app_launcher = __DIR__.'/../_cli/.launcher.php';

app::init([
  "projdir" => __DIR__.'/..',
  "appcode" => \app\config\bootstrap::APPCODE,
]);
BgLauncherApp::run();
