$url = "https://raw.githubusercontent.com/SteamDatabase/SalienCheat/master/cheat.php"
$path = Get-Location
$file = "\cheat.php"
$output = Join-Path $path $file
Invoke-WebRequest -Uri $url -OutFile $output
.\php.exe $output
