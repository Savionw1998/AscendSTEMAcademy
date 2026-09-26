/* STEM-a-lotl: Rescue Lab — progress record.
 *
 * One compact JSON object per student. Kept in localStorage for everyone and
 * mirrored to WordPress user meta for logged-in students (see the plugin).
 * Merging is monotonic: nothing a child earned is ever lost by a sync.
 *
 * Ownership of premium levels is NOT stored here. It comes from the server
 * (Ascend Axolotl Games key system) on every load; a local edit cannot grant it.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory(require('./levels.js'));
  else root.AscendRLProgress = factory(root.AscendRLLevels);
})(typeof self !== 'undefined' ? self : this, function (Levels) {
  'use strict';

  var VERSION = 1;
  var MAX_DESIGNS = 6;

  function empty() {
    return { v: VERSION, levels: {}, settings: { sound: true, ghost: true, assist: false }, runs: 0, fails: 0, practice: {} };
  }

  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  function sanitize(raw) {
    var p = empty();
    if (!raw || typeof raw !== 'object') return p;
    if (raw.settings && typeof raw.settings === 'object') {
      ['sound', 'ghost', 'assist'].forEach(function (k) { if (typeof raw.settings[k] === 'boolean') p.settings[k] = raw.settings[k]; });
    }
    p.runs = Math.max(0, parseInt(raw.runs, 10) || 0);
    p.fails = Math.max(0, parseInt(raw.fails, 10) || 0);
    if (raw.practice && typeof raw.practice === 'object') {
      Object.keys(raw.practice).forEach(function (k) { if (/^[0-9]{4}-W[0-9]{2}$/.test(k) && raw.practice[k] === true) p.practice[k] = true; });
    }
    if (raw.levels && typeof raw.levels === 'object') {
      Object.keys(raw.levels).forEach(function (id) {
        var n = parseInt(id, 10), src = raw.levels[id];
        if (!(n >= 1 && n <= Levels.LEVELS.length) || !src || typeof src !== 'object') return;
        var lv = { done: !!src.done, badges: {}, variations: {}, designs: [], stage: Math.max(0, parseInt(src.stage, 10) || 0), best: null };
        ['rescue', 'efficiency', 'invention'].forEach(function (b) { if (src.badges && src.badges[b] === true) lv.badges[b] = true; });
        if (src.variations && typeof src.variations === 'object') Object.keys(src.variations).forEach(function (v) { if (/^[0-9]+$/.test(v) && src.variations[v] === true) lv.variations[v] = true; });
        if (Array.isArray(src.designs)) src.designs.slice(0, MAX_DESIGNS).forEach(function (d) {
          if (d && typeof d === 'object' && d.design) lv.designs.push({ name: String(d.name || 'Design').slice(0, 40), ts: parseInt(d.ts, 10) || 0, variation: parseInt(d.variation, 10) || 0, design: d.design, stats: d.stats && typeof d.stats === 'object' ? d.stats : {} });
        });
        if (src.best && typeof src.best === 'object') lv.best = { time: Number(src.best.time) || 0, parts: parseInt(src.best.parts, 10) || 0 };
        p.levels[n] = lv;
      });
    }
    return p;
  }

  /** Keep the best of both records. */
  function merge(a, b) {
    a = sanitize(a); b = sanitize(b);
    var out = clone(a);
    out.runs = Math.max(a.runs, b.runs);
    out.fails = Math.max(a.fails, b.fails);
    Object.keys(b.practice).forEach(function (k) { out.practice[k] = true; });
    Object.keys(b.levels).forEach(function (id) {
      var lb = b.levels[id], la = out.levels[id];
      if (!la) { out.levels[id] = clone(lb); return; }
      la.done = la.done || lb.done;
      la.stage = Math.max(la.stage, lb.stage);
      Object.keys(lb.badges).forEach(function (k) { la.badges[k] = true; });
      Object.keys(lb.variations).forEach(function (k) { la.variations[k] = true; });
      if (lb.best && (!la.best || lb.best.time < la.best.time)) la.best = clone(lb.best);
      var seen = {};
      la.designs.forEach(function (d) { seen[JSON.stringify(d.design)] = true; });
      lb.designs.forEach(function (d) { var k = JSON.stringify(d.design); if (!seen[k]) { seen[k] = true; la.designs.push(clone(d)); } });
      la.designs.sort(function (x, y) { return y.ts - x.ts; });
      la.designs = la.designs.slice(0, MAX_DESIGNS);
    });
    // settings follow the record with more runs (the more recent one, in practice)
    if (b.runs > a.runs) out.settings = clone(b.settings);
    return out;
  }

  function level(p, id) {
    if (!p.levels[id]) p.levels[id] = { done: false, badges: {}, variations: {}, designs: [], stage: 0, best: null };
    return p.levels[id];
  }

  function recordSuccess(p, levelId, variation, badges, stats) {
    var lv = level(p, levelId);
    lv.done = true;
    lv.badges.rescue = true;
    lv.variations[variation] = true;
    (badges || []).forEach(function (b) { lv.badges[b] = true; });
    if (stats && typeof stats.time === 'number') {
      if (!lv.best || stats.time < lv.best.time) lv.best = { time: Math.round(stats.time * 100) / 100, parts: stats.parts || 0 };
    }
    return lv;
  }

  function saveDesign(p, levelId, variation, design, name, stats, ts) {
    var lv = level(p, levelId), key = JSON.stringify(design);
    lv.designs = lv.designs.filter(function (d) { return JSON.stringify(d.design) !== key; });
    lv.designs.unshift({ name: name, ts: ts || 0, variation: variation, design: clone(design), stats: stats || {} });
    lv.designs = lv.designs.slice(0, MAX_DESIGNS);
    return lv;
  }

  /** Access state for the level map. owned = server-provided set of level ids. */
  function levelState(p, lvl, owned, hasPass) {
    var lv = p.levels[lvl.id] || { done: false, badges: {}, variations: {} };
    var badgeCount = Object.keys(lv.badges).length;
    var access = lvl.free ? 'free' : (hasPass || (owned && owned[lvl.id]) ? 'owned' : 'locked');
    var status = !lv.done ? 'new' : (badgeCount >= 3 ? 'mastery' : 'completed');
    return { access: access, status: status, done: !!lv.done, badges: lv.badges, badgeCount: badgeCount, variationsDone: Object.keys(lv.variations).length };
  }

  /** Earned decorations: one per completed mission, plus one per three badges. */
  function decorations(p) {
    var out = [], badges = 0;
    Levels.LEVELS.forEach(function (lvl, i) {
      var lv = p.levels[lvl.id];
      if (lv && lv.done) out.push({ id: Levels.AREAS[i].id, label: Levels.AREAS[i].decor });
      if (lv) badges += Object.keys(lv.badges).length;
    });
    var extras = ['Axolotl plushie', 'Star chart', 'Trophy shelf', 'Rainbow banner', 'Golden wrench'];
    for (var i = 0; i < Math.min(extras.length, Math.floor(badges / 3)); i++) out.push({ id: 'bonus' + i, label: extras[i] });
    return out;
  }

  /** ISO week key, e.g. 2026-W39, from a Date-like (kept out of the engine). */
  function weekKey(date) {
    var d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    var day = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - day);
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
    var week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    return d.getUTCFullYear() + '-W' + (week < 10 ? '0' : '') + week;
  }

  /** The rotating practice challenge: always free content, deterministic per week. */
  function practice(p, key, owned, hasPass) {
    var pool = [];
    Levels.LEVELS.forEach(function (lvl) {
      var access = lvl.free || hasPass || (owned && owned[lvl.id]);
      if (!access || lvl.mode === 'sequence') return;
      lvl.variations.forEach(function (v, vi) { lvl.challenges.forEach(function (c) { pool.push({ level: lvl, variation: vi, challenge: c }); }); });
    });
    if (!pool.length) return null;
    var n = 0;
    for (var i = 0; i < key.length; i++) n = (n * 31 + key.charCodeAt(i)) % 100003;
    var pick = pool[n % pool.length];
    return { key: key, level: pick.level, variation: pick.variation, challenge: pick.challenge, done: !!p.practice[key] };
  }

  /** Plain-language numbers for the parent view. Practice observations, not an assessment. */
  function summary(p) {
    var done = 0, badges = 0, designs = 0, concepts = [];
    Levels.LEVELS.forEach(function (lvl) {
      var lv = p.levels[lvl.id];
      if (!lv) return;
      if (lv.done) { done++; concepts.push(lvl.concept); }
      badges += Object.keys(lv.badges).length;
      designs += lv.designs.length;
    });
    return { missions: done, badges: badges, designs: designs, concepts: concepts, runs: p.runs, fails: p.fails };
  }

  return { VERSION: VERSION, MAX_DESIGNS: MAX_DESIGNS, empty: empty, sanitize: sanitize, merge: merge, level: level, recordSuccess: recordSuccess, saveDesign: saveDesign, levelState: levelState, decorations: decorations, weekKey: weekKey, practice: practice, summary: summary };
});
