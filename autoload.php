<?php

require_once 'vendor/autoload.php';

$loader = new \Aura\Autoload\Loader;
$loader->register();
$loader->addPrefix('Model', __DIR__ . '/src/model');
