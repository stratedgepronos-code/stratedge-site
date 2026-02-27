@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo === Deploy StratEdge vers stratedgepronos.fr ===
echo.
git add .
git commit -m "Update"
git push
echo.
echo Si le push a reussi, le site se mettra a jour dans 1-2 min.
pause
