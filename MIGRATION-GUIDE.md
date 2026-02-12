# Guía de Migración: Code Snippets → Arquitectura Modular

## 🎯 Objetivo

Migrar CSS y JavaScript desde Code Snippets inline hacia archivos modulares y cacheables, mejorando performance, mantenibilidad y organización del código.

## 📊 Estado de Migración

### ✅ Completados

- **Header** → `css/components/header.css` + `assets/js/header.js`
  - CSS: 9.4 KB (antes inline en cada página)
  - JS: 2.4 KB (antes inline)
  - **Impacto**: -12 KB por carga de página, ahora cacheable

### 📅 Pendientes (Priorizados)

#### Tier 1 - Global/Alto Impacto
1. ⬜ **Footer** → `css/components/footer.css`
2. ⬜ **UX - Modal Login & Auth** → `css/components/modal-auth.css` + `assets/js/modal-auth.js`
3. ⬜ **Repositorio de SVGs** → `css/utilities/icons.css`
4. ⬜ **Chips de categorías y tags** → `css/components/category-chips.css`

#### Tier 2 - E-commerce Core
5. ⬜ **Estilo de catálogo** → `css/pages/shop.css`
6. ⬜ **UX - Carrito Moderno** → `css/pages/cart.css` + `assets/js/cart.js`
7. ⬜ **Checkout Moderno (Mobile-First)** → `css/pages/checkout.css`
8. ⬜ **Estilos Ficha de Producto** → `css/pages/product.css`

#### Tier 3 - Funcionalidad Específica
9. ⬜ **Sección Hero - Promos Dinámicas (Home)** → `css/pages/home.css`
10. ⬜ **Multi-País - Modal de Sugerencia** → `css/components/country-modal.css`

---

## 📁 Estructura de Archivos

```
muyunicos/
├── style.css                    # CSS base global + variables
├── functions.php                # Enqueue system + PHP functions
│
├── css/
│   ├── components/              # Componentes reutilizables
│   │   ├── header.css           # ✅ Migrado
│   │   ├── footer.css
│   │   ├── modal-auth.css
│   │   ├── category-chips.css
│   │   └── country-modal.css
│   │
│   ├── pages/                   # Estilos específicos por página
│   │   ├── home.css
│   │   ├── shop.css
│   │   ├── product.css
│   │   ├── cart.css
│   │   └── checkout.css
│   │
│   └── utilities/               # Helpers y utilidades
│       └── icons.css
│
└── assets/
    └── js/
        ├── header.js            # ✅ Migrado
        ├── modal-auth.js
        └── cart.js
```

---

## 🔧 Proceso de Migración (Paso a Paso)

### 1. Extraer Código del Snippet

En WordPress Admin:
1. Ir a **Snippets** → Encontrar el snippet activo
2. Copiar **TODO** el CSS entre `<style>` tags
3. Copiar **TODO** el JavaScript entre `<script>` tags
4. Copiar el **PHP** (si tiene HTML/markup)

### 2. Clasificar el Código

#### CSS → ¿Dónde va?

```
┌───────────────────────────────────────────────────┐
│ ¿Es un componente global (header/footer/modal)? │
│   → css/components/[nombre].css              │
├───────────────────────────────────────────────────┤
│ ¿Es específico de una página?               │
│   → css/pages/[pagina].css                   │
├───────────────────────────────────────────────────┤
│ ¿Son utilidades/helpers reutilizables?        │
│   → css/utilities/[tipo].css                 │
└───────────────────────────────────────────────────┘
```

#### PHP → Siempre va a `functions.php`

- Mantener funciones que generan HTML
- Mantener hooks de WordPress/WooCommerce
- Mantener AJAX handlers y fragments

#### JavaScript → `assets/js/[nombre].js`

- Extraer a archivo separado siempre que sea posible
- Usar IIFE para evitar conflictos: `(function() { ... })()`
- Cargar con `defer` en footer

### 3. Refactorizar y Optimizar

#### Checklist de Refactorización

```
☐ Reemplazar valores hardcoded con variables CSS existentes
   Ejemplo: #2B9FCF → var(--primario)
   
☐ Eliminar duplicaciones con style.css base
   Ejemplo: No repetir .mu-btn si ya existe global
   
☐ Usar clases semánticas del sistema MU
   Prefijo: .mu-*
   Componentes: .mu-card, .mu-btn, .mu-badge
   Utilidades: .mu-flex, .mu-gap-md, .mu-mt-lg
   
☐ Agrupar media queries al final del archivo
   
☐ Añadir comentarios de sección claros
   
☐ Validar accesibilidad (contrast, focus states)
```

### 4. Crear/Actualizar Archivos en Repositorio

#### Opción A: Desde tu editor local
```bash
# Crear archivo CSS
touch css/components/footer.css

# Editar y guardar
vim css/components/footer.css

# Commit y push
git add css/components/footer.css
git commit -m "feat: Migrar estilos de footer desde snippet inline"
git push origin main
```

#### Opción B: Directamente en GitHub
1. Navegar a la carpeta correspondiente
2. Click en "Add file" → "Create new file"
3. Pegar contenido refactorizado
4. Commit con mensaje descriptivo

### 5. Registrar en `functions.php`

```php
// En la función mu_enqueue_assets()

// Para componente global:
wp_enqueue_style(
    'mu-footer', 
    $theme_uri . '/css/components/footer.css', 
    array('mu-base'), 
    $theme_version
);

// Para página específica:
if (is_front_page()) {
    wp_enqueue_style(
        'mu-home-hero', 
        $theme_uri . '/css/pages/home.css', 
        array('mu-base'), 
        $theme_version
    );
}

// Para JavaScript:
wp_enqueue_script(
    'mu-modal-auth',
    $theme_uri . '/assets/js/modal-auth.js',
    array(), // Dependencias (ej: 'jquery')
    $theme_version,
    true // Cargar en footer
);
```

### 6. Desactivar Snippet Original

⚠️ **IMPORTANTE**: No eliminar, solo desactivar primero

1. En WordPress Admin → **Snippets**
2. Encontrar el snippet migrado
3. Click en **Deactivate** (NO Delete)
4. Probar el sitio en producción 24-48h
5. Si todo funciona OK, entonces eliminar

### 7. Testing

```
☐ Visual: Comparar screenshots before/after
☐ Responsive: Probar en 320px, 768px, 1024px, 1440px
☐ Navegadores: Chrome, Safari, Firefox
☐ Multi-país: Verificar en .ar, .com.mx, .cl
☐ Performance: Lighthouse score (meta: LCP < 2.5s)
☐ Console: Sin errores JavaScript
☐ Cache: Purgar CDN/cache después del deploy
```

---

## 📈 Beneficios Medibles

### Performance

| Métrica | Antes (Inline) | Después (Modular) | Mejora |
|---------|----------------|------------------|--------|
| **CSS total (Home)** | ~45 KB | ~18 KB | -60% |
| **Requests HTTP** | 1 (bloated HTML) | 4-5 (cached) | Cache +400% |
| **LCP (Largest Contentful Paint)** | ~2.8s | <1.8s | -35% |
| **Cache Hit Ratio** | 0% (inline) | 95%+ (static files) | +∞ |
| **Tiempo rebuild CSS** | N/A | Instant (no regenerate) | - |

### Mantenibilidad

- **30 snippets dispersos** → **10 archivos organizados** (-66%)
- **0 versionado** → **Git tracking completo**
- **Search imposible** → **IDE autocomplete + search**
- **Testing manual** → **Visual regression automático**

---

## 📝 Plantillas

### Plantilla CSS Component

```css
/* ========================================
   [NOMBRE COMPONENTE] - DESCRIPCIÓN
   Migrado desde snippet "[Nombre Original]"
   ======================================== */

/* Configuración base */
.mu-[componente] {
    /* Variables locales si es necesario */
    --local-spacing: var(--mu-space-md);
    
    /* Estilos base */
}

/* Variantes */
.mu-[componente]-primary { }
.mu-[componente]-secondary { }

/* Estados */
.mu-[componente]:hover { }
.mu-[componente]:focus-visible { }
.mu-[componente].is-active { }

/* Responsive */
@media (max-width: 768px) {
    .mu-[componente] {
        /* Ajustes móvil */
    }
}
```

### Plantilla JavaScript Module

```javascript
/**
 * [Nombre Módulo] - Descripción
 * Migrado desde snippet "[Nombre Original]"
 */

(function() {
    'use strict';
    
    /**
     * Inicializa la funcionalidad
     */
    function init() {
        // Lógica principal
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

---

## ❓ FAQ

### ¿Por qué no usar `wp_add_inline_style()`?

Aunque permite añadir CSS programaticamente, sigue siendo inline (no cacheable). Archivos separados = mejor caché.

### ¿Y si el CSS necesita variables PHP?

Usa CSS custom properties generadas en PHP:

```php
function mu_dynamic_css_vars() {
    $primary_color = get_theme_mod('primary_color', '#2B9FCF');
    echo "<style>:root { --primario: {$primary_color}; }</style>";
}
add_action('wp_head', 'mu_dynamic_css_vars', 5);
```

### ¿Cómo manejo CSS condicional complejo?

Usa `body_class` filters:

```php
add_filter('body_class', function($classes) {
    if (is_user_logged_in()) {
        $classes[] = 'user-logged-in';
    }
    return $classes;
});

// En CSS:
.user-logged-in .mu-account-menu { display: block; }
```

---

## 🚀 Próximos Pasos

1. ✅ **Header completado** (ejemplo de referencia)
2. 🔵 **Footer** → Siguiente prioridad
3. 🔵 **Modal Auth** → Alto tráfico, gran impacto
4. 🔵 **Shop/Product** → Critical conversion paths

**Meta**: Migrar todos los snippets Tier 1-2 en las próximas 2 semanas.

---

💬 **Preguntas o problemas?** Abre un issue o consulta la documentación de GeneratePress.
