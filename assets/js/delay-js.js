(function() {
    'use strict';

    const userEvents = ['keydown', 'mousemove', 'touchmove', 'touchstart', 'scroll', 'wheel', 'click'];
    let triggered = false;

    function triggerExecution() {
        if (triggered) return;
        triggered = true;

        userEvents.forEach(e => window.removeEventListener(e, triggerExecution, { passive: true }));

        const delayedScripts = document.querySelectorAll('script[type="text/wpsc-queued"]');
        if (!delayedScripts.length) return;

        const scriptArray = Array.from(delayedScripts);
        executeScriptsInChunks(scriptArray);
    }

    async function executeScriptsInChunks(scripts) {
        for (let i = 0; i < scripts.length; i++) {
            const oldScript = scripts[i];
            const newScript = document.createElement('script');

            Array.from(oldScript.attributes).forEach(attr => {
                if (attr.name !== 'type' && attr.name !== 'data-wpsc-src') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });

            const realSrc = oldScript.getAttribute('data-wpsc-src');

            if (realSrc) {
                newScript.src = realSrc;
                await new Promise(resolve => {
                    newScript.onload = newScript.onerror = resolve;
                    if (oldScript && oldScript.parentNode) {
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    } else {
                        document.head.appendChild(newScript);
                    }
                });
            } else {
                newScript.textContent = oldScript.textContent;
                if (oldScript && oldScript.parentNode) {
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                } else {
                    document.head.appendChild(newScript);
                }
            }

            if ('scheduler' in window && 'yield' in window.scheduler) {
                await window.scheduler.yield();
            } else {
                await new Promise(resolve => setTimeout(resolve, 0));
            }
        }

        window.dispatchEvent(new CustomEvent('wpsc_scripts_loaded'));
    }

    userEvents.forEach(e => window.addEventListener(e, triggerExecution, { passive: true }));
    setTimeout(triggerExecution, 7000);
})();
