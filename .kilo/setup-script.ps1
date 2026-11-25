# Setup de worktree Muyunicos (tema WP). Corre una vez por worktree nuevo.
$ErrorActionPreference = 'Stop'
Write-Host "== Setup worktree Muyunicos =="
foreach ($cmd in 'php', 'node', 'git') {
  $found = Get-Command $cmd -ErrorAction SilentlyContinue
  if ($found) { Write-Host ("OK  {0} -> {1}" -f $cmd, $found.Source) }
  else { Write-Host ("FALTA {0}" -f $cmd) }
}
Write-Host "Tema WP: sin npm install (PHP + JS estatico)."