(function () {
  const root = document.getElementById('game-root');
  if (!root) {
    return;
  }

  const boardEl = document.getElementById('board');
  const statusEl = document.getElementById('status-text');
  const messageEl = document.getElementById('message-text');
  const copyBtn = root.querySelector('[data-action="copy"]');
  const copyFeedback = document.getElementById('copy-feedback');
  const shareInput = document.getElementById('share-link');

  let selected = null;
  let etag = null;
  let state = window.gameInitial || {};
  let pollingTimer = null;

  const caps = {
    r: root.dataset.capR,
    b: root.dataset.capB,
  };

  function squareElement(r, c) {
    return boardEl.querySelector(`.sq[data-r="${r}"][data-c="${c}"]`);
  }

  function clearHighlights() {
    boardEl.querySelectorAll('.sq.highlight').forEach((sq) => sq.classList.remove('highlight'));
  }

  function highlightSquares(squares) {
    clearHighlights();
    squares.forEach(([r, c]) => {
      const sq = squareElement(r, c);
      if (sq) {
        sq.classList.add('highlight');
      }
    });
  }

  function renderBoard(newState) {
    const board = newState.board || [];
    for (let r = 0; r < 8; r++) {
      for (let c = 0; c < 8; c++) {
        const sq = squareElement(r, c);
        if (!sq) continue;
        sq.innerHTML = '';
        const val = board[r]?.[c] ?? '.';
        if (val !== '.') {
          const piece = document.createElement('div');
          const side = (val === 'r' || val === 'R') ? 'r' : 'b';
          const isKing = val === 'R' || val === 'B';
          piece.className = 'piece ' + side + (isKing ? ' king' : '');
          piece.dataset.side = side;
          piece.textContent = isKing ? '★' : '';
          sq.appendChild(piece);
        }
      }
    }
    state = newState;
    updateStatus();
    highlightLastMove(newState.last_move);
  }

  function updateStatus() {
    if (!statusEl || !state) return;
    if (state.winner) {
      const side = state.winner;
      const name = (state.names && state.names[side]) ? state.names[side] : (side === 'r' ? 'Red' : 'Black');
      statusEl.textContent = (root.dataset.statusWin || '%s wins').replace('%s', name);
    } else if (state.draw) {
      statusEl.textContent = root.dataset.statusDraw || 'Draw';
    } else {
      const side = state.turn;
      const name = (state.names && state.names[side]) ? state.names[side] : (side === 'r' ? 'Red' : 'Black');
      statusEl.textContent = (root.dataset.statusTurn || '%s to move').replace('%s', name);
    }
    if (messageEl) {
      messageEl.textContent = state.message || '';
    }
  }

  function highlightLastMove(lastMove) {
    if (!lastMove) {
      clearHighlights();
      return;
    }
    const squares = [];
    if (Array.isArray(lastMove.from)) squares.push(lastMove.from);
    if (Array.isArray(lastMove.to)) squares.push(lastMove.to);
    if (Array.isArray(lastMove.captured)) {
      lastMove.captured.forEach((cap) => squares.push(cap));
    }
    highlightSquares(squares);
  }

  function postMove(from, to) {
    const id = root.dataset.gameId;
    const lang = root.dataset.lang;
    const csrf = root.dataset.csrf;
    const turn = state.turn || 'r';
    const cap = caps[turn];
    const params = new URLSearchParams();
    params.set('action', 'move');
    params.set('id', id);
    params.set('lang', lang);
    params.set('_csrf', csrf);
    params.set('from', from.join(','));
    params.set('to', to.join(','));
    if (cap) {
      params.set('cap', cap);
    }
    fetch('?' + params.toString(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: params.toString(),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.error) {
          if (messageEl) {
            messageEl.textContent = data.error;
          }
          return;
        }
        state = Object.assign({}, state, data);
        renderBoard(state);
        root.dataset.updated = data.updated_at || Date.now();
      })
      .catch((err) => console.error(err))
      .finally(() => {
        selected = null;
        clearHighlights();
      });
  }

  boardEl.addEventListener('click', (event) => {
    const sq = event.target.closest('.sq');
    if (!sq) return;
    const r = parseInt(sq.dataset.r, 10);
    const c = parseInt(sq.dataset.c, 10);
    const piece = sq.querySelector('.piece');
    if (!selected) {
      if (!piece) return;
      const side = piece.dataset.side;
      if (side !== (state.turn || 'r')) {
        return;
      }
      selected = [r, c];
      highlightSquares([selected]);
    } else {
      const dest = [r, c];
      if (selected[0] === dest[0] && selected[1] === dest[1]) {
        selected = null;
        clearHighlights();
        return;
      }
      postMove(selected, dest);
    }
  });

  function fetchState() {
    const id = root.dataset.gameId;
    const lang = root.dataset.lang;
    const headers = {};
    if (etag) {
      headers['If-None-Match'] = etag;
    }
    fetch(`/?action=state&id=${encodeURIComponent(id)}&lang=${encodeURIComponent(lang)}`, {
      headers,
    })
      .then((response) => {
        if (response.status === 304) {
          return null;
        }
        const newEtag = response.headers.get('ETag');
        if (newEtag) {
          etag = newEtag;
        }
        return response.json();
      })
      .then((data) => {
        if (!data) return;
        state = Object.assign({}, state, data);
        renderBoard(state);
        root.dataset.updated = data.updated_at || Date.now();
      })
      .catch((err) => console.error(err));
  }

  function startPolling() {
    if (pollingTimer) return;
    pollingTimer = setInterval(fetchState, 8000);
  }

  function startStream() {
    if (!('EventSource' in window)) {
      startPolling();
      return;
    }
    const id = root.dataset.gameId;
    const lang = root.dataset.lang;
    const source = new EventSource(`/?action=stream&id=${encodeURIComponent(id)}&lang=${encodeURIComponent(lang)}`);
    source.addEventListener('state', () => {
      fetchState();
    });
    source.addEventListener('error', () => {
      source.close();
      startPolling();
    });
  }

  if (copyBtn && shareInput) {
    copyBtn.addEventListener('click', async () => {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(shareInput.value);
        } else {
          shareInput.select();
          document.execCommand('copy');
        }
        copyFeedback.classList.add('visible');
        setTimeout(() => copyFeedback.classList.remove('visible'), 1200);
      } catch (err) {
        console.error(err);
      }
    });
  }

  renderBoard(state);
  startStream();
})();
