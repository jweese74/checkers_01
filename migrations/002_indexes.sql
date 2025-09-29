CREATE INDEX IF NOT EXISTS idx_games_updated_at ON games(updated_at);
CREATE INDEX IF NOT EXISTS idx_games_completed_at ON games(completed_at);
CREATE INDEX IF NOT EXISTS idx_games_winner ON games(winner) WHERE winner IS NOT NULL;
