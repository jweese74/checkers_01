<?php

namespace App\Domain;

class Rules
{
    public static function initialBoard(): array
    {
        $board = array_fill(0, 8, array_fill(0, 8, '.'));
        for ($r = 0; $r < 3; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ((($r + $c) & 1) === 1) {
                    $board[$r][$c] = 'b';
                }
            }
        }
        for ($r = 5; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ((($r + $c) & 1) === 1) {
                    $board[$r][$c] = 'r';
                }
            }
        }
        return $board;
    }

    public static function initialState(?string $redName = null, ?string $blackName = null): array
    {
        return [
            'board' => self::initialBoard(),
            'turn' => 'r',
            'names' => ['r' => $redName ?: 'Red', 'b' => $blackName ?: 'Black'],
            'must_continue' => null,
            'history' => [],
            'halfmove' => 0,
            'winner' => null,
            'draw' => false,
            'mode' => 'shared',
            'error' => null,
        ];
    }

    public static function legalMoves(array $state): array
    {
        $board = $state['board'];
        $side = $state['turn'];
        $must = $state['must_continue'];
        $moves = [];
        $captures = [];

        $startSquares = [];
        if ($must) {
            $startSquares[] = [$must['r'], $must['c']];
        } else {
            for ($r = 0; $r < 8; $r++) {
                for ($c = 0; $c < 8; $c++) {
                    if (self::isFriend($board[$r][$c], $side)) {
                        $startSquares[] = [$r, $c];
                    }
                }
            }
        }

        foreach ($startSquares as [$r, $c]) {
            $piece = $board[$r][$c];
            if ($piece === '.') {
                continue;
            }
            foreach (self::dirsFor($piece) as [$dr, $dc]) {
                $mr = $r + $dr;
                $mc = $c + $dc;
                $jr = $r + 2 * $dr;
                $jc = $c + 2 * $dc;
                if (self::inBounds($jr, $jc) && self::isEnemy($board[$mr][$mc] ?? '.', $side) && ($board[$jr][$jc] ?? '') === '.') {
                    $captures[] = [
                        'from' => [$r, $c],
                        'to' => [$jr, $jc],
                        'capture' => [[$mr, $mc]],
                    ];
                }
            }
            if (!$must) {
                foreach (self::dirsFor($piece) as [$dr, $dc]) {
                    $nr = $r + $dr;
                    $nc = $c + $dc;
                    if (self::inBounds($nr, $nc) && ($board[$nr][$nc] ?? '') === '.') {
                        $moves[] = ['from' => [$r, $c], 'to' => [$nr, $nc], 'capture' => []];
                    }
                }
            }
        }

        if (!empty($captures)) {
            $expanded = [];
            foreach ($captures as $cap) {
                self::expandCaptureSequences($board, $side, $cap, $expanded);
            }
            return $expanded;
        }
        return $moves;
    }

    public static function applyMove(array $state, array $request): MoveResult
    {
        $legal = self::legalMoves($state);
        $match = null;
        foreach ($legal as $move) {
            if ($move['from'] === $request['from'] && $move['to'] === $request['to']) {
                $match = $move;
                break;
            }
        }
        if ($match === null) {
            $message = 'invalid_move';
            foreach ($legal as $candidate) {
                if (!empty($candidate['capture'])) {
                    $message = 'must_capture';
                    break;
                }
            }
            $status = $message === 'must_capture' ? 'must_capture' : 'invalid';
            return new MoveResult($state, $message, null, [], $status);
        }

        $board = $state['board'];
        [$fr, $fc] = $match['from'];
        [$tr, $tc] = $match['to'];
        $piece = $board[$fr][$fc];
        $isCapture = !empty($match['capture']);

        $board[$fr][$fc] = '.';
        foreach ($match['capture'] as [$cr, $cc]) {
            $board[$cr][$cc] = '.';
        }

        $promoted = false;
        if ($piece === 'r' && $tr === 0) {
            $piece = 'R';
            $promoted = true;
        }
        if ($piece === 'b' && $tr === 7) {
            $piece = 'B';
            $promoted = true;
        }
        $board[$tr][$tc] = $piece;

        $state['board'] = $board;
        $state['history'][] = [
            'from' => $match['from'],
            'to' => $match['to'],
            'capture' => $match['capture'],
            'piece' => $piece,
        ];
        $state['move_count'] = ($state['move_count'] ?? 0) + 1;

        if ($isCapture || $promoted) {
            $state['halfmove'] = 0;
        } else {
            $state['halfmove'] = ($state['halfmove'] ?? 0) + 1;
        }

        $state['last_move'] = [
            'from' => $match['from'],
            'to' => $match['to'],
            'captured' => $match['capture'],
        ];

        if ($isCapture && !$promoted) {
            $temp = $state;
            $temp['must_continue'] = ['r' => $tr, 'c' => $tc];
            $further = self::legalMoves($temp);
            foreach ($further as $next) {
                if ($next['from'] === [$tr, $tc] && !empty($next['capture'])) {
                    $state['must_continue'] = ['r' => $tr, 'c' => $tc];
                    return new MoveResult($state, 'must_continue', $state['last_move'], $match['capture'], 'must_continue');
                }
            }
        }

        $state['must_continue'] = null;
        $state['turn'] = self::enemyOf($state['turn']);
        $state['error'] = null;

        $opponent = $state['turn'];
        $hasOpponentPiece = false;
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if (self::isFriend($board[$r][$c], $opponent)) {
                    $hasOpponentPiece = true;
                    break 2;
                }
            }
        }
        $status = 'ok';
        if (!$hasOpponentPiece) {
            $state['winner'] = self::enemyOf($opponent);
            $status = 'finished';
        } else {
            $oppMoves = self::legalMoves($state);
            if (empty($oppMoves)) {
                $state['winner'] = self::enemyOf($opponent);
                $status = 'finished';
            }
        }

        if (($state['halfmove'] ?? 0) >= 50) {
            $state['draw'] = true;
            $status = 'draw';
        }

        return new MoveResult($state, null, $state['last_move'], $match['capture'], $status);
    }

    private static function dirsFor(string $piece): array
    {
        $isKing = ($piece === 'R' || $piece === 'B');
        if ($isKing) {
            return [[-1, -1], [-1, 1], [1, -1], [1, 1]];
        }
        return $piece === 'r' ? [[-1, -1], [-1, 1]] : [[1, -1], [1, 1]];
    }

    private static function enemyOf(string $side): string
    {
        return $side === 'r' ? 'b' : 'r';
    }

    private static function isEnemy(string $piece, string $side): bool
    {
        if ($piece === '.' || $piece === '') {
            return false;
        }
        return $side === 'r' ? ($piece === 'b' || $piece === 'B') : ($piece === 'r' || $piece === 'R');
    }

    private static function isFriend(string $piece, string $side): bool
    {
        if ($piece === '.' || $piece === '') {
            return false;
        }
        return $side === 'r' ? ($piece === 'r' || $piece === 'R') : ($piece === 'b' || $piece === 'B');
    }

    private static function inBounds(int $r, int $c): bool
    {
        return $r >= 0 && $r < 8 && $c >= 0 && $c < 8;
    }

    private static function expandCaptureSequences(array $board, string $side, array $move, array &$out): void
    {
        $clone = $board;
        [$fr, $fc] = $move['from'];
        [$tr, $tc] = $move['to'];
        $piece = $clone[$fr][$fc];
        $clone[$fr][$fc] = '.';
        foreach ($move['capture'] as [$cr, $cc]) {
            $clone[$cr][$cc] = '.';
        }
        $promoted = false;
        if ($piece === 'r' && $tr === 0) {
            $piece = 'R';
            $promoted = true;
        }
        if ($piece === 'b' && $tr === 7) {
            $piece = 'B';
            $promoted = true;
        }
        $clone[$tr][$tc] = $piece;

        if ($promoted) {
            $out[] = $move;
            return;
        }

        $further = [];
        foreach (self::dirsFor($piece) as [$dr, $dc]) {
            $mr = $tr + $dr;
            $mc = $tc + $dc;
            $jr = $tr + 2 * $dr;
            $jc = $tc + 2 * $dc;
            if (self::inBounds($jr, $jc) && self::isEnemy($clone[$mr][$mc] ?? '.', $side) && ($clone[$jr][$jc] ?? '') === '.') {
                $further[] = [
                    'from' => [$tr, $tc],
                    'to' => [$jr, $jc],
                    'capture' => [[$mr, $mc]],
                ];
            }
        }
        if (empty($further)) {
            $out[] = $move;
        } else {
            foreach ($further as $next) {
                $merged = [
                    'from' => $move['from'],
                    'to' => $next['to'],
                    'capture' => array_merge($move['capture'], $next['capture']),
                ];
                self::expandCaptureSequences($clone, $side, $merged, $out);
            }
        }
    }
}
