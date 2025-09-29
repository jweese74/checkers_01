<?php

namespace App\Storage;

use SQLite3;
use RuntimeException;

class Db
{
    public static function open(string $path): SQLite3
    {
        $db = new SQLite3($path);
        $db->busyTimeout(5000);
        $db->exec('PRAGMA journal_mode=WAL;');
        $db->exec('PRAGMA synchronous=NORMAL;');
        $db->exec('PRAGMA foreign_keys=ON;');
        self::migrate($db);
        return $db;
    }

    private static function migrate(SQLite3 $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER PRIMARY KEY, applied_at INTEGER NOT NULL)');
        $current = self::currentVersion($db);
        $files = glob(__DIR__ . '/../../migrations/*.sql');
        sort($files);
        foreach ($files as $file) {
            $version = self::versionFromFile($file);
            if ($version === null || $version <= $current) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Failed to read migration ' . $file);
            }
            $db->exec('BEGIN');
            try {
                foreach (self::splitStatements($sql) as $statement) {
                    if (trim($statement) !== '') {
                        $db->exec($statement);
                    }
                }
                $stmt = $db->prepare('INSERT INTO schema_version(version, applied_at) VALUES(:v,:t)');
                $stmt->bindValue(':v', $version, SQLITE3_INTEGER);
                $stmt->bindValue(':t', time(), SQLITE3_INTEGER);
                $stmt->execute();
                $db->exec('COMMIT');
                $current = $version;
            } catch (\Throwable $e) {
                $db->exec('ROLLBACK');
                throw $e;
            }
        }
    }

    private static function currentVersion(SQLite3 $db): int
    {
        $res = $db->query('SELECT COALESCE(MAX(version), 0) AS v FROM schema_version');
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        return $row ? (int)$row['v'] : 0;
    }

    private static function versionFromFile(string $file): ?int
    {
        if (preg_match('/^(\\d+)/', basename($file), $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            if ($char === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inString = !$inString;
            }
            if ($char === ';' && !$inString) {
                $statements[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }
        return $statements;
    }
}
