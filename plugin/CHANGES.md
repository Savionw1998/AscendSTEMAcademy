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

### Completion animation

Finishing a board plays a confetti burst over a "Theme solved!" card naming the
theme. It then re-reads from the server and shows whatever comes back — the next
board for a pass holder, the locked screen otherwise. The card says which is
coming before it happens.

`loadState()` re-reads progress, streak and level on the way in, so the header
counters and the dashboard's badge data (`axx_puzzle_log`) stay in step.

### Still one theme a day

The daily limit is intact on all three endpoints that enforce it — `get_state`,
`submit_guess` and `use_hint`. A pass lifts it; nothing else does. Streaks still
count calendar days.

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

---

## Passes

Both games stay one-puzzle-a-day. A pass is what lifts that, sold through the
WooCommerce store already on this site.

| Pass | Price | What it does |
| --- | --- | --- |
| Next Puzzle | $0.99 | Opens one more puzzle now. Stacks if bought several. |
| 30-Day Pass | $3.25 | 30 days unlimited |
| Annual Pass | $38.99 | 365 days unlimited |
| Full Unlock | $79.99 | Permanent, includes everything added later |

Priced from NYT Games — $4.25/month and $39.99/year — less about a dollar. The
two prices with no NYT equivalent (the single puzzle and the lifetime unlock)
are a judgement call and easy to change.

### How it works

- **No recurring billing.** WooCommerce Subscriptions is not installed, so a
  "monthly" pass is a one-off purchase granting 30 days. Nothing auto-renews,
  nothing can fail to renew, and there is nothing for a parent to cancel.
- **Time extends, it does not reset.** Buying more time adds to whatever is
  left, so renewing early never costs a student days.
- **Lifetime wins.** Once someone holds Full Unlock, later time purchases
  cannot downgrade it.
- **Fulfilment is idempotent.** WooCommerce fires its status hooks more than
  once in normal operation; processed order IDs are recorded per student so a
  repeat firing grants nothing.
- **Credits are never spent by accident.** Holding a next-puzzle credit does
  not consume it; only pressing the button does.
- **Guest checkout grants nothing** — there is no account to unlock. Both
  product descriptions tell buyers to be logged in.

### What students see

- **Cross-a-lotl** — the locked screen offers "Open the next theme" if they
  hold a credit, otherwise buy links for whichever passes exist.
- **Hex-a-lotl** — a student short of the pond threshold sees the same offer
  under the progress bar. A pass opens ponds outright.

Only passes with a real product behind them are ever shown, so the store can
be built up one product at a time without exposing a dead link.

### The products

Created as **drafts** in your store, so nothing is on sale until you publish:

| ID | Product |
| --- | --- |
| 6988 | Next Puzzle Pass |
| 6989 | 30-Day Games Pass |
| 6990 | Annual Games Pass |
| 6991 | Full Unlock |

Set up as simple virtual products, in stock, Full Unlock limited to one per
order. Their IDs are seeded into the plugin, so the link-up happens on upload
with nothing to configure.

**Two things left for you:** publish the four products when the prices look
right, and check their tax setting. I left tax at the WooCommerce default
(taxable) rather than decide how digital goods should be taxed for you.

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
