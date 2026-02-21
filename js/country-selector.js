(function () {
    'use strict';
    function initCountrySelector() {
        var container = document.querySelector('.mu-header-country-item');
        if (!container) return;
        var trigger  = container.querySelector('.country-selector-trigger');
        var dropdown = container.querySelector('.country-selector-dropdown');
        if (!trigger || !dropdown) return;

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            trigger.setAttribute('aria-expanded', isVisible ? 'false' : 'true');
        });

        document.addEventListener('click', function (e) {
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
                trigger.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', initCountrySelector);
})();