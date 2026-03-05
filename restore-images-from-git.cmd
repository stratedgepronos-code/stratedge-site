@echo off
REM Restaure les images uploads/ depuis l'historique Git.
REM Double-cliquer ou lancer depuis ce dossier.

cd /d "%~dp0"
echo Restauration de public_html/uploads/ depuis le commit 7bdd4de...
git checkout 7bdd4de -- public_html/uploads/
git restore --staged public_html/uploads/
echo.
echo Terminé. Les fichiers sont dans public_html/uploads/ (non committés).
echo Ensuite : 1) Envoyer uploads/ sur le serveur (FTP ou Panel ^> Restauration images)
echo           2) Migration SQL (image_data, locked_image_data) si pas fait
echo           3) Panel ^> Restauration images ^> Sauvegarder tout en BDD
pause
