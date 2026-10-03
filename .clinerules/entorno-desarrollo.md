# Entorno de desarrollo — reglas operativas para Cline

Este archivo no define normas del proyecto: resume el entorno operativo. Las
fuentes normativas son `.specify/memory/constitution.md` (principios y
gobernanza) y `MIGRATION-GUIDE.md` §1 (flujo), §7 (diagnóstico) y §9 (entorno
local).

## Shell y ejecución

- Entorno soportado: Windows + PowerShell 7 (`pwsh`). No asumir bash, WSL, cmd
  ni Windows PowerShell 5.1.
- Ejecutar todos los comandos desde la raíz del repositorio salvo indicación
  explícita.
- Ante un fallo, verificar en este orden antes de diagnosticar el código: shell
  activo (`$PSVersionTable.PSVersion`; `(Get-Process -Id $PID).Path` debe
  terminar en `pwsh.exe`), directorio actual, PATH y disponibilidad de la
  herramienta.
- Usar `Select-String` en vez de `grep`, `curl.exe` en vez de `curl` (en
  PowerShell, `curl` es alias de Invoke-WebRequest) y los scripts de Spec Kit
  desde `.specify/scripts/powershell/`.
- Rutas con espacios o caracteres especiales: entre comillas dobles.
- La terminal integrada de VS Code y de Cline debe abrir PowerShell 7; el perfil
  por defecto y el de automatización viven versionados en `.vscode/settings.json`
  (`.vscode/extensions.json` recomienda la extensión Cline).
- Los comandos de verificación de artefactos nuevos (quickstarts, tareas) se
  escriben en PowerShell. Los ejemplos POSIX de `specs/001-003` son históricos y
  no se reescriben.

## Spec Kit

- Integración única y predeterminada: `cline`, con scripts PowerShell
  (`--script ps`). Los workflows `/speckit-*` viven en `.clinerules/workflows/`.
- Estado esperado: `specify integration status` → OK, default/installed: cline,
  0 archivos gestionados modificados o faltantes.
- Actualización: `specify integration upgrade cline --script ps`, revisando
  después `git status` y `git diff`; commitear por separado.
- `.clinerules/workflows/` y `.specify/` son archivos gestionados por Spec Kit:
  no editarlos a mano.

## Límites de seguridad

- No modificar bases de datos, credenciales ni servicios externos.
- No desplegar a producción (el despliegue es manual, por FTP, y exclusivo del
  mantenedor).

Estas operaciones requieren aprobación explícita del mantenedor antes de
ejecutarse.

## Verificación local reproducible

- `php -l` sobre los PHP tocados (PHP local 8.5.9; producción corre 8.5.4).
- `Select-String` para invariantes (§7 del guide y quickstarts de `specs/`).
- `git diff --name-only` para verificar alcance: nada bajo `Tema-GeneratePress/`.
- Sin suite de pruebas, CI ni staging: lo que requiera navegador, sesión o pago
  se verifica manualmente contra producción (constitución, "Restricción
  dominante").
