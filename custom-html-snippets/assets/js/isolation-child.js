(function () {
    'use strict';

    var scheduled = false;

    function measure() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(function () {
            scheduled = false;
            var body = document.body;
            var doc = document.documentElement;
            if (!body || !doc) return;

            var height = Math.max(
                body.scrollHeight,
                body.offsetHeight,
                doc.clientHeight,
                doc.scrollHeight,
                doc.offsetHeight
            );

            var lastChild = body.lastElementChild;
            if (lastChild) {
                var rect = lastChild.getBoundingClientRect();
                var bottom = rect.bottom + (window.pageYOffset || doc.scrollTop || 0);
                if (bottom > height) {
                    height = bottom;
                }
            }

            // 2px buffer to prevent fractional-pixel scrollbar flicker
            height = Math.ceil(height) + 2;
            parent.postMessage({ type: 'chs:height', height: height }, '*');
        });
    }

    window.addEventListener('load', measure);
    window.addEventListener('resize', measure);
    document.addEventListener('DOMContentLoaded', measure);

    if (window.ResizeObserver && document.body) {
        new ResizeObserver(measure).observe(document.body);
        if (document.documentElement) {
            new ResizeObserver(measure).observe(document.documentElement);
        }
    }

    if (window.MutationObserver && document.body) {
        new MutationObserver(measure).observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true
        });
    }

    if (document.fonts) {
        document.fonts.ready.then(measure);
    }

    // Measure on image loads
    document.querySelectorAll('img').forEach(function (img) {
        if (!img.complete) {
            img.addEventListener('load', measure);
            img.addEventListener('error', measure);
        }
    });

    // Staggered measurements for late fonts and asynchronous widgets
    measure();
    setTimeout(measure, 100);
    setTimeout(measure, 400);
    setTimeout(measure, 1000);

    // Anchor link and navigation handler
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a');
        if (!link) return;

        var href = link.getAttribute('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) return;

        var hashIndex = href.indexOf('#');
        var isHashOnly = href.charAt(0) === '#';
        var isAnchor = false;
        var hash = '';

        if (isHashOnly) {
            isAnchor = true;
            hash = href.substring(1);
        } else if (hashIndex !== -1) {
            // Anchor link containing path or full URL
            var targetHash = href.substring(hashIndex + 1);
            isAnchor = true;
            hash = targetHash;
        }

        if (isAnchor && hash) {
            e.preventDefault();

            var targetEl = document.getElementById(hash);
            if (!targetEl) {
                try {
                    targetEl = document.querySelector('[name="' + hash.replace(/"/g, '\\"') + '"]');
                } catch (err) {}
            }

            var offsetTop = 0;
            var foundInside = false;

            if (targetEl) {
                foundInside = true;
                var rect = targetEl.getBoundingClientRect();
                offsetTop = rect.top + (window.pageYOffset || document.documentElement.scrollTop || 0);
            }

            parent.postMessage({
                type: 'chs:anchor_click',
                hash: hash,
                foundInside: foundInside,
                offsetTop: offsetTop
            }, '*');
            return;
        }

        // For ordinary links without target, default to _top so navigation happens in the main window
        if (!link.getAttribute('target')) {
            link.setAttribute('target', '_top');
        }
    }, true);

    // Listen for parent hash query (e.g. on direct deep-link load)
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type === 'chs:check_hash' && data.hash) {
            var hash = data.hash;
            var targetEl = document.getElementById(hash);
            if (!targetEl) {
                try {
                    targetEl = document.querySelector('[name="' + hash.replace(/"/g, '\\"') + '"]');
                } catch (err) {}
            }
            if (targetEl) {
                var rect = targetEl.getBoundingClientRect();
                var offsetTop = rect.top + (window.pageYOffset || document.documentElement.scrollTop || 0);
                parent.postMessage({
                    type: 'chs:anchor_click',
                    hash: hash,
                    foundInside: true,
                    offsetTop: offsetTop
                }, '*');
            }
        }
    });
}());
