document.addEventListener('DOMContentLoaded', function() {
    if ('IntersectionObserver' in window) {
        var lazyIframes = document.querySelectorAll('iframe[data-wpsc-facade]');
        var iframeObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var iframe = entry.target;
                    iframeObserver.unobserve(iframe);
                }
            });
        });
        lazyIframes.forEach(function(iframe) {
            iframeObserver.observe(iframe);
        });
    }
});
