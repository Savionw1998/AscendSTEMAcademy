#!/usr/bin/env node
/* Builds preview/index.html: a standalone, offline-capable copy of the game
 * that inlines the same CSS and JS the plugin enqueues. Used for review on any
 * device and as the body of the draft preview page on the site.
 *
 *   node ascend-stem-a-lotl-rescue-lab/tools/build-preview.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = f => fs.readFileSync(path.join(root, 'assets', f), 'utf8');

const config = {
  preview: true,
  loggedIn: false,
  hubUrl: 'https://ascendstemacademy.com/user/',
  loginUrl: 'https://ascendstemacademy.com/login/',
  mascotUrl: 'https://ascendstemacademy.com/wp-content/uploads/2026/09/stem-a-lotl-scientist-sticker.png',
  mascotHappyUrl: 'https://ascendstemacademy.com/wp-content/uploads/2026/09/lucas-axolotl-wink.png'
};

const css = read('rescue-lab.css').replace(/@import url\([^)]*\);\s*/g, '');
const js = ['levels.js', 'engine.js', 'progress.js', 'rescue-lab.js'].map(read).join('\n;\n');

/* Compact copy for the site page body: comment lines and indentation removed,
   code otherwise untouched (verified with node --check by the build). */
function compact(src) {
  return src
    .replace(/^[ \t]*\/\*[\s\S]*?\*\/[ \t]*$/gm, '')
    .split('\n')
    .map(l => l.replace(/^[ \t]+/, ''))
    .filter(l => l && !/^\/\//.test(l) && !/^\*/.test(l))
    .join('\n');
}
const cssCompact = compact(css);
const jsCompact = compact(js);
require('vm').createScript(jsCompact); // throws if compaction broke the syntax

const bodyOf = (cssText, jsText) => `<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<style>
${cssText}
</style>
<div class="rl-root" id="rl-game"><noscript>STEM-a-lotl: Rescue Lab needs JavaScript turned on to play.</noscript></div>
<script>window.AscendRL = ${JSON.stringify(config)};</script>
<script>
${jsText}
</script>`;
const body = bodyOf(css, js);
const bodyCompact = bodyOf(cssCompact, jsCompact);

const page = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>STEM-a-lotl: Rescue Lab (preview)</title>
<meta name="robots" content="noindex">
<style>body{margin:0;background:#fff;font-family:Roboto,Arial,sans-serif;}</style>
</head>
<body>
${body}
</body>
</html>
`;

fs.mkdirSync(path.join(root, 'preview'), { recursive: true });
fs.writeFileSync(path.join(root, 'preview', 'index.html'), page);
fs.writeFileSync(path.join(root, 'preview', 'wp-page-body.html'), '<!-- wp:html -->\n' + bodyCompact + '\n<!-- /wp:html -->\n');
fs.writeFileSync(path.join(root, 'preview', 'index-compact.html'), page.replace(body, () => bodyCompact));
// claude.ai artifact: no document skeleton, title first, explicit page ground
fs.writeFileSync(path.join(root, 'preview', 'artifact.html'), `<title>STEM-a-lotl Rescue Lab</title>
<style>body{margin:0;background:#ffffff;color:#24332a;font-family:Roboto,Arial,sans-serif;}</style>
${body}`);
console.log('preview/index.html: ' + (page.length / 1024).toFixed(1) + ' KB; wp-page-body.html (compact): ' + (bodyCompact.length / 1024).toFixed(1) + ' KB; backslashes in page body: ' + (bodyCompact.match(/\\/g) || []).length);
