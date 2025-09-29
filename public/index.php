<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Controllers\GameController;
use App\Controllers\PageController;
use App\Controllers\StreamController;
use App\Http\Request;
use App\Router;
use App\Services\GameService;
use App\Services\ReminderService;
use App\Services\TranslationService;
use App\Storage\GameRepository;
use App\Http\Response;

require __DIR__ . '/../app/Bootstrap.php';

$config = require __DIR__ . '/../config.php';
$bootstrap = Bootstrap::init($config);
$db = $bootstrap['db'];

$request = Request::fromGlobals();

$repository = new GameRepository($db);
$translation = new TranslationService();
$gameService = new GameService($repository);
$reminder = new ReminderService($db); // reserved for future use
$pageController = new PageController($config, $repository, $gameService, $translation);
$gameController = new GameController($config, $gameService, $repository, $translation);
$streamController = new StreamController($repository);
$router = new Router($pageController, $gameController, $streamController);

try {
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    error_log($e->getMessage());
    $response = Response::json(['error' => 'server_error'], 500);
}

$response->send();
