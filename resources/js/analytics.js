/**
 * Leemroz — centralized front-end analytics service.
 *
 * One place that every page/component calls: window.analytics.track(name, params).
 * It fans out to (a) our first-party backend (batched + sendBeacon), (b) GA4 /
 * GTM dataLayer, and (c) Meta Pixel — but ONLY the channels the visitor has
 * consented to, and only after their tags have loaded. Nothing non-essential
 * loads before consent. Analytics failure never throws into the page.
 *
 * Config is injected by the layout as window.LeemrozAnalytics:
 *   { enabled, ga4Id, gtmId, metaPixelId, googleAdsId, sessionId, api }
 */
(function () {
  const CFG = window.LeemrozAnalytics || {};
  const API = (CFG.api || '/api/v1/analytics').replace(/\/$/, '');
  const CONSENT_KEY = 'lz_consent';
  const ANON_KEY = 'lz_aid';
  const FIRST_TOUCH_KEY = 'lz_ft';
  const QUEUE_KEY = 'lz_evq';

  const storage = {
    get(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { window.localStorage.setItem(k, v); } catch (e) {} },
    getJSON(k, d) { try { return JSON.parse(this.get(k)) ?? d; } catch (e) { return d; } },
    setJSON(k, v) { this.set(k, JSON.stringify(v)); },
  };

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function readCookie(name) {
    const m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
  }

  const anonymousId = (function () {
    let id = storage.get(ANON_KEY);
    if (!id) { id = uuid(); storage.set(ANON_KEY, id); }
    return id;
  })();

  function sessionId() {
    return CFG.sessionId || readCookie('lz_sid') || anonymousId;
  }

  /* ------------------------------------------------------------------ consent */

  function defaultConsent() {
    return { necessary: true, functional: false, analytics: false, marketing: false, set: false };
  }
  function getConsent() {
    return Object.assign(defaultConsent(), storage.getJSON(CONSENT_KEY, {}));
  }
  function saveConsent(partial) {
    const c = Object.assign(getConsent(), partial, { set: true });
    storage.setJSON(CONSENT_KEY, c);
    applyConsent(c);
    document.dispatchEvent(new CustomEvent('lz:consent', { detail: c }));
    return c;
  }

  let loaded = { gtag: false, gtm: false, meta: false };

  function applyConsent(c) {
    c = c || getConsent();
    // Google Consent Mode v2 signal (works whether gtag/GTM load now or later).
    gtag('consent', 'update', {
      analytics_storage: c.analytics ? 'granted' : 'denied',
      ad_storage: c.marketing ? 'granted' : 'denied',
      ad_user_data: c.marketing ? 'granted' : 'denied',
      ad_personalization: c.marketing ? 'granted' : 'denied',
    });

    if (c.analytics) {
      if (CFG.gtmId) loadGtm(CFG.gtmId);
      if (CFG.ga4Id && !CFG.gtmId) loadGtag();
      startBackend();
    }
    if (c.marketing) {
      if (CFG.metaPixelId) loadMetaPixel(CFG.metaPixelId);
      if (CFG.googleAdsId) loadGtag(); // gtag also drives Google Ads
    }
  }

  /* -------------------------------------------------------------- tag loaders */

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  // Deny by default until the visitor opts in.
  gtag('consent', 'default', {
    analytics_storage: 'denied', ad_storage: 'denied',
    ad_user_data: 'denied', ad_personalization: 'denied',
  });

  function injectScript(src, attrs) {
    const s = document.createElement('script');
    s.async = true;
    s.src = src;
    Object.assign(s, attrs || {});
    document.head.appendChild(s);
    return s;
  }

  function loadGtag() {
    if (loaded.gtag) return;
    loaded.gtag = true;
    const id = CFG.ga4Id || CFG.googleAdsId;
    if (!id) return;
    injectScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id));
    gtag('js', new Date());
    if (CFG.ga4Id) gtag('config', CFG.ga4Id, { send_page_view: true });
    if (CFG.googleAdsId) gtag('config', CFG.googleAdsId);
  }

  function loadGtm(id) {
    if (loaded.gtm) return;
    loaded.gtm = true;
    (function (w, d, s, l, i) {
      w[l] = w[l] || []; w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
      const f = d.getElementsByTagName(s)[0]; const j = d.createElement(s);
      j.async = true; j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i;
      f.parentNode.insertBefore(j, f);
    })(window, document, 'script', 'dataLayer', id);
  }

  function loadMetaPixel(id) {
    if (loaded.meta) return;
    loaded.meta = true;
    /* eslint-disable */
    !(function (f, b, e, v, n, t, s) {
      if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
      if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    /* eslint-enable */
    window.fbq('init', id);
    window.fbq('track', 'PageView');
  }

  /* ------------------------------------------------------- attribution capture */

  function captureAttribution() {
    const p = new URLSearchParams(window.location.search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'];
    const touch = {};
    keys.forEach((k) => { if (p.get(k)) touch[k] = p.get(k); });
    if (Object.keys(touch).length) {
      touch.landing_page = window.location.href;
      touch.referrer = document.referrer || '';
      if (!storage.get(FIRST_TOUCH_KEY)) storage.setJSON(FIRST_TOUCH_KEY, touch); // first-touch, once
      try { window.sessionStorage.setItem('lz_lt', JSON.stringify(touch)); } catch (e) {}
    }
  }

  /* -------------------------------------------------- backend batch + beacon */

  let batch = [];
  let flushTimer = null;
  let backendOn = false;

  function startBackend() {
    if (backendOn) return;
    backendOn = true;
    flushQueuedOffline();
    schedulePageView();
  }

  function enqueue(evt) {
    if (!backendOn) return; // gated on analytics consent
    batch.push(evt);
    if (batch.length >= 10) flush();
    else if (!flushTimer) flushTimer = setTimeout(flush, 4000);
  }

  function flush(useBeacon) {
    if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; }
    if (!batch.length) return;
    const events = batch.splice(0, batch.length);
    const body = JSON.stringify({ session_id: sessionId(), anonymous_id: anonymousId, events });
    post(API + '/event', body, useBeacon, events);
  }

  function post(url, body, useBeacon, retryPayload) {
    try {
      if (useBeacon && navigator.sendBeacon) {
        const ok = navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
        if (!ok && retryPayload) queueOffline(retryPayload);
        return;
      }
      fetch(url, {
        method: 'POST', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body,
      }).catch(() => { if (retryPayload) queueOffline(retryPayload); });
    } catch (e) {
      if (retryPayload) queueOffline(retryPayload);
    }
  }

  function queueOffline(events) {
    const q = storage.getJSON(QUEUE_KEY, []);
    storage.setJSON(QUEUE_KEY, q.concat(events).slice(-100));
  }
  function flushQueuedOffline() {
    const q = storage.getJSON(QUEUE_KEY, []);
    if (!q.length) return;
    storage.setJSON(QUEUE_KEY, []);
    post(API + '/event', JSON.stringify({ session_id: sessionId(), anonymous_id: anonymousId, events: q }), false, q);
  }

  /* ------------------------------------------------------------- page + depth */

  let pageViewId = null;
  let pageStart = Date.now();
  let maxScroll = 0;

  function schedulePageView() {
    // Enrich the server session row with client-only signals.
    post(API + '/session', JSON.stringify({
      session_id: sessionId(), anonymous_id: anonymousId,
      screen: (window.screen ? window.screen.width + 'x' + window.screen.height : null),
      language: navigator.language,
    }), false);

    // Record the page view; keep its id to patch engagement on unload.
    fetch(API + '/page-view', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        session_id: sessionId(), anonymous_id: anonymousId,
        url: window.location.href, path: window.location.pathname,
        title: document.title, referrer: document.referrer || '',
        device_type: deviceType(),
      }),
    }).then((r) => r.ok ? r.json() : null).then((d) => { pageViewId = d && d.data ? d.data.id : null; }).catch(() => {});
  }

  function deviceType() {
    const ua = navigator.userAgent;
    if (/tablet|ipad|playbook|silk|(android(?!.*mobile))/i.test(ua)) return 'tablet';
    if (/Mobile|iP(hone|od)|Android.*Mobile|BlackBerry|IEMobile|Opera Mini/i.test(ua)) return 'mobile';
    return 'desktop';
  }

  function onScroll() {
    const h = document.documentElement;
    const denom = (h.scrollHeight - h.clientHeight) || 1;
    const pct = Math.min(100, Math.round(((h.scrollTop || window.scrollY) / denom) * 100));
    if (pct > maxScroll) maxScroll = pct;
  }

  function sendEngagement() {
    if (!backendOn) return;
    const seconds = Math.round((Date.now() - pageStart) / 1000);
    const body = JSON.stringify({
      session_id: sessionId(), id: pageViewId,
      time_on_page: seconds, scroll_depth: maxScroll, is_exit: true,
    });
    flush(true); // flush any pending events with beacon first
    if (pageViewId) post(API + '/page-view', body, true);
  }

  /* --------------------------------------------------------------- public API */

  // Meta standard-event name map (mirrors the server MetaConversionsApi).
  const FB_MAP = {
    booking_confirmed: 'Purchase', purchase: 'Purchase',
    generate_lead: 'Lead', lead_created: 'Lead', lead: 'Lead',
    begin_checkout: 'InitiateCheckout', add_payment_info: 'AddPaymentInfo',
    view_item: 'ViewContent', view_package: 'ViewContent', view_hotel: 'ViewContent',
    add_to_cart: 'AddToCart', travel_search: 'Search',
    search_hotel: 'Search', search_flight: 'Search', search_cab: 'Search',
  };

  function track(name, params) {
    params = params || {};
    const consent = getConsent();
    const eventId = params.event_id || (uuid());

    try {
      // First-party backend (analytics consent).
      enqueue({
        name: name,
        event_id: eventId,
        params: sanitize(params),
        value: params.value, currency: params.currency,
        product_type: params.product_type, product_id: params.product_id,
        anonymous_id: anonymousId,
      });

      // GA4 / GTM dataLayer (analytics consent).
      if (consent.analytics) {
        window.dataLayer.push(Object.assign({ event: name }, sanitize(params)));
        if (typeof window.gtag === 'function' && loaded.gtag && CFG.ga4Id) {
          window.gtag('event', name, sanitize(params));
        }
      }

      // Meta Pixel (marketing consent) — pass eventID for browser/CAPI dedup.
      if (consent.marketing && loaded.meta && typeof window.fbq === 'function') {
        const std = FB_MAP[name];
        const fbData = {};
        if (params.value != null) fbData.value = params.value;
        if (params.currency) fbData.currency = params.currency;
        if (params.product_id != null) fbData.content_ids = [String(params.product_id)];
        if (params.product_type) fbData.content_type = params.product_type;
        if (std) window.fbq('track', std, fbData, { eventID: eventId });
        else window.fbq('trackCustom', name, fbData, { eventID: eventId });
      }
    } catch (e) { /* never throw into the page */ }

    return eventId;
  }

  // gtag is also exposed globally for Google Ads conversion snippets.
  window.gtag = window.gtag || gtag;

  function sanitize(obj) {
    const deny = ['password', 'otp', 'cvv', 'cvc', 'card', 'card_number', 'pin', 'token', 'passport', 'aadhaar', 'aadhar', 'pan', 'email', 'phone'];
    const out = {};
    Object.keys(obj || {}).forEach((k) => {
      if (k === 'event_id' || k === 'user_data') return;
      if (deny.indexOf(k.toLowerCase()) !== -1) return;
      const v = obj[k];
      if (v == null) return;
      if (typeof v === 'object') out[k] = v;
      else out[k] = v;
    });
    return out;
  }

  const analytics = {
    track,
    page: schedulePageView,
    setConsent: saveConsent,
    getConsent,
    anonymousId,
    sessionId,
    // Report a Google Ads conversion (marketing consent). payload from backend.
    adsConversion(payload) {
      if (getConsent().marketing && typeof window.gtag === 'function' && payload && payload.send_to) {
        window.gtag('event', 'conversion', payload);
      }
    },
  };
  window.analytics = analytics;

  /* --------------------------------------------------------------- bootstrap */

  captureAttribution();
  const c = getConsent();
  if (c.set) applyConsent(c); // returning visitor with a saved choice

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('pagehide', sendEngagement);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') flush(true);
  });
})();
