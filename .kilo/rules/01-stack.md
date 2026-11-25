# 01 — Stack Muyunicos (tema WP)

Tema hijo de **GeneratePress** (`muyunicos`): WordPress + Woo. PHP en `functions.php`
e `inc/*.php`, JS en `js/`, CSS en `css/`. Repo propio `muyunicos/muyunicos`, rama `main`.

## Estructura
- `functions.php` — bootstrap del tema.
- `inc/` — módulos PHP (carrito, checkout, pedidos, cotizador, galeria, login, etc.).
- `js/` — scripts del tema (admin, cart, checkout, galeria, hero, etc.).
- `css/` — hojas de estilo por área.
- `tests/` — suites Node (`*.test.js`) + browser (Playwright).
- `.clinerules/`, `.specify/` — Spec Kit (tooling local, gitignored).

## Reglas
- **PHP**: `php -l` tras tocar `.php`.
- **Tests**: `node tests/*.test.js` desde la raíz del tema.
- **Datos**: nada de datos de usuario dentro del tema; van a `uploads/`.
- No introducir Node/Python/`exec()` en runtime (solo PHP en hosting).
- Leer `.clinerules/entorno-desarrollo.md` y `MIGRATION-GUIDE.md` antes de cambios grandes.