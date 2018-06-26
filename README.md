# How to use this

## First steps

1. Join https://steamcommunity.com/groups/SteamDB (needed to represent captures)
2. Open https://steamcommunity.com/saliengame/gettoken and save it as `token.txt` in the same folder as `cheat.php`
3. Select PHP or Python version of the script. You don't need both

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
```
php cheat.php token1
php cheat.php token2
```
### Android

To use SalienCheat on **Android** follow the following steps;

1. Install Termux from Play Store

2. Give Storage Permissions to Termux (Long press the app icon> app info> permissions> Storage)

3. Open Termux

4. Type "apt update" enter

5. Type "apt upgrade" enter

6. Type "apt install termux-api php" enter

7. Type "pkg install git php"

8. Type "git clone https://github.com/SteamDatabase/SalienCheat.git"

9. Type "cd SalienCheat"

10. Type "php cheat.php YOURTOKEN"

**To Update Script as @xPaw is working very hard to make stable Script**

1. hit ctrl-c (Volume down + C) to stop the script

2. Type "git pull"

3. Type "php cheat.php YOURTOKEN"

I hope this help, this is the best way to use this script power efficiently on android device.
### Linux

1. Install `php-curl` and enable it in `php.ini`
2. You know what you are doing. 🐧

## Python

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
3. To run: `docker run -it --init --rm -e TOKEN=<32 character token from gettoken url> steamdb/saliencheat`
4. To stop running, Ctrl+C
