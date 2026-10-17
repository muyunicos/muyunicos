/**
 * Muy Únicos — Editor de reseñas (solo admin)
 *
 * Antes vivía inline dentro del shortcode mu_testimonios_section. Se movió a archivo
 * físico para cumplir la constitución (II: sin lógica en el HTML de salida) y para que
 * el navegador lo cachee. Se encola solo para quien puede gestionar reseñas.
 *
 * Depende de muTestimonials (reviews, nonce, aiNonce, ajaxUrl) de js/testimonials.js.
 * El editor trae la reseña de la base con la acción mu_get_review y el botón
 * "Traducir con IA" llama a mu_ai_translate (inc/ai-translate.php).
 *
 * @package GeneratePress_Child
 */
        (function () {
            var MODAL_LANGS = ['es', 'pt', 'en'];

            /** Objeto inerte que se devuelve cuando un elemento no está en el HTML. */
            var INERT_ELEMENT = {
                value: '', checked: false, disabled: false, textContent: '', innerHTML: '',
                className: '', placeholder: '', files: [], style: {},
                addEventListener: function () {}, reset: function () {},
                querySelector: function () { return null; }
            };

            /** Ids ausentes ya avisados (para no repetir el aviso en consola). */
            var missingIds = [];

            /**
             * Elemento por id.
             *
             * El marcado del editor viaja en el HTML cacheado de la página y este JS es un
             * archivo cacheado aparte: pueden estar desfasados un rato (por ejemplo, la
             * caché sirve el marcado anterior al botón «Traducir»). Si falta un elemento se
             * devuelve un objeto inerte y se deja constancia en consola, así el editor sigue
             * funcionando en lugar de romperse por completo.
             */
            function byId(id) {
                var el = document.getElementById(id);

                if (el) { return el; }

                if (missingIds.indexOf(id) === -1) {
                    missingIds.push(id);
                    console.warn('[MU] El editor de reseñas no encontró #' + id + ' (¿página cacheada?).');
                }

                return INERT_ELEMENT;
            }
            /**
             * Registra un listener solo si el elemento existe.
             *
             * El marcado del modal viaja dentro del HTML cacheado y este JS es un archivo
             * cacheado aparte: pueden estar desfasados un rato. Con esta guarda el editor
             * sigue funcionando aunque falte un elemento agregado en una version nueva.
             */
            function on(id, type, handler) {
                var el = byId(id);

                if (el) { el.addEventListener(type, handler); }
            }

            function reviewsData() {
                return (typeof muTestimonials !== 'undefined' && muTestimonials.reviews) ? muTestimonials.reviews : [];
            }

            /** Muestra el bloque del idioma elegido (solo cambia el comentario). */
            function setLang(lang) {
                currentLang = lang;

                document.querySelectorAll('.mu-review-lang-pill').forEach(function (pill) {
                    pill.classList.toggle('is-active', pill.getAttribute('data-lang') === lang);
                });
                document.querySelectorAll('.mu-review-lang-block').forEach(function (block) {
                    block.style.display = (block.getAttribute('data-lang') === lang) ? 'block' : 'none';
                });

                // El botón de IA traduce del español al idioma activo: en ES no aplica.
                var btn = byId('mu-review-translate');
                if (btn) { btn.disabled = ('es' === lang); }

                if ('es' === lang) { setStatus('', false); }
            }

            /**
             * El comentario en español se muestra como placeholder en PT/EN: es
             * solo ayuda visual y nunca se envía (no se guarda por accidente una
             * copia del español).
             */
            function syncPlaceholders() {
                var es = byId('mu-review-text').value;
                MODAL_LANGS.forEach(function (lang) {
                    if ('es' === lang) return;
                    var field = byId('mu-review-text-' + lang);
                    if (field) { field.placeholder = es; }
                });
            }

            /** Punto en la píldora: ese idioma ya tiene comentario propio guardado. */
            function markTranslated(lang, hasValue) {
                var pill = document.querySelector('.mu-review-lang-pill[data-lang="' + lang + '"]');
                if (pill) { pill.setAttribute('data-has-translation', hasValue ? '1' : '0'); }
            }

            /** Fecha del input type=date, sin romper si el dato viene raro. */
            function dateValue(timestamp) {
                var ts = parseInt(timestamp, 10);
                if (!ts) return '';
                var date = new Date(ts * 1000);
                return isNaN(date.getTime()) ? '' : date.toISOString().split('T')[0];
            }

            /** Aviso explícito en vez de un formulario vacío (índice inválido). */
            function showError(message) {
                byId('mu-review-modal-error-text').textContent = message;
                byId('mu-review-modal-error').style.display = 'block';
                byId('mu-review-form').style.display = 'none';
                byId('mu-delete-review-btn').style.display = 'none';
            }

            /** Estado limpio: alta, sin Eliminar, sin traducciones marcadas, ES activo. */
            function resetModal() {
                var form = byId('mu-review-form');
                form.reset();
                form.style.display = 'block';
                byId('mu-review-modal-error').style.display = 'none';
                byId('mu-review-index').value = '';
                byId('mu-purchase-photo-preview').innerHTML = '';
                byId('mu-delete-review-btn').style.display = 'none';
                MODAL_LANGS.forEach(function (lang) { markTranslated(lang, false); });
                syncPlaceholders();
                setLang('es');
            }

            /** Idioma activo del editor (lo fija setLang). */
            var currentLang = 'es';

            /** ¿El formulario ya tiene un nombre cargado? */
            function hasName() { return '' !== byId('mu-review-name').value.trim(); }

            /** Aviso de carga del editor. */
            function setBusy(text) {
                var box = byId('mu-review-modal-loading');
                if (!box) { return; }
                box.textContent = text || '';
                box.style.display = text ? 'block' : 'none';
            }

            /** Estado del botón Traducir con IA (verde si salió bien). */
            function setStatus(text, ok) {
                var box = byId('mu-review-translate-status');
                if (!box) { return; }
                box.textContent = text || '';
                box.className = 'mu-review-translate-status' + (ok ? ' is-ok' : (text ? ' is-bad' : ''));
            }

            /**
             * Vuelca una reseña en el formulario.
             *
             * Lo usan los dos caminos del editor: el listado que quedó en la página
             * (pintado inmediato) y la respuesta del servidor, que es la fuente de
             * verdad y evita el formulario vacío cuando la página viene de caché.
             */
            function fillReview(review) {
                byId('mu-review-name').value = review.author_name || '';
                byId('mu-review-rating').value = review.rating || 5;
                byId('mu-review-text').value = review.text || '';
                byId('mu-review-date').value = dateValue(review.time);
                byId('mu-review-profile-url').value = review.author_url || review.profile_url || '';
                byId('mu-review-profile-photo').value = review.profile_photo_url || '';
                byId('mu-review-purchase-photo-url').value = review.purchase_photo_url || '';
                byId('mu-review-hidden').checked = !!review.hidden;

                MODAL_LANGS.forEach(function (lang) {
                    if ('es' === lang) return;
                    var field = byId('mu-review-text-' + lang);
                    var value = review['text_' + lang] || '';
                    if (field) { field.value = value; }
                    markTranslated(lang, '' !== value);
                });

                syncPlaceholders();

                // Botón de eliminar solo con una reseña existente
                byId('mu-delete-review-btn').style.display = 'inline-block';

                byId('mu-purchase-photo-preview').innerHTML = review.purchase_photo_url
                    ? '<img src="' + review.purchase_photo_url + '" style="max-width:100px;border-radius:4px;">'
                    : '';
            }

            window.muReviewModal = {
                /** Compatibilidad: sin índice = alta (link "✎ Manual"). */
                open: function(index) {
                    if (typeof index === 'undefined' || index === null || index === '') {
                        this.openNew();
                        return;
                    }
                    this.openEdit(index);
                },

                /** Alta de reseña: formulario limpio, sin Eliminar. */
                openNew: function() {
                    resetModal();
                    byId('mu-review-modal-title').textContent = 'Agregar Reseña';
                    byId('mu-review-modal').style.display = 'flex';
                },

                /**
                 * Edición.
                 *
                 * Pinta primero con el listado que quedó en la página (respuesta
                 * inmediata) y enseguida confirma contra el servidor: la reseña se
                 * trae de la base, así el formulario nunca queda vacío aunque la
                 * página venga de caché o el índice del listado haya quedado viejo.
                 */
                openEdit: function(index) {
                    resetModal();
                    byId('mu-review-modal').style.display = 'flex';
                    byId('mu-review-index').value = String(index);
                    byId('mu-review-modal-title').textContent = 'Editar Reseña';

                    var local   = reviewsData()[index];
                    var painted = false;

                    if (local && 'object' === typeof local) {
                        fillReview(local);
                        painted = true;
                    }

                    this.loadFromServer(index, !painted);
                },

                /**
                 * Trae la reseña desde la base (fuente de verdad del editor).
                 *
                 * @param {number}  index       Índice en el listado guardado.
                 * @param {boolean} showLoading Avisar que está cargando.
                 */
                loadFromServer: function(index, showLoading) {
                    if (showLoading) { setBusy('Cargando la reseña…'); }

                    var fd = new FormData();
                    fd.append('action', 'mu_get_review');
                    fd.append('nonce', muTestimonials.nonce);
                    fd.append('review_index', String(index));

                    fetch(muTestimonials.ajaxUrl, { method: 'POST', body: fd })
                        .then(function (response) { return response.json(); })
                        .then(function (res) {
                            setBusy('');

                            var data = (res && res.data) ? res.data : {};

                            if (!res || !res.success) {
                                if (hasName()) {
                                    return; // ya quedó pintado con el listado
                                }

                                console.warn('[MU] No se pudo cargar la reseña ' + index + ': ' + (data.error || ''));
                                showError('No se pudo cargar la reseña seleccionada. ' + (data.error || 'Recargá la página e intentá de nuevo.'));
                                return;
                            }

                            fillReview(data.review || {});

                            if (data.stale && data.stale.length) {
                                setStatus('La traducción guardada quedó desactualizada: cambió el comentario en español.', false);
                            }
                        })
                        .catch(function () { setBusy(''); }); // sin red: queda lo que pintó el listado
                },

                close: function() {
                    byId('mu-review-modal').style.display = 'none';
                },
            
            deleteReview: function() {
                if (!confirm('¿Estás seguro de que quieres eliminar esta reseña? Esta acción no se puede deshacer.')) {
                    return;
                }
                
                var index = document.getElementById('mu-review-index').value;
                if (index === '') return;
                
                var formData = new FormData();
                formData.append('action', 'mu_delete_review');
                formData.append('nonce', muTestimonials.nonce);
                formData.append('review_index', index);
                
                fetch(muTestimonials.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        window.muReviewModal.close();
                        location.reload();
                    } else {
                        alert('Error: ' + data.data);
                    }
                })
                .catch(function(error) {
                    alert('Error al eliminar la reseña');
                });
            }
        };
        
        // Función global para compatibilidad
        function openReviewModal(index) {
            window.muReviewModal.open(index);
        }
        
        function closeReviewModal() {
            window.muReviewModal.close();
        }
        
        // Preview de foto de compra
        on('mu-review-purchase-photo', 'change', function(e) {
            var file = e.target.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('mu-purchase-photo-preview').innerHTML = 
                        '<img src="' + e.target.result + '" style="max-width:100px;border-radius:4px;">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Submit del formulario
        on('mu-review-form', 'submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'mu_save_review');
            formData.append('nonce', muTestimonials.nonce);
            
            var fileInput = document.getElementById('mu-review-purchase-photo');
            if (fileInput.files[0]) {
                formData.append('purchase_photo', fileInput.files[0]);
            }
            
            fetch(muTestimonials.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.data);
                }
            })
            .catch(function(error) {
                alert('Error al guardar la reseña');
            });
        });

        // --- Píldoras de idioma del editor (solo cambia el comentario) ---
        document.querySelectorAll('.mu-review-lang-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                setLang(pill.getAttribute('data-lang'));
            });
        });

        // El comentario en español alimenta el placeholder de PT/EN (solo visual).
        on('mu-review-text', 'input', syncPlaceholders);

        // Si la validación falla en el comentario base (que puede estar oculto
        // porque se está viendo PT/EN), volver a ES: el navegador no puede
        // enfocar ni avisar sobre un campo oculto.
        on('mu-review-form', 'invalid', function () {
            setLang('es');
        }, true);

        // --- Botón "Traducir con IA": completa el idioma activo y NO guarda ---
        on('mu-review-translate', 'click', function () {
            var button = this;
            var lang   = currentLang;

            if ('es' === lang) { return; }

            var source = byId('mu-review-text').value.trim();

            if ('' === source) {
                setStatus('Escribí primero el comentario en español.', false);
                return;
            }

            var fd = new FormData();
            fd.append('action', 'mu_ai_translate');
            fd.append('nonce', muTestimonials.aiNonce);
            fd.append('ambito', 'reviews');
            fd.append('lang', lang);
            fd.append('fields', JSON.stringify({ text: source }));

            button.disabled = true;
            setStatus('Traduciendo…', false);

            fetch(muTestimonials.ajaxUrl, { method: 'POST', body: fd })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    button.disabled = false;

                    var data = (res && res.data) ? res.data : {};

                    if (!res || !res.success) {
                        setStatus(data.error || 'No se pudo traducir.', false);
                        return;
                    }

                    var item = (data.items && data.items.text) ? data.items.text : null;

                    if (!item || item.unchanged || !item.translation) {
                        setStatus('El texto ya está en ese idioma: no hay nada que reemplazar.', false);
                        return;
                    }

                    byId('mu-review-text-' + lang).value = item.translation;
                    markTranslated(lang, true);
                    setStatus('Listo. Revisá la traducción y guardá.', true);
                })
                .catch(function () {
                    button.disabled = false;
                    setStatus('No se pudo traducir. Revisá la conexión e intentá de nuevo.', false);
                });
        });

        // Un clic en el fondo oscuro cierra el modal (salida rápida tras un aviso).
        on('mu-review-modal', 'click', function (e) {
            if (e.target === this) { window.muReviewModal.close(); }
        });
        })();


