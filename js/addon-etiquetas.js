/**
 * Addon Etiquetas v3.0 — Builder Logic
 *
 * Objeto MU: Constructor de etiquetas personalizadas.
 * - Modos: personalizar / super_pack
 * - Calculadora de precios con crédito y promo 2x1.5
 * - Resumen de selección
 * - Gestión de extras (checkbox, qty, textarea)
 * - Integración con variaciones WooCommerce
 *
 * Datos vía wp_localize_script:
 *   MU_Config    { general, extras_definitions, items }
 *   muEtiquetasData { currencySymbol }
 *
 * Carga condicional: is_product() (categorías 18/19)
 * Depende de: jquery
 */
(function () {
    'use strict';

    // ========================================================================
    // OBJETO MU — Motor del Builder
    // ========================================================================

    window.MU = {
        state: { mode: 'personalizar', items: {}, packQty: 0, packRopa: {} },
        isBuilderActive: true,
        stashedState: null,

        init: function () {
            jQuery('.quantity').hide();
            this.setMode('personalizar');
            this.setupFormatListener();
            this.variationInit();
        },

        // --- Listener de formato (variación "impresas" vs digital) ---
        setupFormatListener: function () {
            var self = this;
            var $variationSelect = jQuery('#pa_formato');
            if ($variationSelect.length > 0) {
                var checkFormat = function () {
                    var selected = $variationSelect.val();
                    if (selected === 'impresas') self.toggleBuilder(true);
                    else self.toggleBuilder(false);
                };
                $variationSelect.on('change', checkFormat);
                jQuery('.variations_form').on('check_variations', checkFormat);
                setTimeout(checkFormat, 200);
            } else {
                this.toggleBuilder(true);
            }
        },

        toggleBuilder: function (show) {
            var wasActive = this.isBuilderActive;
            this.isBuilderActive = show;
            var $builder     = jQuery('#mu-builder-container');
            var $totalWrapper = jQuery('#mu-total-wrapper');
            var $dataInput    = jQuery('#mu_data_input');
            var $summary      = jQuery('#mu-selection-summary');

            if (show) {
                $dataInput.prop('disabled', false);
                if (this.stashedState) {
                    this.restoreState(this.stashedState);
                    this.stashedState = null;
                }
                if ($builder.is(':hidden')) {
                    $builder.slideDown();
                    $totalWrapper.slideDown();
                }
                this.calculate();
            } else {
                if (wasActive) {
                    this.stashedState = this.snapshotState();
                }
                $builder.slideUp();
                $totalWrapper.slideUp();
                $summary.empty();
                document.getElementById('mu-final-price').textContent = this.formatMoney(0);
                $dataInput.val('').prop('disabled', true);
                this.toggleSubmit(true);
            }
        },

        snapshotState: function () {
            return JSON.parse(JSON.stringify({
                mode:     this.state.mode,
                items:    this.state.items,
                packQty:  this.state.packQty,
                packRopa: this.state.packRopa
            }));
        },

        restoreState: function (snap) {
            this.state.mode     = snap.mode || 'personalizar';
            this.state.items    = snap.items || {};
            this.state.packQty  = snap.packQty || 0;
            this.state.packRopa = snap.packRopa || {};

            jQuery('.mu-mode-btn').removeClass('active');
            jQuery('#btn-mode-' + this.state.mode).addClass('active');
            jQuery('.mu-view-container').addClass('mu-hidden');
            jQuery('#view-' + this.state.mode).removeClass('mu-hidden');

            this.rebuildUIFromState();
        },

        rebuildUIFromState: function () {
            var k, eK, ext, uID;

            if (this.state.mode === 'personalizar') {
                for (k in MU_Config.items) {
                    if (k === 'super_pack') continue;
                    var qty = this.state.items[k] ? this.state.items[k].qty : 0;
                    this.updateUI(k, qty);

                    if (this.state.items[k] && this.state.items[k].extras) {
                        for (eK in this.state.items[k].extras) {
                            ext = this.state.items[k].extras[eK];
                            uID = k + '_' + eK;
                            var $chk = jQuery('#check-' + uID);
                            $chk.prop('checked', !!ext.active);
                            jQuery('#opt-' + uID).toggleClass('active', !!ext.active);
                            if (ext.active) {
                                jQuery('#body-' + uID).show();
                            } else {
                                jQuery('#body-' + uID).hide();
                            }
                            if (ext.isText) {
                                jQuery('#text-' + uID).val(ext.textValue || '');
                                var qtyInput = document.getElementById('qty-' + uID);
                                if (qtyInput) qtyInput.value = ext.qty || 0;
                            } else if (MU_Config.extras_definitions[eK] && MU_Config.extras_definitions[eK].type !== 'fixed_price') {
                                jQuery('#rad-all-' + uID).toggleClass('active', ext.mode === 'all');
                                jQuery('#rad-custom-' + uID).toggleClass('active', ext.mode === 'custom');
                                var customWrap = document.getElementById('custom-wrap-' + uID);
                                if (customWrap) customWrap.style.display = (ext.mode === 'custom') ? 'flex' : 'none';
                                var qInput = document.getElementById('qty-' + uID);
                                if (qInput) qInput.value = ext.qty || 0;
                                this.updateAllLabel(k, eK, qty);
                            }
                        }
                    }
                }
            } else {
                this.updateUI('super_pack', this.state.packQty);
                var ropaKeys = ['plancha_ropa_clara', 'plancha_ropa_oscura', 'plancha_tela_sintetica'];
                ropaKeys.forEach(function (rk) {
                    var rqty = this.state.packRopa[rk] ? this.state.packRopa[rk].qty : 0;
                    this.updateUI(rk + '_sp', rqty);
                }.bind(this));
            }
        },

        resetStateGlobal: function () {
            this.state.items = {};
            this.state.packQty = 0;
            this.state.packRopa = {};
            this.stashedState = null;
            this.resetState();
            this.calculate();
        },

        // --- Acordeón (wrapper para MU_UI_Helper) ---
        toggleSection: function (id) {
            var section = document.getElementById('section-' + id);
            if (!section || section.classList.contains('fixed')) return;
            var isExpanding = section.classList.contains('collapsed');

            if (isExpanding) {
                var parent = section.parentElement;
                jQuery(parent).find('.mu-o-section.expanded').not(section).removeClass('expanded').addClass('collapsed');
            }
            section.classList.toggle('expanded', isExpanding);
            section.classList.toggle('collapsed', !isExpanding);
        },

        // --- Modos ---
        setMode: function (mode) {
            this.state.mode = mode;
            this.resetState();
            if (mode === 'super_pack') {
                this.state.packQty = 1;
                this.updateUI('super_pack', 1);
            }
            jQuery('.mu-mode-btn').removeClass('active');
            jQuery('#btn-mode-' + mode).addClass('active');
            jQuery('.mu-view-container').addClass('mu-hidden');
            jQuery('#view-' + mode).removeClass('mu-hidden');
            this.calculate();
        },

        resetState: function () {
            this.state.items = {};
            this.state.packQty = 0;
            this.state.packRopa = {};
            jQuery('.mu-qty-input').val(0);
            jQuery('textarea.mu-text-area').val('');
            jQuery('.mu-row').removeClass('selected');
            jQuery('.mu-item-options').slideUp();
            jQuery('input[type="checkbox"]').prop('checked', false);
            jQuery('.mu-advanced-option').removeClass('active');
            jQuery('.mu-option-body').hide();
        },

        initItemState: function (key) {
            if (!this.state.items[key]) this.state.items[key] = { qty: 0, extras: {} };
        },

        // --- Actualizar cantidades ---
        updateItem: function (key, change) {
            var isPackMode = this.state.mode === 'super_pack';

            if (key === 'super_pack') {
                this.state.packQty = Math.max(0, this.state.packQty + change);
                this.updateUI('super_pack', this.state.packQty);
            } else if (isPackMode) {
                var cleanKey = key.replace('_sp', '');
                if (!this.state.packRopa[cleanKey]) this.state.packRopa[cleanKey] = { qty: 0 };
                this.state.packRopa[cleanKey].qty = Math.max(0, this.state.packRopa[cleanKey].qty + change);
                this.updateUI(key, this.state.packRopa[cleanKey].qty);
            } else {
                this.initItemState(key);
                var newQty = Math.max(0, this.state.items[key].qty + change);
                this.state.items[key].qty = newQty;
                this.updateUI(key, newQty);

                if (this.state.items[key].extras) {
                    for (var extKey in this.state.items[key].extras) {
                        var extState = this.state.items[key].extras[extKey];
                        var def = MU_Config.extras_definitions[extKey];
                        if (extState.active && (extState.mode === 'all' || extState.isText)) {
                            if (def.type !== 'fixed_price') {
                                extState.qty = newQty;
                                var inputQty = document.getElementById('qty-' + key + '_' + extKey);
                                if (inputQty) inputQty.value = newQty;
                            }
                        }
                        this.updateAllLabel(key, extKey, newQty);
                    }
                }
                if (newQty === 0) this.resetOptions(key);
            }
            this.calculate();
        },

        updateUI: function (key, qty) {
            var input = document.getElementById('qty-' + key);
            if (input) input.value = qty;
            var $row     = jQuery('#row-' + key);
            var $options = $row.find('.mu-item-options');
            if (qty > 0) {
                $row.addClass('selected');
                if ($options.length && $options.is(':hidden')) $options.slideDown(200);
            } else {
                $row.removeClass('selected');
                if ($options.length) $options.slideUp(200);
            }
        },

        // --- Funciones de extras ---
        resetOptions: function (key) {
            if (key === 'super_pack' || key.endsWith('_sp')) return;
            if (this.state.items[key].extras) {
                for (var extKey in this.state.items[key].extras) {
                    var uID = key + '_' + extKey;
                    var chk = document.getElementById('check-' + uID);
                    if (chk) chk.checked = false;
                    jQuery('#opt-' + uID).removeClass('active');
                    jQuery('#body-' + uID).hide();
                    this.state.items[key].extras[extKey].active    = false;
                    this.state.items[key].extras[extKey].qty       = 0;
                    this.state.items[key].extras[extKey].textValue = '';
                    jQuery('#text-' + uID).val('');
                }
            }
        },

        toggleExtra: function (itemKey, extraKey) {
            this.initItemState(itemKey);
            var uID       = itemKey + '_' + extraKey;
            var isChecked = document.getElementById('check-' + uID).checked;
            var def       = MU_Config.extras_definitions[extraKey];
            var isText       = (def && def.input_type === 'text_box');
            var isFixedPrice = (def && def.type === 'fixed_price');

            if (!this.state.items[itemKey].extras[extraKey]) {
                this.state.items[itemKey].extras[extraKey] = { active: false, mode: 'all', qty: 0, isText: isText, textValue: '' };
            }
            var extState   = this.state.items[itemKey].extras[extraKey];
            extState.active = isChecked;

            if (isChecked) {
                jQuery('#body-' + uID).slideDown(200);
                jQuery('#opt-' + uID).addClass('active');
                if (isFixedPrice) {
                    extState.qty = 1;
                } else if (extState.mode === 'all' || isText) {
                    extState.qty = this.state.items[itemKey].qty;
                    this.updateAllLabel(itemKey, extraKey, extState.qty);
                    if (isText) document.getElementById('qty-' + uID).value = extState.qty;
                }
            } else {
                jQuery('#body-' + uID).slideUp(200);
                jQuery('#opt-' + uID).removeClass('active');
                extState.qty = 0;
            }
            this.calculate();
        },

        updateExtraText: function (itemKey, extraKey) {
            var uID = itemKey + '_' + extraKey;
            if (this.state.items[itemKey] && this.state.items[itemKey].extras[extraKey]) {
                this.state.items[itemKey].extras[extraKey].textValue = document.getElementById('text-' + uID).value;
                this.calculate();
            }
        },

        setExtraMode: function (itemKey, extraKey, mode) {
            var uID      = itemKey + '_' + extraKey;
            var extState = this.state.items[itemKey].extras[extraKey];
            extState.mode = mode;
            jQuery('#rad-all-' + uID).toggleClass('active', mode === 'all');
            jQuery('#rad-custom-' + uID).toggleClass('active', mode === 'custom');
            var customWrap = document.getElementById('custom-wrap-' + uID);
            if (mode === 'all') {
                customWrap.style.display = 'none';
                extState.qty = this.state.items[itemKey].qty;
            } else {
                customWrap.style.display = 'flex';
                if (extState.qty === 0) extState.qty = 1;
                document.getElementById('qty-' + uID).value = extState.qty;
            }
            this.calculate();
        },

        updateExtraQty: function (itemKey, extraKey, change) {
            var extState  = this.state.items[itemKey].extras[extraKey];
            var parentQty = this.state.items[itemKey].qty;
            var newQty    = Math.max(1, Math.min(parentQty, extState.qty + change));
            extState.qty = newQty;
            document.getElementById('qty-' + itemKey + '_' + extraKey).value = newQty;
            this.calculate();
        },

        updateAllLabel: function (itemKey, extraKey, qty) {
            var lbl = document.getElementById('lbl-all-' + itemKey + '_' + extraKey);
            if (lbl) lbl.textContent = 'Todas (' + qty + ')';
        },

        // ====================================================================
        // CALCULADORA CENTRAL
        // ====================================================================

        calculate: function () {
            if (!this.isBuilderActive) return;

            var basePrice      = MU_Config.general.precio_base_wc;
            var initialCredit  = MU_Config.general.precio_minimo_ref;
            var usedCredit     = 0;
            var extrasCostTotal = 0;
            var p    = MU_Config.items;
            var defs = MU_Config.extras_definitions;

            var calcCost = function (qty, price) {
                return (Math.floor(qty / 2) * (price * 1.5)) + ((qty % 2) * price);
            };

            if (this.state.mode === 'personalizar') {
                for (var k in this.state.items) {
                    var i = this.state.items[k];
                    if (i.qty > 0) {
                        usedCredit += calcCost(i.qty, p[k].precio);
                        if (i.extras) {
                            for (var eK in i.extras) {
                                if (i.extras[eK].active) {
                                    if (defs[eK].type === 'fixed_price') {
                                        extrasCostTotal += defs[eK].precio;
                                    } else if (i.extras[eK].qty > 0) {
                                        extrasCostTotal += (i.extras[eK].qty * defs[eK].precio);
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if (this.state.packQty > 0) usedCredit += calcCost(this.state.packQty, p.super_pack.precio);
                for (var pk in this.state.packRopa) {
                    if (this.state.packRopa[pk].qty > 0) {
                        usedCredit += calcCost(this.state.packRopa[pk].qty, p[pk].precio);
                    }
                }
            }

            var remainingCredit = Math.max(0, initialCredit - usedCredit);
            var itemOverrun     = Math.max(0, usedCredit - initialCredit);
            var grandTotal      = basePrice + itemOverrun + extrasCostTotal;

            this.updateAllPriceTags(remainingCredit);
            document.getElementById('mu-final-price').textContent = this.formatMoney(grandTotal);
            this.generateSummary(grandTotal);
        },

        updateAllPriceTags: function (remainingCredit) {
            var self = this;
            var updateTag = function (domId, qty, price) {
                var tag = document.getElementById('price-tag-' + domId);
                if (!tag) return;
                if (qty > 0) {
                    var nextQty = qty + 1;
                    if (nextQty % 2 === 0) {
                        tag.innerHTML = '<span class="promo-badge">¡PROMO!</span> Agregá otra<br>por <span class="price-strike">' + self.formatMoney(price) + '</span> <strong>' + self.formatMoney(price * 0.5) + '</strong>';
                    } else {
                        tag.textContent = 'Agregá otra por ' + self.formatMoney(price);
                    }
                    tag.className = 'mu-price-tag';
                } else {
                    if (remainingCredit > 0) {
                        var diff = Math.max(0, price - remainingCredit);
                        if (diff === 0) {
                            tag.innerHTML = '<span class="mu-price-included"></span>';
                        } else {
                            tag.innerHTML = '+ ' + self.formatMoney(diff);
                            tag.className = 'mu-price-tag mu-price-plus';
                        }
                    } else {
                        tag.innerHTML = '+ ' + self.formatMoney(price);
                        tag.className = 'mu-price-tag mu-price-full';
                    }
                }
            };

            if (this.state.mode === 'personalizar') {
                for (var k in MU_Config.items) {
                    if (k === 'super_pack') continue;
                    updateTag(k, this.state.items[k] ? this.state.items[k].qty : 0, MU_Config.items[k].precio);
                }
            } else {
                updateTag('super_pack', this.state.packQty, MU_Config.items.super_pack.precio);
                var ropaKeys = ['plancha_ropa_clara', 'plancha_ropa_oscura', 'plancha_tela_sintetica'];
                ropaKeys.forEach(function (key) {
                    updateTag(key + '_sp', self.state.packRopa[key] ? self.state.packRopa[key].qty : 0, MU_Config.items[key].precio);
                });
            }
        },

        // ====================================================================
        // RESUMEN + DATOS PARA CORE
        // ====================================================================

        generateSummary: function (total) {
            var summary      = [];
            var displayLines = [];
            var hasSel       = false;
            var p    = MU_Config.items;
            var defs = MU_Config.extras_definitions;

            if (this.state.mode === 'personalizar') {
                for (var k in this.state.items) {
                    var i = this.state.items[k];
                    if (i.qty > 0) {
                        hasSel = true;
                        var label = '<strong>' + i.qty + 'x</strong> ' + p[k].name;
                        var extrasTxt = [];
                        if (i.extras) {
                            for (var eK in i.extras) {
                                if (i.extras[eK].active) {
                                    var extLabel = defs[eK].name;
                                    if (i.extras[eK].isText && i.extras[eK].textValue) {
                                        var textoLimpio = i.extras[eK].textValue.replace(/\n/g, '/');
                                        extrasTxt.push(extLabel + ': ' + textoLimpio);
                                    } else {
                                        if (defs[eK].type === 'fixed_price') {
                                            extrasTxt.push(extLabel + ' (1)');
                                        } else {
                                            extrasTxt.push(extLabel + ' (' + i.extras[eK].qty + ')');
                                        }
                                    }
                                }
                            }
                        }
                        if (extrasTxt.length) label += ' <br><small>+ ' + extrasTxt.join(', ') + '</small>';
                        summary.push(label);
                        displayLines.push({ value: label });
                    }
                }
            } else {
                if (this.state.packQty > 0) {
                    hasSel = true;
                    var txt = '<strong>' + this.state.packQty + 'x</strong> ' + p.super_pack.label;
                    summary.push(txt);
                    displayLines.push({ value: txt });
                }
                for (var rk in this.state.packRopa) {
                    var ri = this.state.packRopa[rk];
                    if (ri.qty > 0) {
                        hasSel = true;
                        var rtxt = '<strong>' + ri.qty + 'x</strong> ' + p[rk].name;
                        summary.push(rtxt);
                        displayLines.push({ value: rtxt });
                    }
                }
            }

            var sumDiv = document.getElementById('mu-selection-summary');
            if ( ! sumDiv ) return;
            sumDiv.innerHTML = hasSel ? 'Tu pedido incluye:<br>' + summary.join('<br>') : '';
            if (hasSel) jQuery('#mu-selection-summary').slideDown();
            else jQuery('#mu-selection-summary').slideUp();

            this.toggleSubmit(hasSel);

            var dataInput = document.getElementById('mu_data_input');
    if ( ! dataInput ) return;

    dataInput.value = JSON.stringify({
        total_calculated: total,
        display_lines: displayLines,
        raw_data: {
            mode:     this.state.mode,
            items:    this.state.items,
            packQty:  this.state.packQty,
            packRopa: this.state.packRopa
        }
    });
},

        formatMoney: function (n) {
            return new Intl.NumberFormat('es-AR', {
                style: 'currency',
                currency: 'ARS',
                minimumFractionDigits: 0
            }).format(n);
        },

        toggleSubmit: function (hasSelection) {
            if (!this.isBuilderActive) {
                jQuery('.single_add_to_cart_button').removeClass('disabled').prop('disabled', false);
                return;
            }
            jQuery('.single_add_to_cart_button').toggleClass('disabled', !hasSelection).prop('disabled', !hasSelection);
        },

        // ====================================================================
        // VARIACIONES — Precio Dinámico (ex mu_core_variation_scripts)
        // ====================================================================

        variationInit: function () {
            var self     = this;
            var $        = jQuery;
            var $form    = $('form.variations_form');
            var $muWrapper = $('#mu-total-wrapper');
            var $wooPrice  = $('.woocommerce-variation-price, .single_variation .price');

            window.mu_base_product_price = 0;

            if ($form.length > 0) {
                $muWrapper.hide();

                $form.on('found_variation', function (event, variation) {
                    var price = variation.display_price;
                    window.mu_base_product_price = price;

                    // Actualizar precio base en config para recálculo
                    if (typeof MU_Config !== 'undefined') {
                        MU_Config.general.precio_base_wc = price;
                    }

                    if (self.isBuilderActive) {
                        $wooPrice.addClass('mu-replaced');
                        $muWrapper.fadeIn(200);
                        self.calculate();
                    } else {
                        $wooPrice.removeClass('mu-replaced');
                        $muWrapper.hide();
                    }
                });

                $form.on('hide_variation', function () {
                    window.mu_base_product_price = 0;
                    $muWrapper.hide();
                    $wooPrice.removeClass('mu-replaced');
                });
            }
        }
    };

    // Alias global para compatibilidad con Core v2.1
    window.mu_calculate_totals = function () {
        if (window.MU && typeof window.MU.calculate === 'function') {
            window.MU.calculate();
        }
    };

    // ========================================================================
    // INIT
    // ========================================================================

    document.addEventListener('DOMContentLoaded', function () {
        MU.init();
    });

})();
