/**
 * Same-origin CCTV embed ↔ parent monitor dock health bridge.
 */
(function () {
    'use strict';

    const MSG = { health: 'olm-cctv-health' };

    let mediaEl = null;
    let status = 'waiting';
    let detail = '';
    let lastFrameAt = 0;
    let stallTimer = null;

    function post(payload) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage(payload, '*');
            }
        } catch (_) {}
    }

    function setHealth(next, nextDetail = '') {
        status = next;
        detail = nextDetail || '';
        if (next === 'live') lastFrameAt = Date.now();
        post({ type: MSG.health, status, detail, at: Date.now() });
    }

    function armStallWatch() {
        if (stallTimer) window.clearInterval(stallTimer);
        stallTimer = window.setInterval(() => {
            if (status !== 'live') return;
            if (Date.now() - lastFrameAt > 20000) {
                setHealth('stalled', '影像可能已停格或斷線');
            }
        }, 5000);
    }

    function markFrame() {
        lastFrameAt = Date.now();
        if (status !== 'live') setHealth('live');
        else post({ type: MSG.health, status: 'live', detail: '', at: Date.now() });
    }

    window.OlmCctvBridge = {
        attach(el) {
            mediaEl = el;
            setHealth('waiting', '等待影像畫面');
            armStallWatch();

            if (!el) {
                setHealth('error', '沒有可播放媒體');
                return;
            }

            if (el.tagName === 'VIDEO') {
                el.addEventListener('loadeddata', markFrame);
                el.addEventListener('playing', markFrame);
                el.addEventListener('timeupdate', () => {
                    if (el.readyState >= 2) markFrame();
                });
                el.addEventListener('stalled', () => setHealth('stalled', '串流停滯'));
                el.addEventListener('waiting', () => {
                    if (status === 'live') setHealth('stalled', '緩衝中');
                });
                el.addEventListener('error', () => setHealth('error', '播放錯誤'));
            }

            if (el.tagName === 'IMG') {
                el.addEventListener('load', markFrame);
                el.addEventListener('error', () => setHealth('error', '影像載入失敗'));
                if (el.complete && el.naturalWidth > 1) markFrame();
            }
        },
        setHealth,
        markFrame,
        getStatus() {
            return { status, detail, hasMedia: Boolean(mediaEl), bridge: true };
        },
        MSG,
    };

    post({ type: MSG.health, status: 'waiting', detail: 'bridge-ready', at: Date.now(), bridge: true });
})();
