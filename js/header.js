/**
 * Header - Interacciones JavaScript
 * fix/mobile-submenu-v3: GeneratePress maneja el toggle del sub-menu
 * via .dropdown-menu-toggle. Este archivo solo gestiona el dropdown
 * de Mi Cuenta — la lógica de acordeón del menú nativo queda 100% en GP.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Mi Cuenta dropdown (móvil)
    ------------------------------------------------------------------ */
    function initAccountDropdown() {
        var accountWraps = document.querySelectorAll('.mu-account-dropdown-wrap');

        accountWraps.forEach(function (wrap) {
            var trigger    = wrap.querySelector('.mu-open-auth-modal');
            var hasSubMenu = wrap.querySelector('.mu-sub-menu');

            if (trigger) {
                trigger.addEventListener('click', function (e) {
                    if (window.innerWidth <= 768 && hasSubMenu) {
                        e.preventDefault();
                        e.stopPropagation();
                        accountWraps.forEach(function (w) {
                            if (w !== wrap) { w.classList.remove('active'); }
                        });
                        wrap.classList.toggle('active');
                    }
                });
            }
        });

        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768) {
                accountWraps.forEach(function (wrap) {
                    if (!wrap.contains(e.target)) { wrap.classList.remove('active'); }
                });
            }
        });

        var resizeAccountTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeAccountTimer);
            resizeAccountTimer = setTimeout(function () {
                if (window.innerWidth > 768) {
                    accountWraps.forEach(function (wrap) { wrap.classList.remove('active'); });
                }
            }, 100);
        });
    }

    /* ------------------------------------------------------------------
       Init
    ------------------------------------------------------------------ */
    function init() {
        initAccountDropdown();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
