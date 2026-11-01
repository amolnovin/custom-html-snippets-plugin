/* Separate, opaque-origin documents may request height changes only for their own frame. */
(function () {
    'use strict';
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.type !== 'chs:height' || typeof data.height !== 'number' || !Number.isFinite(data.height)) return;
        document.querySelectorAll('iframe.chs-isolated-frame').forEach(function (frame) {
            if (event.source !== frame.contentWindow) return;
            var height = Math.min(60000, Math.max(100, Math.ceil(data.height)));
            if (frame.style.height !== height + 'px') frame.style.height = height + 'px';
        });
    });
}());
