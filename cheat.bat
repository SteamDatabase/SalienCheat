@echo off

if not exist C:\php\php.exe (
	echo PHP wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadphp.ps1"
)

if not exist token.txt (
	set /p token=Please get a token from here: https://steamcommunity.com/saliengame/gettoken and enter it: 
	echo %token% > token.txt
)

echo The script can be terminated at any time by pressing Ctrl-C

:start
C:\php\php.exe -f cheat.php
goto start
