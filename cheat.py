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
from datetime import datetime
from getpass import getpass

import requests
from tqdm import tqdm

try:
    _input = raw_input
except:
    _input = input


def get_access_token(force_input=False):
    token_re = re.compile("^[a-z0-9]{32}$")
    token_path = './token.txt'
    token = ''

    if not force_input:
        if token_re.match(sys.argv[-1]):
            token = sys.argv[-1]
        else:
            if os.path.isfile(token_path):
                data = open(token_path, 'r', encoding='utf-8').read()

                try:
                    token = json.loads(data)['token']
                except:
                    token = data.strip()

                if not token_re.match(token):
                    token = ''
                else:
                    game.log("Loaded token from token.txt")

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
    player_info = None
    planet = None

    def __init__(self, access_token):
        super(Saliens, self).__init__()
        self.access_token = access_token
        self.headers['User-Agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36'
        self.headers['Accept'] = '*/*'
        self.headers['Origin'] = 'https://steamcommunity.com'
        self.headers['Referer'] = 'https://steamcommunity.com/saliengame/play'
        self.pbar_init()

        class CustomHandler(logging.Handler):
            def emit(_, record):
                self.log("%s | %s | %s", record.levelname, record.name, record.msg % record.args)

        self.LOG = logging.getLogger()
        self.LOG.addHandler(CustomHandler())

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
                    raise Exception("HTTP %s" % resp.status_code)
                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("No response is json")
            except Exception as exp:
                self.log("spost error: %s", str(exp))
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
                    raise Exception("HTTP %s" % resp.status_code)
                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("No response is json")
            except Exception as exp:
                self.log("spost error: %s", str(exp))
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

    def pbar_init(self):
        self.level_pbar = tqdm(ascii=True,
                               dynamic_ncols=True,
                               desc="Player Level",
                               total=0,
                               initial=0,
                               bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {n_fmt}/{total_fmt} | {remaining:>8}',
                               )
        self.planet_pbar = tqdm(ascii=True,
                                dynamic_ncols=True,
                                desc="Planet progress",
                                total=0,
                                initial=0,
                                bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {remaining:>8}',
                                )
        self.zone_pbar = tqdm(ascii=True,
                              dynamic_ncols=True,
                              desc="Zone progress",
                              total=0,
                              initial=0,
                              bar_format='{desc:<22} {percentage:3.0f}% |{bar}| {remaining:>8}',
                              )

    def pbar_refresh(self):
        mul = 1000000

        if not self.player_info:
            return

        player_info = self.player_info

        self.level_pbar.desc = "Player Level {level}".format(**player_info)
        self.level_pbar.total = int(player_info['next_level_score'])
        self.level_pbar.n = int(player_info['score'])
        self.level_pbar.refresh()

        if self.planet:
            planet = self.planet
            state = planet['state']
            planet_progress = mul if state['captured'] else int(state['capture_progress'] * mul)
            self.planet_pbar.desc="Planet ({id}) progress".format(**planet)
            self.planet_pbar.n = planet_progress
            self.planet_pbar.total = mul
        else:
            self.planet_pbar.desc="Planet progress"
            self.planet_pbar.n = 0
            self.planet_pbar.total = 0
            self.planet_pbar.start_t = time()

        self.planet_pbar.refresh()

        if self.planet and 'active_zone_position' in player_info:
            zone = self.planet['zones'][int(player_info['active_zone_position'])]
            zone_progress = mul if zone['captured'] else int(zone['capture_progress'] * mul)
            self.zone_pbar.desc="Zone ({zone_position}) progress".format(**zone)
            self.zone_pbar.n = zone_progress
            self.zone_pbar.total = mul
        else:
            self.zone_pbar.desc="Zone  progress"
            self.zone_pbar.n = 0
            self.zone_pbar.total = 0
            self.zone_pbar.start_t = time()

        self.zone_pbar.refresh()

    def log(self, text, *args):
        self.level_pbar.write(datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " | " + (text % args))
        self.pbar_refresh()



# ------- MAIN ----------

game = Saliens(None)
game.LOG.setLevel(logging.DEBUG if sys.argv[-1] == 'debug' else logging.INFO)
game.access_token = get_access_token()

while not game.is_access_token_valid():
    game.access_token = get_access_token(True)

# display current stats
game.log("Getting player info...")
game.represent_clan(4777282)
game.refresh_player_info()

# join battle
while True:
    game.log("Finding planet...")
    game.refresh_player_info()
    game.leave_all()

    # locate uncaptured planet and join it
    planets = game.get_planets()
    planets = list(filter(lambda x: not x['state']['captured'], planets))

    game.log("Found %s uncaptured planets: %s", len(planets), list(map(lambda x: int(x['id']), planets)))

    planets = list(map(lambda x: game.get_planet(x['id']), planets))

    for planet in planets:
        planet['n_hard_zones'] = list(map(lambda x: x['difficulty'], filter(lambda y: not y['captured'], planet['zones']))).count(3)

    hard_planets = list(filter(lambda x: x['n_hard_zones'] > 0, planets))
    if hard_planets:
        planets = hard_planets

    planets = sorted(planets, reverse=False, key=lambda x: x['n_hard_zones'])
#   planets = sorted(planets, reverse=False, key=lambda x: x['state']['current_players'])

    if not planets:
        LOG.error("No uncaptured planets left :(")
        raise SystemExit

    game.log("Joining planet %s..", planets[0]['id'])

    planet_id = planets[0]['id']
    game.join_planet(planet_id)
    deadline = time() + 60 * 30

    game.refresh_player_info()
    game.refresh_planet_info()

    # if join didnt work for retry
    if game.planet['id'] != planet_id:
        sleep(2)
        continue

    game.log("Planet name: {name} ({id})".format(id=game.planet['id'], **game.planet['state']))
    game.log("Current players: {current_players}".format(**game.planet['state']))
    game.log("Giveaway AppIDs: {giveaway_apps}".format(**game.planet))

    # zone
    game.log("Finding conflict zone...")

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
            game.pbar_refresh()

            if 'clan_info' not in game.player_info or game.player_info['clan_info']['accountid'] != 4777282:
                game.represent_clan(4777282)

            game.log("Fighting in zone %s (%s) for 2mins", zone_id, difficulty)
            game.join_zone(zone_id)
            game.refresh_player_info()

            try:
                for i in range(120 // 2):
                    sleep(2)

                    if i+1 % 10 == 0:
                        game.refresh_planet_info()

                    game.pbar_refresh()
            except KeyboardInterrupt:
                raise SystemExit

            score = 120 * (5 * (2**(difficulty - 1)))
            game.log("Submitting score of %s...", score)
            game.report_score(score)

            game.refresh_planet_info()
            game.refresh_player_info()

    game.log("Planet was captured/disappared or we timed out. Moving on...")
