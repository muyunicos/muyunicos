/**
 * MUYUNICOS - UI Interactive Scripts
 * Country Selector + WPLingua Toggle
 * Version: 1.0.0
 */

(function() {
    'use strict';

    /**
     * Country Selector - Dropdown functionality
     */
    function initCountrySelector() {
        var container = document.querySelector('.mu-header-country-item'); 
        if(!container) return;

        var trigger = container.querySelector('.country-selector-trigger');
        var dropdown = container.querySelector('.country-selector-dropdown');
        
        if (!trigger || !dropdown) return;
        
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            trigger.setAttribute('aria-expanded', isVisible ? 'false' : 'true');
        });
        
        document.addEventListener('click', function(e) {
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    }

    /**
     * WPLingua Switcher - Toggle UI with close button and restore tab
     */
    function initWpLinguaSwitcherToggle() {
        const switcher = document.querySelector('.wplng-switcher.insert-bottom-center');
        if (!switcher) return;

        const switcherContent = switcher.querySelector('.switcher-content');
        const languages = switcherContent && switcherContent.querySelector('.wplng-languages');
        if (!switcherContent || !languages) return;

        // Remove previous buttons from navigation
        Array.from(languages.querySelectorAll('.wplng-close-btn')).forEach(btn => btn.remove());

        // Insert close button at the end of language list
        function createCloseButton() {
            if (languages.querySelector('.wplng-close-btn')) return;
            const closeBtn = document.createElement('button');
            closeBtn.className = 'wplng-close-btn';
            closeBtn.innerHTML = '✕';
            closeBtn.title = 'Cerrar selector de idioma';
            closeBtn.setAttribute('aria-label', 'Cerrar selector de idioma');
            closeBtn.tabIndex = 0;
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeSwitcher();
            });
            languages.appendChild(closeBtn);
        }

        // Restore tab/flap
        function createRestoreTab() {
            const existingTab = document.querySelector('.wplng-restore-tab');
            if (existingTab) existingTab.remove();
            const restoreTab = document.createElement('div');
            restoreTab.className = 'wplng-restore-tab';

            // World icon 🌐
            const globe = document.createElement('span');
            globe.textContent = '🌐';
            restoreTab.appendChild(globe);

            restoreTab.addEventListener('click', function(e){
                e.preventDefault();
                openSwitcher();
            });
            document.body.appendChild(restoreTab);
        }

        function closeSwitcher() {
            switcher.classList.add('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'true');
            setTimeout(createRestoreTab, 150);
        }

        function openSwitcher() {
            switcher.classList.remove('wplng-collapsed');
            localStorage.setItem('wplng_switcher_closed', 'false');
            setTimeout(function(){
                createCloseButton();
            }, 1);
            const restoreTab = document.querySelector('.wplng-restore-tab');
            if (restoreTab) {
                restoreTab.style.opacity = '0';
                setTimeout(function(){ restoreTab.remove(); }, 260);
            }
        }

        // Persistence
        const isClosed = localStorage.getItem('wplng_switcher_closed') === 'true';
        if (isClosed) {
            switcher.classList.add('wplng-collapsed');
            createRestoreTab();
        } else {
            createCloseButton();
        }

        // Rebuild button if navigation/page reload/no render
        const observer = new MutationObserver(function() {
            if (!switcher.classList.contains('wplng-collapsed') && !languages.querySelector('.wplng-close-btn')) {
                createCloseButton();
            }
        });
        observer.observe(languages, {childList:true, subtree:false});
    }

    /**
     * Share button - Native Share API (mobile) + clipboard fallback (desktop)
     */
    function initShareButtons() {
        const shareBtns = document.querySelectorAll('.dcms-share-btn');
        if (!shareBtns.length) return;

        shareBtns.forEach((btn) => {
            if (btn.dataset.muShareBound === '1') return;
            btn.dataset.muShareBound = '1';

            btn.addEventListener('click', async (e) => {
                e.preventDefault();

                const desc = document.querySelector('meta[name="description"]');
                const shareData = {
                    title: document.title,
                    text: (desc && desc.content) ? desc.content : document.title,
                    url: window.location.href
                };

                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                        return;
                    } catch (err) {
                        if (err && err.name !== 'AbortError') {
                            copyToClipboard(shareData.url, btn);
                        }
                        return;
                    }
                }

                copyToClipboard(shareData.url, btn);
            });
        });

        function copyToClipboard(text, btn) {
            if (!navigator.clipboard) {
                fallbackCopyTextToClipboard(text, btn);
                return;
            }

            navigator.clipboard.writeText(text).then(function() {
                showFeedback(btn);
            }, function() {
                fallbackCopyTextToClipboard(text, btn);
            });
        }

        function fallbackCopyTextToClipboard(text, btn) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.setAttribute('readonly', '');
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showFeedback(btn);
            } catch (err) {
                // noop
            }
            document.body.removeChild(textArea);
        }

        function showFeedback(btn) {
            if (btn.classList.contains('is-copied')) return;

            btn.classList.add('is-copied');

            // Tooltip flotante
            const tooltip = document.createElement('span');
            tooltip.className = 'dcms-share-tooltip';
            tooltip.textContent = '¡Enlace copiado!';
            tooltip.setAttribute('role', 'status');
            tooltip.setAttribute('aria-live', 'polite');

            // Asegurar contexto
            const parent = btn.parentNode;
            if (parent && parent.style && parent.style.position !== 'relative') {
                parent.style.position = 'relative';
            }

            if (parent) {
                parent.insertBefore(tooltip, btn.nextSibling);
            }

            setTimeout(() => {
                btn.classList.remove('is-copied');
                if (tooltip && tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
            }, 2000);
        }
    }

    /**
     * Initialize all UI components
     */
    function init() {
        initCountrySelector();
        initWpLinguaSwitcherToggle();
        initShareButtons();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Fallback timeout for WPLingua (might load late)
    setTimeout(initWpLinguaSwitcherToggle, 1000);

})();
