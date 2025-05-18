<?php
require __DIR__ . '/vendor/autoload.php';

use App\Command\CleanupCommand;
use App\Command\ReleaseCommand;
use App\Command\InitCommand;
use App\Command\RollbackCommand;
use App\Command\UnlockCommand;
use App\Lock\Locker;
use Symfony\Component\Console\Application;

$locker = new Locker();

$application = new Application();
$application->add(new InitCommand());
$application->add(new ReleaseCommand($locker));
$application->add(new UnlockCommand($locker));
$application->add(new RollbackCommand($locker));
$application->add(new CleanupCommand());
$application->run();
