"""Build site/snippets/ascend-site-snippet.php (+ paste-ready .txt) from the base snippet and fix.json."""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
SNIP = os.path.join(HERE, "..", "snippets")
base = open(os.path.join(HERE, "snippet-base.php"), encoding="utf-8").read()
fix = json.load(open(os.path.join(HERE, "fix.json"), encoding="utf-8"))
fixjson = json.dumps(fix, ensure_ascii=False, separators=(",", ":"))
assert "ASA_FIX_JSON" not in fixjson

tail = open(os.path.join(HERE, "snippet-fix.php"), encoding="utf-8").read().replace("__FIX_JSON__", fixjson)
out = base.rstrip() + "\n\n" + tail.split("\n", 1)[1]  # drop the "<?php" line of the fix part
open(os.path.join(SNIP, "ascend-site-snippet.php"), "w", encoding="utf-8").write(out)
open(os.path.join(SNIP, "paste-into-code-snippets.txt"), "w", encoding="utf-8").write(out.split("\n", 1)[1])
print("snippet bytes:", len(out.encode()))
