<?php

namespace App\Services;

use App\Domain\MoveResult;
use App\Domain\Rules;
use App\Security\Capability;
use App\Storage\GameRepository;
use RuntimeException;

class GameService
{
    public function __construct(private GameRepository $repository)
    {
    }

    public function createGame(string $mode, ?string $nameRed, ?string $nameBlack, ?string $emailRed, ?string $emailBlack): array
    {
        $id = $this->generateGameId();
        $capRed = Capability::generate();
        $capBlack = Capability::generate();
        $state = $this->repository->createGame($id, $mode, $nameRed, $nameBlack, $emailRed, $emailBlack, $capRed, $capBlack);
        $state['capabilities'] = ['r' => $capRed, 'b' => $capBlack];
        return $state;
    }

    public function getGame(string $id): ?array
    {
        return $this->repository->getGameById($id);
    }

    public function applyMove(array $game, array $from, array $to, ?string $capability): MoveResult
    {
        $side = $game['turn'];
        $expected = $game['capabilities'][$side] ?? null;
        if (!Capability::verify($expected, $capability)) {
            throw new RuntimeException('capability_denied');
        }
        $result = Rules::applyMove($game, ['from' => $from, 'to' => $to]);
        $result->state['updated_at'] = time();
        $this->repository->saveState($game['id'], $result->state);
        return $result;
    }

    private function generateGameId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
