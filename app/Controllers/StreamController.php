<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Storage\GameRepository;

class StreamController
{
    public function __construct(private GameRepository $repository)
    {
    }

    public function stream(Request $request): Response
    {
        $id = $request->getGameId();
        if (!$id) {
            return Response::json(['error' => 'not_found'], 404);
        }
        $retry = 3000;
        return Response::sse(function (Response $response) use ($id, $retry): void {
            ignore_user_abort(true);
            $lastSent = 0;
            $start = time();
            while (time() - $start < 60) {
                $game = $this->repository->getGameById($id);
                if (!$game) {
                    $response->writeEvent('end', ['reason' => 'not_found'], $retry);
                    break;
                }
                if ($game['updated_at'] > $lastSent) {
                    $lastSent = $game['updated_at'];
                    $response->writeEvent('state', [
                        'updated_at' => $game['updated_at'],
                        'turn' => $game['turn'],
                    ], $retry);
                }
                sleep(2);
            }
        });
    }
}
