[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
$workingdir = (Get-Location).Path
if($PSVersiontable.PSVersion.Major -lt 3)
{
    Write-Warning "Please download Python and place it in the same folder as this script."
    Write-Output "Download from: https://www.python.org/ftp/python/3.6.5/python-3.6.5-embed-amd64.zip"
    Write-Output ("Save to this directory: $workingdir `nand rename it to python.zip" -f (Get-Location).Path)
    Write-Output "Download from: https://bootstrap.pypa.io/get-pip.py"
    Write-Output ("Save to this directory: $workingdir" -f (Get-Location).Path)
    Read-Host "Press Enter when you're done!"
}
else
{
    Invoke-WebRequest -Uri https://www.python.org/ftp/python/3.6.5/python-3.6.5-embed-amd64.zip -OutFile python.zip
    Invoke-WebRequest -Uri https://bootstrap.pypa.io/get-pip.py -OutFile get-pip.py
}
if($PSVersiontable.PSVersion.Major -lt 5)
{
    if((Test-Path "$workdingdir\python") -eq $False)
    {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        [IO.Compression.ZipFile]::ExtractToDirectory('python.zip', 'python\')
    }
    else
    {
        Write-Warning "Directory $work\python already found. Delete if necessary!"
    }
}
else
{
    Expand-Archive -LiteralPath python.zip -DestinationPath python\ -Force
}

((Get-Content python\python36._pth)) -Replace "#import", "import" | Set-Content python\python36._pth

python\python.exe get-pip.py
python\python.exe -m pip install requests tqdm colorama

del python.zip
del get-pip.py
