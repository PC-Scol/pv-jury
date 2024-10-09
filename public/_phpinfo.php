<?php
require __DIR__.'/../vendor/autoload.php';

use nur\config;

config::configure(config::CONFIGURE_INITIAL_ONLY);
if (config::is_debug() || config::is_devel()) phpinfo();
