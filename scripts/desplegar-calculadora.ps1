<#
  desplegar-calculadora.ps1 — Build del cotizador + publicación al TEMA.

  Un solo comando desde el repo del tema:
    1) npm install (solo si falta node_modules) + build:js + build:css en ..\calculadora
    2) Copia los 4 artefactos a  assets\  (verificando SHA256)
    3) Corre la puerta  tests\cotizador-assets.js
    4) git add / commit / push SOLO del tema (muyunicos)

  Reglas:
    - NO toca assets\datos_config.json (es dato vivo del admin).
    - NO pushea el repo calculadora (es solo la fuente).
    - Si el tema ya tiene los mismos assets, no commitea nada.

  Uso (PowerShell 7, desde la raíz del tema):
    pwsh -NoProfile -File scripts/desplegar-calculadora.ps1
    pwsh -NoProfile -File scripts/desplegar-calculadora.ps1 -Calculadora 'D:\otros\calculadora'
    pwsh -NoProfile -File scripts/desplegar-calculadora.ps1 -SinPush
    pwsh -NoProfile -File scripts/desplegar-calculadora.ps1 -Mensaje 'fix: modo economico'
#>
[CmdletBinding()]
param(
    # Repo fuente del cotizador (por defecto, hermano del tema).
    [string] $Calculadora = '..\calculadora',
    # Mensaje de commit en el tema.
    [string] $Mensaje = 'Cotizador: build + assets publicados al tema',
    # No hacer push (útil para probar).
    [switch] $SinPush
)

$ErrorActionPreference = 'Stop'

$temaRaiz = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $temaRaiz '.git'))) {
    throw "Esto no parece el repo del tema (no hay .git en $temaRaiz)."
}

$calcRaiz = if ([System.IO.Path]::IsPathRooted($Calculadora)) { $Calculadora } else { Join-Path $temaRaiz $Calculadora }
$calcRaiz = [System.IO.Path]::GetFullPath($calcRaiz)
if (-not (Test-Path (Join-Path $calcRaiz 'package.json'))) {
    throw "No encuentro el repo calculadora en: $calcRaiz (usá -Calculadora)."
}

Write-Host "== Cotizador -> Tema ==" -ForegroundColor Cyan
Write-Host "Tema       : $temaRaiz"
Write-Host "Calculadora: $calcRaiz"
Write-Host ""

Write-Host "1/4 Build en calculadora..." -ForegroundColor Yellow
Push-Location $calcRaiz
try {
    if (-not (Test-Path (Join-Path $calcRaiz 'node_modules'))) {
        npm install
        if ($LASTEXITCODE -ne 0) { throw "npm install falló." }
    }
    npm run build:js
    if ($LASTEXITCODE -ne 0) { throw "npm run build:js falló." }
    npm run build:css
    if ($LASTEXITCODE -ne 0) { throw "npm run build:css falló." }
} finally {
    Pop-Location
}

Write-Host "2/4 Copiar artefactos..." -ForegroundColor Yellow
$pares = @(
    @('js\calculadora_stickers.js', 'js\calculadora_stickers.js'),
    @('css\calculadora_stickers.css', 'css\calculadora_stickers.css'),
    @('datos_config_etag.php', 'datos_config_etag.php'),
    @('guardar_datos.php', 'guardar_datos.php')
)
$origenAssets = Join-Path $calcRaiz 'assets'
$destinoAssets = Join-Path $temaRaiz 'assets'
foreach ($p in $pares) {
    $src = Join-Path $origenAssets $p[0]
    $dst = Join-Path $destinoAssets $p[1]
    if (-not (Test-Path $src)) { throw "Falta el archivo de origen: $src" }
    $dirDst = Split-Path -Parent $dst
    if (-not (Test-Path $dirDst)) { New-Item -ItemType Directory -Force -Path $dirDst | Out-Null }
    Copy-Item -LiteralPath $src -Destination $dst -Force
    $h1 = (Get-FileHash -LiteralPath $src -Algorithm SHA256).Hash
    $h2 = (Get-FileHash -LiteralPath $dst -Algorithm SHA256).Hash
    if ($h1 -ne $h2) { throw "Copia corrupta: $($p[1]) (SHA256 distinto)." }
    Write-Host ("   OK  {0}  ({1} bytes)" -f $p[1], (Get-Item $dst).Length)
}

Write-Host "3/4 Puerta de assets..." -ForegroundColor Yellow
$gate = Join-Path $temaRaiz 'tests\cotizador-assets.js'
$fuenteGate = Join-Path $temaRaiz '..\calculadora\assets'
if ((Test-Path $gate) -and (Test-Path $fuenteGate)) {
    Push-Location $temaRaiz
    try { node $gate } finally { Pop-Location }
} elseif (Test-Path $gate) {
    Write-Host "   (calculadora no está en ..\calculadora: se omite la puerta)"
} else {
    Write-Host "   (no existe tests/cotizador-assets.js: se omite)"
}

Write-Host "4/4 Git (solo tema)..." -ForegroundColor Yellow
Push-Location $temaRaiz
try {
    git add -- assets/js/calculadora_stickers.js assets/css/calculadora_stickers.css assets/datos_config_etag.php assets/guardar_datos.php scripts/desplegar-calculadora.ps1
    if ($LASTEXITCODE -ne 0) { throw "git add falló." }
    $staged = git diff --cached --name-only
    if ($staged) {
        Write-Host "   Archivos staged:"
        $staged | ForEach-Object { Write-Host "     $_" }
        git commit -m $Mensaje
        if ($LASTEXITCODE -ne 0) { throw "git commit falló." }
        Write-Host "   Commit OK."
    } else {
        Write-Host "   Sin cambios nuevos en los assets."
    }

    if ($SinPush) {
        Write-Host "   -SinPush: no se hace push." -ForegroundColor DarkYellow
    } else {
        # Pushea aunque no haya cambios nuevos: puede haber un commit local pendiente.
        git push
        if ($LASTEXITCODE -ne 0) { throw "git push falló." }
        Write-Host "   Push OK. El webhook del tema va a deployar." -ForegroundColor Green
    }
} finally {
    Pop-Location
}

Write-Host ""
Write-Host "LISTO." -ForegroundColor Green