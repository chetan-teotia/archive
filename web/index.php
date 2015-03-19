<?php

$autoloader = __DIR__.'/../vendor/autoload.php';
if (!file_exists($autoloader)) {
  die('You must run `composer install` in the sample app directory');
}

require($autoloader);

use Slim\Slim;
use Gregwar\Cache\Cache;

use OpenTok\OpenTok;
use OpenTok\Role;
use OpenTok\MediaMode;

// PHP CLI webserver compatibility, serving static files
$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

putenv("API_KEY=xxxx");
putenv("API_SECRET=xxxxx");

// Verify that the API Key and API Secret are defined
if (!(getenv('API_KEY') && getenv('API_SECRET'))) {
    die('You must define an API_KEY and API_SECRET in the run-demo file');
}

// Initialize Slim application
$app = new Slim(array(
    'templates.path' => __DIR__.'/../templates',
    'view' => new \Slim\Views\Twig()
));

// Intialize a cache, store it in the app container
$app->container->singleton('cache', function() {
    return new Cache;
});

// Initialize OpenTok instance, store it in the app contianer
$app->container->singleton('opentok', function () {
    return new OpenTok(getenv('API_KEY'), getenv('API_SECRET'));
});
// Store the API Key in the app container
$app->apiKey = getenv('API_KEY');

// If a sessionId has already been created, retrieve it from the cache
$sessionId = $app->cache->getOrCreate('sessionId', array(), function() use ($app) {
    // If the sessionId hasn't been created, create it now and store it
    $session = $app->opentok->createSession(array(
      'mediaMode' => MediaMode::ROUTED
    ));
    return $session->getSessionId();
});

// Configure routes

$app->get('/', function () use ($app, $sessionId) {
    
$token = $app->opentok->generateToken($sessionId, array(
        'role' => Role::MODERATOR
    ));

$app->render('index.html', array(
        'apiKey' => $app->apiKey,
        'sessionId' => $sessionId,
        'token' => $token
    ));

});

$app->get('/download/:archiveId', function ($archiveId) use ($app) {
    $archive = $app->opentok->getArchive($archiveId);
    $app->redirect($archive->url);
});

$app->get('/start', function () use ($app, $sessionId) {
    
    echo "working";

    $archive = $app->opentok->startArchive($sessionId, "PHP Archiving Sample App");
    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});

$app->get('/stop/:archiveId', function($archiveId) use ($app) {
    $archive = $app->opentok->stopArchive($archiveId);
    $app->response->headers->set('Content-Type', 'application/json');
    echo $archive->toJson();
});


$app->run();
