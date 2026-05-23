(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.querySelector('.mu-country-modal-overlay');
    if (!overlay) return;

    var closeBtn = overlay.querySelector('.mu-country-modal__close');
    var stayBtn  = overlay.querySelector('.mu-country-modal__btn-stay');

    // Mostrar el modal
    overlay.classList.add('is-visible');

    function dismiss(setCookie) {
      overlay.classList.remove('is-visible');
      if (setCookie) {
        var domain = overlay.dataset.currentDomain || '';
        document.cookie =
          'muyu_stay_here=' + encodeURIComponent(domain) +
          '; path=/; max-age=2592000; SameSite=Lax';
      }
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', function () { dismiss(false); });
    }

    if (stayBtn) {
      stayBtn.addEventListener('click', function () { dismiss(true); });
    }

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') dismiss(false);
    });

    // Cerrar al hacer click fuera de la caja
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) dismiss(false);
    });
  });
}());
