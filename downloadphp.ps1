[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
Invoke-WebRequest -Uri https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip -OutFile php.zip
Expand-Archive -LiteralPath php.zip -DestinationPath php\
Copy-Item -Path php\php.ini-production -Destination php\php.ini
((Get-Content php\php.ini)) -Replace ";extension=curl", ("extension=" + (Get-Item -Path ".\php") + "\ext\php_curl.dll") | Set-Content php\php.ini
