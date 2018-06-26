[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$workingdir = (Get-Location).Path

$git = "https://raw.githubusercontent.com/SteamDatabase/SalienCheat/master/"

$bat = "python-cheat.bat"
$batold = "python-cheat.old.bat"
$cheat = "cheat.py"
$cheatold = "cheat.old.py"

if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Your powershell version does not support file downloading."
    Read-Host "Press any key to exit"
}
else
{
    Write-Output "Backing up old version"
    if (Test-Path .\$cheatold) { Remove-Item .\$cheatold }
    if (Test-Path .\$batold) { Remove-Item .\$batold }
    if (Test-Path .\$cheat) { Move-Item .\$cheat .\$cheatold }
    if (Test-Path .\$bat) { Move-Item .\$bat .\$batold }
    Invoke-WebRequest -Uri $git$cheat -OutFile $cheat
    Invoke-WebRequest -Uri $git$bat -OutFile $bat
    Write-Output "Updating done!"
    Read-Host "Press any key to exit"
}