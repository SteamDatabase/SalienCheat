@echo off

if not exist php\php.exe (
	echo PHP wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadphp.ps1"
)

if not exist token.txt (
	set /p token=Please get a token from here: https://steamcommunity.com/saliengame/gettoken and enter it: 
	echo %token% > token.txt
)

echo The script can be terminated at any time by pressing Ctrl-C

:start
powershell -Command "[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12; Invoke-WebRequest http://github.com/SteamDatabase/SalienCheat/archive/master.zip -OutFile update.zip"
powershell -NoP -NonI -Command  "Expand-Archive -Force '.\update.zip' '.\'
move ".\SalienCheat-master\*.*" ".\"
del update.zip
php\php.exe -f cheat.php
goto start
