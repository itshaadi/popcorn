<?php
set_time_limit(0);
require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once '/vendor/rmccue/requests/library/Requests.php';
Requests::register_autoloader();
use Slim\Slim;
use API\Application;

$config = array();

$app = new Application(array('mode' => 'development'));

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'log.level' => \Slim\Log::WARN,
        'debug' => false
    ));
});


// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
    $app->config(array(
        'log.enable' => true,
        'log.level' => \Slim\Log::DEBUG,
        'debug' => true
    ));
});

// Get log writer
$log = $app->getLog();

//Middlewares
$app->add(new API\Middleware\JSON('/v1'));



