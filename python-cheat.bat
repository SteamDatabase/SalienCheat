@echo off
setlocal enabledelayedexpansion

if not exist python\python.exe (
	echo Python portable wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadpython.ps1"
)

if not exist git\bin\git.exe (
	echo Git wasn't detected; we'll download and install it for you.
	PowerShell -ExecutionPolicy Unrestricted -File "downloadgit.ps1"
)

if not exist .git\index (
	git\bin\git.exe init > nul
	git\bin\git.exe remote add https://github.com/DouglasAntunes/SalienCheat.git > nul

)

cls
echo The script can be terminated at any time by pressing Ctrl-C or clicking X
echo -------------------------------------------------------------------------

:update
cls
echo Updating Script...
git\bin\git.exe pull > nul

:start
echo The script can be terminated at any time by pressing Ctrl-C or clicking X
echo -------------------------------------------------------------------------
python\python.exe cheat.py
goto update
