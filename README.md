# How to use this

## First steps

1. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
2. Open https://steamcommunity.com/saliengame/gettoken and save it as `token.txt` in same folder as `cheat.php`
3. Select PHP or Python version of the script, you don't need both

## PHP

📣 [Check this reddit thread for a complete guide and troubleshooting](https://redd.it/8t5w8v)

1. Install PHP (yes, really)
   1. Download https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip
   2. Extract zip to `C:\php`
   3. Open `php.ini-production` in a text editor
   4. Find `;extension=curl` and remove the semicolon
   5. Make sure you have enabled visible file endings in windows
   6. Save as `php.ini`
   7. Make sure it is an .ini file now, if it is still a ini-production file try again until it is .ini
2. Extract the contents of this script to the same folder
3. Open your CMD
4. Change the directory to C:/php using the command `cd C:/php`
5. Run the script: `php cheat.php`

You can also provide token directly in CLI, to ease running multiple accounts:
```
php cheat.php token1
php cheat.php token2
```

## Python

0. (optional) Setup virtual env: `virtualenv env && source env/bin/activate`
1. `pip install requests tqdm`
2. Run the script: `python cheat.py [token]`

## Docker
1. Extract contents of this script somewhere.
2. To build: `docker build . -t steamdb/saliencheat`
3. To run: `docker run -it --init --rm -e TOKEN=<32 character token from gettoken url> steamdb/saliencheat`
4. To stop running, Ctrl+C
