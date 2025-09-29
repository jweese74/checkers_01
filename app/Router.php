<?php

namespace App;

use App\Controllers\GameController;
use App\Controllers\PageController;
use App\Controllers\StreamController;
use App\Http\Request;
use App\Http\Response;
use InvalidArgumentException;

class Router
{
    public function __construct(
        private PageController $pageController,
        private GameController $gameController,
        private StreamController $streamController
    ) {
    }

    public function dispatch(Request $request): Response
    {
        $action = $request->getAction();
        return match ($action) {
            'home' => $this->pageController->home($request),
            'view' => $this->pageController->viewGame($request),
            'new' => $this->gameController->create($request),
            'move' => $this->gameController->move($request),
            'state' => $this->gameController->state($request),
            'stream' => $this->streamController->stream($request),
            default => throw new InvalidArgumentException('Unhandled action'),
        };
    }
}
