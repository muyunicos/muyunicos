(function () {
    'use strict';

    function initWpLinguaSwitcherToggle() {
        var switcher = document.querySelector('.wplng-switcher.insert-bottom-center');
        if (!switcher) return;
        var switcherContent = switcher.querySelector('.switcher-content');
        var languages = switcherContent && switcherContent.querySelector('.wplng-languages');
        if (!switcherContent || !languages) return;

        Array.from(languages.querySelectorAll('.wplng-close-btn')).forEach(function (btn) {
            btn.remove();
        });

        function createCloseButton() {
            if (languages.querySelector('.wplng-close-btn')) return;
            var closeBtn = document.createElement('button');
            closeBtn.className = 'wplng-close-btn';
            closeBtn.innerHTML = '✕';
            closeBtn.title = 'Cerrar selector de idioma';
            closeBtn.setAttribute('aria-label', 'Cerrar selector de idioma');
            closeBtn.tabIndex = 0;
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                closeSwitcher();
            });
            languages.appendChild(closeBtn);
        }

        function createRestoreTab() {
            var existing = document.querySelector('.wplng-restore-tab');
            if (existing) existing.remove();
            var tab = document.createElement('div');
            tab.className = 'wplng-restore-tab';
            var globe = document.createElement('span');
            globe.textContent = '🌐';
            tab.appendChild(globe);
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                openSwitcher();
            });
            document.body.appendChild(tab);
        }

        function closeSwitcher() {
            switcher.classList.add('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'true');
            setTimeout(createRestoreTab, 150);
        }

        function openSwitcher() {
            switcher.classList.remove('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'false');
            setTimeout(createCloseButton, 1);
            var tab = document.querySelector('.wplng-restore-tab');
            if (tab) {
                tab.style.opacity = '0';
                setTimeout(function () { tab.remove(); }, 260);
            }
        }

        var isClosed = localStorage.getItem('wplng_switcher_closed') === 'true';
        if (isClosed) {
            switcher.classList.add('wplng-collapsed');
            createRestoreTab();
        } else {
            createCloseButton();
        }

        var observer = new MutationObserver(function () {
            if (!switcher.classList.contains('wplng-collapsed') && !languages.querySelector('.wplng-close-btn')) {
                createCloseButton();
            }
        });
        observer.observe(languages, { childList: true, subtree: false });
    }

    document.addEventListener('DOMContentLoaded', initWpLinguaSwitcherToggle);
    setTimeout(initWpLinguaSwitcherToggle, 1000);
})();