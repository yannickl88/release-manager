<?php
require __DIR__ . '/vendor/autoload.php';

use App\Command\CleanupCommand;
use App\Command\ReleaseCommand;
use App\Command\InitCommand;
use App\Command\RollbackCommand;
use App\Command\UnlockCommand;
use App\Lock\Locker;
use App\Platform\AbstractPlatform;
use Symfony\Component\Console\Application;

$platform = AbstractPlatform::detect();
$locker = new Locker($platform);

$application = new Application();
$application->add(new InitCommand($platform));
$application->add(new ReleaseCommand($locker, $platform));
$application->add(new UnlockCommand($locker));
$application->add(new RollbackCommand($locker));
$application->add(new CleanupCommand());
$application->run();
