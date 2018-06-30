# How to use this

## First steps

1. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
2. Open https://steamcommunity.com/saliengame/gettoken and save it (<kbd>Ctrl</kbd>+<kbd>S</kbd>) as `token.txt` in the same folder as `cheat.php`

## PHP

### Windows

1. [Download this script](https://github.com/SteamDatabase/SalienCheat/archive/master.zip)
2. Extract it into a new folder
3. Click `cheat.bat` and follow instructions

### Mac

0. (optional) Launch the App Store and download any updates for macOS. Newer versions of macOS have php and curl included by default
1. Extract the contents of this script to the Downloads folder
2. Launch Terminal and run the script: `php downloads/cheat.php`

You can also provide token directly in CLI, to ease running multiple accounts:
```bash
php cheat.php token1
php cheat.php token2
```

### Linux

1. Install `php-curl` and enable it in `php.ini`
2. You know what you are doing. 🐧

## Python

⚠ **Python version currently does not support Boss battles, so you should choose the PHP version.** ⚠

### Windows

1. [Download this script](https://github.com/SteamDatabase/SalienCheat/archive/master.zip)
2. Extract it into a new folder
3. Click `python-cheat.bat` and follow instructions

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
2. Run `vagrant up` to setup VM
3. Run cheat
  * For PHP `vagrant ssh -c 'php cheat.php [token]`
  * For Python `vagrant ssh -c 'python3 cheat.py [token]`

## Docker
1. Extract contents of this script somewhere.
2. To build: `docker build . -t steamdb/saliencheat`
3. To run: `docker run -it --init --rm -v '<directory/to/SalienCheat>':'/app':'rw' 'steamdb/saliencheat`
4. In your appointed directory, have the master files there and create a `token.txt` in that same root, for the PHP script to use.
5. To stop running, Ctrl+C
