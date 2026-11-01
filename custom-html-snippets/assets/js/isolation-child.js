(function () {
    'use strict';
    var scheduled = false;
    function measure() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(function () {
            scheduled = false;
            var body = document.body;
            if (!body) return;
            var height = Math.max(body.scrollHeight, body.offsetHeight);
            parent.postMessage({type: 'chs:height', height: height}, '*');
        });
    }
    window.addEventListener('load', measure);
    window.addEventListener('resize', measure);
    if (window.ResizeObserver && document.body) new ResizeObserver(measure).observe(document.body);
    if (document.fonts) document.fonts.ready.then(measure);
    measure();
}());
