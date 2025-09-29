#!/usr/bin/env php
<?php
declare(strict_types=1);

use App\Bootstrap;
use App\Domain\Rules;
use App\Domain\Serializer;
use App\Http\Request;
use App\Http\Response;
use App\Router;
use App\Services\GameService;
use App\Services\TranslationService;
use App\Storage\GameRepository;
use App\Controllers\PageController;
use App\Controllers\GameController;
use App\Controllers\StreamController;

require __DIR__ . '/../app/Bootstrap.php';

$config = require __DIR__ . '/../config.php';
$config['database_path'] = ':memory:';
$config['error_display'] = true;
$bootstrap = Bootstrap::init($config);
$db = $bootstrap['db'];

$repository = new GameRepository($db);
$translation = new TranslationService();
$gameService = new GameService($repository);

$tests = [];

$tests['rules_initial_setup'] = function (): void {
    $state = Rules::initialState(null, null);
    assert($state['board'][0][1] === 'b');
    assert($state['board'][7][0] === 'r');
    assert($state['turn'] === 'r');
};

$tests['rules_simple_move'] = function () use ($gameService, $repository): void {
    $game = $repository->createGame('test1234', 'shared', null, null, null, null, bin2hex(random_bytes(4)), bin2hex(random_bytes(4)));
    $result = Rules::applyMove($game, ['from' => [5, 0], 'to' => [4, 1]]);
    assert($result->status === 'ok');
};

$tests['serializer_roundtrip'] = function (): void {
    $state = Rules::initialState(null, null);
    $encoded = Serializer::encodeBoard($state['board']);
    $decoded = Serializer::decodeBoard($encoded);
    assert($decoded[0][1] === 'b');
    $history = [['from' => [5, 0], 'to' => [4, 1]]];
    $encHistory = Serializer::encodeHistory($history);
    $decHistory = Serializer::decodeHistory($encHistory);
    assert($decHistory[0]['from'][0] === 5);
};

$tests['repository_cycle'] = function () use ($repository): void {
    $state = $repository->createGame('cycle1', 'shared', 'Alice', 'Bob', null, null, bin2hex(random_bytes(4)), bin2hex(random_bytes(4)));
    $state['board'][5][0] = '.';
    $state['board'][4][1] = 'r';
    $state['last_move'] = ['from' => [5, 0], 'to' => [4, 1], 'captured' => []];
    $state['move_count'] = 1;
    $repository->saveState('cycle1', $state);
    $loaded = $repository->getGameById('cycle1');
    assert($loaded !== null);
    assert($loaded['board'][4][1] === 'r');
};

$tests['router_dispatch'] = function () use ($config, $repository, $gameService, $translation): void {
    $page = new PageController($config, $repository, $gameService, $translation);
    $game = new GameController($config, $gameService, $repository, $translation);
    $stream = new StreamController($repository);
    $router = new Router($page, $game, $stream);
    $req = new Request(['action' => 'home'], [], ['REQUEST_METHOD' => 'GET']);
    $res = $router->dispatch($req);
    assert($res instanceof Response);
};

$allPassed = true;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "[PASS] $name\n";
    } catch (Throwable $e) {
        $allPassed = false;
        echo "[FAIL] $name: " . $e->getMessage() . "\n";
    }
}
exit($allPassed ? 0 : 1);