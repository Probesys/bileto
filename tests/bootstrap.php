<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

// Reset the database before running the tests
$kernel = new \App\Kernel('test', true);
$kernel->boot();

$application = new Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
$application->setAutoExit(false);

$commands = [
    'doctrine:database:drop --force --if-exists',
    'doctrine:database:create',
    'doctrine:schema:create',
];
foreach ($commands as $command) {
    $input = new \Symfony\Component\Console\Input\StringInput($command);
    $result = $application->run($input);
    if ($result !== 0) {
        exit($result);
    }
}

$kernel->shutdown();
