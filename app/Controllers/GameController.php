<?php

namespace App\Controllers;

use App\Domain\MoveResult;
use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Services\GameService;
use App\Services\TranslationService;
use App\Storage\GameRepository;
use InvalidArgumentException;
use RuntimeException;

class GameController
{
    public function __construct(
        private array $config,
        private GameService $gameService,
        private GameRepository $repository,
        private TranslationService $translator
    ) {
    }

    public function create(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::redirect('/');
        }
        try {
            $request->requireCsrf();
            $mode = $request->getMode();
            $lang = $request->getLanguage($this->config['supported_languages'], $this->config['default_language']);
            $nameRed = $request->getName('rname');
            $nameBlack = $request->getName('bname');
            $emailRed = $request->getEmail('email_red');
            $emailBlack = $request->getEmail('email_black');
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            return Response::redirect('/');
        }

        $state = $this->gameService->createGame($mode, $nameRed, $nameBlack, $emailRed, $emailBlack);
        $_SESSION['caps'][$state['id']] = $state['capabilities'];
        $url = '/?action=view&id=' . urlencode($state['id']) . '&lang=' . urlencode($lang);
        return Response::redirect($url, 303);
    }

    public function state(Request $request): Response
    {
        $id = $request->getGameId();
        if (!$id) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $game = $this->repository->getGameById($id);
        if (!$game) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $etag = sha1($game['updated_at'] . $game['turn']);
        $ifNoneMatch = $request->header('If-None-Match');
        $response = Response::json([
            'id' => $game['id'],
            'updated_at' => $game['updated_at'],
            'turn' => $game['turn'],
            'board' => $game['board'],
            'names' => $game['names'],
            'winner' => $game['winner'],
            'draw' => $game['draw'],
            'last_move' => $game['last_move'],
        ]);
        return $response->withEtag($etag, $ifNoneMatch);
    }

    public function move(Request $request): Response
    {
        if (!$request->isPost()) {
            return Response::json(['error' => 'method_not_allowed'], 405);
        }
        try {
            $request->requireCsrf();
        } catch (RuntimeException $e) {
            return Response::json(['error' => 'csrf'], 403);
        }
        $lang = $request->getLanguage($this->config['supported_languages'], $this->config['default_language']);
        try {
            $id = $request->getGameId();
            if (!$id) {
                throw new InvalidArgumentException('Invalid id');
            }
            $from = $request->getMove('from');
            $to = $request->getMove('to');
            $game = $this->gameService->getGame($id);
            if (!$game) {
                throw new InvalidArgumentException('Game not found');
            }
            $cap = $request->getCapability();
            if (!$cap && isset($_SESSION['caps'][$id])) {
                $turn = $game['turn'];
                $cap = $_SESSION['caps'][$id][$turn] ?? null;
            }
            $result = $this->gameService->applyMove($game, $from, $to, $cap);
        } catch (InvalidArgumentException $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'capability_denied') {
                $message = $this->translator->translate($lang, 'cap_required');
                return Response::json(['error' => $message], 403);
            }
            return Response::json(['error' => 'server_error'], 500);
        }

        return $this->formatMoveResponse($lang, $result);
    }

    private function formatMoveResponse(string $lang, MoveResult $result): Response
    {
        $message = $result->message ? $this->translator->translate($lang, $result->message) : null;
        return Response::json([
            'board' => $result->state['board'],
            'turn' => $result->state['turn'],
            'winner' => $result->state['winner'],
            'draw' => $result->state['draw'],
            'names' => $result->state['names'],
            'last_move' => $result->lastMove,
            'captured' => $result->captured,
            'status' => $result->status,
            'message' => $message,
            'updated_at' => $result->state['updated_at'] ?? time(),
        ]);
    }
}
