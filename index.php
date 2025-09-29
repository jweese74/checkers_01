<?php
/**
 * Checkers (American draughts) — single-file PHP + HTML
 * - Minimal connection (no libraries, tiny inline JS/CSS)
 * - Full rules: mandatory captures, multi-jumps, kinging, turn & move validation
 * - Auto-save/Resume via SQLite (games table)
 * - Bilingual EN/ES (toggle or ?lang=en|es)
 *
 * Deploy:
 *   1) Ensure PHP 8+ with SQLite3 enabled.
 *   2) Place this file as checkers.php.
 *   3) Make a writable ./data/ directory (chmod 0775 or 0777 as needed).
 *   4) Visit checkers.php to start a new game; share the link to play asynchronously.
 */

declare(strict_types=1);
ini_set('display_errors', '1'); error_reporting(E_ALL);

// ---------- Configuration ----------
const DB_PATH = __DIR__ . '/data/checkers.sqlite';
if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0775, true);

// ---------- i18n ----------
$lang = ($_GET['lang'] ?? $_POST['lang'] ?? 'en') === 'es' ? 'es' : 'en';
$T = [
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
    'help' =>
      "Rules: 8×8 board, dark squares only. Men move forward diagonally; kings move both ways. Captures are mandatory. Multi-jumps must continue with the same piece. Promotion on far row.",
    'turn_note' => 'Click one of your pieces, then a highlighted destination.',
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
    'help' =>
      "Reglas: tablero 8×8, solo casillas oscuras. Peones avanzan en diagonal; damas se mueven en ambos sentidos. Capturas obligatorias. En saltos múltiples, debes seguir con la misma ficha. Coronación en la última fila.",
    'turn_note' => 'Haz clic en una de tus fichas y luego en un destino resaltado.',
  ],
][$lang];

function t(string $key, ...$args): string {
  global $T; $s = $T[$key] ?? $key; return $args ? sprintf($s, ...$args) : $s;
}

// ---------- Storage ----------
class Store {
  private SQLite3 $db;
  function __construct() {
    $this->db = new SQLite3(DB_PATH);
    $this->db->exec('PRAGMA journal_mode=WAL;');
    $this->db->exec('CREATE TABLE IF NOT EXISTS games (
      id TEXT PRIMARY KEY,
      state TEXT NOT NULL,
      updated_at INTEGER NOT NULL
    )');
  }
  function load(string $id): ?array {
    $stmt = $this->db->prepare('SELECT state FROM games WHERE id=:id');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $res = $stmt->execute(); $row = $res->fetchArray(SQLITE3_ASSOC);
    return $row ? json_decode($row['state'], true) : null;
  }
  function save(string $id, array $state): void {
    $stmt = $this->db->prepare('INSERT INTO games(id,state,updated_at) VALUES (:id,:st,:ts)
      ON CONFLICT(id) DO UPDATE SET state=:st2, updated_at=:ts2');
    $json = json_encode($state, JSON_UNESCAPED_UNICODE);
    $now = time();
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':st', $json, SQLITE3_TEXT);
    $stmt->bindValue(':ts', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':st2', $json, SQLITE3_TEXT);
    $stmt->bindValue(':ts2', $now, SQLITE3_INTEGER);
    $stmt->execute();
  }
}
$store = new Store();

// ---------- Game Logic ----------
/**
 * Board encoding:
 * - '.' empty
 * - 'r' red man, 'R' red king
 * - 'b' black man, 'B' black king
 * Turn: 'r' or 'b'
 */
function initial_board(): array {
  $board = array_fill(0, 8, array_fill(0, 8, '.'));
  // Place Black at top (rows 0..2) on dark squares (r+c odd)
  for ($r=0;$r<3;$r++) for($c=0;$c<8;$c++) if((($r+$c)&1)==1) $board[$r][$c]='b';
  // Place Red at bottom (rows 5..7)
  for ($r=5;$r<8;$r++) for($c=0;$c<8;$c++) if((($r+$c)&1)==1) $board[$r][$c]='r';
  return $board;
}

function new_state(?string $redName=null, ?string $blackName=null): array {
  return [
    'board' => initial_board(),
    'turn' => 'r',                 // Red moves first in many online implementations. If you prefer Black, change here.
    'names' => ['r'=>$redName?:'Red', 'b'=>$blackName?:'Black'],
    'must_continue' => null,       // ['r'=>row,'c'=>col] if mid-multijump
    'history' => [],               // compact move log
    'halfmove' => 0,               // for 50-move draw: increments on non-capturing, non-promotion moves
    'winner' => null,              // 'r' or 'b'
    'draw' => false,
  ];
}

// Directions per side
function dirs_for(string $piece): array {
  $isKing = ($piece==='R'||$piece==='B');
  if ($isKing) return [[-1,-1],[-1,1],[1,-1],[1,1]];
  return ($piece==='r') ? [[-1,-1],[-1,1]] : [[1,-1],[1,1]]; // red moves up (toward 0)
}
function enemy_of(string $side): string { return $side==='r' ? 'b' : 'r'; }
function is_enemy(string $piece, string $side): bool {
  if ($piece==='.'||$piece==='') return false;
  return ($side==='r') ? ($piece==='b'||$piece==='B') : ($piece==='r'||$piece==='R');
}
function is_friend(string $piece, string $side): bool {
  if ($piece==='.'||$piece==='') return false;
  return ($side==='r') ? ($piece==='r'||$piece==='R') : ($piece==='b'||$piece==='B');
}
function in_bounds(int $r,int $c): bool { return $r>=0&&$r<8&&$c>=0&&$c<8; }

function clone_board(array $b){ return array_map(fn($row)=>array_values($row), $b); }

// Generate legal moves (with capture priority and multi-jump enforcement).
function legal_moves(array $state): array {
  $b = $state['board']; $side = $state['turn']; $must = $state['must_continue'];
  $moves = []; $captures = [];

  $startSquares = [];
  if ($must) { $startSquares[] = [$must['r'],$must['c']]; }
  else {
    for($r=0;$r<8;$r++) for($c=0;$c<8;$c++)
      if (is_friend($b[$r][$c], $side)) $startSquares[] = [$r,$c];
  }

  foreach ($startSquares as [$r,$c]) {
    $p = $b[$r][$c]; if ($p==='.') continue;
    // Captures
    foreach (dirs_for($p) as [$dr,$dc]) {
      $mr=$r+$dr; $mc=$c+$dc; $jr=$r+2*$dr; $jc=$c+2*$dc;
      if (in_bounds($jr,$jc) && is_enemy($b[$mr][$mc],$side) && $b[$jr][$jc]==='.') {
        $captures[] = [
          'from'=>[$r,$c], 'to'=>[$jr,$jc], 'capture'=>[[$mr,$mc]]
        ];
      }
    }
    // Simple moves (only if no captures anywhere and not in must-continue)
    if (!$must) {
      foreach (dirs_for($p) as [$dr,$dc]) {
        $nr=$r+$dr; $nc=$c+$dc;
        if (in_bounds($nr,$nc) && $b[$nr][$nc]==='.') {
          $moves[] = ['from'=>[$r,$c],'to'=>[$nr,$nc],'capture'=>[]];
        }
      }
    }
  }

  // If any capture exists globally, they are mandatory
  if (!empty($captures)) {
    // Expand captures to include multi-jump continuations (DFS)
    $expanded = [];
    foreach ($captures as $cap) {
      expand_capture_sequences($b, $side, $cap, $expanded);
    }
    return $expanded;
  }
  return $moves;
}

function expand_capture_sequences(array $board, string $side, array $move, array &$out) {
  // Apply one capture step, then see if further captures exist from new square
  $b = clone_board($board);
  [$fr,$fc] = $move['from']; [$tr,$tc] = $move['to'];
  $p = $b[$fr][$fc];
  $b[$fr][$fc]='.'; // move
  // remove captured
  foreach ($move['capture'] as [$cr,$cc]) $b[$cr][$cc]='.';
  // place piece (maybe kinged for discovering further jumps? Usually promotion happens after the entire multi-jump finishes;
  // American checkers: promotion occurs upon reaching far row and the move ends — no further jumps after crowning.
  // We'll implement that: if landing row is promotion row and piece was man, no further jumps are explored.
  $promoted = false;
  if ($p==='r' && $tr===0) { $p='R'; $promoted=true; }
  if ($p==='b' && $tr===7) { $p='B'; $promoted=true; }
  $b[$tr][$tc]=$p;

  if ($promoted) { // move ends
    $out[] = $move;
    return;
  }

  // Look for further captures from ($tr,$tc)
  $further = [];
  foreach (dirs_for($p) as [$dr,$dc]) {
    $mr=$tr+$dr; $mc=$tc+$dc; $jr=$tr+2*$dr; $jc=$tc+2*$dc;
    if (in_bounds($jr,$jc) && is_enemy($b[$mr][$mc],$side) && $b[$jr][$jc]==='.') {
      $further[] = [
        'from'=>[$tr,$tc],'to'=>[$jr,$jc],'capture'=>[[$mr,$mc]]
      ];
    }
  }
  if (empty($further)) {
    $out[] = $move; // terminal in this path
  } else {
    foreach ($further as $cap2) {
      $merged = [
        'from'=>$move['from'],
        'to'=>$cap2['to'],
        'capture'=>array_merge($move['capture'], $cap2['capture'])
      ];
      expand_capture_sequences($b, $side, $merged, $out);
    }
  }
}

function apply_move(array $state, array $req): array {
  $legal = legal_moves($state);
  $match = null;
  foreach ($legal as $mv) {
    if ($mv['from']===$req['from'] && $mv['to']===$req['to']) { $match=$mv; break; }
  }
  if (!$match) { $state['error'] = t('invalid_move'); return $state; }

  $b = $state['board']; $side=$state['turn'];
  [$fr,$fc] = $match['from']; [$tr,$tc] = $match['to'];
  $piece = $b[$fr][$fc];
  $isCapture = !empty($match['capture']);
  $b[$fr][$fc]='.';
  foreach ($match['capture'] as [$cr,$cc]) $b[$cr][$cc]='.';

  // Promotion (after full sequence per our generator)
  $promoted=false;
  if ($piece==='r' && $tr===0) { $piece='R'; $promoted=true; }
  if ($piece==='b' && $tr===7) { $piece='B'; $promoted=true; }
  $b[$tr][$tc]=$piece;

  // Update halfmove clock: resets on capture or promotion
  if ($isCapture || $promoted) $state['halfmove']=0; else $state['halfmove']++;

  $state['board']=$b;
  $state['history'][] = compact('fr','fc','tr','tc') + ['cap'=>array_map(fn($x)=>['r'=>$x[0],'c'=>$x[1]], $match['capture']), 'p'=>$piece];

  // Multi-jump enforcement: If capture was made AND further captures from landing square exist for same piece (without promotion), force continuation.
  if ($isCapture && !$promoted) {
    // Temporarily set must_continue and re-scan from this square only
    $temp = $state; $temp['must_continue']=['r'=>$tr,'c'=>$tc];
    $further = legal_moves($temp);
    $hasFurther = false;
    foreach ($further as $m2) {
      if ($m2['from']===[$tr,$tc] && !empty($m2['capture'])) { $hasFurther=true; break; }
    }
    if ($hasFurther) {
      $state['must_continue']=['r'=>$tr,'c'=>$tc];
      $state['error']=t('must_continue');
      return $state; // same player's turn continues
    }
  }

  // Turn swap & clear chain
  $state['must_continue']=null;
  $state['turn']= enemy_of($side);
  unset($state['error']);

  // Detect game over / no moves
  $opp = $state['turn'];
  $anyOpp = false;
  for($r=0;$r<8;$r++) for($c=0;$c<8;$c++) if (is_friend($b[$r][$c],$opp)) {$anyOpp=true; break 2;}
  $oppMoves = $anyOpp ? legal_moves($state) : [];
  if (!$anyOpp || empty($oppMoves)) {
    $state['winner']=$side;
  }

  // 50-move draw rule
  if ($state['halfmove']>=50) $state['draw']=true;

  return $state;
}

// ---------- Helpers ----------
function game_id(): string {
  return bin2hex(random_bytes(8)); // 16 hex chars
}
function get_param(string $key, $default=null) {
  return $_POST[$key] ?? $_GET[$key] ?? $default;
}

// ---------- Controller ----------
$action = get_param('action','');
$id = get_param('id','');

if ($action==='new') {
  $id = game_id();
  $mode = get_param('mode','shared'); // 'hotseat' or 'shared' (both work the same; hotseat just a label)
  $rname = trim((string)get_param('rname',''));
  $bname = trim((string)get_param('bname',''));
  $state = new_state($rname?:null,$bname?:null);
  $state['mode']=$mode;
  $store->save($id, $state);
  header('Location: '.basename(__FILE__).'?id='.urlencode($id).'&lang='.$lang);
  exit;
}

if ($id) {
  $state = $store->load($id);
  if (!$state) { $state = new_state(); $store->save($id,$state); }
  // Process a move?
  if ($action==='move' && isset($_POST['from'],$_POST['to'])) {
    $from = array_map('intval', explode(',', $_POST['from']));
    $to   = array_map('intval', explode(',', $_POST['to']));
    // Validate turn locally only; server is source of truth anyway.
    $state = apply_move($state, ['from'=>$from,'to'=>$to]);
    $store->save($id,$state);
    header('Content-Type: application/json');
    echo json_encode($state);
    exit;
  }

  // Render game page
  render_page($id, $state, $lang);
  exit;
}

// No ID: show landing (new game form)
render_landing($lang);

// ---------- Views ----------
function render_landing(string $lang): void { ?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang)?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars(t('title'))?></title>
<style>
  :root { --bg:#0c0d10; --fg:#eaeef3; --muted:#a9b0bb; --accent:#77c3ff; --red:#e15; --black:#333; }
  body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.45 system-ui,Segoe UI,Roboto,Ubuntu,sans-serif; display:flex; min-height:100vh; align-items:center; justify-content:center; }
  .card { background:#151820; border:1px solid #222634; border-radius:16px; padding:24px; width:min(680px,92vw); box-shadow:0 10px 30px rgba(0,0,0,.35); }
  h1 { margin:0 0 8px 0; font-size:28px; letter-spacing:.3px; }
  p { color:var(--muted); margin:.25rem 0 1rem 0; }
  form { display:grid; gap:10px; }
  input[type=text], select { background:#0f1219; color:var(--fg); border:1px solid #2a3142; border-radius:10px; padding:10px 12px; }
  button { background:linear-gradient(180deg,#2a7fff,#1769ff); color:white; border:0; padding:10px 14px; border-radius:12px; cursor:pointer; font-weight:600; }
  .row { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
  .lang { margin-top:8px; }
  a { color:var(--accent); text-decoration:none; }
</style>
</head>
<body>
  <div class="card">
    <h1><?=htmlspecialchars(t('title'))?></h1>
    <p><?=htmlspecialchars(t('help'))?></p>
    <form method="post">
      <input type="hidden" name="action" value="new">
      <div class="row">
        <label>
          <div><?=htmlspecialchars(t('name_red'))?></div>
          <input type="text" name="rname" placeholder="<?=htmlspecialchars(t('red'))?>">
        </label>
        <label>
          <div><?=htmlspecialchars(t('name_black'))?></div>
          <input type="text" name="bname" placeholder="<?=htmlspecialchars(t('black'))?>">
        </label>
      </div>
      <div class="row">
        <label>
          <div>Mode</div>
          <select name="mode">
            <option value="shared"><?=htmlspecialchars(t('shared'))?></option>
            <option value="hotseat"><?=htmlspecialchars(t('hotseat'))?></option>
          </select>
        </label>
        <label>
          <div><?=htmlspecialchars(t('language'))?></div>
          <select name="lang">
            <option value="en" <?= $lang==='en'?'selected':'' ?>>English</option>
            <option value="es" <?= $lang==='es'?'selected':'' ?>>Español</option>
          </select>
        </label>
      </div>
      <button type="submit"><?=htmlspecialchars(t('new_game'))?></button>
    </form>
    <p class="lang" style="margin-top:14px">
      <a href="?lang=en">English</a> · <a href="?lang=es">Español</a>
    </p>
  </div>
</body>
</html>
<?php }

function render_page(string $id, array $state, string $lang): void {
  $b = $state['board']; $turn=$state['turn']; $names=$state['names'];
  $status = $state['winner'] ? sprintf(t('game_over'), $names[$state['winner'][0]??$state['winner']]) :
            ($state['draw'] ? t('draw') : sprintf(t('to_move'), $names[$turn]));
  $err = $state['error'] ?? '';
  ?>
<!doctype html>
<html lang="<?=htmlspecialchars($lang)?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=htmlspecialchars(t('title'))?></title>
<style>
  :root { --bg:#0c0d10; --fg:#eaeef3; --muted:#a9b0bb; --accent:#77c3ff; --tile:#dcdfe6; --dark:#29415a; --hi:#2dd4bf; --err:#ff5e7a; --red:#e15; --blk:#111; }
  body { margin:0; background:var(--bg); color:var(--fg); font:16px/1.45 system-ui,Segoe UI,Roboto,Ubuntu,sans-serif; display:grid; min-height:100vh; grid-template-rows:auto 1fr auto; }
  header, footer { padding:12px 16px; background:#151820; border-bottom:1px solid #222634; }
  footer { border-top:1px solid #222634; border-bottom:none; }
  main { display:flex; gap:20px; padding:16px; justify-content:center; align-items:flex-start; flex-wrap:wrap; }
  .board { display:grid; grid-template-columns: repeat(8, min(10vw,64px)); grid-template-rows: repeat(8, min(10vw,64px)); border:6px solid #2a3142; border-radius:14px; overflow:hidden; }
  .sq { display:flex; align-items:center; justify-content:center; font-weight:800; cursor:pointer; user-select:none; position:relative; }
  .light { background:#f3f6fb; }
  .dark  { background:var(--dark); }
  .sq.hi::after { content:""; position:absolute; inset:6px; border:3px solid var(--hi); border-radius:10px; }
  .piece { width:70%; height:70%; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow: inset 0 6px 0 rgba(255,255,255,.2), 0 2px 6px rgba(0,0,0,.5); }
  .r { background:linear-gradient(180deg,#ff5a5a,#c31a1a); }
  .b { background:linear-gradient(180deg,#586a7a,#2a3b4f); }
  .king { outline:4px solid #ffd166; }
  .panel { min-width:280px; max-width:420px; background:#151820; border:1px solid #222634; border-radius:14px; padding:14px; }
  h1 { margin:.2rem 0 .6rem 0; font-size:22px; }
  .muted { color:var(--muted); }
  .status { margin:.3rem 0 .6rem 0; font-weight:600; }
  .err { color:var(--err); font-weight:600; }
  input[type=text] { width:100%; background:#0f1219; color:var(--fg); border:1px solid #2a3142; border-radius:10px; padding:8px 10px; }
  button, .btn { background:linear-gradient(180deg,#2a7fff,#1769ff); color:white; border:0; padding:8px 12px; border-radius:10px; cursor:pointer; font-weight:600; }
  a { color:var(--accent); text-decoration:none; }
  .row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .chip { background:#0f1219; border:1px solid #2a3142; padding:6px 10px; border-radius:999px; }
  .mini { font-size:12px; opacity:.8; }
</style>
</head>
<body>
<header class="row" style="justify-content:space-between; align-items:center;">
  <div class="row" style="gap:12px;">
    <strong><?=htmlspecialchars(t('title'))?></strong>
    <span class="chip mini"><?=htmlspecialchars($state['mode']??'shared')?></span>
    <a class="chip mini" href="?id=<?=urlencode($id)?>&lang=en">EN</a>
    <a class="chip mini" href="?id=<?=urlencode($id)?>&lang=es">ES</a>
  </div>
  <div class="row">
    <a class="btn" href="<?=htmlspecialchars(basename(__FILE__))?>"><?=htmlspecialchars(t('new_game'))?></a>
  </div>
</header>

<main>
  <div class="board" id="board" data-id="<?=htmlspecialchars($id)?>" data-lang="<?=htmlspecialchars($lang)?>">
    <?php
      for($r=0;$r<8;$r++){
        for($c=0;$c<8;$c++){
          $light = (($r+$c)&1)==0;
          $piece = $b[$r][$c];
          $cls = 'sq '.($light?'light':'dark');
          echo '<div class="'.$cls.'" data-r="'.$r.'" data-c="'.$c.'">';
          if($piece!=='.'){
            $side = strtolower($piece)==='r'?'r':'b';
            $isK = ($piece==='R'||$piece==='B');
            echo '<div class="piece '.$side.($isK?' king':'').'" title="'.htmlspecialchars(($side==='r'?t('red'):t('black')).($isK?' (K)':'' )).'">'.($isK?'★':'').'</div>';
          }
          echo '</div>';
        }
      }
    ?>
  </div>

  <div class="panel">
    <h1><?=htmlspecialchars(t('title'))?></h1>
    <div class="status"><?=htmlspecialchars($status)?></div>
    <?php if (!empty($err)) { echo '<div class="err">'.htmlspecialchars($err).'</div>'; } ?>
    <p class="muted"><?=htmlspecialchars(t('turn_note'))?></p>

    <hr style="border-color:#222634; opacity:.4; margin:12px 0">
    <div>
      <div class="mini"><?=htmlspecialchars(t('your_link'))?></div>
      <div class="row" style="margin-top:6px;">
        <input type="text" id="share" readonly value="<?=htmlspecialchars(current_url_with(['id'=>$id,'lang'=>$lang]))?>">
        <button onclick="copyShare()"><?=htmlspecialchars(t('copy'))?></button>
      </div>
      <div id="copied" class="mini" style="display:none; margin-top:6px; color:#7cffb8;"><?=htmlspecialchars(t('copied'))?></div>
    </div>

    <hr style="border-color:#222634; opacity:.4; margin:12px 0">
    <div class="mini"><?=htmlspecialchars(t('help'))?></div>
  </div>
</main>

<footer class="row" style="justify-content:center;">
  <span class="muted">ID: <?=htmlspecialchars($id)?> · <?=htmlspecialchars(t('language'))?>:
    <a href="?id=<?=urlencode($id)?>&lang=en">EN</a> / <a href="?id=<?=urlencode($id)?>&lang=es">ES</a>
  </span>
</footer>

<script>
const boardEl = document.getElementById('board');
let selected = null;
let highlights = [];

function coord(el){ return [parseInt(el.dataset.r), parseInt(el.dataset.c)]; }

function clearHi(){ highlights.forEach(el=>el.classList.remove('hi')); highlights=[]; }
function highlightSquares(sqs){ clearHi(); sqs.forEach(([r,c])=>{
  const el = document.querySelector(`.sq[data-r="${r}"][data-c="${c}"]`);
  if (el) { el.classList.add('hi'); highlights.push(el); }
}); }

function same(a,b){ return a && b && a[0]===b[0] && a[1]===b[1]; }

boardEl.addEventListener('click', (e)=>{
  const sq = e.target.closest('.sq'); if(!sq) return;
  const [r,c] = coord(sq);
  const piece = sq.querySelector('.piece');
  if (!selected) {
    if (piece) { selected = [r,c]; sq.classList.add('hi'); highlights=[sq]; }
  } else {
    const to = [r,c];
    if (same(selected,to)) { clearHi(); selected=null; return; }
    submitMove(selected,to);
  }
});

function submitMove(from,to){
  fetch(location.pathname + '?id='+encodeURIComponent(boardEl.dataset.id)+'&lang='+encodeURIComponent(boardEl.dataset.lang), {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=move&from='+from.join(',')+'&to='+to.join(',')+'&lang='+boardEl.dataset.lang
  }).then(r=>r.json()).then(st=>{
    // Re-render board minimalistically (no full reload) for low bandwidth
    redraw(st);
    if (st.error) {
      // Briefly flash error in footer (non-blocking)
      console.warn(st.error);
    }
  }).catch(err=>console.error(err)).finally(()=>{
    selected=null; clearHi();
  });
}

function redraw(st){
  // Pieces from st.board
  for (let r=0;r<8;r++){
    for (let c=0;c<8;c++){
      const sq = document.querySelector(`.sq[data-r="${r}"][data-c="${c}"]`);
      const val = st.board[r][c];
      sq.innerHTML='';
      if (val!=='.'){
        const side = (val==='r'||val==='R')?'r':'b';
        const king = (val==='R'||val==='B');
        const div = document.createElement('div');
        div.className='piece '+side+(king?' king':'');
        div.textContent = king?'★':'';
        sq.appendChild(div);
      }
    }
  }
  // Status text
  const statusEl = document.querySelector('.status');
  if (st.winner) {
    const name = (st.names && st.names[st.winner]) ? st.names[st.winner] : (st.winner==='r'?'<?=t('red')?>':'<?=t('black')?>');
    statusEl.textContent = "<?=t('game_over')?>".replace('%s', name);
  } else if (st.draw) {
    statusEl.textContent = "<?=t('draw')?>";
  } else {
    const name = (st.names && st.names[st.turn]) ? st.names[st.turn] : (st.turn==='r'?'<?=t('red')?>':'<?=t('black')?>');
    statusEl.textContent = "<?=t('to_move')?>".replace('%s', name);
  }
  const err = st.error||'';
  let errEl = document.querySelector('.err');
  if (!errEl) {
    errEl = document.createElement('div'); errEl.className='err';
    document.querySelector('.panel').insertBefore(errEl, statusEl.nextSibling);
  }
  errEl.textContent = err;
}

function copyShare(){
  const inp = document.getElementById('share');
  inp.select(); inp.setSelectionRange(0,99999);
  document.execCommand('copy');
  document.getElementById('copied').style.display='block';
  setTimeout(()=>document.getElementById('copied').style.display='none', 1200);
}
</script>
</body>
</html>
<?php
}

function current_url_with(array $params): string {
  $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
  $q = $_GET; foreach($params as $k=>$v){ $q[$k]=$v; }
  return $base . '?' . http_build_query($q);
}
