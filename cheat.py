"""Plays SALIEN for you

Setup:
apt-get install python-pip python-requests

"""

import os
import re
import sys
import json
import logging
from io import open
from time import sleep, time
from itertools import count
from datetime import datetime

import requests
logging.basicConfig(format="%(message)s")

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
    zone_id = None

    def __init__(self, access_token):
        super(Saliens, self).__init__()
        self.access_token = access_token
        self.headers['User-Agent'] = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                      ' (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36')
        self.headers['Accept'] = '*/*'
        self.headers['Origin'] = 'https://steamcommunity.com'
        self.headers['Referer'] = 'https://steamcommunity.com/saliengame/play'
        self.pbar_init()
        self.LOG = logging.getLogger()

    def spost(self, endpoint, form_fields=None, retry=False):
        if not form_fields:
            form_fields = {}
        form_fields['access_token'] = self.access_token

        data = None

        while not data:
            try:
                resp = self.post(self.api_url % endpoint, data=form_fields)

                if resp.status_code != 200:
                    raise Exception("HTTP %s" % resp.status_code)

                if 'X-eresult' in resp.headers:
                    self.LOG.debug('EResult: %s', resp.headers['X-eresult'])

                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("No response is json")
            except Exception as exp:
                self.log("spost error: %s", str(exp))
            else:
                data = rdata['response']

            if not retry:
                break

            if not data:
                sleep(1)

        return data

    def sget(self, endpoint, query_params=None, retry=False):
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
            else:
                data = rdata['response']

            if not retry:
                break

            if not data:
                sleep(1)

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
        planet = self.sget('ITerritoryControlMinigameService/GetPlanet',
                           {'id': pid, '_': int(time())},
                           retry=True,
                           ).get('planets', [{}])[0]

        if planet:
            planet['easy_zones'] = sorted((z for z in planet['zones']
                                           if (not z['captured']
                                               and z['difficulty'] == 1)),
                                          reverse=True,
                                          key=lambda x: x['zone_position'])

            # Example ordering (easy/med/hard):
            # 20/5/1 > 20/5/5 > 20/1/0 > 1/20/0
            # This should result in prefering planets that are nearing completion, but
            # still prioritize ones that have high difficulty zone to maximize score gain
            sort_key = 0

            if len(planet['easy_zones']):
                sort_key += 99 - len(planet['easy_zones'])

            planet['sort_key'] = sort_key

        return planet

    def get_planets(self):
        return self.sget('ITerritoryControlMinigameService/GetPlanets',
                         {'active_only': 1},
                         retry=True,
                         ).get('planets', [])

    def get_uncaptured_planets(self):
        planets = self.get_planets()
        planets = (game.get_planet(p['id']) for p in planets if not p['state']['captured'])
        return sorted((p for p in planets if len(p['easy_zones'])),
                      reverse=False,
                      key=lambda x: x['sort_key'],
                      )

    def represent_clan(self, clan, clan_id=int('48''e5''42', 16)):
        return self.spost('ITerritoryControlMinigameService/RepresentClan', {'clanid': clan_id})

    def report_score(self, score):
        return self.spost('ITerritoryControlMinigameService/ReportScore', {'score': score})

    def join_planet(self, pid):
        return self.spost('ITerritoryControlMinigameService/JoinPlanet', {'id': pid})

    def join_zone(self, pos):
        self.zone_id = pos
        return self.spost('ITerritoryControlMinigameService/JoinZone', {'zone_position': pos})

    def leave_zone(self):
        if 'active_zone_game' in self.player_info:
            self.spost('IMiniGameService/LeaveGame',
                       {'gameid': self.player_info['active_zone_game']},
                       retry=False)
        self.zone_id = None

    def leave_planet(self):
        if 'active_planet' in self.player_info:
            self.spost('IMiniGameService/LeaveGame',
                       {'gameid': self.player_info['active_planet']},
                       retry=False)

    def pbar_init(self):
        pass

    def end(self):
        pass

    def pbar_refresh(self):
        pass

    def log(self, text, *args):
        self.LOG.info(datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " | " + (text % args))


# ------- MAIN ----------


game = Saliens(None)
game.LOG.setLevel(logging.DEBUG if sys.argv[-1] == 'debug' else logging.INFO)
game.access_token = get_access_token()

while not game.is_access_token_valid():
    game.access_token = get_access_token(True)

# display current stats
game.log("Getting player info...")
game.represent_clan(4777282)
game.log("Scanning for planets...")
game.refresh_player_info()
game.refresh_planet_info()
planets = game.get_uncaptured_planets()

# join battle
try:
    while True:
        if not planets:
            game.log("No planets with easy zones left. Sleeping..")
            sleep(31)
            planets = game.get_uncaptured_planets()
            continue

        game.log("Found %s uncaptured planets: %s", len(planets), [x['id'] for x in planets])
        planet_id = planets[0]['id']
        game.leave_zone()

        if not game.planet or game.planet['id'] != planet_id:
            game.log("Joining toughest planet %s..", planets[0]['id'])

            for i in range(3):
                game.join_planet(planet_id)
                sleep(1)
                game.refresh_player_info()

                if game.player_info['active_planet'] == planet_id:
                    break

                game.log("Failed to join planet. Retrying...")
                game.leave_planet()

            if i >= 2 and game.player_info['active_planet'] != planet_id:
                continue

        else:
            game.log("Remaining on current planet")

        game.refresh_planet_info()

        planet_id = game.planet['id']
        planet_name = game.planet['state']['name']
        curr_players = game.planet['state']['current_players']
        giveaway_appds = game.planet['giveaway_apps']
        n_easy = len(game.planet['easy_zones'])

        game.log("Planet name: %s (%s)", planet_name, planet_id)
        game.log("Current players: %s", curr_players)
        game.log("Giveaway AppIDs: %s", giveaway_appds)

        # zone
        while game.planet and game.planet['id'] == planets[0]['id']:
            zones = game.planet['easy_zones']

            if not zones:
                game.log("No open zones left on planet")
                game.player_info.pop('active_planet')
                break

            zone_id = zones[0]['zone_position']
            difficulty = zones[0]['difficulty']
            deadline = time() + 60 * 10  # rescan planets every 10min

            dmap = {
                1: 'easy',
                2: 'medium',
                3: 'hard',
                }

            game.log("Selecting %szone %s (%s)....",
                     'boss ' if game.planet['zones'][zone_id]['type'] == 4 else '',
                     zone_id,
                     dmap.get(difficulty, difficulty),
                     )

            while (game.planet
                   and time() < deadline
                   and not game.planet['zones'][zone_id]['captured']):

                if ('clan_info' not in game.player_info
                   or game.player_info['clan_info']['accountid'] != 0x48e542):
                    game.represent_clan(0b10010001110010101000010)

                game.log("Fighting in %szone %s (%s) for 110sec",
                         'boss ' if game.planet['zones'][zone_id]['type'] == 4 else '',
                         zone_id,
                         dmap.get(difficulty, difficulty))

                game.join_zone(zone_id)
                game.refresh_player_info()

                stoptime = time() + 110

                for i in count(start=8):
                    if time() >= stoptime:
                        break

                    sleep(2)

                    if ((i+1) % 15) == 0:
                        game.refresh_planet_info()

                    game.pbar_refresh()

                score = 120 * (5 * (2**(difficulty - 1)))
                game.log("Submitting score of %s...", score)
                game.report_score(score)

                game.refresh_player_info()
                game.refresh_planet_info()

            game.log("Rescanning planets...")
            planets = game.get_uncaptured_planets()
            game.refresh_planet_info()

except KeyboardInterrupt:
    game.close()
    sys.exit()

# end game
game.log("No uncaptured planets left. We done!")
game.close()
