# Ascend Axolotl Games — what changed

Two games were updated. Guess-a-lotl and Connect-a-lotl are untouched.

## Files

| File | What it is |
| --- | --- |
| `ascend-axolotl-games.php` | The plugin. Replaces the copy already on the site. |
| `hex-a-lotl-dictionary.txt` | New. 270,174 English words, one per line. Goes in the same folder. |

Both belong in `wp-content/plugins/ascend-axolotl-games/`.

---

## Cross-a-lotl

### Found words now stay highlighted (this was a bug)

The server sends each found word's path as `[row, column]` pairs. The browser was
reading them as `.r` and `.c`, which do not exist on a pair — so every coordinate
came out `undefined` and no tile was ever marked. The highlight only appeared
after a page refresh, when a different code path happened to read the same data
correctly.

Paths now go through one shared `normPath()` helper that accepts either shape, so
this cannot silently break again.

### Highlights are stronger, in theme colours

Found words were pale green (`#BFE0B4`) on an off-white tile — easy to miss even
when they did render. They are now solid:

- **Theme words** — deep green `#1D4010`, white letters
- **Spangram** — deep blue `#045C82`, white letters (unchanged, matches the badge)

Both are existing site palette colours. Dragging over an already-found tile now
shows the selection colour again; previously the found colour won and you got no
feedback tracing across solved letters. Each newly found word pops briefly as it
locks in.

### Completion animation, then straight to the next board

Finishing a board plays a confetti burst over a "Theme solved!" card naming the
theme, holds for 2.6 seconds, then loads the next puzzle automatically.

`loadState()` re-reads progress, streak and level from the server on the way in,
so the header counters and the dashboard's badge data (`axx_puzzle_log`) stay in
step with the new board.

### The one-per-day lock is gone

Auto-advance and "come back tomorrow" cannot both be true. The daily gate was
removed from all three endpoints that enforced it — `get_state`, `submit_guess`
and `use_hint`. Students can now play as many themes in a row as they like.
Streaks still count calendar days, so a streak is unaffected.

---

## Hex-a-lotl

### Two word banks per pond

Ordinary English words were being rejected because each pond only accepted its
own hand-written list. Every pond now has two banks:

- **Bonus words** — the existing curated list, unchanged. Still the pond's
  yardstick: max score, the unlock threshold, the longest-word target and the
  career rank are all measured against these alone.
- **Word pool** — every dictionary word that fits the pond's seven letters.
  Accepted and scored, but never counted into max score.

Because max score is untouched, **no student's rank or unlock threshold moves**.
Pool words are pure upside: they carry a student toward the same bar faster,
which is the point. Bonus words keep the longest-word premium and now show a ★
and their own colour, so they still feel worth hunting.

### Coverage

Measured against the 23 ponds live on the site:

| | Before | After |
| --- | --- | --- |
| Accepted words | 898 | 8,873 |
| Per pond | 24–42 | 125–974 |
| Smallest gain | — | +101 |

Max score and the unlock threshold are byte-identical before and after.

### Pools maintain themselves

- Missing pools are built on the next wp-admin page load after the update.
- Adding a pond, or changing a pond's letters, builds that pond's pool on save.
- Editing bonus words preserves the pool — without this, one save from wp-admin
  would have silently wiped every pool.
- **Hex-a-lotl → Puzzles & Settings** shows current pool coverage and has a
  "rebuild every pond's pool" checkbox for after swapping the dictionary file.

The dictionary is streamed line by line rather than loaded into an array, so
memory stays flat regardless of word count. Rebuilding all 23 ponds is a single
pass over the file and took 0.32s in testing.

### Word list

Sourced from SCOWL/ENABLE. Filtered to 4+ letters, A–Z only, and screened
against a profanity blocklist plus a stem list covering inflections — 411 words
removed. Swap in your own list any time: replace the `.txt`, then tick the
rebuild box.

---

## Installing

1. Back up the site (UpdraftPlus or WPvivid, both already installed).
2. Upload both files to `wp-content/plugins/ascend-axolotl-games/`, replacing
   the existing `.php`.
3. Open **wp-admin → Ascend Games → Hex-a-lotl: Puzzles & Settings**. Pools
   build on that page load; the page reports the coverage.
4. Play a Cross-a-lotl board to confirm words stay coloured in.

Rollback is just restoring the previous `.php`. No student data is migrated,
renamed, or deleted by this update — pools live in a new key alongside the
existing puzzle data.

## Known pre-existing issue, not addressed

29 admin-screen strings contain literal `—` / `’` escapes inside
single-quoted PHP, so wp-admin shows the raw text instead of an em dash or
apostrophe. It is cosmetic, affects admin screens only, and is unrelated to
these two games, so I left it alone rather than widen the change. Worth a
separate pass.
