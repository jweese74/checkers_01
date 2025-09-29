<?php

namespace App\Storage;

use App\Domain\Rules;
use App\Domain\Serializer;
use SQLite3;

class GameRepository
{
    public function __construct(private SQLite3 $db)
    {
    }

    public function createGame(string $id, string $mode, ?string $nameRed, ?string $nameBlack, ?string $emailRed, ?string $emailBlack, string $capRed, string $capBlack): array
    {
        $state = Rules::initialState($nameRed, $nameBlack);
        $state['mode'] = $mode;
        $now = time();
        $stmt = $this->db->prepare('INSERT INTO games (id, board, turn, last_move, must_continue, halfmove, history, winner, draw, name_red, name_black, email_red, email_black, cap_red, cap_black, reminder_sent, move_count, mode, created_at, updated_at, completed_at) VALUES (:id,:board,:turn,:last,:must,:half,:history,:winner,:draw,:name_red,:name_black,:email_red,:email_black,:cap_red,:cap_black,0,0,:mode,:created,:updated,NULL)');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':board', Serializer::encodeBoard($state['board']), SQLITE3_TEXT);
        $stmt->bindValue(':turn', $state['turn'], SQLITE3_TEXT);
        $stmt->bindValue(':last', null, SQLITE3_NULL);
        $stmt->bindValue(':must', json_encode($state['must_continue']), SQLITE3_TEXT);
        $stmt->bindValue(':half', $state['halfmove'], SQLITE3_INTEGER);
        $stmt->bindValue(':history', Serializer::encodeHistory($state['history']), SQLITE3_TEXT);
        $stmt->bindValue(':winner', null, SQLITE3_NULL);
        $stmt->bindValue(':draw', 0, SQLITE3_INTEGER);
        if ($nameRed === null) {
            $stmt->bindValue(':name_red', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':name_red', $nameRed, SQLITE3_TEXT);
        }
        if ($nameBlack === null) {
            $stmt->bindValue(':name_black', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':name_black', $nameBlack, SQLITE3_TEXT);
        }
        if ($emailRed === null) {
            $stmt->bindValue(':email_red', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':email_red', $emailRed, SQLITE3_TEXT);
        }
        if ($emailBlack === null) {
            $stmt->bindValue(':email_black', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':email_black', $emailBlack, SQLITE3_TEXT);
        }
        $stmt->bindValue(':cap_red', $capRed, SQLITE3_TEXT);
        $stmt->bindValue(':cap_black', $capBlack, SQLITE3_TEXT);
        $stmt->bindValue(':mode', $mode, SQLITE3_TEXT);
        $stmt->bindValue(':created', $now, SQLITE3_INTEGER);
        $stmt->bindValue(':updated', $now, SQLITE3_INTEGER);
        $stmt->execute();
        return $state + [
            'id' => $id,
            'last_move' => null,
            'must_continue' => null,
            'halfmove' => 0,
            'history' => [],
            'draw' => false,
            'winner' => null,
            'move_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'mode' => $mode,
            'capabilities' => ['r' => $capRed, 'b' => $capBlack],
            'emails' => ['r' => $emailRed, 'b' => $emailBlack],
        ];
    }

    public function bootstrapGame(string $id): array
    {
        $capRed = bin2hex(random_bytes(16));
        $capBlack = bin2hex(random_bytes(16));
        return $this->createGame($id, 'shared', null, null, null, null, $capRed, $capBlack);
    }

    public function getGameById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
        if (!$row) {
            return null;
        }
        return $this->hydrate($row);
    }

    public function saveState(string $id, array $state): void
    {
        $lastMove = $state['last_move'] ?? null;
        $winner = $state['winner'] ?? null;
        $draw = !empty($state['draw']) ? 1 : 0;
        $completed = $winner || $draw ? time() : null;
        $stmt = $this->db->prepare('UPDATE games SET board=:board, turn=:turn, last_move=:last, must_continue=:must, halfmove=:half, history=:history, winner=:winner, draw=:draw, move_count=:move_count, updated_at=:updated, completed_at=COALESCE(completed_at,:completed) WHERE id=:id');
        $stmt->bindValue(':board', Serializer::encodeBoard($state['board']), SQLITE3_TEXT);
        $stmt->bindValue(':turn', $state['turn'], SQLITE3_TEXT);
        $encodedLast = Serializer::encodeLastMove($lastMove);
        if ($encodedLast === null) {
            $stmt->bindValue(':last', null, SQLITE3_NULL);
        } else {
            $stmt->bindValue(':last', $encodedLast, SQLITE3_TEXT);
        }
        $stmt->bindValue(':must', json_encode($state['must_continue']), SQLITE3_TEXT);
        $stmt->bindValue(':half', $state['halfmove'], SQLITE3_INTEGER);
        $stmt->bindValue(':history', Serializer::encodeHistory($state['history']), SQLITE3_TEXT);
        if ($winner) {
            $stmt->bindValue(':winner', $winner, SQLITE3_TEXT);
        } else {
            $stmt->bindValue(':winner', null, SQLITE3_NULL);
        }
        $stmt->bindValue(':draw', $draw, SQLITE3_INTEGER);
        $stmt->bindValue(':move_count', $state['move_count'] ?? 0, SQLITE3_INTEGER);
        $stmt->bindValue(':updated', time(), SQLITE3_INTEGER);
        if ($completed) {
            $stmt->bindValue(':completed', $completed, SQLITE3_INTEGER);
        } else {
            $stmt->bindValue(':completed', null, SQLITE3_NULL);
        }
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getRecentGames(int $limit = 10): array
    {
        $stmt = $this->db->prepare('SELECT id, name_red, name_black, updated_at, winner, draw FROM games ORDER BY updated_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $games = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $games[] = $row;
        }
        return $games;
    }

    public function getScoreboard(int $limit = 10): array
    {
        $threshold = time() - 90 * 86400;
        $sql = 'SELECT name_red AS name, SUM(CASE WHEN winner = "r" THEN 1 ELSE 0 END) AS wins, SUM(CASE WHEN winner = "b" THEN 1 ELSE 0 END) AS losses, SUM(draw) AS draws, SUM(move_count) AS moves, COUNT(*) AS games
                FROM games WHERE completed_at IS NOT NULL AND completed_at >= :threshold AND name_red IS NOT NULL
                GROUP BY name_red
                UNION ALL
                SELECT name_black AS name, SUM(CASE WHEN winner = "b" THEN 1 ELSE 0 END) AS wins, SUM(CASE WHEN winner = "r" THEN 1 ELSE 0 END) AS losses, SUM(draw) AS draws, SUM(move_count) AS moves, COUNT(*) AS games
                FROM games WHERE completed_at IS NOT NULL AND completed_at >= :threshold AND name_black IS NOT NULL
                GROUP BY name_black';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':threshold', $threshold, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $aggregate = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $name = $row['name'];
            if (!$name) {
                continue;
            }
            if (!isset($aggregate[$name])) {
                $aggregate[$name] = ['name' => $name, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'moves' => 0, 'games' => 0];
            }
            $aggregate[$name]['wins'] += (int)$row['wins'];
            $aggregate[$name]['losses'] += (int)$row['losses'];
            $aggregate[$name]['draws'] += (int)$row['draws'];
            $aggregate[$name]['moves'] += (int)$row['moves'];
            $aggregate[$name]['games'] += (int)$row['games'];
        }
        $filtered = array_filter($aggregate, fn($entry) => $entry['games'] >= 2 && $entry['moves'] >= 10);
        usort($filtered, function ($a, $b) {
            if ($a['wins'] === $b['wins']) {
                return $b['moves'] <=> $a['moves'];
            }
            return $b['wins'] <=> $a['wins'];
        });
        return array_slice(array_values($filtered), 0, $limit);
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => $row['id'],
            'board' => Serializer::decodeBoard($row['board']),
            'turn' => $row['turn'],
            'must_continue' => json_decode($row['must_continue'] ?? 'null', true),
            'halfmove' => (int)$row['halfmove'],
            'history' => Serializer::decodeHistory($row['history']),
            'winner' => $row['winner'] ?: null,
            'draw' => (bool)$row['draw'],
            'names' => ['r' => $row['name_red'] ?: 'Red', 'b' => $row['name_black'] ?: 'Black'],
            'last_move' => Serializer::decodeLastMove($row['last_move']),
            'move_count' => (int)$row['move_count'],
            'mode' => $row['mode'] ?? 'shared',
            'error' => null,
            'created_at' => (int)$row['created_at'],
            'updated_at' => (int)$row['updated_at'],
            'completed_at' => $row['completed_at'] ? (int)$row['completed_at'] : null,
            'emails' => ['r' => $row['email_red'], 'b' => $row['email_black']],
            'capabilities' => ['r' => $row['cap_red'], 'b' => $row['cap_black']],
        ];
    }
}
