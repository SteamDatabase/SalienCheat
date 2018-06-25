[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download php and place it in the same folder as this script."
    Write-Output "Download from: https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip"
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip -OutFile php.zip
}
if($PSVersiontable.PSVersion.Major -lt 5)
{
    Write-Warning "Please extract php.zip to a directory and name it php!"
    Read-Host "Press Enter when you're done!"
}
else
{
    Expand-Archive -LiteralPath php.zip -DestinationPath php\
}
Copy-Item -Path php\php.ini-production -Destination php\php.ini
((Get-Content php\php.ini)) -Replace ";extension=curl", ("extension=" + (Get-Item -Path ".\php") + "\ext\php_curl.dll") | Set-Content php\php.ini
