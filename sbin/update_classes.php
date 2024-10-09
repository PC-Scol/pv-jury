#!/usr/bin/php
<?php
require(__DIR__.'/../vendor/autoload.php');

use nur\php\UpdateClassesApp;

UpdateClassesApp::run(new class extends UpdateClassesApp {
  const MAPPINGS = [
    "src" => [
      "package" => "app\\",
      "path" => __DIR__."/../src_app",
      "classes" => [
      ],
    ],
  ];
});
