(function () {
    'use strict';

    var cfg = window.muOneTap;
    if (!cfg || !cfg.clientId) {
        return;
    }

    function enviar(credential) {
        var body = new URLSearchParams();
        body.set('action', 'nmu_onetap_login');
        body.set('nonce', cfg.nonce);
        body.set('credential', credential);
        body.set('redirect', cfg.redirect || window.location.href);

        fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                    return;
                }
                window.console && console.warn('[muOneTap] login rechazado', res && res.data);
            })
            .catch(function (err) {
                window.console && console.warn('[muOneTap] error de red', err);
            });
    }

    function iniciar() {
        if (!(window.google && window.google.accounts && window.google.accounts.id)) {
            return false;
        }

        window.google.accounts.id.initialize({
            client_id: cfg.clientId,
            callback: function (resp) {
                if (resp && resp.credential) {
                    enviar(resp.credential);
                }
            },
            auto_select: false
        });

        window.google.accounts.id.prompt();
        return true;
    }

    if (!iniciar()) {
        var intentos = 0;
        var t = window.setInterval(function () {
            intentos++;
            if (iniciar() || intentos > 25) {
                window.clearInterval(t);
            }
        }, 300);
    }
})();