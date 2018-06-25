[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$workingdir = (Get-Location).Path
$arch = (Get-WmiObject win32_operatingsystem | select osarchitecture).osarchitecture.Substring(0, 2).replace('32', '86')
if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download php and place it in the same folder as this script."
    Write-Output "Download from: https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x$arch.zip"
    Write-Output ("Save to this directory: $workingdir `nand rename it to php.zip" -f (Get-Location).Path)
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x$arch.zip -OutFile php.zip
}
if($PSVersiontable.PSVersion.Major -lt 5)
{
    if((Test-Path "$workdingdir\php") -eq $False)
    {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        [IO.Compression.ZipFile]::ExtractToDirectory('php.zip', 'php\')
    }
    else
    {
        Write-Warning "Directory $work\php already found. Delete if necessary!"
    }
}
else
{
    Expand-Archive -LiteralPath php.zip -DestinationPath php\ -Force
}
Copy-Item -Path php\php.ini-production -Destination php\php.ini
((Get-Content php\php.ini)) -Replace ";extension=curl", ("extension=" + (Get-Item -Path ".\php") + "\ext\php_curl.dll") | Set-Content php\php.ini
