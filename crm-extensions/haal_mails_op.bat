@echo off
REM ============================================================================
REM  Auteur:   Khayrallah Issa
REM  Project:  CRM WooPremium uitbreiding
REM  Bestand:  haal_mails_op.bat
REM
REM  Roept Local's eigen PHP CLI aan en draait cron/fetch_emails.php met de
REM  imap-extensie ingeladen. We maken een eigen mini php.ini in tmp met alle
REM  Local-extensies + imap erbij, en geven die mee met -c. Daardoor zijn
REM  pdo_mysql, mbstring, openssl EN imap allemaal beschikbaar.
REM
REM  Auteur: Khayrallah Issa
REM ============================================================================
chcp 65001 > nul
cd /d "%~dp0"

set "LOCALPHP=C:\Users\khayr\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe"
set "EXTDIR=C:\Users\khayr\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\ext"
set "TMPINI=%TEMP%\crm_cli_php.ini"
set "INIPATHFILE=%TEMP%\crm_local_inipath.txt"

REM Stap 1: Local's PHP het pad van haar eigen php.ini laten printen.
"%LOCALPHP%" -r "echo php_ini_loaded_file();" > "%INIPATHFILE%" 2>nul

REM Stap 2: dat pad in een variabele zetten.
set /p SRCINI=<"%INIPATHFILE%"

if not exist "%SRCINI%" (
    echo FOUT: kon Local's php.ini niet vinden.
    echo Gevonden pad: "%SRCINI%"
    echo Probeer Local te starten en de site te activeren, en draai dit opnieuw.
    pause
    exit /b 1
)

echo === Inkomende mails ophalen via IMAP ===
echo PHP:    %LOCALPHP%
echo Bron:   %SRCINI%
echo Tmp:    %TMPINI%
echo Ext:    %EXTDIR%
echo.

REM Stap 3: kopieer Local's php.ini naar het tmp-bestand.
copy /Y "%SRCINI%" "%TMPINI%" > nul

REM Stap 4: voeg expliciet extension_dir en de imap-extensie achteraan toe.
REM Een latere [PHP]-instelling overschrijft eerdere in php.ini, dus dit telt.
(
echo.
echo ; --- Toegevoegd door haal_mails_op.bat ---
echo extension_dir="%EXTDIR%"
echo extension=php_imap.dll
) >> "%TMPINI%"

REM Stap 5: cron-script draaien met onze custom ini.
"%LOCALPHP%" -c "%TMPINI%" cron\fetch_emails.php

echo.
echo ============================================================================
echo  Klaar. Open in WordPress admin de dealer-pagina (Contacthistorie tab)
echo  om de nieuwe mails in de inbox te zien.
echo ============================================================================
pause
