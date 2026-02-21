# MUY ÚNCOS - GUÍA DE MIGRACIÓN DE SNIPPETS

Documento vivo que rastrea la migración progresiva desde **Code Snippets** hacia archivos modulares CSS/JS/PHP en el tema hijo `generatepress-child`.

---

## 📊 SECCIÓN 0: ESTADO DE MIGRACIÓN

### Tabla de Progreso (Actualizado: 21/02/2026)

| Snippet Original | Tipo | Estado | Archivo Destino | Tamaño | Notas |
|---|---|---|---|---|---|
| **GEOLOCALIZACIÓN & MULTI-PAÍS** |  |  |  |  |  |
| Auto-detección de País por Dominio | PHP | ✅ Migrado | `functions.php` | ~1.2 KB | Hook `template_redirect`, establece `billing_country` |
| Shortcode País de Facturación | PHP | ✅ Migrado | `functions.php` | ~0.5 KB | `[mi_pais_facturacion]` |
| Funciones Auxiliares Multi-País (CORE) | PHP | ✅ Migrado | `functions.php` | ~2.1 KB | `muyu_get_countries_data()`, `muyu_clean_uri()`, etc. |
| Selector de País en Header | PHP+CSS+JS | ✅ Migrado | `functions.php` + `css/components/header.css` + `js/mu-ui-scripts.js` | ~8.5 KB | Dropdown con banderas, ya existía |
| **Modal de Sugerencia de País** | **PHP+CSS+JS** | **✅ Migrado** | **`functions.php` + `css/components/country-modal.css` + `js/country-modal.js`** | **~7.2 KB** | **Geolocalización WC, cookie 1 año, multi-idioma** |
| **CHECKOUT** |  |  |  |  |  |
| Campos Checkout Optimizados | PHP | ✅ Migrado | `functions.php` | ~4.5 KB | Lógica condicional físico/digital |
| Validación Checkout | PHP | ✅ Migrado | `functions.php` | ~1.8 KB | `woocommerce_checkout_process` |
| AJAX Check Email | PHP | ✅ Migrado | `functions.php` | ~0.8 KB | `wc_ajax_mu_check_email` |
| Estilos Checkout | CSS | ✅ Migrado | `css/checkout.css` | 12.3 KB | Variables GP, mobile-first |
| Lógica Checkout (WhatsApp, toggle) | JS | ✅ Migrado | `js/checkout.js` | 8.7 KB | libphonenumber.js, validaciones |
| **MODAL AUTH** |  |  |  |  |  |
| HTML Modal Auth | PHP | ✅ Migrado | `functions.php` | ~3.2 KB | `wp_footer`, estructura HTML |
| WC-AJAX Handlers (login/register) | PHP | ✅ Migrado | `functions.php` | ~2.1 KB | 4 endpoints `wc_ajax_*` |
| Estilos Modal Auth | CSS | ✅ Migrado | `css/components/modal-auth.css` | 9.8 KB | Animaciones, responsive |
| Lógica Modal Auth | JS | ✅ Migrado | `js/modal-auth.js` | 6.5 KB | Step navigation, AJAX calls |
| **HEADER** |  |  |  |  |  |
| Iconos Header | PHP | ✅ Migrado | `functions.php` | ~2.8 KB | `mu_header_icons()`, hook `generate_after_primary_menu` |
| Estilos Header | CSS | ✅ Migrado | `css/components/header.css` | 8.2 KB | `.mu-header-icons`, `.mu-account-dropdown` |
| Lógica Header (dropdowns) | JS | ✅ Migrado | `js/header.js` | 3.1 KB | Dropdown account, sticky behavior |
| **FOOTER** |  |  |  |  |  |
| Estructura Footer Custom | PHP | ✅ Migrado | `functions.php` | ~3.5 KB | `muyunicos_custom_footer_structure()` |
| Estilos Footer | CSS | ✅ Migrado | `css/components/footer.css` | 11.7 KB | Grid, accordion mobile, trust badge |
| Lógica Footer (accordions) | JS | ✅ Migrado | `js/footer.js` | 1.9 KB | Accordions mobile |
| **CART** |  |  |  |  |  |
| Estilos Carrito | CSS | ✅ Migrado | `css/cart.css` | 7.4 KB | Tabla responsive, badges |
| Lógica Carrito | JS | ✅ Migrado | `js/cart.js` | 4.2 KB | Update quantities, remove items |
| **OTROS** |  |  |  |  |  |
| Repositorio de Iconos SVG | PHP | ✅ Migrado | `functions.php` | ~1.5 KB | `mu_get_icon()`, 10+ iconos |
| Botón Compartir (Share) | PHP+CSS+JS | ✅ Migrado | `functions.php` + `css/components/share-button.css` + inline JS | ~3.8 KB | Shortcode `[dcms_share]` |
| Botón Flotante WhatsApp | PHP+CSS | ✅ Migrado | `functions.php` + `style.css` | ~1.2 KB | `wp_footer` hook |
| Formulario Búsqueda Custom | PHP+CSS | ✅ Migrado | `functions.php` + `style.css` | ~2.1 KB | `get_product_search_form` filter |
| Add Multiple Products to Cart | PHP | ✅ Migrado | `functions.php` | ~0.9 KB | `?add-multiple=1,2,3` |
| BACS Replace NUMERODEPEDIDO | PHP | ✅ Migrado | `functions.php` | ~1.1 KB | Email + Thank you page |
| Move Category Description | PHP | ✅ Migrado | `functions.php` | ~0.4 KB | `woocommerce_after_shop_loop` |
| Google Site Kit Canonical | PHP | ✅ Migrado | `functions.php` | ~0.3 KB | Filter `googlesitekit_canonical_home_url` |
| **RESTRICCIÓN DIGITAL** |  |  |  |  |  |
| Sistema de Restricción Digital v2.2 | PHP (Clase) | ✅ Migrado/Refactorizado | `functions.php` | ~18.5 KB | `MUYU_Digital_Restriction_System`, optimizado uso multi-país core |
| **HOME** |  |  |  |  |  |
| Estilos Home | CSS | ✅ Migrado | `css/home.css` | 5.8 KB | Hero, featured products |
| **SHOP** |  |  |  |  |  |
| Estilos Shop | CSS | ✅ Migrado | `css/shop.css` | 6.2 KB | Grid productos, filtros |
| **PRODUCT** |  |  |  |  |  |
| Estilos Producto | CSS | ✅ Migrado | `css/product.css` | 9.1 KB | Gallery, variations, tabs |

### Estadísticas

- **Total Snippets Migrados**: 39+
- **Total CSS Modularizado**: ~84 KB
- **Total JS Modularizado**: ~29 KB
- **Total PHP en functions.php**: ~62 KB (incluyendo clase restricción digital)
- **Última Actualización**: 21 de febrero de 2026

---

## 🛠️ SECCIÓN 1: PRINCIPIOS DE MIGRACIÓN

### 1.1 Filosofía General

1. **Anti-Duplicación**: Antes de escribir CSS, verificar si GeneratePress ya provee el estilo en `assets/css/main.min.css`
2. **Variables First**: Usar las variables CSS existentes en `style.css`
3. **Performance**: Archivos cacheables > estilos inline
4. **Nomenclatura**: Prefijo `.mu-*` + BEM cuando corresponda
5. **PHP Robusto**: Envolver funciones en `if ( !function_exists() )`

### 1.2 Estructura de Archivos

```
muyunicos/  (= generatepress-child)
├── style.css                  # Variables CSS + base del child theme
├── functions.php              # Enqueue system + funciones PHP
├── MIGRATION-GUIDE.md         # Este archivo (estado de migración)
├── css/
│   ├── components/             # Componentes reutilizables
│   │   ├── header.css
│   │   ├── footer.css
│   │   ├── modal-auth.css
│   │   ├── country-modal.css       # NUEVO: Modal sugerencia de país
│   │   └── share-button.css
│   ├── cart.css                # Página carrito
│   ├── checkout.css            # Página checkout
│   ├── home.css                # Home page
│   ├── shop.css                # Shop/Cat/Tag
│   └── product.css             # Single product
├── js/
│   ├── header.js
│   ├── footer.js
│   ├── modal-auth.js
│   ├── country-modal.js        # NUEVO: Lógica del modal de país
│   ├── cart.js
│   ├── checkout.js
│   └── mu-ui-scripts.js       # Country selector + WPLingua toggle
└── assets/
    └── css/
        └── main.min.css        # READ-ONLY: GeneratePress parent theme
```

---

## 📝 SECCIÓN 2: PROTOCOLO PASO A PASO

### Step 0: Anti-Duplicación Check (OBLIGATORIO)

**Antes de migrar cualquier snippet**, verificar:

```bash
# En terminal, desde la raíz del tema:
grep -r "selector-que-quiero-usar" assets/css/main.min.css
```

Si GeneratePress ya provee el estilo:
- **Opción A**: Usar el estilo del parent tal cual (ideal)
- **Opción B**: Si necesitas sobreescribirlo, agregar comentario:
  ```css
  /* override GP: [razón específica] */
  .selector { ... }
  ```

### Step 1: Identificar Tipo de Snippet

- **Global/Componente**: Va a `css/components/` o directamente en `functions.php`
- **Página específica**: Va a `css/[nombre-pagina].css` con conditional enqueue
- **PHP puro**: Directamente a `functions.php` con docblock apropiado

### Step 2: Refactorizar Código

#### CSS:
```css
/* Antes (snippet inline) */
.mi-clase {
    color: #2B9FCF;  /* 🚫 Hardcoded */
    padding: 20px;
}

/* Después (modular) */
.mu-mi-clase {
    color: var(--primario);  /* ✅ Variable */
    padding: var(--mu-space-md);
}
```

#### PHP:
```php
// Antes (snippet sin protección)
function mi_funcion() { ... }

// Después (robusto)
if ( !function_exists('mu_mi_funcion') ) {
    /**
     * Descripción clara de la función
     * @param string $param Descripción del parámetro
     * @return mixed Descripción del retorno
     */
    function mu_mi_funcion($param) {
        // Lógica
    }
}
```

#### JavaScript:
```javascript
// Antes (snippet inline)
jQuery(function($) {
    $('.clase').click(...);
});

// Después (IIFE + strict mode)
(function() {
    'use strict';
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Lógica
    }
})();
```

### Step 3: Actualizar `functions.php` (si aplica)

Si creaste un archivo CSS/JS nuevo:

```php
function mu_enqueue_assets() {
    // ... código existente ...
    
    // NUEVO: Enqueue condicional
    if ( is_nueva_condicion() ) {
        wp_enqueue_style(
            'mu-nuevo-archivo',
            get_stylesheet_directory_uri() . '/css/nuevo-archivo.css',
            array('mu-base'),  // Dependencia
            wp_get_theme()->get('Version')
        );
    }
}
```

### Step 4: Actualizar ESTA GUÍA

Editar **Sección 0** (arriba):
1. Cambiar estado de "❌ Pendiente" a "✅ Migrado"
2. Completar columna "Archivo Destino"
3. Agregar tamaño del archivo (usar `ls -lh archivo.css`)
4. Actualizar fecha de "Última Actualización"

---

## 🎯 SECCIÓN 3: CASOS ESPECIALES

### 3.1 Funciones Multi-País (Geolocalización)

**Dependencias**: Las funciones auxiliares multi-país son **CORE** y deben cargarse primero:

```php
// ✅ CORRECTO: Funciones auxiliares primero
if ( !function_exists('muyu_get_countries_data') ) { ... }
if ( !function_exists('muyu_clean_uri') ) { ... }

// Luego, funciones que las usan:
if ( !function_exists('mu_auto_detect_country_by_domain') ) {
    function mu_auto_detect_country_by_domain() {
        $countries = muyu_get_countries_data();  // Usa helper
        // ...
    }
}
```

**Funciones auxiliares disponibles**:
- `muyu_get_main_domain()` - Dominio principal cacheado
- `muyu_country_language_prefix($code)` - Prefijo de idioma ('/pt', '/en')
- `muyu_get_countries_data()` - Array completo de países
- `muyu_get_current_country_from_subdomain()` - País actual por subdominio
- `muyu_clean_uri($prefix, $uri)` - Normaliza URIs con prefijo de idioma
- `muyu_country_modal_text($code, $type)` - Textos localizados para modal

### 3.2 Iconos SVG

**Nunca** insertar SVG inline. Usar siempre:

```php
// ✅ CORRECTO
echo mu_get_icon('instagram');

// ❌ INCORRECTO
echo '<svg>...</svg>';  // Duplicación, no cacheable
```

**Iconos disponibles**:
`arrow`, `search`, `close`, `share`, `check`, `instagram`, `facebook`, `pinterest`, `tiktok`, `youtube`

### 3.3 WC-AJAX vs wp_ajax

**Preferir WC-AJAX** para operaciones de WooCommerce:

```php
// ✅ CORRECTO (WC-AJAX, más rápido)
add_action('wc_ajax_mu_check_email', 'mi_funcion');

// ❌ EVITAR (wp_ajax, más lento)
add_action('wp_ajax_mi_accion', 'mi_funcion');
add_action('wp_ajax_nopriv_mi_accion', 'mi_funcion');
```

### 3.4 Restricción de Contenido Digital

La clase `MUYU_Digital_Restriction_System` es un **singleton** que gestiona:
- Índices de productos digitales
- Redirecciones automáticas
- Filtrado de queries
- Ocultación de variaciones físicas

**No modificar directamente**. Usar funciones helper:
```php
if ( muyu_is_restricted_user() ) {
    // Lógica para subdominios (solo digital)
}

$country = muyu_get_user_country_code();  // 'AR', 'MX', 'BR', etc.
```

### 3.5 Modal de Sugerencia de País

**Nuevo componente migrado** (21/02/2026):

- **Función**: Detecta el país del usuario mediante `wc_get_customer_geolocation()` y sugiere el sitio correcto
- **Cookie**: `muyu_stay_here` - Persiste 1 año en `.muyunicos.com`
- **Multi-idioma**: Usa `muyu_country_modal_text()` para textos localizados (es/pt/en)
- **Enqueue condicional**: Solo se carga si debe mostrarse (optimización)
- **Archivos**:
  - `css/components/country-modal.css` - Estilos con variables CSS
  - `js/country-modal.js` - Lógica IIFE + event listeners
  - `functions.php` - Funciones `mu_should_show_country_modal()` y `mu_country_modal_html()`

**Ejemplo de uso**:
```php
// El modal se renderiza automáticamente en wp_footer
// No requiere shortcode ni invocación manual
// Solo se muestra si el usuario está en dominio incorrecto
```

---

## ✅ SECCIÓN 4: CHECKLIST DE MIGRACIÓN

Antes de marcar un snippet como "Migrado":

- [ ] Código refactorizado (variables, nomenclatura, comentarios)
- [ ] Anti-duplicación verificada (Step 0)
- [ ] Archivo creado/actualizado en ubicación correcta
- [ ] `functions.php` actualizado (enqueue si aplica)
- [ ] Probado en frontend (no rompe layout existente)
- [ ] Probado en mobile (responsive)
- [ ] Tabla de Sección 0 actualizada
- [ ] Commit con mensaje descriptivo (`feat:`, `fix:`, `refactor:`)

---

## 🔗 SECCIÓN 5: RECURSOS

### Variables CSS Disponibles (style.css)

```css
--primario: #2B9FCF;
--secundario: #FFD77A;
--texto: #277292;
--exito: #a3ffbc;
--mu-space-xs: 5px;
--mu-space-sm: 10px;
--mu-space-md: 20px;
--mu-space-lg: 40px;
--mu-space-xl: 40px;
--mu-radius-sm: 6px;
--mu-radius: 12px;
--mu-radius-md: 16px;
--mu-radius-lg: 20px;
--mu-radius-xl: 32px;
--mu-shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
--mu-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
--mu-shadow-md: 0 8px 16px rgba(0, 0, 0, 0.15);
--mu-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
--mu-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
--mu-transition-fast: all 0.2s ease;
```

### Breakpoints Mobile-First

```css
/* Mobile: 0-768px (default) */
.mu-clase { ... }

/* Tablet y superior: 769px+ */
@media (min-width: 769px) {
    .mu-clase { ... }
}
```

### Hooks GeneratePress Útiles

- `generate_header` (priority 1-20)
- `generate_after_primary_menu`
- `generate_before_footer`
- `wp_footer` (priority 5-100)

---

## 📌 SECCIÓN 6: PR TEMPLATE

Cuando hagas un Pull Request de migración, usar este template:

```markdown
## Migración: [Nombre del Snippet]

### Cambios
- ✅ Migrado snippet "[nombre]" a `[archivo destino]`
- ♻️ Refactorizado: [detalle de mejoras]
- 📄 Actualizado MIGRATION-GUIDE.md

### Archivos Modificados
- `functions.php` (+XXX líneas)
- `css/[archivo].css` (nuevo, XX KB)
- `js/[archivo].js` (nuevo, XX KB)

### Testing
- [ ] Desktop (Chrome, Firefox)
- [ ] Mobile (Responsive)
- [ ] No rompe layout existente
- [ ] Variables CSS usadas correctamente

### Screenshots
(Opcional: adjuntar capturas before/after)
```

---

**Última Revisión**: 21 de febrero de 2026  
**Mantenedor**: Jonatan Pintos  
**Repositorio**: [github.com/muyunicos/muyunicos](https://github.com/muyunicos/muyunicos)