# Hex-a-lotl shared word bank

A single dictionary shared by every Hex-a-lotl puzzle, replacing the hand-written
per-puzzle word arrays currently hardcoded in the Ascend Axolotl Games plugin.

## Why a shared bank

Today each puzzle carries its own list of accepted answers, so a common word that
is perfectly buildable from the puzzle's letters gets rejected with "not in the
library". With a shared bank the game stops asking "is this word on this puzzle's
list?" and instead asks:

1. Is the word at least 4 letters?
2. Does it contain the puzzle's required centre letter?
3. Are all of its letters in the puzzle's letter set?
4. Is it in the shared bank?

Any puzzle — including ones added later — then accepts every dictionary word it
can actually make, with no per-puzzle curation.

## What is in the bank

`data/wordbank.txt` (one word per line) and `data/wordbank.json` (a sorted JSON
array) hold **42,839** words: all SCOWL frequency bands through 40, which is
common-to-moderately-common English, minus anything under 4 letters and minus
profanity. Deliberately excluded are the obscure Scrabble-dictionary entries that
frustrate students without adding fun.

Size: 464 KB raw, 113 KB gzipped.

Typical yield is 60–300 accepted words per 7-letter puzzle, depending on how
friendly the letters are.

## Rebuilding

```sh
mkdir pkgs && cd pkgs
npm pack wordlist-english naughty-words
for f in *.tgz; do tar xzf "$f" --strip-components=2 -C .; done
cd ..
python3 tools/build-wordbank.py --src ./pkgs --max-band 40 --out ./data
```

Raise `--max-band` to widen the vocabulary. Band 70 yields ~109,000 words
(1.2 MB raw, 303 KB gzipped) and roughly doubles the words per puzzle, at the
cost of a lot of obscure vocabulary.
