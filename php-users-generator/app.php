<?php
require __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/GenerateCommand.php';

use Symfony\Component\Console\Application;

$application = new Application();

$command = new GenerateCommand(null, 'webserver', 80, 'api/visit');

$application->add($command);
$application->run();
