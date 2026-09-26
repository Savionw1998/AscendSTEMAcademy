/* STEM-a-lotl: Rescue Lab — progress record, access rules and pellet skins.
 *
 * Progress (stars, saved designs, settings) is one compact JSON object per
 * student, kept in localStorage and mirrored to WordPress user meta. Merging
 * is monotonic: nothing a child earned is ever lost by a sync.
 *
 * Access to puzzles and key-bought skins is NOT stored here. It comes from the
 * server on every load (see the plugin); a local edit cannot grant it. The
 * access rules below mirror the server so the preview's demo mode behaves the
 * same way, clearly labelled as a demo.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory(require('./levels.js'));
  else root.AscendRLProgress = factory(root.AscendRLLevels);
})(typeof self !== 'undefined' ? self : this, function (Levels) {
  'use strict';

  var VERSION = 2;
  var MAX_DESIGNS = 6;
  var BADGES = ['rescue', 'efficiency', 'invention'];
  var TOTAL = Levels.LEVELS.length;

  function empty() {
    return { v: VERSION, levels: {}, settings: { sound: true, ghost: true, assist: false, skin: 'pink' }, runs: 0, fails: 0 };
  }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function skinById(id) { for (var i = 0; i < Levels.SKINS.length; i++) if (Levels.SKINS[i].id === id) return Levels.SKINS[i]; return null; }

  function sanitize(raw) {
    var p = empty();
    if (!raw || typeof raw !== 'object') return p;
    if (raw.settings && typeof raw.settings === 'object') {
      ['sound', 'ghost', 'assist'].forEach(function (k) { if (typeof raw.settings[k] === 'boolean') p.settings[k] = raw.settings[k]; });
      if (typeof raw.settings.skin === 'string' && skinById(raw.settings.skin)) p.settings.skin = raw.settings.skin;
    }
    p.runs = Math.max(0, parseInt(raw.runs, 10) || 0);
    p.fails = Math.max(0, parseInt(raw.fails, 10) || 0);
    if (raw.levels && typeof raw.levels === 'object') {
      Object.keys(raw.levels).forEach(function (id) {
        var n = parseInt(id, 10), src = raw.levels[id];
        if (!(n >= 1 && n <= TOTAL) || !src || typeof src !== 'object') return;
        var lv = { done: !!src.done, badges: {}, designs: [], best: null };
        BADGES.forEach(function (b) { if (src.badges && src.badges[b] === true) lv.badges[b] = true; });
        if (Array.isArray(src.designs)) src.designs.slice(0, MAX_DESIGNS).forEach(function (d) {
          if (d && typeof d === 'object' && d.design && Array.isArray(d.design.parts)) lv.designs.push({ name: String(d.name || 'Design').slice(0, 40), ts: parseInt(d.ts, 10) || 0, design: { parts: d.design.parts.slice(0, 12) }, stats: d.stats && typeof d.stats === 'object' ? d.stats : {} });
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
    Object.keys(b.levels).forEach(function (id) {
      var lb = b.levels[id], la = out.levels[id];
      if (!la) { out.levels[id] = clone(lb); return; }
      la.done = la.done || lb.done;
      Object.keys(lb.badges).forEach(function (k) { la.badges[k] = true; });
      if (lb.best && (!la.best || lb.best.time < la.best.time)) la.best = clone(lb.best);
      var seen = {};
      la.designs.forEach(function (d) { seen[JSON.stringify(d.design)] = true; });
      lb.designs.forEach(function (d) { var k = JSON.stringify(d.design); if (!seen[k]) { seen[k] = true; la.designs.push(clone(d)); } });
      la.designs.sort(function (x, y) { return y.ts - x.ts; });
      la.designs = la.designs.slice(0, MAX_DESIGNS);
    });
    if (b.runs > a.runs) out.settings = clone(b.settings);
    return out;
  }

  function level(p, id) {
    if (!p.levels[id]) p.levels[id] = { done: false, badges: {}, designs: [], best: null };
    return p.levels[id];
  }

  function recordSuccess(p, levelId, badges, stats) {
    var lv = level(p, levelId);
    lv.done = true; lv.badges.rescue = true;
    (badges || []).forEach(function (b) { lv.badges[b] = true; });
    if (stats && typeof stats.time === 'number' && (!lv.best || stats.time < lv.best.time)) lv.best = { time: Math.round(stats.time * 100) / 100, parts: stats.parts || 0 };
    return lv;
  }

  function saveDesign(p, levelId, design, name, stats, ts) {
    var lv = level(p, levelId), key = JSON.stringify(design);
    lv.designs = lv.designs.filter(function (d) { return JSON.stringify(d.design) !== key; });
    lv.designs.unshift({ name: name, ts: ts || 0, design: clone(design), stats: stats || {} });
    lv.designs = lv.designs.slice(0, MAX_DESIGNS);
    return lv;
  }

  function stars(p) { var n = 0; Object.keys(p.levels).forEach(function (id) { n += Object.keys(p.levels[id].badges).length; }); return n; }
  function fed(p) { return Object.keys(p.levels).filter(function (id) { return p.levels[id].done; }).length; }
  function worldDone(p, world) {
    return Levels.LEVELS.filter(function (l) { return l.world === world; }).every(function (l) { return p.levels[l.id] && p.levels[l.id].done; });
  }

  /* ------------------------------------------------------------ access */

  /**
   * access = { owned: {id: 'key'|'daily'|'grant'}, hasPass, config }.
   * Returns 'free' | 'owned' | 'locked' for one puzzle.
   */
  function accessOf(lvl, access) {
    var cfg = (access && access.config) || Levels.CONFIG;
    if (lvl.id <= cfg.freeLevels) return 'free';
    if (access && (access.hasPass || (access.owned && access.owned[lvl.id]))) return 'owned';
    return 'locked';
  }

  function levelState(p, lvl, access) {
    var lv = p.levels[lvl.id] || { done: false, badges: {} };
    var badgeCount = Object.keys(lv.badges).length;
    return { access: accessOf(lvl, access), status: !lv.done ? 'new' : (badgeCount >= 3 ? 'mastery' : 'completed'), done: !!lv.done, badges: lv.badges, badgeCount: badgeCount };
  }

  /** The puzzle the next daily unlock would open: the lowest locked one. */
  function nextLocked(access) {
    for (var i = 0; i < Levels.LEVELS.length; i++) if (accessOf(Levels.LEVELS[i], access) === 'locked') return Levels.LEVELS[i];
    return null;
  }

  /**
   * The daily rule, shared by the demo and mirrored in PHP: on a calendar day
   * the student has not yet claimed, the lowest locked puzzle opens. One per
   * day; missed days are not banked. Returns { opened: id|null, state }.
   */
  function claimDaily(state, today) {
    var cfg = state.config || Levels.CONFIG, out = clone(state);
    out.owned = out.owned || {};
    if (!cfg.dailyUnlock || out.lastDaily === today || out.hasPass) return { opened: null, state: out };
    var next = nextLocked(out);
    if (!next) return { opened: null, state: out };
    out.owned[next.id] = 'daily';
    out.lastDaily = today;
    return { opened: next.id, state: out };
  }

  /* ------------------------------------------------------------- skins */

  /** Is a skin available to this student? Key skins need server ownership. */
  function skinState(p, skin, ownedSkins) {
    var r = skin.rule, s = stars(p);
    switch (r.type) {
      case 'free': return { open: true, how: 'Starter pellet' };
      case 'stars': return { open: s >= r.n, how: 'Earn ' + r.n + ' stars (' + Math.min(s, r.n) + '/' + r.n + ')' };
      case 'world': return { open: worldDone(p, r.world), how: 'Feed Lucas in every ' + Levels.WORLDS[r.world - 1].name + ' puzzle' };
      case 'keys': return { open: !!(ownedSkins && ownedSkins[skin.id]), how: 'Special pellet', keys: true };
    }
    return { open: false, how: '' };
  }

  function earnedSkinIds(p, ownedSkins) {
    return Levels.SKINS.filter(function (s) { return skinState(p, s, ownedSkins).open; }).map(function (s) { return s.id; });
  }

  /* --------------------------------------------------------- summaries */

  function summary(p) {
    var ideas = [], designs = 0;
    Levels.LEVELS.forEach(function (lvl) {
      var lv = p.levels[lvl.id];
      if (!lv) return;
      if (lv.done && ideas.indexOf(lvl.idea) === -1) ideas.push(lvl.idea);
      designs += lv.designs.length;
    });
    return { fed: fed(p), stars: stars(p), maxStars: TOTAL * 3, designs: designs, ideas: ideas, runs: p.runs, fails: p.fails };
  }

  /** Local calendar day key, e.g. 2026-09-26 (the server uses the site timezone). */
  function dayKey(date) {
    var m = date.getMonth() + 1, d = date.getDate();
    return date.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (d < 10 ? '0' : '') + d;
  }

  return {
    VERSION: VERSION, MAX_DESIGNS: MAX_DESIGNS, BADGES: BADGES, empty: empty, sanitize: sanitize, merge: merge, level: level,
    recordSuccess: recordSuccess, saveDesign: saveDesign, stars: stars, fed: fed, worldDone: worldDone,
    accessOf: accessOf, levelState: levelState, nextLocked: nextLocked, claimDaily: claimDaily,
    skinById: skinById, skinState: skinState, earnedSkinIds: earnedSkinIds, summary: summary, dayKey: dayKey
  };
});
