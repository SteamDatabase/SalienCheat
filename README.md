# SalienCheat

👽 Cheating Salien minigame, the proper way.

---

> # PLEASE READ THE ENTIRE README BEFORE ASKING FOR HELP

---

## How to use this

1. Log into Steam in your browser
2. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
3. Open https://steamcommunity.com/saliengame/gettoken and find the piece that looks like `"token":"xxxxxxxx"`
4. Create a new file called `token.txt` next to `cheat.php` and paste only the `xxxxxxxx` part of your token in
5. You're now ready to go, simply select _ONE_ method below (PHP, Python or Node.js) to run the script.

> Note: You do not need your browser open to run these scripts.

### PHP

1. Install PHP (yes, really)
   1. Download https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip
   2. Extract zip to `C:\php`
   3. Open `php.ini-production` in a text editor
   4. Find `;extension=curl` and remove the semicolon
   5. Save as `php.ini`
2. Extract the contents of this script to the same folder
3. Run the script: `php cheat.php`

### Python

0. (optional) Setup virtual env: `virtualenv env && source env/bin/activate`
1. `pip install requests tqdm`
2. Run the script: `python cheat.py`

### Node.js

1. Install the _Latest_ [Node.js](https://nodejs.org/en/) version
2. Open the `node` folder and then open command line (Tip: ['Shift + Right Click' in explorer -> 'Open Command Line/Powershell here'](http://i.imgur.com/6FJcydX.png))
3. Type `npm i` to get dependencies
4. Go back a directory (Type `cd ..` on windows)
5. Now run the script, type `node cheat.js`
