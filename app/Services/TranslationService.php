<?php

namespace App\Services;

class TranslationService
{
    private array $translations;

    public function __construct()
    {
        $this->translations = [
            'en' => [
                'title' => 'Checkers',
                'new_game' => 'New Game',
                'your_link' => 'Share this link with your opponent:',
                'red' => 'Red',
                'black' => 'Black',
                'to_move' => '%s to move',
                'invalid_move' => 'Invalid move.',
                'must_capture' => 'A capture is available; you must capture.',
                'must_continue' => 'Multi-jump available: you must continue with the same piece.',
                'game_over' => 'Game over: %s wins',
                'draw' => 'Draw by 50-move rule (no capture or promotion).',
                'language' => 'Idioma / Language',
                'hotseat' => 'Hot-seat (same device)',
                'shared' => 'Online (shared link, turn-based)',
                'set_names' => 'Set Names',
                'name_red' => 'Name (Red)',
                'name_black' => 'Name (Black)',
                'save' => 'Save',
                'resume' => 'Resume Game',
                'copy' => 'Copy',
                'copied' => 'Copied!',
                'help' => 'Rules: 8×8 board, dark squares only. Men move forward diagonally; kings move both ways. Captures are mandatory. Multi-jumps must continue with the same piece. Promotion on far row.',
                'turn_note' => 'Click one of your pieces, then a highlighted destination.',
                'mode' => 'Mode',
                'recent_games' => 'Recent Games',
                'scoreboard' => 'Scoreboard',
                'wins' => 'Wins',
                'losses' => 'Losses',
                'player' => 'Player',
                'draws' => 'Draws',
                'moves' => 'Moves',
                'no_games' => 'No games yet. Start a new one!',
                'cap_required' => 'Capability token required for this move.',
            ],
            'es' => [
                'title' => 'Damas',
                'new_game' => 'Nueva partida',
                'your_link' => 'Comparte este enlace con tu oponente:',
                'red' => 'Rojo',
                'black' => 'Negro',
                'to_move' => 'Juega %s',
                'invalid_move' => 'Movimiento inválido.',
                'must_capture' => 'Hay captura disponible; debes capturar.',
                'must_continue' => 'Hay salto múltiple: debes continuar con la misma ficha.',
                'game_over' => 'Fin de la partida: gana %s',
                'draw' => 'Tablas por regla de 50 jugadas (sin capturas ni coronaciones).',
                'language' => 'Idioma / Language',
                'hotseat' => 'Turnos en el mismo dispositivo',
                'shared' => 'En línea (enlace compartido, por turnos)',
                'set_names' => 'Establecer nombres',
                'name_red' => 'Nombre (Rojo)',
                'name_black' => 'Nombre (Negro)',
                'save' => 'Guardar',
                'resume' => 'Reanudar partida',
                'copy' => 'Copiar',
                'copied' => '¡Copiado!',
                'help' => 'Reglas: tablero 8×8, solo casillas oscuras. Peones avanzan en diagonal; damas se mueven en ambos sentidos. Capturas obligatorias. En saltos múltiples, debes seguir con la misma ficha. Coronación en la última fila.',
                'turn_note' => 'Haz clic en una de tus fichas y luego en un destino resaltado.',
                'mode' => 'Modo',
                'recent_games' => 'Partidas recientes',
                'scoreboard' => 'Clasificación',
                'wins' => 'Victorias',
                'losses' => 'Derrotas',
                'player' => 'Jugador',
                'draws' => 'Tablas',
                'moves' => 'Movimientos',
                'no_games' => 'Aún no hay partidas. ¡Comienza una nueva!',
                'cap_required' => 'Se requiere un token de capacidad para este movimiento.',
            ],
        ];
    }

    public function translate(string $lang, string $key, ...$args): string
    {
        $value = $this->translations[$lang][$key] ?? $key;
        return $args ? sprintf($value, ...$args) : $value;
    }

    public function getAll(string $lang): array
    {
        return $this->translations[$lang] ?? $this->translations['en'];
    }
}
