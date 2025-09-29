<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Security\Csrf;
use App\Services\GameService;
use App\Services\TranslationService;
use App\Storage\GameRepository;

class PageController
{
    public function __construct(
        private array $config,
        private GameRepository $repository,
        private GameService $gameService,
        private TranslationService $translator
    ) {
    }

    public function home(Request $request): Response
    {
        $lang = $request->getLanguage($this->config['supported_languages'], $this->config['default_language']);
        $translations = $this->translator->getAll($lang);
        $recent = $this->repository->getRecentGames();
        $scoreboard = $this->repository->getScoreboard();
        $csrf = Csrf::token();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $content = $this->render('home', compact('lang', 'translations', 'recent', 'scoreboard', 'csrf', 'flash'));
        return Response::html($content);
    }

    public function viewGame(Request $request): Response
    {
        $lang = $request->getLanguage($this->config['supported_languages'], $this->config['default_language']);
        $translations = $this->translator->getAll($lang);
        $id = $request->getGameId();
        if (!$id) {
            return Response::redirect('/');
        }
        $game = $this->gameService->getGame($id);
        if (!$game) {
            $game = $this->repository->bootstrapGame($id);
        }
        $_SESSION['caps'][$id] = $game['capabilities'];
        $csrf = Csrf::token();
        $shareUrl = $this->shareUrl($id, $lang);
        $content = $this->render('game', compact('lang', 'translations', 'game', 'csrf', 'shareUrl'));
        return Response::html($content);
    }

    private function shareUrl(string $id, string $lang): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = $_SERVER['SCRIPT_NAME'] ?? '/';
        $query = http_build_query(['action' => 'view', 'id' => $id, 'lang' => $lang]);
        return $scheme . '://' . $host . $path . '?' . $query;
    }

    private function render(string $view, array $params): string
    {
        extract($params, EXTR_SKIP);
        $nonce = $_SESSION['csp_nonce'] ?? '';
        ob_start();
        include __DIR__ . '/../Views/' . $view . '.php';
        $content = ob_get_clean();
        ob_start();
        include __DIR__ . '/../Views/layout.php';
        return ob_get_clean();
    }
}
