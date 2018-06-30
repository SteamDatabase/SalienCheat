@echo off
setlocal enabledelayedexpansion

if not exist php\php.exe (
	echo PHP wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadphp.ps1"
)

if not exist git\bin\git.exe (
	echo Git wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadgit.ps1"
)

if not exist token.txt (
	set /p token=Please get a token from here: https://steamcommunity.com/saliengame/gettoken and enter it: 
	echo !token! > token.txt
)

if not exist .git\index (
	git\bin\git.exe init > nul
	git\bin\git.exe remote add https://github.com/DouglasAntunes/SalienCheat.git > nul

)

:update
cls
echo Updating Script...
git\bin\git.exe pull > nul
:start
echo cls
echo The script can be terminated at any time by pressing Ctrl-C or clicking X
echo -------------------------------------------------------------------------
php\php.exe -f cheat.php
goto update
