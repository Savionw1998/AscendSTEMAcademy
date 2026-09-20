# ⚡ Circuit Clash

A head-to-head STEM duel for two players on one device. Player 1 types their name,
types the name of the classmate they are challenging, picks a subject track, and the
two trade questions until someone takes the badge.

## How it plays

1. **Name your rival.** Both names are required; the setup screen shows the
   head-to-head record if these two have duelled on this device before.
2. **Pick a track** — Mixed, Math, Physics, Chemistry, Biology, Code & Logic or
   Engineering — and how many questions each player gets (3, 5 or 7).
3. **Take turns.** The active player has 20 seconds. Questions ramp from easy to hard.
4. **Steal the miss.** A wrong answer or a timeout opens a 10-second steal for the
   opponent, worth a flat 60 points.
5. **Spend your power-ups.** Each player gets one **50/50** (removes two wrong answers)
   and one **Double Down** (doubles the points won, but costs 75 on a miss).
6. **Lightning round.** A tie triggers alternating sudden-death questions until the
   scoreboard breaks.
7. **Take the badge.** The winner earns a Bronze / Silver / Gold / Platinum badge
   showing their name, the rival they beat, the score and the date — downloadable
   as a PNG and stored in the on-device Badge Case.

## Scoring

| Event | Points |
|-------|--------|
| Correct answer | 100 + up to 60 speed bonus |
| 3+ answer streak | +25 per correct answer |
| Successful steal | 60 |
| Double Down correct | ×2 |
| Double Down wrong | −75 |

Badge tier comes from the winner's accuracy and margin: **Platinum** at ≥90% accuracy
with a 200+ point margin, **Gold** at ≥75%, **Silver** at ≥50%, **Bronze** otherwise.

## Files

- `index.html` — the whole game (markup, styles, logic). Open it directly in a browser.
- `questions.js` — the question bank, 72 questions across 6 tracks and 3 difficulty
  levels. Add questions here; no other file needs to change.

## Adding questions

```js
{ q: "What is the SI unit of force?",
  a: ["Joule", "Watt", "Newton", "Pascal"],  // exactly 4 choices
  c: 2,                                      // index of the correct one
  why: "Force is measured in newtons (N).",  // shown on reveal — keep it teaching
  lvl: 1 }                                   // 1 easy · 2 medium · 3 hard
```

## Controls

Click or tap an answer, or press **A / B / C / D**. The ☾ Theme button flips between
dark and light.
