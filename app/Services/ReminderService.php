<?php

namespace App\Services;

use SQLite3;

class ReminderService
{
    public function __construct(private SQLite3 $db)
    {
    }

    public function findPending(int $thresholdSeconds): array
    {
        $limit = time() - $thresholdSeconds;
        $stmt = $this->db->prepare('SELECT id, email_red, email_black FROM games WHERE reminder_sent = 0 AND updated_at <= :limit AND (email_red IS NOT NULL OR email_black IS NOT NULL)');
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $pending = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $pending[] = $row;
        }
        return $pending;
    }

    public function markSent(string $id): void
    {
        $stmt = $this->db->prepare('UPDATE games SET reminder_sent = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->execute();
    }
}
