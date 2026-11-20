@echo off
setlocal
cd /d "%~dp0"
chcp 65001 >nul
set APP_ENV=local
set PHP_EXE=%~dp0runtime\php.exe
if not exist "%PHP_EXE%" (
  echo PHP local não encontrado. Consulte o README para instalar PHP 8.4.
  pause
  exit /b 1
)
"%PHP_EXE%" -c "%~dp0runtime\php.ini" -d "extension_dir=%~dp0runtime\ext" bin\init.php
if errorlevel 1 (
  pause
  exit /b 1
)
"%PHP_EXE%" -c "%~dp0runtime\php.ini" -d "extension_dir=%~dp0runtime\ext" bin\setup-code.php
echo.
echo Abra http://127.0.0.1:4180 no navegador.
echo Primeiro acesso: http://127.0.0.1:4180/configurar.php
echo Mantenha esta janela aberta. Pressione Ctrl+C para parar.
echo.
"%PHP_EXE%" -c "%~dp0runtime\php.ini" -d "extension_dir=%~dp0runtime\ext" -S 127.0.0.1:4180 -t public router.php
pause
