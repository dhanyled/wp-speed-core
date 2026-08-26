document.addEventListener('DOMContentLoaded', function() {
    if ('IntersectionObserver' in window) {
        var lazyIframes = document.querySelectorAll('iframe[data-wpsc-facade]');
        var iframeObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var iframe = entry.target;
                    var origSrc = iframe.getAttribute('data-wpsc-src');
                    if (origSrc && !iframe.getAttribute('srcdoc')) {
                        iframe.setAttribute('src', origSrc);
                    }
                    iframeObserver.unobserve(iframe);
                }
            });
        }, { rootMargin: '200px 0px' });

        lazyIframes.forEach(function(iframe) {
            iframeObserver.observe(iframe);
        });
    }
});
