/**
 * Header - Interacciones JavaScript
 * Migrado desde snippet "Header"
 * fix/mobile-submenu-notros: reescritura initNativeSubmenus con delegación
 * de eventos para corregir clicks en links hijos del sub-menu en móvil.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Mi Cuenta dropdown (móvil)
    ------------------------------------------------------------------ */
    function initAccountDropdown() {
        var accountWraps = document.querySelectorAll('.mu-account-dropdown-wrap');

        accountWraps.forEach(function (wrap) {
            var trigger  = wrap.querySelector('.mu-open-auth-modal');
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
       Submenús nativos en móvil — delegación de eventos

       ESTRATEGIA:
       - Un solo listener delegado en .main-nav captura todos los clicks.
       - Si el click es en un <a> que es hijo directo de un
         .menu-item-has-children Y el menú está en modo toggled:
           → preventDefault + toggle de sfHover/toggled-on en el <li>.
       - Si el click es en un <a> que está DENTRO de un .sub-menu:
           → se deja pasar (no preventDefault). El browser navega.
       - Esto evita el problema anterior de clonar nodos y perder
         referencias, y garantiza que los links hijos siempre naveguen.
    ------------------------------------------------------------------ */
    function initNativeSubmenus() {
        var nav = document.querySelector('.main-navigation .main-nav');
        if (!nav) { return; }

        // Eliminar listener previo si existe (evita duplicados en reinit)
        if (nav._muSubMenuHandler) {
            nav.removeEventListener('click', nav._muSubMenuHandler);
        }

        nav._muSubMenuHandler = function (e) {
            // Solo actuar en modo mobile (menú colapsado visible)
            var navigation = document.querySelector('.main-navigation');
            if (!navigation || !navigation.classList.contains('toggled')) { return; }

            var target = e.target;

            // Subir hasta encontrar el <a> que fue clickeado
            while (target && target !== nav && target.tagName !== 'A') {
                target = target.parentNode;
            }
            if (!target || target.tagName !== 'A') { return; }

            var parentLi = target.parentNode;

            // CASO 1: Click en link HIJO del sub-menu → dejar navegar
            if (target.closest('.sub-menu')) {
                // No hacemos nada; el browser navega normalmente.
                // pointer-events:auto ya está garantizado por CSS en estado abierto.
                return;
            }

            // CASO 2: Click en link padre de un item con hijos → acordeón
            if (parentLi && parentLi.classList.contains('menu-item-has-children')) {
                e.preventDefault();

                var isOpen = parentLi.classList.contains('toggled-on') ||
                             parentLi.classList.contains('sfHover');

                // Cerrar todos los items abiertos del mismo nivel
                var siblings = nav.querySelectorAll(
                    '.menu-item-has-children.toggled-on, .menu-item-has-children.sfHover'
                );
                siblings.forEach(function (s) {
                    if (s !== parentLi) {
                        s.classList.remove('toggled-on', 'sfHover', 'open');
                    }
                });

                // Toggle del item clickeado
                parentLi.classList.toggle('toggled-on', !isOpen);
                parentLi.classList.toggle('sfHover',    !isOpen);
                parentLi.classList.toggle('open',       !isOpen);
            }
        };

        nav.addEventListener('click', nav._muSubMenuHandler);
    }

    /* ------------------------------------------------------------------
       Re-init al abrir/cerrar el menú hamburguesa
    ------------------------------------------------------------------ */
    function initMenuToggleListener() {
        var menuToggle = document.querySelector('.menu-toggle');
        if (!menuToggle) { return; }

        menuToggle.addEventListener('click', function () {
            // GP tarda ~50ms en añadir .toggled; esperamos 120ms por seguridad.
            setTimeout(initNativeSubmenus, 120);
        });
    }

    /* ------------------------------------------------------------------
       Init
    ------------------------------------------------------------------ */
    function init() {
        initAccountDropdown();
        initNativeSubmenus();
        initMenuToggleListener();

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(initNativeSubmenus, 250);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
