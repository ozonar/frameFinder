<?php

require_once 'vendor/autoload.php';

$loader = new \Aura\Autoload\Loader;
$loader->register();
$loader->addPrefix('Record', __DIR__ . '/src/record');
$loader->addPrefix('Model', __DIR__ . '/src/model');

use Model\Database;

Database::initializeActiveRecord();
