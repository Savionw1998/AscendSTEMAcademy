#!/usr/bin/env python3
"""Build the shared Hex-a-lotl word bank.

Sources (both fetched from npm, no site access needed):
  wordlist-english  SCOWL frequency-banded English word lists. Band 10 is the
                    most common vocabulary, band 70 the most obscure. We take
                    everything through --max-band.
  naughty-words     Profanity blocklist, applied on top of a hand-written stem
                    list, because this is a student-facing school site.

Usage:
  npm pack wordlist-english naughty-words && tar xzf ...   # see README.md
  python3 build-wordbank.py --src ./pkgs --max-band 40 --out ../data
"""
import argparse
import json
import os
import re

# Words shorter than this are never accepted by the game.
MIN_LENGTH = 4

# Substrings that disqualify a word outright. Broader than the naughty-words
# list because that list misses inflections (e.g. "shitting", "bitchy").
BLOCKED_STEMS = {
    "anal", "anus", "bastard", "bitch", "cock", "crap", "cunt", "damn", "dick",
    "erotic", "fart", "fellat", "fuck", "hell", "masturb", "nigg", "orgasm",
    "penis", "porn", "pussy", "rape", "scrotum", "semen", "shit", "slut",
    "sodom", "sperm", "testicle", "tit", "turd", "vagina", "whore",
}

BANDS = [10, 20, 35, 40, 50, 55, 60, 70]
VALID = re.compile(r"^[a-z]{%d,}$" % MIN_LENGTH)


def load_bands(src, max_band):
    words = set()
    for band in BANDS:
        if band > max_band:
            break
        for dialect in ("english", "american"):
            path = os.path.join(src, f"{dialect}-words-{band}.json")
            if not os.path.exists(path):
                continue
            with open(path) as fh:
                words |= {w.lower() for w in json.load(fh) if VALID.match(w.lower())}
    return words


def load_blocklist(src):
    path = os.path.join(src, "en.json")
    with open(path) as fh:
        # Multi-word phrases can never match a single game word.
        return {w.strip().lower() for w in json.load(fh) if " " not in w}


def is_clean(word, blocklist):
    return word not in blocklist and not any(s in word for s in BLOCKED_STEMS)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--src", required=True, help="directory holding the unpacked npm data files")
    ap.add_argument("--max-band", type=int, default=40, choices=BANDS)
    ap.add_argument("--out", required=True, help="directory to write the word bank into")
    args = ap.parse_args()

    blocklist = load_blocklist(args.src)
    words = sorted(w for w in load_bands(args.src, args.max_band) if is_clean(w, blocklist))

    os.makedirs(args.out, exist_ok=True)
    with open(os.path.join(args.out, "wordbank.txt"), "w") as fh:
        fh.write("\n".join(words) + "\n")
    with open(os.path.join(args.out, "wordbank.json"), "w") as fh:
        json.dump(words, fh, separators=(",", ":"))

    print(f"{len(words)} words written to {args.out}")


if __name__ == "__main__":
    main()
