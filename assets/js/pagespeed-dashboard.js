
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('wpsc-run-psi-btn');
    if (!btn) return;

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.innerText = '⚡ Running Audit...';

        fetch(wpscPsiSettings.rest_url + '?force=1', {
            method: 'GET',
            headers: {
                'X-WP-Nonce': wpscPsiSettings.nonce
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.innerText = '⚡ Run PageSpeed Audit';
            if (data.success) {
                location.reload();
            } else {
                alert('Audit error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.innerText = '⚡ Run PageSpeed Audit';
            alert('Audit request failed: ' + err.message);
        });
    });
});
