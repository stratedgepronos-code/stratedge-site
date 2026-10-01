# StratEdge — Copier index.php depuis Téléchargements vers public_html
# Usage : .\scripts\copy-index-from-downloads.ps1
# Cherche dans cet ordre :
#   1) %USERPROFILE%\Downloads\index.php
#   2) %USERPROFILE%\Downloads\public_html\index.php

$ErrorActionPreference = "Stop"
# Repo = dossier parent de /scripts (ex. Site StratEdge)
$repo = Split-Path $PSScriptRoot -Parent
$dst = Join-Path $repo "public_html\index.php"
if (-not (Test-Path (Split-Path $dst -Parent))) {
    Write-Error "public_html introuvable à côté de scripts/ : $dst"
}
$dl = $env:USERPROFILE
$src1 = Join-Path $dl "Downloads\index.php"
$src2 = Join-Path $dl "Downloads\public_html\index.php"

$src = $null
if (Test-Path -LiteralPath $src1) { $src = $src1 }
elseif (Test-Path -LiteralPath $src2) { $src = $src2 }
else {
    Write-Error "Aucun index.php trouvé.`n  - $src1`n  - $src2`nPlace ton fichier à l'un de ces emplacements."
}

Copy-Item -LiteralPath $src -Destination $dst -Force
Write-Host "OK : copié" -ForegroundColor Green
Write-Host "  De : $src"
Write-Host "  Vers: $dst"
Write-Host "Pense à vérifier les liens Stake (packs hors tennis : ?c=n26yI0vn ; tennis : ?c=2bd992d384) puis git add / commit / push."
