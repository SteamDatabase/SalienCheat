# How to use this

## First steps

1. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
2. Grab `token` from https://steamcommunity.com/saliengame/gettoken and put it in `token.txt` (create file yourself)
3. Select PHP or Python version of the script, you don't need both

## PHP

1. Install PHP (yes, really)
   1. Download https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip
   2. Extract zip to `C:\php`
   3. Open `php.ini-production` in a text editor
   4. Find `;extension=curl` and remove the semicolon
   5. Save as `php.ini`
2. Extract the contents of this script to the same folder
3. Run the script: `php cheat.php`

## Python

0. (optional) Setup virtual env: `virtualenv env && source env/bin/activate`
1. `pip install requests tqdm`
2. Run the script: `python cheat.py`

## Node.js

1. Install _Latest_ Node.js https://nodejs.org/en/
2. Open command line in the `node` folder
3. Run: `npm i` (or if you have yarn installed; run `yarn`)
4. Step back a directory (`cd ..` on windows)
5. Run the script: `node cheat.js`
