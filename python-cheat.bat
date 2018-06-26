@echo off
setlocal enabledelayedexpansion

if not exist python\python.exe (
	echo Python portable wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadpython.ps1"
)

cls
echo The script can be terminated at any time by pressing Ctrl-C or clicking X
echo -------------------------------------------------------------------------

:start
python\python.exe cheat.py
goto start
