# Ascend STEM Academy — Game Drawer

Self-contained mini-games. Each game is plain HTML/CSS/JS with **no build step and no
dependencies**, so a game can be dropped into the drawer as an `<iframe>` on any site
(static host, WordPress, React, Django — it does not matter).

## Games

| Game | Players | Entry | Time |
|------|---------|-------|------|
| ⚡ [Circuit Clash](circuit-clash/) | 2 (hot-seat) | `circuit-clash/index.html` | ~4 min |

`manifest.json` lists the same data in machine-readable form — point the drawer at it
so new games appear without touching drawer code.

## Dropping a game into the drawer

```html
<iframe
  src="/games/circuit-clash/index.html"
  title="Circuit Clash"
  style="width:100%;height:760px;border:0;border-radius:16px"
  loading="lazy"></iframe>
```

Build the drawer tile list straight from the manifest:

```js
const { games } = await (await fetch('/games/manifest.json')).json();
drawer.innerHTML = games.map(g => `
  <button class="game-tile" data-src="/games/${g.entry}">
    <span class="game-icon">${g.icon}</span>
    <strong>${g.title}</strong>
    <small>${g.tagline} · ${g.players} players · ${g.durationMinutes} min</small>
  </button>`).join('');
```

## Listening for badges

When a duel ends, the game posts the badge to the hosting page:

```js
window.addEventListener('message', e => {
  if (e.data?.type !== 'ascend:badge-earned') return;
  const { winner, loser, tier, title, score } = e.data.badge;
  // save to a profile, show a toast, post to a class leaderboard…
});
```

In production, check `e.origin` against your own domain before trusting the message.

Badges and head-to-head records are also kept in `localStorage`
(`ascend.cc.badges`, `ascend.cc.rivalry`), so the game works fully offline with no
account and no backend.
