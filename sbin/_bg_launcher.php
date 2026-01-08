<?php
# Lancer une application en tâche de fond
require __DIR__.'/../vendor/autoload.php';

use cli\BgLauncherApp;
use nulib\app\app;

# chemin vers le lanceur PHP
const NULIB_APP_app_launcher = __DIR__.'/../_cli/.launcher.php';

app::init([
  "projdir" => __DIR__.'/..',
  "projcode" => \app\config\bootstrap::PROJCODE,
]);
BgLauncherApp::run();
