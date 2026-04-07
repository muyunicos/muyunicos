<?php
/**
 * Productos Personalizados — Addon Etiquetas v3.0
 *
 * Builder de etiquetas personalizadas (usando Core v2.1).
 * - Configuración de productos y extras
 * - Renderizado del constructor (UI) vía MU_UI_Helper
 * - Total box
 * - Enqueue condicional de JS con config vía wp_localize_script
 * - Lógica de variaciones (precio dinámico)
 *
 * Dependencia: inc/products-core.php (MU Core v2.1)
 * CSS: css/product-builder.css
 * JS:  js/addon-etiquetas.js
 *
 * @package MuyUnicos
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificar dependencias
if ( ! function_exists( 'mu_core_is_active' ) || ! mu_core_is_active() ) {
    return;
}

// ============================================================================
// 1. CONFIGURACIÓN
// ============================================================================

if ( ! function_exists( 'mu_etiquetas_get_configuracion' ) ) {
    function mu_etiquetas_get_configuracion( $product ) {
        if ( ! $product ) {
            return false;
        }
        return [
            'general' => [
                'moneda'            => get_woocommerce_currency_symbol(),
                'precio_base_wc'    => (float) $product->get_price(),
                'precio_minimo_ref' => 1000,
            ],
            'extras_definitions' => [
                'corte' => [
                    'label'  => 'Servicio de Corte',
                    'name'   => 'Corte',
                    'desc'   => 'Te las entregamos troqueladas y listas para usar.<br><small>Si no seleccionás esta opción, se entrega la plancha entera para cortar a mano.</small>',
                    'precio' => 500,
                    'type'   => 'qty_dependent',
                ],
                'vinilo' => [
                    'label'  => 'Material Vinilo',
                    'name'   => 'Vinilo',
                    'desc'   => 'Se imprimen en Filmilo resistente al agua, roces y lavados<br><small>Se pueden lavar solo con agua fría</small>',
                    'precio' => 1000,
                    'type'   => 'qty_dependent',
                ],
                'materias' => [
                    'label'      => 'Materias personalizadas',
                    'name'       => 'Materias',
                    'desc'       => 'Agregamos el nombre de la materia a cada etiqueta',
                    'precio'     => 1000,
                    'type'       => 'fixed_price',
                    'input_type' => 'text_box',
                    'input_label' => 'Listado de Materias',
                    'input_help' => 'Escribí los nombres uno debajo del otro.',
                ],
            ],
            'items' => [
                'plancha_surtidas' => [
                    'label'     => 'Plancha Surtidas',
                    'name'      => 'Surtidas',
                    'desc'      => 'Etiquetas adhesivas personalizadas<br>40 unidades para todo uso',
                    'precio'    => 1000,
                    'extras'    => [ 'corte', 'vinilo' ],
                    'info_html' => '<p>Plancha de etiquetas adhesivas personalizadas con el nombre que elijas. Son ideales para todo tipo de útiles<br><strong>Incluye:</strong></p><ul><li><b>✅ 5 grandes de 5 x 3 cm.</b> Para cuadernos, carpetas, cartuchera, mochila, lunchera, etc.</li><li><b>✅ 14 medianas de 5 x 2 cm.</b> Para reglas, fibrones, sacapuntas, agendas, boligoma, etc.</li><li><b>✅ 9 chicas de 5 x 1 cm.</b> Para fibras, crayones, corrector, goma, etc.</li><li><b>✅ 12 mini de 5 x 0,4cm.</b> Para lapices, pinceles, cepillo de dientes, tijeras, cubiertos, etc.</li></ul>',
                ],
                'plancha_lapices' => [
                    'label'     => 'Plancha Lápices',
                    'name'      => 'Lápices',
                    'desc'      => 'Etiquetas adhesivas personalizadas<br>30 unidades (3,5 x 3 cm)',
                    'precio'    => 1000,
                    'extras'    => [ 'corte' ],
                    'info_html' => '<p>Plancha de etiquetas adhesivas personalizadas con el nombre que elijas. Son ideales para todo tipo de lápices, crayones, fibras, lapiceras, y otros útiles. Tambien se pueden fraccionar en 60 unidades cortándolas al medio ya que cada etiqueta tiene el nombre dos veces para ambas caras del lápiz.</p>',
                ],
                'plancha_cuadernos' => [
                    'label'     => 'Plancha Cuadernos',
                    'name'      => 'Cuadernos',
                    'desc'      => 'Etiquetas adhesivas personalizadas<br>12 unidades (6,5 x 3,5 cm)',
                    'precio'    => 1000,
                    'extras'    => [ 'corte', 'vinilo', 'materias' ],
                    'info_html' => '<p>Plancha de etiquetas adhesivas personalizadas con el nombre que elijas. Traen cada una un espacio en blanco para completar con nombre de materias, colegio y otros datos personales. Son ideales para vasos, táper, cuadernos, capetas, etc.</p>',
                ],
                'plancha_ropa_clara' => [
                    'label'     => 'Transfer Ropa Clara',
                    'name'      => 'Transfer',
                    'desc'      => 'Etiquetas personalizadas para planchar<br>5 unidades (44 x 18 mm)',
                    'precio'    => 2000,
                    'extras'    => [],
                    'info_html' => '<p>Pack de etiquetas termotransferibles personalizadas con el nombre que elijas. Son ideales para prendas de algodón claras (su base es transparente).</p>',
                ],
                'plancha_ropa_oscura' => [
                    'label'     => 'Transfer Ropa Clara y Oscura <small>PREMIUM</small>',
                    'name'      => 'Transfer Premium',
                    'desc'      => 'Etiquetas personalizadas para planchar<br>5 unidades (44 x 18 mm)',
                    'precio'    => 3000,
                    'extras'    => [],
                    'info_html' => '<p>Pack de etiquetas termotransferibles personalizadas con el nombre que elijas. Son ideales para prendas de algodón (su base es blanca). Material premium más resistente.</p>',
                ],
                'plancha_tela_sintetica' => [
                    'label'     => 'Tela Sintética',
                    'name'      => 'Tela',
                    'desc'      => 'Etiquetas personalizadas para coser<br>5 unidades (48 x 19 mm)',
                    'precio'    => 3000,
                    'extras'    => [],
                    'info_html' => '<p>Pack de etiquetas termotransferibles personalizadas con el nombre que elijas. Son ideales para mochilas, cartucheras, camperas. Se pueden coser o pegar (con pegamento para telas no incluido).</p>',
                ],
                'super_pack' => [
                    'label'     => 'Super Pack',
                    'desc'      => 'Combo perfecto:<br>∙ 40 Etiquetas Surtidas (vinilo)<br>∙ 30 Etiquetas para Lápices<br>Vienen cortadas y listas para usar',
                    'precio'    => 4000,
                    'extras'    => [],
                    'info_html' => '<p>Pack de dos planchas de etiquetas adhesivas personalizadas con el nombre que elijas.<br><strong>Incluye:</strong></p><ul><li><b>✅ 5 grandes de 5 x 3 cm. <small>(vinilo)</small></b> Para cuadernos, carpetas, cartuchera, mochila, lunchera, etc.</li><li><b>✅ 14 medianas de 5 x 2 cm. <small>(vinilo)</small></b> Para reglas, fibrones, sacapuntas, agendas, boligoma, etc.</li><li><b>✅ 9 chicas de 5 x 1 cm. <small>(vinilo)</small></b> Para fibras, crayones, corrector, goma, etc.</li><li><b>✅ 12 mini de 5 x 0,4cm. <small>(vinilo)</small></b> Para lapices, pinceles, cepillo de dientes, tijeras, cubiertos, etc.</li><li><b>✅ 9 chicas de 5 x 1 cm. <small>(vinilo)</small></b> Para fibras, crayones, corrector, goma, etc.</li><li><b>✅ 30 para lápices de 3,5 x 3cm.</b> Para todo tipo de lápices, crayones, fibras, lapiceras, y otros útiles.</li></ul>Todas vienen cortadas y listas para usar en un paquete especial.',
                ],
            ],
        ];
    }
}

// ============================================================================
// 2. RENDERIZADO DEL CONSTRUCTOR (UI)
// ============================================================================

if ( ! function_exists( 'mu_etiquetas_render_v3' ) ) {
    add_action( 'woocommerce_before_add_to_cart_button', 'mu_etiquetas_render_v3', 10 );
    function mu_etiquetas_render_v3() {
        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_the_ID() );
        }
        if ( ! $product || ! has_term( [ 19, 18 ], 'product_cat', $product->get_id() ) ) {
            return;
        }
        if ( function_exists( 'muyu_is_restricted_user' ) && muyu_is_restricted_user() ) {
            return;
        }

        $config = mu_etiquetas_get_configuracion( $product );
        ?>

        <div id="mu-builder-container" style="display:none;"> <!-- Se muestra vía JS -->
            <h4>Seleccioná una opción:</h4>

            <div class="mu-mode-selector">
                <div class="mu-mode-btn active" onclick="MU.setMode('personalizar')" id="btn-mode-personalizar">
                    <h4>Pedido personalizado</h4>
                    <span>Elegí las etiquetas que necesites</span>
                </div>
                <div class="mu-mode-btn" onclick="MU.setMode('super_pack')" id="btn-mode-super_pack">
                    <h4>Super Pack</h4>
                    <span>Con el nombre que elijas y listo para usar</span>
                </div>
            </div>

            <!-- MODO PERSONALIZAR -->
            <div id="view-personalizar" class="mu-view-container">
                <?php
                MU_UI_Helper::render_section( 'adhesivas', '✨ Etiquetas Adhesivas', 'expanded', function () use ( $config ) {
                    mu_render_group( [ 'plancha_surtidas', 'plancha_lapices', 'plancha_cuadernos' ], $config );
                } );
                MU_UI_Helper::render_section( 'ropa', '👕 Etiquetas para Ropa', 'collapsed', function () use ( $config ) {
                    mu_render_group( [ 'plancha_ropa_clara', 'plancha_ropa_oscura', 'plancha_tela_sintetica' ], $config );
                } );
                ?>
            </div>

            <!-- MODO SUPER PACK -->
            <div id="view-super_pack" class="mu-view-container mu-hidden">
                <?php
                MU_UI_Helper::render_section( 'super_pack_main', 'Super Pack Escolar', 'fixed', function () use ( $config ) {
                    mu_render_group( [ 'super_pack' ], $config );
                } );
                MU_UI_Helper::render_section( 'ropa-pack', '👕 Etiquetas para Ropa (Opcional)', 'collapsed', function () use ( $config ) {
                    mu_render_group( [ 'plancha_ropa_clara', 'plancha_ropa_oscura', 'plancha_tela_sintetica' ], $config, '_sp' );
                } );
                ?>
            </div>

            <!-- INPUT ESTÁNDAR PARA CORE V2.1 -->
            <input type="hidden" name="mu_custom_data" id="mu_data_input">
        </div>
        <?php
    }
}

// Total Box
if ( ! function_exists( 'mu_etiquetas_render_total_box' ) ) {
    add_action( 'woocommerce_before_add_to_cart_button', 'mu_etiquetas_render_total_box', 20 );
    function mu_etiquetas_render_total_box() {
        ?>
        <div id="mu-total-wrapper">
            <div id="mu-selection-summary"></div>
            <div class="mu-total-box" id="mu-total-display">
                <div class="mu-total-row">
                    <span class="mu-total-label">Total:</span>
                    <strong class="mu-total-amount" id="mu-final-price">$0</strong>
                </div>
            </div>
        </div>
        <?php
    }
}

// ============================================================================
// 3. HELPERS DE RENDERIZADO
// ============================================================================

if ( ! function_exists( 'mu_render_group' ) ) {
    function mu_render_group( $keys, $config, $suffix = '' ) {
        foreach ( $keys as $key ) {
            if ( isset( $config['items'][ $key ] ) ) {
                $item    = $config['items'][ $key ];
                $fullKey = $key . $suffix;

                $extras_cb = null;
                if ( ! empty( $item['extras'] ) ) {
                    $extras_cb = function () use ( $fullKey, $item, $config ) {
                        echo '<div style="font-size: 0.9em; font-weight: bold; color: var(--mu-builder-accent); margin-top: 10px; margin-bottom: 10px; text-transform: uppercase;">Opcionales:</div>';
                        foreach ( $item['extras'] as $exKey ) {
                            if ( isset( $config['extras_definitions'][ $exKey ] ) ) {
                                mu_render_extra_specific( $fullKey, $exKey, $config['extras_definitions'][ $exKey ] );
                            }
                        }
                    };
                }

                MU_UI_Helper::render_row(
                    $fullKey,
                    $item['label'],
                    $item['desc'],
                    "price-tag-{$fullKey}",
                    'MU.updateItem',
                    $extras_cb,
                    $item['info_html'] ?? null
                );
            }
        }
    }
}

if ( ! function_exists( 'mu_render_extra_specific' ) ) {
    function mu_render_extra_specific( $item_key, $extra_key, $def ) {
        $unique_id      = $item_key . '_' . $extra_key;
        $is_text_mode   = isset( $def['input_type'] ) && $def['input_type'] === 'text_box';
        $is_fixed_price = isset( $def['type'] ) && $def['type'] === 'fixed_price';
        ?>
        <div class="mu-option-group mu-advanced-option" id="opt-<?php echo esc_attr( $unique_id ); ?>">
            <label class="mu-check-header">
                <div class="mu-check-start">
                    <input type="checkbox" id="check-<?php echo esc_attr( $unique_id ); ?>" onchange="MU.toggleExtra('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>')">
                    <div class="mu-check-info">
                        <span class="mu-opt-title"><?php echo $def['label']; ?></span>
                        <span class="mu-opt-desc"><?php echo $def['desc']; ?></span>
                    </div>
                </div>
                <div class="mu-price-badge">
                    <?php echo '+' . wc_price( $def['precio'], array( 'decimals' => 0 ) ) . ( $is_fixed_price ? '' : ' c/u' ); ?>
                </div>
            </label>
            <div class="mu-option-body" id="body-<?php echo esc_attr( $unique_id ); ?>">
                <?php if ( $is_text_mode ) : ?>
                    <div class="mu-text-input-wrapper">
                        <?php if ( ! empty( $def['input_label'] ) ) : ?>
                            <label class="mu-text-input-label"><?php echo $def['input_label']; ?></label>
                        <?php endif; ?>
                        <textarea class="mu-text-area" id="text-<?php echo esc_attr( $unique_id ); ?>" placeholder="Ej: Matemática&#10;Lengua..." oninput="MU.updateExtraText('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>')"></textarea>
                        <?php if ( ! empty( $def['input_help'] ) ) : ?>
                            <span class="mu-text-help"><?php echo $def['input_help']; ?></span>
                        <?php endif; ?>
                        <input type="hidden" id="qty-<?php echo esc_attr( $unique_id ); ?>" value="0">
                    </div>
                <?php elseif ( ! $is_fixed_price ) : ?>
                    <div class="mu-mode-radios">
                        <label class="mu-radio-box active" id="rad-all-<?php echo esc_attr( $unique_id ); ?>">
                            <input type="radio" name="mode_<?php echo esc_attr( $unique_id ); ?>" value="all" checked onchange="MU.setExtraMode('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>', 'all')">
                            <span class="mu-radio-label" id="lbl-all-<?php echo esc_attr( $unique_id ); ?>">Todas</span>
                        </label>
                        <label class="mu-radio-box" id="rad-custom-<?php echo esc_attr( $unique_id ); ?>">
                            <input type="radio" name="mode_<?php echo esc_attr( $unique_id ); ?>" value="custom" onchange="MU.setExtraMode('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>', 'custom')">
                            <span class="mu-radio-label">Elegir Cant.</span>
                        </label>
                    </div>
                    <div class="mu-custom-qty-wrapper" id="custom-wrap-<?php echo esc_attr( $unique_id ); ?>" style="display:none;">
                        <div class="mu-qty-wrap">
                            <button type="button" class="mu-qty-btn" onclick="MU.updateExtraQty('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>', -1)">-</button>
                            <input type="number" class="mu-qty-input" id="qty-<?php echo esc_attr( $unique_id ); ?>" value="1" readonly>
                            <button type="button" class="mu-qty-btn" onclick="MU.updateExtraQty('<?php echo esc_js( $item_key ); ?>', '<?php echo esc_js( $extra_key ); ?>', 1)">+</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

// ============================================================================
// 4. ENQUEUE JS + CONFIG vía wp_localize_script
// ============================================================================

if ( ! function_exists( 'mu_etiquetas_enqueue_builder_js' ) ) {
    add_action( 'wp_enqueue_scripts', 'mu_etiquetas_enqueue_builder_js', 25 );
    function mu_etiquetas_enqueue_builder_js() {
        if ( ! is_product() ) {
            return;
        }

        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_queried_object_id() );
        }
        if ( ! $product || ! has_term( [ 19, 18 ], 'product_cat', $product->get_id() ) ) {
            return;
        }

        $uri = get_stylesheet_directory_uri();
        $ver = defined( 'MU_CORE_VERSION' ) ? MU_CORE_VERSION : '2.1';

        wp_enqueue_script( 'mu-addon-etiquetas', "$uri/js/addon-etiquetas.js", [ 'jquery' ], $ver, true );

        $config = mu_etiquetas_get_configuracion( $product );
        wp_localize_script( 'mu-addon-etiquetas', 'MU_Config', $config );
        wp_localize_script( 'mu-addon-etiquetas', 'muEtiquetasData', [
            'currencySymbol' => get_woocommerce_currency_symbol(),
        ] );
    }
}

// ============================================================================
// 5. LÓGICA FRONTEND: VARIACIONES (Precio Dinámico)
// ============================================================================

if ( ! function_exists( 'mu_core_enqueue_variation_logic' ) ) {
    add_action( 'wp_enqueue_scripts', 'mu_core_enqueue_variation_logic', 26 );
    function mu_core_enqueue_variation_logic() {
        if ( ! is_product() ) {
            return;
        }

        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_queried_object_id() );
        }
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }

        // La lógica de variaciones está embebida en addon-etiquetas.js (sección MU.variationInit)
        // Se activa automáticamente si el producto es variable.
    }
}
