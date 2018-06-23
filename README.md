# How to use this

## First steps

1. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
2. Open https://steamcommunity.com/saliengame/gettoken and save it as `token.txt` in same folder as `cheat.php`
3. Select PHP or Python version of the script, you don't need both

## PHP

📣 [Check this reddit thread for a complete guide and troubleshooting](https://redd.it/8t5w8v)

### Windows

1. Install PHP (yes, really)
   1. Download https://windows.php.net/downloads/releases/php-7.2.7-nts-Win32-VC15-x64.zip
   2. Extract zip to `C:\php`
   3. Open `php.ini-production` in a text editor
   4. Find `;extension=curl` and remove the semicolon
   5. Save as `php.ini`
2. Extract the contents of this script to the same folder
3. Run the script: `php cheat.php`

### Mac

0. (optional) Launch the App Store and download any updates for macOS. Newer versions of macOS have php and curl included by default.
1. Extract the contents of this script to the Downloads folder.
2. Launch Terminal and run the script: `php downloads/cheat.php`

You can also provide token directly in CLI, to ease running multiple accounts:
```
php cheat.php token1
php cheat.php token2
```

## Python

### Linux/Cygwin

0. (optional) Setup virtual env: `virtualenv env && source env/bin/activate`
1. `pip install requests tqdm`
2. Run the script: `python cheat.py [token]`

### Mac

0. (optional) Launch the App Store and download any updates for macOS. Newer versions of macOS have Python 2.7.10 included by default.
1. Extract the contents of this script to the Downloads folder.
2. Launch Terminal and run the following scripts:
   1. `sudo easy_install pip`
   2. `pip install requests tqdm`
   3. `python downloads/cheat.py [token]`

## Vagrant

1. Install [vagrant](https://www.vagrantup.com/downloads.html) and [VirtualBox](https://www.virtualbox.org/wiki/Downloads)
2. run `vagrant up` to setup VM
3. run cheat
  * For PHP `vagrant ssh -c 'php cheat.php [token]`
  * For Python `vagrant ssh -c 'python3 cheat.py [token]`

## Docker
1. Extract contents of this script somewhere.
2. To build: `docker build . -t steamdb/saliencheat`
3. To run: `docker run -it --init --rm -e TOKEN=<32 character token from gettoken url> steamdb/saliencheat`
4. To stop running, Ctrl+C
