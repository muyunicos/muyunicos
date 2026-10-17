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
 * - Repetición anual (feature 012): la casilla «Se repite cada año» de cada panel hace
 *   que sólo cuenten día y mes (el año de las fechas se ignora). La de Global arrastra a
 *   los países que no tenían decisión propia; el aviso replica anuales_incompletas.
 * - Lienzo del editor (feature 013): el banner REAL (mu_hero_slide_render) dentro de la
 *   fila, sincronizado con el formulario por nodos data-mu. Es vista: no guarda nada, no
 *   hace requests y no genera HTML nuevo. El clic en un nodo lleva a su campo (N1).
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
     * Muestra la variante del idioma elegido y sincroniza píldoras, input oculto y botón de IA.
     *
     * [017] Hay UN solo .mu-hero-banner-lang-block: cada campo lleva sus tres variantes
     * (.mu-hero-banner-variante con data-lang) y acá se muestra la del idioma activo. Se
     * oculta con `hidden` y nunca con `disabled`: un campo deshabilitado no viaja en el POST
     * y se perderían las traducciones al guardar.
     */
    function setLang($row, idioma) {
        var lang = String(idioma || 'es').toLowerCase();
        if (IDIOMAS.indexOf(lang) === -1) { lang = 'es'; }

        $row.find('.mu-hero-banner-variante').each(function () {
            var $variante = $(this);
            $variante.prop('hidden', String($variante.attr('data-lang') || '') !== lang);
        });

        $row.find('.mu-lang-pill').each(function () {
            var $pill  = $(this);
            var activa = $pill.attr('data-lang') === lang;
            $pill.toggleClass('is-active', activa);
            $pill.attr('aria-pressed', activa ? 'true' : 'false');
        });

        // El idioma abierto se recuerda al guardar (solo la pestaña del editor).
        $row.find('.mu-hero-banner-idioma-valor').val(lang);

        // [018] Las variantes son de un idioma, pero NO se descartan al cambiar: cada idioma
        // conserva las suyas y acá se repintan las del idioma activo. Antes, mirar el original
        // en español borraba las opciones que ya habías generado para PT y EN.
        $row.data('langActual', lang);
        pintarVariantesDeIdioma($row, lang);

        // La IA traduce del español a otro idioma: en ES no aplica.
        $row.find('.mu-hero-banner-ai-btn').prop('disabled', lang === 'es');
    }

    /**
     * ¿Ese campo tiene contenido propio (distinto del español)? Función PURA: la verifica
     * el arnés JS de 010.
     *
     * Un campo VACÍO no es contenido propio: por diseño (sobrescritura dispersa) el
     * guardado persiste vacío lo idéntico al español, así que vacío significa «muestra el
     * español» (el formulario lo refleja con el placeholder). Comparar sin exigir
     * contenido marcaba la píldora en TODOS los banners, incluso sin traducciones.
     */
    function tieneContenidoPropio(valor, base) {
        // Espacios en blanco no son contenido (el guardado sanea y recorta, así que el
        // JS tiene que mirar lo mismo que va a persistirse).
        var v = String(valor === undefined || valor === null ? '' : valor).trim();
        var b = String(base === undefined || base === null ? '' : base).trim();

        return '' !== v && v !== b;
    }

    /**
     * Punto de la píldora [automático]: ese idioma guarda algún valor propio, es
     * decir no vacío y distinto del español.
     */
    function actualizarPunto($row, lang) {
        if (lang === 'es') return;

        var $pills = $row.find('.mu-lang-pill[data-lang="' + lang + '"]');
        if (!$pills.length) return;

        var $origen = $row.find('.mu-hero-banner-variante[data-lang="es"]');
        var tiene   = false;

        $row.find('.mu-hero-banner-variante[data-lang="' + lang + '"] [data-field]').each(function () {
            var $campo = $(this);
            var $base  = $origen.find('[data-field="' + String($campo.attr('data-field') || '') + '"]');
            if (!$base.length) return;

            if (tieneContenidoPropio($campo.val(), $base.val())) {
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

    // ---------------------------------------------------------------
    // Lienzo del editor (feature 013): el banner REAL, en vivo
    // ---------------------------------------------------------------

    /** Mapa marca del lienzo → campo del formulario (clic-para-foco, N1). */
    var MU_A_CAMPO = {
        imagen: 'imagen',
        eyebrow: 'eyebrow',
        titulo: 'titulo',
        descripcion: 'descripcion',
        cta: 'cta_url',
        cta_texto: 'cta_texto',
        cta_secundario: 'cta_secundario_url',
        cta_secundario_texto: 'cta_secundario_texto',
        badge: 'free_badge_url',
        badge_text: 'free_badge_text'
    };

    /**
     * Escala del lienzo para encajar en la columna (nunca agranda). Función PURA: la
     * verifica el arnés JS de 013.
     */
    function escalaCanvas(disponible, ancho) {
        ancho = parseFloat(ancho) || 0;
        disponible = parseFloat(disponible) || 0;

        if (ancho <= 0 || disponible <= 0) { return 1; }

        var escala = disponible / ancho;

        if (escala > 1) { escala = 1; }
        if (escala < 0.05) { escala = 0.05; }

        return escala;
    }

    /** Valor de un campo en el idioma pedido, con el respaldo al español (como el front). */
    function valorCampo($row, lang, campo) {
        var $bloque = $row.find('.mu-hero-banner-variante[data-lang="' + lang + '"]');
        var valor   = $bloque.length ? String($bloque.find('[data-field="' + campo + '"]').val() || '') : '';

        if (valor === '' && lang !== 'es') {
            valor = String($row.find('.mu-hero-banner-variante[data-lang="es"] [data-field="' + campo + '"]').val() || '');
        }

        return valor;
    }

    /** Defaults del badge (los del front), para mostrar lo que se vería con el campo vacío. */
    function badgeDefaults() {
        var d = (window.muHeroBannersData && window.muHeroBannersData.badgeDefaults) || {};

        return { url: String(d.url || ''), text: String(d.text || '') };
    }

    /**
     * Texto plano del HTML que deja un nodo editable del lienzo. Función PURA: la verifica
     * el arnés JS. `contenteditable="plaintext-only"` marca los saltos de línea con <br> (o
     * <div>), según el navegador: acá se normalizan a \n para que el textarea del formulario
     * reciba el mismo contenido que se ve.
     */
    function textoPlanoDeNodo(html) {
        var plano = String(html || '')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/(div|p)>/gi, '\n')
            .replace(/<(div|p)[^>]*>/gi, '\n')
            .replace(/\n{2,}/g, '\n')
            .replace(/\n+$/, '');

        return $('<div></div>').html(plano).text();
    }

    /**
     * Pinta el lienzo con los valores ACTUALES del formulario (no guarda nada) y replica
     * las condiciones del front: eyebrow, CTA secundario y badge se ocultan cuando el sitio
     * tampoco los mostraría. El título admite HTML (<span class="highlight">); el resto es
     * texto plano. Sin request y sin generar HTML nuevo: se actualizan los nodos marcados.
     */
    function pintarLienzo($row) {
        var $c = $row.find('[data-mu-canvas]');
        if (!$c.length) { return; }

        var lang     = idiomaActivo($row);
        var defaults = badgeDefaults();

        $c.attr('data-idioma', lang);

        var titulo  = valorCampo($row, lang, 'titulo');
        var eyebrow = valorCampo($row, lang, 'eyebrow');
        var imagen  = valorCampo($row, lang, 'imagen');
        var ctaU    = valorCampo($row, lang, 'cta_url');
        var secT    = valorCampo($row, lang, 'cta_secundario_texto');
        var secU    = valorCampo($row, lang, 'cta_secundario_url');
        var bdgT    = valorCampo($row, lang, 'free_badge_text');
        var bdgU    = valorCampo($row, lang, 'free_badge_url');

        $c.find('[data-mu="titulo"]').html(titulo);
        $c.find('[data-mu="descripcion"]').text(valorCampo($row, lang, 'descripcion'));
        $c.find('[data-mu="cta_texto"]').text(valorCampo($row, lang, 'cta_texto'));
        $c.find('[data-mu="cta_secundario_texto"]').text(secT);
        $c.find('[data-mu="badge_text"]').html(bdgT || defaults.text);

        $c.find('[data-mu="cta"]').attr('href', ctaU);
        $c.find('[data-mu="cta_secundario"]').attr('href', secU);
        $c.find('[data-mu="badge"]').attr('href', bdgU || defaults.url);
        $c.find('[data-mu="imagen"]').attr('src', imagen).attr('alt', textoPlano(titulo));

        // Visibilidad: las mismas tres condiciones del front.
        $c.find('[data-mu="eyebrow"]').toggle(eyebrow !== '');
        $c.find('[data-mu="cta_secundario"]').toggle(secT !== '' && secU !== '');
        $c.find('[data-mu="badge"]').toggle(!!$row.find('input[name$="[show_free_badge]"]').prop('checked'));

        // N2: los nodos de TEXTO PLANO se editan en el lienzo (contenteditable plaintext-only).
        // El TÍTULO queda afuera: admite HTML (<span class="mu-highlight">) y editarlo a
        // texto plano rompería el marcado; su clic sigue llevando al campo del formulario.
        ['eyebrow', 'descripcion', 'cta_texto', 'cta_secundario_texto', 'badge_text'].forEach(function (campo) {
            $c.find('[data-mu="' + campo + '"]').attr({ contenteditable: 'plaintext-only', spellcheck: 'true' });
        });
    }

    /** Ajusta ancho, escala y alto del lienzo según el modo elegido (Escritorio / Móvil). */
    function escalarLienzo($row) {
        var $c = $row.find('[data-mu-canvas]');
        if (!$c.length) { return; }

        var ancho  = parseInt($c.attr('data-ancho'), 10) || 1280;
        var $inner = $c.find('.mu-hero-banner-canvas-inner');
        var alto   = $inner.length ? $inner[0].scrollHeight : 0;

        $c.css('--mu-canvas-w', ancho + 'px');
        $c.css('--mu-canvas-h', (alto > 0 ? alto : 580) + 'px');
        $c.css('--mu-canvas-scale', escalaCanvas($c.width(), ancho));
    }

    /** N1: lleva el foco (con scroll) al campo del formulario que corresponde al nodo. */
    function irAlCampo($row, marca) {
        var campo   = MU_A_CAMPO[marca] || marca;
        var $bloque = $row.find('.mu-hero-banner-variante[data-lang="' + idiomaActivo($row) + '"]');
        var $campo  = $bloque.find('[data-field="' + campo + '"]');

        if (!$campo.length) { return; }

        $row.find('[data-field].is-target').removeClass('is-target');
        $campo.addClass('is-target');

        var $destino = $campo.closest('p');
        if ($destino.length) {
            $('html, body').animate({ scrollTop: Math.max(0, $destino.offset().top - 120) }, 200);
        }

        $campo.trigger('focus');
        window.setTimeout(function () { $campo.removeClass('is-target'); }, 1600);
    }

    // ---------------------------------------------------------------
    // Resumen de la fila (feature 013): plegar, duplicar, ordenar, deshacer
    // ---------------------------------------------------------------

    /** Texto de interfaz traducido (lo localiza el PHP en muHeroBannersData). */
    function textoUI(clave, porDefecto) {
        return (window.muHeroBannersData && window.muHeroBannersData[clave]) || porDefecto;
    }

    /**
     * Duplica una fila: clona el DOM y renumera TODOS los nombres de campo al índice nuevo
     * (si no, el duplicado y el original pelearían por el mismo `mu_banner[N]`).
     */
    function duplicarFila($row, indice) {
        var $clon = $row.clone();

        $clon.attr('data-index', String(indice));
        $clon.removeClass('is-deleted is-folded');

        $clon.find('[name]').each(function () {
            var $campo = $(this);
            $campo.attr(
                'name',
                String($campo.attr('name') || '').replace(/mu_banner\[\d+\]/, 'mu_banner[' + indice + ']')
            );
        });

        $clon.find('.mu-hero-banner-row__delete-flag').val('0');
        $clon.find('input, textarea, select, button').prop('disabled', false);
        $clon.find('.mu-hero-banner-row__body').show();

        return $clon;
    }

    /**
     * Mueve una fila una posición en la lista. El orden de las filas ES el orden de los
     * slides en la home: era lo único que no se podía administrar.
     */
    function moverFila($btn, delta) {
        var $row = $btn.closest('.mu-hero-banner-row');

        if (delta < 0) {
            var $antes = $row.prev('.mu-hero-banner-row');
            if ($antes.length) { $row.insertBefore($antes); }
            return;
        }

        var $despues = $row.next('.mu-hero-banner-row');
        if ($despues.length) { $row.insertAfter($despues); }
    }

    /**
     * El español se muestra como placeholder en los campos vacíos de PT/EN: es
     * ayuda visual y no se envía, así el punto sigue marcando solo lo que tiene
     * valor propio. (Antes el checkbox copiaba los valores al marcarlo, lo que
     * hacía imposible distinguir una traducción real de una copia.)
     */
    function aplicarPlaceholders($row) {
        var $origen = $row.find('.mu-hero-banner-variante[data-lang="es"]');

        ['pt', 'en'].forEach(function (lang) {
            $row.find('.mu-hero-banner-variante[data-lang="' + lang + '"] [data-field]').each(function () {
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
        sincronizarChips($row);
        aplicarPlaceholdersFechas($row);
        actualizarPuntosFechas($row);
        pintarLienzo($row);
        escalarLienzo($row);
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

    /**
     * ¿El inicio es posterior al fin? Función PURA (aviso de rango invertido).
     *
     * [012] Con repetición anual (`anual`) un inicio posterior al fin NO es un error: es
     * la ventana que cruza el fin de año (20/12 → 10/01), el caso más común de promos
     * anuales. Se replica el mismo criterio que mu_hero_banner_avisos() en el servidor.
     */
    function rangoInvertido(inicio, fin, anual) {
        if (anual) { return false; }
        var a = parseDdmmaaaa(inicio);
        var b = parseDdmmaaaa(fin);
        return a !== null && b !== null && a > b;
    }

    /**
     * ¿La repetición anual quedó con una sola fecha? Función PURA.
     *
     * Una sola fecha = sin límite real (se muestra siempre), así que el panel lo avisa:
     * mismo criterio que `anuales_incompletas` en mu_hero_banner_avisos().
     */
    function anualIncompleta(inicio, fin, anual) {
        if (!anual) { return false; }
        var a = String(inicio || '').trim();
        var b = String(fin || '').trim();
        return (a === '') !== (b === '');
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

    /**
     * Estado de la casilla «Se repite cada año» de un ámbito de la fila ('' = general).
     * [012] Espejo en el DOM de mu_hero_banner_anual_efectivo().
     */
    function anualDeFila($row, pais) {
        return !!bloqueFechas($row, pais).find('[data-role="anual"]').prop('checked');
    }

    /**
     * ¿La repetición anual del país DIFIERE de la general? Entonces el país tiene una
     * decisión propia y el servidor la persiste (si coincide, la clave no se guarda).
     */
    function anualPropio($row, pais) {
        if (pais === '') { return false; }
        return anualDeFila($row, pais) !== anualDeFila($row, '');
    }

    /**
     * Al cambiar la casilla de Global, los países que seguían a la general la siguen
     * (los que tenían decisión propia no se tocan). Evita que una casilla heredada quede
     * mostrando un estado que no es el que se va a guardar.
     */
    function sincronizarAnualPaises($row) {
        var general = anualDeFila($row, '');
        var previo  = $row.data('anualGeneral');
        if (typeof previo === 'undefined') { previo = general; }
        $row.data('anualGeneral', general);

        $row.find('.mu-hero-banner-fechas-block[data-pais!=""] [data-role="anual"]').each(function () {
            var $c = $(this);
            if (!!$c.prop('checked') === !!previo) { $c.prop('checked', general); }
        });
    }

    // [013] Las píldoras de ámbito y su "¿de qué país edito las fechas?" se retiraron: la
    // tabla de vigencia muestra TODOS los ámbitos a la vez, así que ya no hay un ámbito
    // activo que recordar (`paisFechas`/`setPaisFechas` vivieron acá hasta esta feature).

    /** Punto de la píldora: ese país tiene una ventana propia (distinta de la general). */
    function actualizarPuntoFechas($row, pais) {
        if (pais === '') return;

        var $chip = $row.find('.mu-hero-banner-chip[data-pais="' + pais + '"]');
        if (!$chip.length) return;

        var tiene = false;

        ['inicio', 'fin'].forEach(function (campo) {
            var propia = fechaPropia($row, pais, campo);
            if (propia !== '' && propia !== fechaPropia($row, '', campo)) { tiene = true; }
        });

        // [012] La repetición anual propia también es una ventana propia.
        if (anualPropio($row, pais)) { tiene = true; }

        $chip.attr('data-has-fechas', tiene ? '1' : '0');
    }

    /** Puntos de todos los chips de país (mover las generales los afecta a todos). */
    function actualizarPuntosFechas($row) {
        $row.find('.mu-hero-banner-chip[data-pais!=""]').each(function () {
            actualizarPuntoFechas($row, String($(this).attr('data-pais') || ''));
        });
    }

    /**
     * Chips de país y tabla de vigencia (feature 013): marca el chip activo y la fila del
     * país en la tabla. Las filas de países SIN tildar quedan atenuadas: su ventana se
     * conserva (M7) pero el front no la mira hasta activar el país.
     */
    function sincronizarChips($row) {
        var $chips = $row.find('.mu-hero-banner-chips');
        var $lista = $chips.find('.mu-hero-banner-chips-lista');
        var $mas   = $chips.find('.mu-hero-banner-chips-mas');
        var libres = 0;

        $chips.find('.mu-hero-banner-chip input[type="checkbox"]').each(function () {
            var $check = $(this);
            var $chip  = $check.closest('.mu-hero-banner-chip');
            var activo = !!$check.prop('checked');
            var code   = String($check.val() || '');

            $chip.toggleClass('is-active', activo);

            // [014] El chip vive donde corresponde: tildado = resumen; libre = selector "+N".
            // Es el MISMO nodo (con su checkbox), así que el POST no cambia.
            if (activo && !$chip.parent().is($chips)) {
                $chip.insertBefore($mas);
            } else if (!activo && !$chip.parent().is($lista)) {
                $chip.appendTo($lista);
            }

            if (!activo) { libres++; }

            $row.find('.mu-hero-banner-fechas-block[data-pais="' + code + '"]')
                .toggleClass('is-inactivo', !activo)
                .attr('data-tildado', activo ? '1' : '0');
        });

        $mas.prop('hidden', libres === 0);
        $mas.find('[data-role="chips-libres"]').text(libres);
    }

    /**
     * [014] Vigencia por excepción: la tabla muestra Global + los países que DIFIEREN de la
     * general. Los que heredan están en el HTML (para que el POST no cambie) pero ocultos,
     * salvo con "ver todos" tildado.
     */
    function mostrarExcepcion($row, code) {
        $row.find('.mu-hero-banner-fechas-block[data-pais="' + code + '"]')
            .removeClass('is-hereda')
            .addClass('is-excepcion')
            .attr('data-es-excepcion', '1');
    }

    function ocultarExcepcion($row, code) {
        $row.find('.mu-hero-banner-fechas-block[data-pais="' + code + '"]')
            .removeClass('is-excepcion')
            .addClass('is-hereda')
            .attr('data-es-excepcion', '0');
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

        var ini   = fechaEfectiva($row, pais, 'inicio');
        var fin   = fechaEfectiva($row, pais, 'fin');
        var anual = anualDeFila($row, pais);

        function avisoTexto(clave, porDefecto) {
            return (window.muHeroBannersData && window.muHeroBannersData[clave]) || porDefecto;
        }

        if (fechaMalFormada(ini) || fechaMalFormada(fin)) {
            $aviso.text(avisoTexto('fechasFormato', 'Usá el formato DDMMAAAA (8 dígitos) en Inicio y Fin.'));
            $aviso.prop('hidden', false);
            return;
        }

        // [012] Anual con una sola fecha: no hay ciclo que repetir (se muestra siempre).
        if (anualIncompleta(ini, fin, anual)) {
            $aviso.text(avisoTexto('fechasAnualIncompleta', 'Con «Se repite cada año» hacen falta las dos fechas: con una sola, el banner se muestra siempre.'));
            $aviso.prop('hidden', false);
            return;
        }

        // [012] Con repetición anual, cruzar el fin de año NO es un rango invertido.
        var invertido = rangoInvertido(ini, fin, anual);
        $aviso.text(invertido ? avisoTexto('fechasMalas', 'Inicio es posterior al fin: el banner no se verá en este país.') : '');
        $aviso.prop('hidden', !invertido);
    }

    /**
     * Estado del botón de IA.
     *
     * [018] Los mensajes de ÉXITO ya no ocupan la fila: van al `title` del botón «Traducir»,
     * así se leen al pasar el mouse (el renglón de estado queda limpio). Los avisos que
     * importan —no se pudo traducir, el servicio cree que ya está en el idioma— siguen
     * visibles en rojo y cortos. Sin texto se limpia todo y se RESTAURA el tooltip original
     * del botón (se guarda la primera vez en data('tituloBase')).
     */
    function setAiStatus($estado, texto, ok) {
        if (!$estado || !$estado.length) return;

        var $btn = $estado.closest('.mu-hero-banner-ai').find('.mu-hero-banner-ai-btn');
        var base = '';

        if ($btn.length) {
            base = String($btn.data('tituloBase') || $btn.attr('title') || '');
            if (!$btn.data('tituloBase')) { $btn.data('tituloBase', base); }
        }

        if (texto && ok) {
            if ($btn.length) { $btn.attr('title', texto + (base ? ' · ' + base : '')); }
            $estado.text('').removeClass('is-ok is-bad');
            return;
        }

        $estado.text(texto || '').toggleClass('is-bad', !!texto).removeClass('is-ok');

        if ($btn.length && !texto) { $btn.attr('title', base); }
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

    /**
     * Tandas de variantes de una fila, POR IDIOMA.
     *
     * [018] Antes había una sola (`aiBatch`) y el cambio de idioma la descartaba: mirar el
     * original en español borraba las opciones que ya estabas probando para PT o EN.
     */
    function tandasDeVariantes($row) {
        var tandas = $row.data('aiBatches');
        if (!tandas) { tandas = {}; $row.data('aiBatches', tandas); }
        return tandas;
    }

    /**
     * Tanda guardada de un idioma. Función PURA: la verifica el arnés JS de 011.
     *
     * @param {object} tandas
     * @param {string} lang
     * @return {object|null}
     */
    function tandaDeIdioma(tandas, lang) {
        var tanda = ( tandas || {} )[ String(lang || '').toLowerCase() ];
        return tanda ? tanda : null;
    }

    /** Contenedor de variantes con las del idioma pedido (o vacío si no hay tanda). */
    function pintarVariantesDeIdioma($row, lang) {
        var $box  = $row.find('[data-role="ai-variants"]');
        var tanda = tandaDeIdioma(tandasDeVariantes($row), lang);

        $box.empty();
        if (!tanda) { return; }

        pintarBotonesVariantes($box, tanda);
    }

    /**
     * Pinta un botón por variante de la tanda. El número activo es la versión que quedó puesta
     * en los campos. Los botones son dinámicos (la cantidad la decide la respuesta), igual que
     * en el editor de reseñas.
     */
    function pintarBotonesVariantes($box, tanda) {
        $box.empty();

        var total = 0;

        ( tanda.campos || [] ).forEach(function (field) {
            var item  = ( tanda.items || {} )[field];
            var lista = ( item && item.variants ) ? item.variants : [];
            if (lista.length > total) { total = lista.length; }
        });

        if (total < 2) { return; }

        for (var i = 0; i < total; i++) {
            var persona = String(( tanda.personas || [] )[i] || '');
            var $b      = $('<button type="button" class="mu-ai-variant-btn"></button>');

            $b.text(String(i + 1))
                .attr('title', persona ? persona : 'Variante ' + (i + 1))
                .attr('data-variante', String(i))
                .toggleClass('is-active', ( tanda.activa || 0 ) === i);

            $box.append($b);
        }
    }

    /** Guarda la tanda del idioma (una por idioma) y pinta sus botones. */
    function pintarVariantes($row, lang, items, personas, campos) {
        var tanda = {
            lang: lang,
            items: items || {},
            personas: personas || [],
            campos: campos || [],
            activa: 0
        };

        tandasDeVariantes($row)[lang] = tanda;
        pintarBotonesVariantes($row.find('[data-role="ai-variants"]'), tanda);
    }

    /** Aplica la variante pedida a todos los campos del idioma activo y refresca la fila. */
    function aplicarVariante($row, indice) {
        var tanda = tandaDeIdioma(tandasDeVariantes($row), idiomaActivo($row));
        if (!tanda) { return; }

        var res = variantesDeFila(tanda.items, indice, tanda.campos);

        Object.keys(res.aplicar).forEach(function (field) {
            $row.find('.mu-hero-banner-variante[data-lang="' + tanda.lang + '"] [data-field="' + field + '"]')
                .val(res.aplicar[field]);
        });

        // Se recuerda qué número quedó puesto EN ESTE idioma: al volver, el botón marcado es el
        // correcto (refrescarFila → setLang repinta desde la tanda).
        tanda.activa = indice;

        $row.find('[data-role="ai-variants"] .mu-ai-variant-btn').each(function () {
            var $b = $(this);
            $b.toggleClass('is-active', parseInt($b.attr('data-variante'), 10) === indice);
        });

        refrescarFila($row);

        var aviso = mensajeVariante(indice, (tanda.personas || [])[indice] || '', res.faltantes);
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

        // Eliminar fila (marca _delete=1) con DESHACER: el mismo botón vuelve atrás y deja la
        // fila con sus valores intactos (el backend descarta sólo lo marcado).
        $list.on('click', '.mu-hero-banner-row__remove', function () {
            var $btn    = $(this);
            var $row    = $btn.closest('.mu-hero-banner-row');
            var $flag   = $row.find('.mu-hero-banner-row__delete-flag');
            var borrada = '1' === String($flag.val());

            if (borrada) {
                $flag.val('0');
                $row.removeClass('is-deleted');
                $row.find('input, textarea, select').not($flag).prop('disabled', false);
                $btn.text(textoUI('remove', 'Eliminar'));
                return;
            }

            $flag.val('1');
            $row.addClass('is-deleted');
            $row.find('input, textarea, select').not($flag).prop('disabled', true);
            $btn.text(textoUI('removed', 'Eliminado (se descarta al guardar) · clic para deshacer'));
        });

        // WP Media picker
        $list.on('click', '.mu-hero-banner-row__image-pick', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) return;

            // Acotado a la variante de ESTE idioma: el campo de imagen tiene una por idioma.
            var $wrap = $(this).closest('.mu-hero-banner-variante');
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
        // Chips de país (feature 013): el checkbox del chip activa el país
        // ---------------------------------------------------------------
        $list.on('change', '.mu-hero-banner-chip input[type="checkbox"]', function () {
            var $row = $(this).closest('.mu-hero-banner-row');

            sincronizarChips($row);
            aplicarPlaceholdersFechas($row);
        });

        // ---------------------------------------------------------------
        // Tabla de vigencia: «limpiar» la ventana de un ámbito
        // ---------------------------------------------------------------
        $list.on('click', '[data-role="limpiar-ventana"]', function (e) {
            e.preventDefault();

            var $btn  = $(this);
            var $row  = $btn.closest('.mu-hero-banner-row');
            var $fila = $btn.closest('.mu-hero-banner-fechas-block');
            var ambito = String($fila.attr('data-pais') || '');

            $fila.find('[data-fecha]').val('');
            $fila.find('input[data-role="anual"]').prop('checked', false);

            if (ambito === '') {
                aplicarPlaceholdersFechas($row);
                actualizarPuntosFechas($row);
            } else {
                actualizarPuntoFechas($row, ambito);
            }

            avisarFechas($row, ambito);

            // [014] Un país que vuelve a heredar deja de ser excepción: vuelve a ocultarse.
            if (ambito !== '') { ocultarExcepcion($row, ambito); }
        });

        // [014] «ver todos»: muestra también los países que heredan (la tabla completa).
        $list.on('change', '[data-role="ver-todos"]', function () {
            $(this).closest('.mu-hero-banner-row')
                .find('.mu-hero-banner-tabla')
                .toggleClass('is-ver-todos', !!$(this).prop('checked'));
        });

        // [014] «+ excepción por país»: promueve la fila de ese país y lleva el foco a Inicio.
        $list.on('click', '[data-role="nueva-excepcion"]', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var $row = $btn.closest('.mu-hero-banner-row');
            var code = String($btn.attr('data-pais') || '');

            mostrarExcepcion($row, code);
            $btn.remove();

            $row.find('.mu-hero-banner-fechas-block[data-pais="' + code + '"] [data-fecha="inicio"]').trigger('focus');
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

        // Repetición anual (feature 012): al tildar/destildar se rehace el aviso del panel.
        // La casilla de Global arrastra a los países que seguían a la general y recalcula
        // puntos/placeholders de todos (heredan de ella).
        $list.on('change', '.mu-hero-banner-fechas-block [data-role="anual"]', function () {
            var $campo = $(this);
            var $row   = $campo.closest('.mu-hero-banner-row');
            var pais   = String($campo.closest('.mu-hero-banner-fechas-block').attr('data-pais') || '');

            if (pais === '') {
                sincronizarAnualPaises($row);
                aplicarPlaceholdersFechas($row);
                actualizarPuntosFechas($row);
                $row.find('.mu-hero-banner-fechas-block').each(function () {
                    avisarFechas($row, String($(this).attr('data-pais') || ''));
                });
            } else {
                actualizarPuntoFechas($row, pais);
                avisarFechas($row, pais);
            }
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
            var lang   = String($campo.closest('.mu-hero-banner-variante').attr('data-lang') || 'es').toLowerCase();

            // [013] El lienzo se repinta siempre (es vista, no guarda nada).
            pintarLienzo($row);

            if (lang === 'es') {
                aplicarPlaceholders($row);
                actualizarPuntos($row);
                escalarLienzo($row);   // el alto puede cambiar (p. ej. un título de dos líneas)
                return;
            }

            actualizarPunto($row, lang);
        });

        // [013] La casilla del badge también cambia el lienzo (lo muestra o lo oculta).
        $list.on('change', '.mu-hero-banner-row input[name$="[show_free_badge]"]', function () {
            pintarLienzo($(this).closest('.mu-hero-banner-row'));
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
            var $origen = $row.find('.mu-hero-banner-variante[data-lang="es"]');
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
                        $row.find('.mu-hero-banner-variante[data-lang="' + lang + '"] [data-field="' + field + '"]')
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
        // Resumen (feature 013): plegar, duplicar y ordenar
        // ---------------------------------------------------------------
        $list.on('click', '[data-role="plegar"]', function (e) {
            e.preventDefault();

            var $row    = $(this).closest('.mu-hero-banner-row');
            var plegada = $row.toggleClass('is-folded').hasClass('is-folded');

            $(this).attr('aria-expanded', plegada ? 'false' : 'true');

            // El lienzo se mide cuando está visible: al desplegar hay que reencajarlo.
            if (!plegada) { escalarLienzo($row); }
        });

        $list.on('click', '[data-role="duplicar"]', function (e) {
            e.preventDefault();

            var $row  = $(this).closest('.mu-hero-banner-row');
            var $clon = duplicarFila($row, nextIndex++);

            $list.append($clon);
            refrescarFila($clon);
        });

        $list.on('click', '[data-role="subir"]', function (e) {
            e.preventDefault();
            moverFila($(this), -1);
        });

        $list.on('click', '[data-role="bajar"]', function (e) {
            e.preventDefault();
            moverFila($(this), 1);
        });

        // ---------------------------------------------------------------
        // Lienzo (feature 013): ancho, clic-para-foco y reencaje
        // ---------------------------------------------------------------
        $list.on('click', '.mu-hero-banner-canvas-w', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var $row = $btn.closest('.mu-hero-banner-row');

            $row.find('[data-mu-canvas]').attr('data-ancho', String($btn.attr('data-ancho') || '1280'));
            $btn.closest('.mu-hero-banner-canvas-widths').find('.mu-hero-banner-canvas-w').removeClass('is-active');
            $btn.addClass('is-active');
            escalarLienzo($row);
        });

        // Un solo handler para el lienzo: los CTA son enlaces y desde el editor NUNCA
        // navegan; si el clic cayó en un nodo marcado, se salta a su campo del formulario.
        $list.on('click', '[data-mu-canvas]', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $nodo = $(e.target).closest('[data-mu]');
            if (!$nodo.length) { return; }

            // N2: si el nodo es editable en el lienzo, el clic se queda ahí (no salta al
            // formulario): el usuario quiere escribir, no navegar.
            var editable = $nodo.attr('contenteditable');
            if ('undefined' !== typeof editable && false !== editable && 'false' !== editable) { return; }

            irAlCampo($(this).closest('.mu-hero-banner-row'), String($nodo.attr('data-mu') || ''));
        });

        // N2: escribir en el lienzo escribe en el campo REAL del formulario (no guarda nada).
        // El título no participa: admite HTML y se edita en su campo.
        $list.on('input', '[data-mu-canvas] [data-mu][contenteditable]', function () {
            var $nodo = $(this);
            var $row  = $nodo.closest('.mu-hero-banner-row');
            var lang  = idiomaActivo($row);
            var campo = MU_A_CAMPO[String($nodo.attr('data-mu') || '')] || '';
            var $in   = $row.find('.mu-hero-banner-variante[data-lang="' + lang + '"] [data-field="' + campo + '"]');

            if (!$in.length) { return; }

            $in.val(textoPlanoDeNodo($nodo.html()));

            // Estado de la fila sin repintar el lienzo: el caret está ahí dentro.
            actualizarPunto($row, lang);
        });

        // Al salir del nodo se normaliza (sin <br> sueltos) y el lienzo vuelve a su forma.
        $list.on('focusout', '[data-mu-canvas] [data-mu][contenteditable]', function () {
            var $nodo = $(this);
            var $row  = $nodo.closest('.mu-hero-banner-row');
            var campo = MU_A_CAMPO[String($nodo.attr('data-mu') || '')] || '';
            var $in   = $row.find('.mu-hero-banner-variante[data-lang="' + idiomaActivo($row) + '"] [data-field="' + campo + '"]');

            if ($in.length) { $in.val(textoPlanoDeNodo($nodo.html())); }

            pintarLienzo($row);
        });

        // Al redimensionar la ventana, los lienzos se reencajan a su columna.
        var muResizeTimer = null;
        $(window).on('resize', function () {
            window.clearTimeout(muResizeTimer);
            muResizeTimer = window.setTimeout(function () {
                $list.find('.mu-hero-banner-row').each(function () { escalarLienzo($(this)); });
            }, 150);
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
        tieneContenidoPropio: tieneContenidoPropio,
        parseDdmmaaaa: parseDdmmaaaa,
        rangoInvertido: rangoInvertido,
        fechaMalFormada: fechaMalFormada,
        anualIncompleta: anualIncompleta,
        escalaCanvas: escalaCanvas,
        textoPlanoDeNodo: textoPlanoDeNodo,
        variantesDeFila: variantesDeFila,
        mensajeVariante: mensajeVariante,
        tandaDeIdioma: tandaDeIdioma
    };

    $(ready);
})(jQuery);
