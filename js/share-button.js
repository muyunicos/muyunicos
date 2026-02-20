/**
 * Share Button - Botón Compartir Inteligente (Native Share API)
 */
(function() {
    'use strict';

    function init() {
        const shareBtns = document.querySelectorAll('.mu-share-btn');
        if (!shareBtns.length) return;
        
        shareBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const shareData = {
                    title: document.title,
                    text: document.querySelector('meta[name="description"]')?.content || document.title,
                    url: window.location.href
                };

                // 1. Intento Nativo (Móvil / Tablet / Edge)
                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            copyToClipboard(shareData.url, btn);
                        }
                    }
                } else {
                    // 2. Fallback Escritorio (Portapapeles)
                    copyToClipboard(shareData.url, btn);
                }
            });
        });
    }

    function copyToClipboard(text, btn) {
        if (!navigator.clipboard) {
            fallbackCopyTextToClipboard(text, btn);
            return;
        }
        navigator.clipboard.writeText(text).then(function() {
            showFeedback(btn);
        }).catch(function(err) {
            console.error('Error al copiar: ', err);
        });
    }

    function fallbackCopyTextToClipboard(text, btn) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showFeedback(btn);
        } catch (err) {
            console.error('Fallback error', err);
        }
        document.body.removeChild(textArea);
    }

    function showFeedback(btn) {
        const originalContent = btn.innerHTML;
        const checkIcon = (typeof muShareVars !== 'undefined' && muShareVars.checkIcon) 
            ? muShareVars.checkIcon 
            : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        
        btn.innerHTML = checkIcon;
        btn.classList.add('is-success');

        const tooltip = document.createElement('span');
        tooltip.className = 'mu-share-tooltip';
        tooltip.textContent = '¡Enlace copiado!';
        
        // Append to parent to avoid interfering with btn.innerHTML if possible,
        // but since we replace btn.innerHTML, let's append it to the body or wrapper.
        btn.parentNode.style.position = 'relative';
        btn.parentNode.insertBefore(tooltip, btn.nextSibling);

        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.classList.remove('is-success');
            if(tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
        }, 2000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();