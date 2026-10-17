/**
 * Muy Únicos — Admin Hero Banners
 * Carga: marketing_page_mu-hero-banners
 * - Add/remove rows (template clone)
 * - WP Media picker para imagen
 * - Selector de países (dropdown + píldoras)
 * - Fechas por país (bloque de ajustes generales): las píldoras eligen DE QUÉ país se
 *   editan las fechas ('Global' = las generales, que heredan los países sin ventana
 *   propia). El punto marca los países con fechas propias y el valor general se usa
 *   como placeholder. Mismo patrón que las píldoras de idioma.
 * - Píldoras de idioma ES | PT | EN (mismo componente que el editor de reseñas):
 *   eligen qué bloque se edita. El punto marca los idiomas con contenido propio y
 *   sale automático del contenido — ya no hay checkbox "Traducir".
 * - Botón "Traducir con IA" (ámbito hero_banner): completa los campos del idioma
 *   activo desde el español y NUNCA guarda (se revisa y se guarda a mano).
 *
 * Los listeners usan delegación sobre #mu-hero-banners-list para que también
 * funcionen en las filas creadas con "+ Agregar banner" (clon del template).
 */
(function ($) {
    'use strict';

    var IDIOMAS = ['es', 'en', 'pt'];

    /**
     * Idioma activo de una fila: la píldora marcada (o el input oculto).
     */
    function idiomaActivo($row) {
        var lang = String($row.find('.mu-lang-pill.is-active').attr('data-lang') || '').toLowerCase();

        if (IDIOMAS.indexOf(lang) === -1) {
            lang = String($row.find('.mu-hero-banner-idioma-valor').val() || 'es').toLowerCase();
        }

        return IDIOMAS.indexOf(lang) === -1 ? 'es' : lang;
    }

    /**
     * Muestra el bloque del idioma elegido y sincroniza píldoras, input oculto y
     * botón de IA. Cada .mu-hero-banner-lang-block trae su propio juego de campos:
     * 'es' es el idioma base y 'pt' / 'en' son las traducciones (réplica).
     */
    function setLang($row, idioma) {
        var lang = String(idioma || 'es').toLowerCase();
        if (IDIOMAS.indexOf(lang) === -1) { lang = 'es'; }

        $row.find('.mu-hero-banner-lang-block').each(function () {
            var $block = $(this);
            $block.css('display', $block.attr('data-lang') === lang ? 'block' : 'none');
        });

        $row.find('.mu-lang-pill').each(function () {
            var $pill  = $(this);
            var activa = $pill.attr('data-lang') === lang;
            $pill.toggleClass('is-active', activa);
            $pill.attr('aria-pressed', activa ? 'true' : 'false');
        });

        // El idioma abierto se recuerda al guardar (solo la pestaña del editor).
        $row.find('.mu-hero-banner-idioma-valor').val(lang);

        // Las variantes son de un idioma concreto: al cambiar de idioma se descartan.
        var previo = String($row.data('langActual') || '');
        if ('' !== previo && previo !== lang) { limpiarVariantes($row); }
        $row.data('langActual', lang);

        // La IA traduce del español a otro idioma: en ES no aplica.
        $row.find('.mu-hero-banner-ai-btn').prop('disabled', lang === 'es');
    }

    /**
     * Punto de la píldora [automático]: ese idioma guarda algún valor propio, es
     * decir distinto del español (lo idéntico al español no se persiste).
     */
    function actualizarPunto($row, lang) {
        if (lang === 'es') return;

        var $pills = $row.find('.mu-lang-pill[data-lang="' + lang + '"]');
        if (!$pills.length) return;

        var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');
        var tiene   = false;

        $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field]').each(function () {
            var $campo = $(this);
            var $base  = $origen.find('[data-field="' + String($campo.attr('data-field') || '') + '"]');
            if (!$base.length) return;

            if (String($campo.val() || '') !== String($base.val() || '')) {
                tiene = true;
                return false; // corta el each: alcanza con un campo propio
            }
        });

        $pills.attr('data-has-translation', tiene ? '1' : '0');
    }

    /** Punto de los dos idiomas traducibles (dependen del español y de sí mismos). */
    function actualizarPuntos($row) {
        ['pt', 'en'].forEach(function (lang) { actualizarPunto($row, lang); });
    }

    /** Valor de un campo sin marcado (el placeholder no interpreta HTML). */
    function textoPlano(valor) {
        return $('<div></div>').html(String(valor || '')).text().trim();
    }

    /**
     * El español se muestra como placeholder en los campos vacíos de PT/EN: es
     * ayuda visual y no se envía, así el punto sigue marcando solo lo que tiene
     * valor propio. (Antes el checkbox copiaba los valores al marcarlo, lo que
     * hacía imposible distinguir una traducción real de una copia.)
     */
    function aplicarPlaceholders($row) {
        var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');

        ['pt', 'en'].forEach(function (lang) {
            $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field]').each(function () {
                var $campo = $(this);
                var $base  = $origen.find('[data-field="' + String($campo.attr('data-field') || '') + '"]');
                if (!$base.length) return;

                var texto = textoPlano($base.val());
                if (texto.length > 90) { texto = texto.slice(0, 90) + '…'; }

                $campo.attr('placeholder', texto);
            });
        });
    }

    /**
     * Estado completo de una fila: píldora activa, puntos y placeholders.
     */
    function refrescarFila($row) {
        setLang($row, idiomaActivo($row));
        aplicarPlaceholders($row);
        actualizarPuntos($row);
        sincronizarPillsPais($row);
        aplicarPlaceholdersFechas($row);
        actualizarPuntosFechas($row);
    }

    // ---------------------------------------------------------------
    // Fechas por país (bloque de ajustes generales)
    // La píldora activa elige QUÉ país se edita; la píldora sin país ('') son las
    // fechas generales, que heredan los países sin ventana propia.
    // ---------------------------------------------------------------

    /** Timestamp comparable de una fecha DDMMAAAA (null si el formato no sirve). Pura. */
    function parseDdmmaaaa(valor) {
        var s = String(valor || '').trim();
        if (!/^\d{8}$/.test(s)) return null;
        return parseInt(s.substr(4, 4) + s.substr(2, 2) + s.substr(0, 2), 10);
    }

    /** ¿El inicio es posterior al fin? Función PURA (aviso de rango invertido). */
    function rangoInvertido(inicio, fin) {
        var a = parseDdmmaaaa(inicio);
        var b = parseDdmmaaaa(fin);
        return a !== null && b !== null && a > b;
    }

    /** Días del mes indicado (28/29/30/31), con bisiesto. Función PURA. */
    function diasDelMes(mes, anio) {
        if (2 === mes) {
            var bisiesto = (anio % 4 === 0 && anio % 100 !== 0) || (anio % 400 === 0);
            return bisiesto ? 29 : 28;
        }
        return [4, 6, 9, 11].indexOf(mes) !== -1 ? 30 : 31;
    }

    /**
     * ¿El valor no está vacío y NO es una fecha DDMMAAAA real? Función PURA.
     * Replica del servidor (checkdate): un 31/02 o un día 99 se avisan en el panel en
     * lugar de guardarse y quedar ignorados en el front.
     */
    function fechaMalFormada(valor) {
        var s = String(valor || '').trim();

        if ('' === s) { return false; }
        if (null === parseDdmmaaaa(s)) { return true; }

        var dia  = parseInt(s.substr(0, 2), 10);
        var mes  = parseInt(s.substr(2, 2), 10);
        var anio = parseInt(s.substr(4, 4), 10);

        if (mes < 1 || mes > 12) { return true; }

        return dia < 1 || dia > diasDelMes(mes, anio);
    }

    /** Bloque de fechas de un país de la fila ('' = generales). */
    function bloqueFechas($row, pais) {
        return $row.find('.mu-hero-banner-fechas-block[data-pais="' + String(pais || '') + '"]');
    }

    /** Valor propio de un campo de fecha ('' = hereda la general). */
    function fechaPropia($row, pais, campo) {
        return String(bloqueFechas($row, pais).find('[data-fecha="' + campo + '"]').val() || '');
    }

    /** Fecha efectiva de un país: la propia o, si está vacía, la general. */
    function fechaEfectiva($row, pais, campo) {
        var propia = fechaPropia($row, pais, campo);
        return propia !== '' ? propia : fechaPropia($row, '', campo);
    }

    /** País cuyas fechas se están editando ('' = generales). */
    function paisFechas($row) {
        return String($row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill.is-active').attr('data-pais') || '');
    }

    /**
     * Muestra las fechas del país pedido ('' = generales) y marca su píldora. Si el
     * país ya no está tildado, cae a las generales: no se edita una ventana que no se
     * va a guardar.
     */
    function setPaisFechas($row, pais) {
        var code  = String(pais || '');
        var $pill = $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill[data-pais="' + code + '"]');

        if (!$pill.length || (code !== '' && $pill.is(':hidden'))) {
            code  = '';
            $pill = $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill[data-pais=""]');
        }

        $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill').each(function () {
            var $p     = $(this);
            var activa = String($p.attr('data-pais') || '') === code;
            $p.toggleClass('is-active', activa);
            $p.attr('aria-pressed', activa ? 'true' : 'false');
        });

        $row.find('.mu-hero-banner-fechas-block').each(function () {
            var $block = $(this);
            $block.css('display', String($block.attr('data-pais') || '') === code ? 'block' : 'none');
        });

        avisarFechas($row, code);
    }

    /** Punto de la píldora: ese país tiene una ventana propia (distinta de la general). */
    function actualizarPuntoFechas($row, pais) {
        if (pais === '') return;

        var $pill = $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill[data-pais="' + pais + '"]');
        if (!$pill.length) return;

        var tiene = false;

        ['inicio', 'fin'].forEach(function (campo) {
            var propia = fechaPropia($row, pais, campo);
            if (propia !== '' && propia !== fechaPropia($row, '', campo)) { tiene = true; }
        });

        $pill.attr('data-has-fechas', tiene ? '1' : '0');
    }

    /** Puntos de todas las píldoras de país (mover las generales los afecta a todos). */
    function actualizarPuntosFechas($row) {
        $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill[data-pais!=""]').each(function () {
            actualizarPuntoFechas($row, String($(this).attr('data-pais') || ''));
        });
    }

    /**
     * Muestra/oculta las píldoras de país según los checkboxes tildados ('Global'
     * siempre visible) y reencuadra la píldora activa si su país dejó de estar tildado.
     */
    function sincronizarPillsPais($row) {
        var tildados = [];

        $row.find('.mu-hero-banner-paises-dropdown input[type="checkbox"]:checked').each(function () {
            tildados.push(String($(this).val()));
        });

        $row.find('.mu-hero-banner-paises-pills .mu-hero-banner-pill').each(function () {
            var $pill = $(this);
            var code  = String($pill.attr('data-pais') || '');
            if (code === '') return; // Global: siempre visible
            $pill.toggle(tildados.indexOf(code) !== -1);
        });

        setPaisFechas($row, paisFechas($row));
    }

    /** El valor general como placeholder de los campos de cada país (ayuda visual). */
    function aplicarPlaceholdersFechas($row) {
        ['inicio', 'fin'].forEach(function (campo) {
            var base = fechaPropia($row, '', campo);
            if (base === '') { base = campo === 'inicio' ? '01012026' : '31122026'; }

            $row.find('.mu-hero-banner-fechas-block[data-pais!=""] [data-fecha="' + campo + '"]')
                .attr('placeholder', base);
        });
    }

    /**
     * Aviso del panel de fechas: formato DDMMAAAA inválido o rango invertido. Los
     * paneles ocultos no pueden avisar por el navegador (un campo oculto con `pattern`
     * bloquearía el envío sin poder mostrarse), así que el aviso es propio del panel.
     */
    function avisarFechas($row, pais) {
        var $aviso = bloqueFechas($row, pais).find('[data-role="fechas-warning"]');
        if (!$aviso.length) return;

        var ini = fechaEfectiva($row, pais, 'inicio');
        var fin = fechaEfectiva($row, pais, 'fin');

        function avisoTexto(clave, porDefecto) {
            return (window.muHeroBannersData && window.muHeroBannersData[clave]) || porDefecto;
        }

        if (fechaMalFormada(ini) || fechaMalFormada(fin)) {
            $aviso.text(avisoTexto('fechasFormato', 'Usá el formato DDMMAAAA (8 dígitos) en Inicio y Fin.'));
            $aviso.prop('hidden', false);
            return;
        }

        var invertido = rangoInvertido(ini, fin);
        $aviso.text(invertido ? avisoTexto('fechasMalas', 'Inicio es posterior al fin: el banner no se verá en este país.') : '');
        $aviso.prop('hidden', !invertido);
    }

    /** Aviso del botón de IA (verde si salió bien). */
    function setAiStatus($estado, texto, ok) {
        if (!$estado || !$estado.length) return;

        $estado.text(texto || '');
        $estado.toggleClass('is-ok', !!ok);
        $estado.toggleClass('is-bad', !ok && !!texto);
    }

    /**
     * Posiciona un dropdown justo debajo de su botón, dentro del head de la fila.
     */
    function posicionarDropdown($btn, $dd) {
        var offset = $btn.position();
        if (!offset) return;
        $dd.css({
            left: Math.round(offset.left) + 'px',
            top: Math.round(offset.top + $btn.outerHeight(true)) + 'px'
        });
    }

    /**
     * Clasifica la respuesta del servicio para los campos pedidos. Función PURA: no
     * toca el DOM ni jQuery, así que se puede verificar con un arnés
     * (specs/008-banners-pildoras-ia/harness/banners-js.js).
     *
     * Tres situaciones que antes se mezclaban en un solo mensaje:
     *   - traducido: el servicio devolvió texto nuevo;
     *   - iguales: el servicio considera que ya está en el idioma destino;
     *   - faltantes: no llegó nada para ese campo.
     *
     * @param {string[]} campos
     * @param {object}   items Respuesta del servicio, por clave de campo.
     * @return {{aplicar: object, traducidos: number, iguales: string[], faltantes: string[]}}
     */
    function clasificarCampos( campos, items ) {
        var res = { aplicar: {}, traducidos: 0, iguales: [], faltantes: [] };

        ( campos || [] ).forEach( function ( field ) {
            var item = ( items || {} )[ field ];

            // Sin item: no llegó nada para ese campo.
            if ( ! item ) {
                res.faltantes.push( field );
                return;
            }

            // El servicio dice que ya está en el idioma, o no trajo texto: nada que aplicar.
            if ( item.unchanged || '' === String( item.translation || '' ).trim() ) {
                res.iguales.push( field );
                return;
            }

            res.aplicar[ field ] = String( item.translation );
            res.traducidos++;
        } );

        return res;
    }

    /**
     * Mensaje de estado para el resultado de una traducción de banner. Función PURA.
     *
     * @param {object} res  Resultado de clasificarCampos().
     * @param {string} lang Idioma destino ('en' | 'pt').
     * @return {{texto: string, ok: boolean}}
     */
    function mensajeTraduccion( res, lang ) {
        var iguales   = ( res.iguales || [] ).join( ', ' );
        var faltantes = ( res.faltantes || [] ).join( ', ' );

        if ( res.traducidos ) {
            return {
                texto: 'Listo: ' + res.traducidos + ' campo' + ( 1 === res.traducidos ? '' : 's' ) + ' traducido' + ( 1 === res.traducidos ? '' : 's' ) + '. Revisá y guardá.'
                    + ( res.iguales.length ? ' (Sin cambios según el servicio: ' + iguales + ')' : '' ),
                ok: true
            };
        }

        if ( res.faltantes.length ) {
            return { texto: 'El servicio no devolvió traducción para: ' + faltantes + '. Probá de nuevo.', ok: false };
        }

        return {
            texto: 'El servicio considera que ya están en ' + String( lang || '' ).toUpperCase() + ': ' + iguales + '. Si no es así, ajustá el tono o el glosario del ámbito, y probá de nuevo.',
            ok: false
        };
    }
    // ---------------------------------------------------------------
    // Variantes de la traducción (una versión por persona)
    // El servicio devuelve una versión por persona y acá se ofrecen botones numerados
    // (1·2·3…), igual que en el editor de reseñas: el número activo es la versión que
    // quedó en los campos y no se guarda nada hasta «Guardar cambios». La cantidad la
    // decide el ámbito (personas × variantes); si el ámbito no declara personas, no hay
    // botones y queda la traducción estándar de siempre.
    // ---------------------------------------------------------------

    /**
     * Textos de la variante pedida para los campos indicados. Función PURA.
     *
     * @param {object}   items  Respuesta del servicio por campo (con `variants`).
     * @param {number}   indice Variante pedida (0 = la primera).
     * @param {string[]} campos Campos del ámbito que se enviaron.
     * @return {{aplicar: object, faltantes: string[]}}
     */
    function variantesDeFila( items, indice, campos ) {
        var res = { aplicar: {}, faltantes: [] };

        ( campos || [] ).forEach( function ( field ) {
            var item  = ( items || {} )[ field ];
            var lista = ( item && item.variants && item.variants.length ) ? item.variants : [];
            var texto = lista[ indice ];

            if ( 'string' !== typeof texto || '' === texto.trim() ) {
                res.faltantes.push( field );
                return;
            }

            res.aplicar[ field ] = texto;
        } );

        return res;
    }

    /**
     * Mensaje de estado al elegir una variante. Función PURA.
     *
     * @param {number}   indice    Variante elegida (0 = la primera).
     * @param {string}   persona   Persona que la escribió ('' si no se sabe).
     * @param {string[]} faltantes Campos sin texto en esa variante.
     * @return {{texto: string, ok: boolean}}
     */
    function mensajeVariante( indice, persona, faltantes ) {
        var sin = ( faltantes && faltantes.length ) ? ' Sin texto en: ' + faltantes.join( ', ' ) + '.' : '';

        return {
            texto: 'Variante ' + ( indice + 1 ) + ( persona ? ' (' + persona + ')' : '' ) + ' aplicada.' + sin + ' Revisá y guardá.',
            ok: ! ( faltantes && faltantes.length )
        };
    }

    /** Descarta la tanda de variantes de la fila (al cambiar de idioma). */
    function limpiarVariantes($row) {
        $row.data('aiBatch', null);
        $row.find('[data-role="ai-variants"]').empty();
    }

    /**
     * Pinta un botón por variante. El 1 queda activo porque es la que el servicio ya
     * dejó puesta en los campos. Los botones son dinámicos (la cantidad la decide la
     * respuesta), igual que en el editor de reseñas.
     */
    function pintarVariantes($row, lang, items, personas, campos) {
        $row.data('aiBatch', {
            lang: lang,
            items: items || {},
            personas: personas || [],
            campos: campos || []
        });

        var $box = $row.find('[data-role="ai-variants"]');
        $box.empty();

        var total = 0;

        ( campos || [] ).forEach(function (field) {
            var item  = ( items || {} )[field];
            var lista = ( item && item.variants ) ? item.variants : [];
            if (lista.length > total) { total = lista.length; }
        });

        if (total < 2) { return; }

        for (var i = 0; i < total; i++) {
            var persona = String((personas || [])[i] || '');
            var $b      = $('<button type="button" class="mu-ai-variant-btn"></button>');

            $b.text(String(i + 1))
                .attr('title', persona ? persona : 'Variante ' + (i + 1))
                .attr('data-variante', String(i))
                .toggleClass('is-active', 0 === i);

            $box.append($b);
        }
    }

    /** Aplica la variante pedida a todos los campos del idioma activo y refresca la fila. */
    function aplicarVariante($row, indice) {
        var batch = $row.data('aiBatch');
        if (!batch) { return; }

        var res = variantesDeFila(batch.items, indice, batch.campos);

        Object.keys(res.aplicar).forEach(function (field) {
            $row.find('.mu-hero-banner-lang-block[data-lang="' + batch.lang + '"] [data-field="' + field + '"]')
                .val(res.aplicar[field]);
        });

        $row.find('[data-role="ai-variants"] .mu-ai-variant-btn').each(function () {
            var $b = $(this);
            $b.toggleClass('is-active', parseInt($b.attr('data-variante'), 10) === indice);
        });

        refrescarFila($row);

        var aviso = mensajeVariante(indice, (batch.personas || [])[indice] || '', res.faltantes);
        setAiStatus($row.find('[data-role="ai-status"]'), aviso.texto, aviso.ok);
    }

    function ready() {
        var $list = $('#mu-hero-banners-list');
        var $tpl  = $('#mu-hero-banner-template');
        if (!$list.length || !$tpl.length) return;

        var nextIndex = $list.find('.mu-hero-banner-row').length;

        // Agregar fila
        $('#mu-hero-banners-add').on('click', function () {
            var html = $tpl.html().replace(/__INDEX__/g, String(nextIndex++));
            var $row = $(html);
            $list.append($row);

            // Normalizar la fila nueva (idioma base ES por defecto)
            refrescarFila($row);
        });

        // Eliminar fila (marca _delete=1 y oculta visualmente; backend descarta)
        $list.on('click', '.mu-hero-banner-row__remove', function () {
            var $row = $(this).closest('.mu-hero-banner-row');
            var $flag = $row.find('.mu-hero-banner-row__delete-flag');
            $flag.val('1');
            $row.addClass('is-deleted');
            $row.find('input, textarea, select').not($flag).prop('disabled', true);
            $(this).text(window.muHeroBannersData && window.muHeroBannersData.removed ? window.muHeroBannersData.removed : 'Eliminado (se descarta al guardar)');
            $(this).prop('disabled', true);
        });

        // WP Media picker
        $list.on('click', '.mu-hero-banner-row__image-pick', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) return;

            // Acotado al bloque de imagen de ESTE idioma: la fila tiene uno por idioma.
            var $wrap = $(this).closest('.mu-hero-banner-row__image');
            var $url  = $wrap.find('.mu-hero-banner-row__image-url');
            var $img  = $wrap.find('.mu-hero-banner-row__image-preview');

            var frame = wp.media({
                title: (window.muHeroBannersData && window.muHeroBannersData.mediaTitle) || 'Seleccionar imagen',
                button: { text: (window.muHeroBannersData && window.muHeroBannersData.mediaButton) || 'Usar esta imagen' },
                multiple: false
            });

            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                // Guardamos URL relativa al sitio cuando es posible (más portable).
                var url = att.url || '';
                try {
                    var loc = window.location;
                    var origin = loc.protocol + '//' + loc.host;
                    if (url.indexOf(origin) === 0) {
                        url = url.substring(origin.length);
                    }
                } catch (err) { /* noop */ }

                $url.val(url);
                $img.attr('src', url).show();
            });

            frame.open();
        });

        // ---------------------------------------------------------------
        // Selector de países
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-paises-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $row = $(this).closest('.mu-hero-banner-row');
            var $dd  = $row.find('.mu-hero-banner-paises-dropdown');
            var visible = $dd.is(':visible');

            $list.find('.mu-hero-banner-paises-dropdown').hide();
            if (!visible) {
                posicionarDropdown($(this), $dd);
                $dd.show();
            }
        });

        // Al marcar/desmarcar un país se muestran u ocultan sus píldoras y su panel de
        // fechas (el marcado se genera en PHP; acá sólo se alterna la visibilidad).
        $list.on('change', '.mu-hero-banner-paises-dropdown input[type="checkbox"]', function () {
            var $row = $(this).closest('.mu-hero-banner-row');
            sincronizarPillsPais($row);
            aplicarPlaceholdersFechas($row);
        });

        // Evitar que el clic dentro del dropdown de países lo cierre
        $list.on('click', '.mu-hero-banner-paises-dropdown', function (e) {
            e.stopPropagation();
        });

        // ---------------------------------------------------------------
        // Píldoras de país del bloque de ajustes: eligen qué fechas se editan
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-paises-pills .mu-hero-banner-pill', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $pill = $(this);
            setPaisFechas($pill.closest('.mu-hero-banner-row'), String($pill.attr('data-pais') || ''));
        });

        // Fechas: el punto del país se recalcula al escribir. Mover las GENERALES
        // refresca además placeholders y puntos de todos los países (heredan de ellas).
        $list.on('input change', '.mu-hero-banner-fechas-block [data-fecha]', function () {
            var $campo = $(this);
            var $row   = $campo.closest('.mu-hero-banner-row');
            var pais   = String($campo.closest('.mu-hero-banner-fechas-block').attr('data-pais') || '');

            if (pais === '') {
                aplicarPlaceholdersFechas($row);
                actualizarPuntosFechas($row);
            } else {
                actualizarPuntoFechas($row, pais);
            }

            avisarFechas($row, pais);
        });

        // ---------------------------------------------------------------
        // Píldoras de idioma: eligen qué bloque se edita (ES | PT | EN)
        // ---------------------------------------------------------------
        $list.on('click', '.mu-lang-pill', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $pill = $(this);
            var lang  = String($pill.attr('data-lang') || '').toLowerCase();
            if (IDIOMAS.indexOf(lang) === -1) return;

            setLang($pill.closest('.mu-hero-banner-row'), lang);
        });

        // Al escribir: refrescar el punto del idioma (¿tiene valor propio?). Si se
        // tocó el español, se actualizan además placeholders y puntos de PT/EN,
        // porque heredan de él.
        $list.on('input change', '.mu-hero-banner-row [data-field]', function () {
            var $campo = $(this);
            var $row   = $campo.closest('.mu-hero-banner-row');
            var lang   = String($campo.closest('.mu-hero-banner-lang-block').attr('data-lang') || 'es').toLowerCase();

            if (lang === 'es') {
                aplicarPlaceholders($row);
                actualizarPuntos($row);
                return;
            }

            actualizarPunto($row, lang);
        });

        // ---------------------------------------------------------------
        // "Traducir con IA": del español al idioma activo. No guarda.
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-ai-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn    = $(this);
            var $row    = $btn.closest('.mu-hero-banner-row');
            var $estado = $row.find('[data-role="ai-status"]');
            var lang    = idiomaActivo($row);

            if (lang === 'es') { return; }

            // Solo los campos que el ámbito declara (textos; URLs e imagen no se traducen).
            var $origen = $row.find('.mu-hero-banner-lang-block[data-lang="es"]');
            var payload = {};

            String($btn.attr('data-fields') || '').split(',').forEach(function (field) {
                field = String(field || '').trim();
                if (!field) return;

                var valor = String($origen.find('[data-field="' + field + '"]').val() || '');
                if (valor !== '') { payload[field] = valor; }
            });

            var campos = Object.keys(payload);
            if (!campos.length) {
                setAiStatus($estado, 'Escribí primero los textos en español.', false);
                return;
            }

            var data = new FormData();
            data.append('action', 'mu_ai_translate');
            data.append('nonce', (window.muHeroBannersData && window.muHeroBannersData.aiNonce) || '');
            data.append('ambito', 'hero_banner');
            data.append('lang', lang);
            data.append('fields', JSON.stringify(payload));

            // La cantidad de variantes la decide el ámbito (personas × variantes): no se
            // pide un número fijo. Si ya hay una tanda para este idioma, el clic siguiente
            // pide una nueva (force).
            var tanda = $row.data('aiBatch');
            if (tanda && tanda.lang === lang) { data.append('force', '1'); }

            $btn.prop('disabled', true);
            setAiStatus($estado, 'Traduciendo…', false);

            var url = (window.muHeroBannersData && window.muHeroBannersData.aiUrl) || '';

            fetch(url, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (res) {
                    $btn.prop('disabled', false);

                    var d = (res && res.data) ? res.data : {};

                    if (!res || !res.success) {
                        setAiStatus($estado, d.error || 'No se pudo traducir.', false);
                        return;
                    }

                                                            var res = clasificarCampos(campos, d.items || {});

                    Object.keys(res.aplicar).forEach(function (field) {
                        $row.find('.mu-hero-banner-lang-block[data-lang="' + lang + '"] [data-field="' + field + '"]')
                            .val(res.aplicar[field]);
                    });

                    refrescarFila($row);

                    // Una versión por persona: botones numerados para cambiar de variante
                    // sin volver a pedirle nada al servicio.
                    pintarVariantes($row, lang, d.items || {}, d.personas || [], campos);

                    var aviso = mensajeTraduccion(res, lang);
                    setAiStatus($estado, aviso.texto, aviso.ok);
                })
                .catch(function () {
                    $btn.prop('disabled', false);
                    setAiStatus($estado, 'No se pudo traducir. Revisá la conexión e intentá de nuevo.', false);
                });
        });

        // ---------------------------------------------------------------
        // Variantes: el número elegido se aplica a todos los campos del idioma activo
        // ---------------------------------------------------------------
        $list.on('click', '[data-role="ai-variants"] .mu-ai-variant-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $b = $(this);
            aplicarVariante($b.closest('.mu-hero-banner-row'), parseInt($b.attr('data-variante'), 10) || 0);
        });

        // ---------------------------------------------------------------
        // Cerrar dropdowns al hacer clic fuera
        // ---------------------------------------------------------------
        $(document).on('click', function () {
            $list.find('.mu-hero-banner-paises-dropdown').hide();
        });

        // Estado inicial de cada fila ya renderizada
        $list.find('.mu-hero-banner-row').each(function () {
            refrescarFila($(this));
        });
    }

    // Funciones puras expuestas para los arneses (specs/008-…/harness/banners-js.js,
    // specs/010-…/harness/fechas-js.js y specs/011-…/harness/variantes-js.js).
    window.muHeroBanners = {
        clasificarCampos: clasificarCampos,
        mensajeTraduccion: mensajeTraduccion,
        parseDdmmaaaa: parseDdmmaaaa,
        rangoInvertido: rangoInvertido,
        fechaMalFormada: fechaMalFormada,
        variantesDeFila: variantesDeFila,
        mensajeVariante: mensajeVariante
    };

    $(ready);
})(jQuery);
