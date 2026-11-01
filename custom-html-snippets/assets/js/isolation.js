/* Separate, opaque-origin documents may request height changes and anchor scrolling only for their own frame. */
(function () {
    'use strict';

    function initFrames() {
        document.querySelectorAll('iframe.chs-isolated-frame').forEach(function (frame) {
            frame.setAttribute('scrolling', 'no');
            frame.style.overflow = 'hidden';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFrames);
    } else {
        initFrames();
    }

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || typeof data !== 'object') return;

        // Handle height resizing
        if (data.type === 'chs:height' && typeof data.height === 'number' && Number.isFinite(data.height)) {
            document.querySelectorAll('iframe.chs-isolated-frame').forEach(function (frame) {
                if (event.source !== frame.contentWindow) return;
                var height = Math.min(60000, Math.max(50, Math.ceil(data.height)));
                if (frame.style.height !== height + 'px') {
                    frame.style.height = height + 'px';
                }
                frame.style.overflow = 'hidden';
                frame.setAttribute('scrolling', 'no');

                // If URL has a hash on initial load, notify iframe to check anchor
                if (window.location.hash && !frame.dataset.chsHashChecked) {
                    frame.dataset.chsHashChecked = '1';
                    var initialHash = window.location.hash.replace(/^#/, '');
                    try {
                        frame.contentWindow.postMessage({ type: 'chs:check_hash', hash: initialHash }, '*');
                    } catch (e) {}
                }
            });
            return;
        }

        // Handle anchor click navigation and smooth scrolling
        if (data.type === 'chs:anchor_click' && data.hash) {
            document.querySelectorAll('iframe.chs-isolated-frame').forEach(function (frame) {
                if (event.source !== frame.contentWindow) return;

                var hash = data.hash;

                if (data.foundInside) {
                    var frameRect = frame.getBoundingClientRect();
                    var parentScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
                    var targetScrollY = frameRect.top + parentScrollY + (data.offsetTop || 0);

                    // Offset buffer for sticky header or admin bar
                    targetScrollY = Math.max(0, targetScrollY - 50);

                    window.scrollTo({
                        top: targetScrollY,
                        behavior: 'smooth'
                    });

                    if (window.history && window.history.pushState) {
                        window.history.pushState(null, '', '#' + hash);
                    } else {
                        window.location.hash = hash;
                    }
                } else {
                    // Try to find target on the host document
                    var hostTarget = document.getElementById(hash);
                    if (!hostTarget) {
                        try {
                            hostTarget = document.querySelector('[name="' + hash.replace(/"/g, '\\"') + '"]');
                        } catch (err) {}
                    }

                    if (hostTarget) {
                        hostTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        if (window.history && window.history.pushState) {
                            window.history.pushState(null, '', '#' + hash);
                        } else {
                            window.location.hash = hash;
                        }
                    } else {
                        window.location.hash = hash;
                    }
                }
            });
        }
    });
}());
