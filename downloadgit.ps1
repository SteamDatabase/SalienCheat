[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$workingdir = (Get-Location).Path

if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download 7zip Extra v9.2 and place it in the same folder as this script."
    Write-Output "https://www.7-zip.org/a/7za920.zip"
    Write-Output ("Save to this directory: $workingdir `and rename it to 7ZE.zip" -f (Get-Location).Path)
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://www.7-zip.org/a/7za920.zip -OutFile 7ZE.zip
}
if($PSVersiontable.PSVersion.Major -lt 5)
{
    if((Test-Path "$workdingdir\7zE_Zip") -eq $False)
    {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        [IO.Compression.ZipFile]::ExtractToDirectory('7ZE.zip', '7zE_Zip\')
    }
    else
    {
        Write-Warning "Directory $workingdir\7zE_Zip already found. Delete if necessary!"
    }
}
else
{
    Expand-Archive -LiteralPath 7ZE.zip -DestinationPath 7zE_Zip\ -Force
}
if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download 7zip Extra and place it in the same folder as this script."
    Write-Output "https://www.7-zip.org/a/7z1805-extra.7z"
    Write-Output ("Save to this directory: $workingdir `and rename it to 7ZE.7z" -f (Get-Location).Path)
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://www.7-zip.org/a/7z1805-extra.7z -OutFile 7ZE.7z
}

.\7zE_Zip\7za.exe x -o"$workingdir\7zE\" 7ZE.7z > $null

$arch = (Get-WmiObject win32_operatingsystem | select osarchitecture).osarchitecture.Substring(0, 2)
if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download git portable and place it in the same folder as this script."
    Write-Output "https://github.com/git-for-windows/git/releases/download/v2.18.0.windows.1/PortableGit-2.18.0-$arch-bit.7z.exe"
    Write-Output ("Save to this directory: $workingdir `and rename it to git.exe" -f (Get-Location).Path)
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://github.com/git-for-windows/git/releases/download/v2.18.0.windows.1/PortableGit-2.18.0-$arch-bit.7z.exe -OutFile git.7z.exe
}

if($arch -eq "64") {
    .\7zE\x64\7za.exe x -o"$workingdir\git\" git.7z.exe > $null
}
else
{
    .\7zE\7za.exe x -o"$workingdir\git\" git.7z.exe > $null
}

