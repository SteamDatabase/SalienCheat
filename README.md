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
```bash
php cheat.php token1
php cheat.php token2
```

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

## Heroku
### Deploying with web-console

[![Deploy](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy)

1. Click the button above.
2. Set SALIENS_TOKEN & IGNORE_UPDATES.
3. That's all!

To check if it works, visit logs at https://dashboard.heroku.com/apps/[YOUR_APP_NAME]/logs

### Updating

If you are using Windows, open cmd by pressing <kbd>win</kbd>+<kbd>r</kbd> -> type `cmd` -> press <kbd>Enter</kbd> and then enter the following:
```bash
git clone https://git.heroku.com/[APP_NAME].git -o heroku
cd [APP_NAME]
git remote add upstream https://github.com/SteamDatabase/SalienCheat.git
git fetch upstream
git merge remotes/upstream/master
git push heroku master
```
