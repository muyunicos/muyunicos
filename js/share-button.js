/**
 * Botón Compartir Inteligente
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

                // 1. INTENTO NATIVO
                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            copyToClipboard(shareData.url, btn);
                        }
                    }
                } else {
                    // 2. FALLBACK
                    copyToClipboard(shareData.url, btn);
                }
            });
        });

        function copyToClipboard(text, btn) {
            if (!navigator.clipboard) {
                fallbackCopyTextToClipboard(text, btn);
                return;
            }
            navigator.clipboard.writeText(text).then(function() {
                showFeedback(btn);
            }, function(err) {
                console.error('Error al copiar: ', err);
            });
        }

        function fallbackCopyTextToClipboard(text, btn) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
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
            
            // Check icon provided via wp_localize_script (muShareVars.checkIcon)
            if (typeof muShareVars !== 'undefined' && muShareVars.checkIcon) {
                btn.innerHTML = muShareVars.checkIcon;
            } else {
                btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            }
            
            btn.classList.add('copied-success');

            const tooltip = document.createElement('span');
            tooltip.className = 'mu-share-tooltip';
            tooltip.textContent = '¡Enlace copiado!';
            
            btn.parentNode.style.position = 'relative';
            btn.parentNode.insertBefore(tooltip, btn.nextSibling);

            setTimeout(() => {
                btn.innerHTML = originalContent;
                btn.classList.remove('copied-success');
                if(tooltip.parentNode) tooltip.parentNode.removeChild(tooltip);
            }, 2000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();