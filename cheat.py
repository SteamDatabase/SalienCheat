"""Plays SALIENT for you

pip install requests tqdm
"""

import os
import re
import sys
import json
import logging
from io import open
from time import sleep, time
from getpass import getpass

import requests
from tqdm import tqdm
import argparse

logging.basicConfig(level=logging.DEBUG if sys.argv[-1] == 'debug' else logging.INFO,
                    format="%(asctime)s | %(message)s")
LOG = logging.getLogger()

try:
    _input = raw_input
except:
    _input = input

ap = argparse.ArgumentParser()
ap.add_argument(
    "-t", "--token", required=False, type=str, help="Miniagame token",default="")
ap.add_argument("-f",
    "--working-folder",
    required=False,
    type=str,
    default=os.getcwd(),
    help=
    "set the bot's working folder, default is folder from where the bot is executed"
)
args = vars(ap.parse_args())
bot_path = args["working_folder"]

def get_access_token(force_input=False):
    token_re = re.compile("^[a-z0-9]{32}$")
    token_path = bot_path+'/token.txt'
    token = args["token"]

    if not force_input:
        if token!="":
            if not token_re.match(token):
                token = ''
                raise Exception("Invalid token parameter")
            else:
                LOG.info("Loaded token from parameter")
                return token

        if os.path.isfile(token_path):
            data = open(token_path, 'r', encoding='utf-8').read()

            try:
                token = json.loads(data)['token']
            except:
                token = data.strip()

            if not token_re.match(token):
                token = ''
            else:
                LOG.info("Loaded token from token.txt")

    if not token:
        token = _input("Login to steamcommunity.com\n"
                       "Visit https://steamcommunity.com/saliengame/gettoken\n"
                       "Copy the token value and paste here.\n"
                       "---\n"
                       "Token: "
                       ).strip()

        while not token_re.match(token):
            token = _input("Enter valid token: ").strip()

    with open(token_path, 'w', encoding='utf-8') as fp:
        if sys.version_info < (3,):
            token = token.decode('utf-8')
        fp.write(token)

    return token

class Saliens(requests.Session):
    api_url = 'https://community.steam-api.com/%s/v0001/'

    def __init__(self, access_token):
        super(Saliens, self).__init__()
        self.access_token = access_token
        self.headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36'
        self.headers['Accept'] = '*/*'
        self.headers['Origin'] = 'https://steamcommunity.com'
        self.headers['Referer'] = 'https://steamcommunity.com/saliengame/play'

    def spost(self, endpoint, form_fields=None, retry=False):
        if not form_fields:
            form_fields = {}
        form_fields['access_token'] = self.access_token

        tries = 0
        data = None

        while not data:
            try:
                resp = self.post(self.api_url % endpoint, data=form_fields)

                if resp.status_code != 200:
                    raise Exception("Not HTTP 200")
                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("No response is json")
            except Exception as exp:
                LOG.debug("spost error: %s", str(exp))
                if retry:
                    sleep(1)
            else:
                data = rdata['response']

            if not retry:
                break

        return data


    def sget(self, endpoint, query_params=None, retry=False):
        tries = 0
        data = None

        while not data:
            try:
                resp = self.get(self.api_url % endpoint, params=query_params)

                if resp.status_code != 200:
                    raise Exception("Not HTTP 200")
                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("No response is json")
            except Exception as exp:
                LOG.debug("spost error: %s", str(exp))
                if retry:
                    sleep(1)
            else:
                data = rdata['response']

            if not retry:
                break

        return data

    def is_access_token_valid(self):
        if not self.access_token:
            return False

        while True:
            resp = self.post(self.api_url % 'ITerritoryControlMinigameService/GetPlayerInfo',
                             data={'access_token': self.access_token}
                             )

            if resp.status_code == 200:
                return True
            elif resp.status_code == 401:
                return False

            sleep(2)

    def refresh_player_info(self):
        self.player_info = self.spost('ITerritoryControlMinigameService/GetPlayerInfo', retry=True)
        return self.player_info

    def refresh_planet_info(self):
        if 'active_planet' in self.player_info:
            self.planet = self.get_planet(self.player_info['active_planet'])
        else:
            self.planet = {}

        return self.planet

    def get_planet(self, pid):
        return self.sget('ITerritoryControlMinigameService/GetPlanet',
                          {'id': pid},
                          retry=True,
                          ).get('planets', [{}])[0]

    def represent_clan(self, clan_id):
        return self.spost('ITerritoryControlMinigameService/RepresentClan', {'clanid': clan_id})

    def report_score(self, score):
        return self.spost('ITerritoryControlMinigameService/ReportScore', {'score': score})

    def get_planets(self):
        return self.sget('ITerritoryControlMinigameService/GetPlanets', {'active_only': 1}, retry=True).get('planets', [])

    def join_planet(self, pid):
        return self.spost('ITerritoryControlMinigameService/JoinPlanet', {'id': pid})

    def join_zone(self, pos):
        return self.spost('ITerritoryControlMinigameService/JoinZone', {'zone_position': pos})

    def leave_all(self):
        if 'active_zone_game' in self.player_info:
            self.spost('IMiniGameService/LeaveGame', {'gameid': self.player_info['active_zone_game']}, retry=False)
        if 'active_planet' in self.player_info:
            self.spost('IMiniGameService/LeaveGame', {'gameid': self.player_info['active_planet']}, retry=False)

    def print_player_info(self):
        player_info = self.player_info

        if getattr(self, 'level_pbar', None):
            self.level_pbar.desc = "Player Level {level}".format(**player_info)
            self.level_pbar.total = int(player_info['next_level_score'])
            self.level_pbar.n = int(player_info['score'])
            print(self.level_pbar)
        else:
            self.level_pbar = tqdm(ascii=True,
                                   position=0,
                                   dynamic_ncols=True,
                                   desc="Player Level {level}".format(**player_info),
                                   total=int(player_info['next_level_score']),
                                   initial=int(player_info['score']),
                                   bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {remaining:>10}',
                                   )

    def print_planet_progress(self):
        planet = self.planet
        state = planet['state']
        mul = 100000

        current_progress = mul if state['captured'] else int(state['capture_progress'] * mul)

        if getattr(self, 'planet_pbar', None):
            self.planet_pbar.desc="Planet ({id}) progress".format(**planet)
            self.planet_pbar.n = current_progress
            print(self.planet_pbar)
        else:
            self.planet_pbar = tqdm(ascii=True,
                                    position=0,
                                    dynamic_ncols=True,
                                    desc="Planet ({id}) progress".format(**planet),
                                    total=mul,
                                    initial=current_progress,
                                    bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {remaining:>10}',
                                    )

    def print_zone_progress(self, zone=None):
        if not self.planet:
            return

        zone = self.planet['zones'][zone]
        mul = 100000

        current_progress = mul if zone['captured'] else int(zone['capture_progress'] * mul)

        if getattr(self, 'zone_pbar', None):
            self.zone_pbar.desc="Zone ({zone_position}) progress".format(**zone)
            self.zone_pbar.n = current_progress
            print(self.zone_pbar)
        else:
            self.zone_pbar = tqdm(ascii=True,
                                  position=0,
                                  dynamic_ncols=True,
                                  desc="Zone ({zone_position}) progress".format(**zone),
                                  total=mul,
                                  initial=current_progress,
                                  bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {remaining:>10}',
                                  )

# ------- MAIN ----------

game = Saliens(None)
game.access_token = get_access_token()

while not game.is_access_token_valid():
    game.access_token = get_access_token(True)

# display current stats
LOG.info("Getting player info...")
game.represent_clan(4777282)
game.refresh_player_info()
game.print_player_info()

# join battle
while True:
    LOG.info("Finding planet...")
    game.refresh_player_info()
    game.leave_all()

    # locate uncaptured planet and join it
    planets = game.get_planets()
    planets = list(filter(lambda x: not x['state']['captured'], planets))
    planets = map(lambda x: game.get_planet(x['id']), planets)

    for planet in planets:
        planet['n_hard_zones'] = list(map(lambda x: x['difficulty'], filter(lambda y: not y['captured'], planet['zones']))).count(3)

    planets = sorted(planets, reverse=True, key=lambda x: x['n_hard_zones'])
#   planets = sorted(planets, reverse=False, key=lambda x: x['state']['current_players'])

    if not planets:
        LOG.error("No uncaputred planets left :(")
        raise SystemExit

    LOG.info("Joining planet %s..", planets[0]['id'])

    planet_id = planets[0]['id']
    game.join_planet(planet_id)
    deadline = time() + 60 * 30

    game.refresh_player_info()
    game.refresh_planet_info()

    # if join didnt work for retry
    if game.planet['id'] != planet_id:
        sleep(2)
        continue

    LOG.info("Planet name: {name} ({id})".format(id=game.planet['id'], **game.planet['state']))
    LOG.info("Current players: {current_players}".format(**game.planet['state']))
    LOG.info("Giveaway AppIDs: {giveaway_apps}".format(**game.planet))
    game.print_planet_progress()

    # zone
    LOG.info("Finding conflict zone...")

    while time() < deadline and game.planet and not game.planet['state']['captured']:
        zones = game.planet['zones']
        zones = list(filter(lambda x: x['captured'] == False, zones))
        boss_zones = list(filter(lambda x: x['type'] == 4, zones))

        if boss_zones:
            zones = boss_zones
        else:
            zones = sorted(zones, reverse=True, key=lambda x: x['zone_position'])
            zones = sorted(zones, reverse=True, key=lambda x: x['difficulty'])

        if not zones:
            LOG.debug("No open zones left on planet")
            game.player_info.pop('active_planet')
            break

        zone_id = zones[0]['zone_position']
        difficulty = zones[0]['difficulty']

        while time() < deadline and game.planet and not game.planet['zones'][zone_id]['captured']:
            game.print_player_info()
            if 'clan_info' not in game.player_info or game.player_info['clan_info']['accountid'] != 4777282:
                game.represent_clan(4777282)
            game.print_planet_progress()
            game.print_zone_progress(zone_id)

            LOG.info("Fighting in zone %s (%s) for 2mins", zone_id, difficulty)
            game.join_zone(zone_id)

            try:
                sleep(120)
            except KeyboardInterrupt:
                raise SystemExit

            score = 120 * (5 * (2**(difficulty - 1)))
            LOG.info("Submitting score of %s...", score)
            game.report_score(score)

            game.refresh_planet_info()
            game.refresh_player_info()

    LOG.info("Planet was comptured or disappared. Moving on...")
