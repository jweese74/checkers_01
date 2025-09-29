<?php

namespace App\Domain;

class Serializer
{
    public static function encodeBoard(array $board): string
    {
        $rows = array_map(fn($row) => implode('', $row), $board);
        return implode('\n', $rows);
    }

    public static function decodeBoard(string $encoded): array
    {
        $rows = explode('\n', trim($encoded));
        $board = [];
        foreach ($rows as $row) {
            $board[] = str_split(str_pad($row, 8, '.', STR_PAD_RIGHT));
        }
        while (count($board) < 8) {
            $board[] = array_fill(0, 8, '.');
        }
        return array_map(fn($row) => array_slice($row, 0, 8), $board);
    }

    public static function encodeLastMove(?array $move): ?string
    {
        return $move ? json_encode($move, JSON_UNESCAPED_UNICODE) : null;
    }

    public static function decodeLastMove(?string $encoded): ?array
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $data = json_decode($encoded, true);
        return is_array($data) ? $data : null;
    }

    public static function encodeHistory(array $history): string
    {
        return json_encode($history, JSON_UNESCAPED_UNICODE);
    }

    public static function decodeHistory(string $encoded): array
    {
        $data = json_decode($encoded, true);
        return is_array($data) ? $data : [];
    }
}
