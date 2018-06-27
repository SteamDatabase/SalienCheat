#!/usr/bin/env python
"""Plays SALIEN for you

pip install requests tqdm
"""

import os
import re
import sys
import json
from io import open
from time import sleep, time
from itertools import count
from datetime import datetime

import requests
from tqdm import tqdm

# determine input func
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

    if not token:
        token = _input("Login to steamcommunity.com\n"
                       "Visit https://steamcommunity.com/saliengame/gettoken\n"
                       "Copy the token value and paste here.\n"
                       "--\n"
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
    zone_capture_rate = 0
    colors = (
        ('^NOR', '\033[0m'),
        ('^GRN', '\033[0;32m'),
        ('^YEL', '\033[0;33m'),
        ('^RED', '\033[0;31m'),
        ('^GRY', '\033[0;36m'),
        )

    def __init__(self, access_token):
        super(Saliens, self).__init__()
        self.access_token = access_token
        self.headers['User-Agent'] = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                                      ' (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36')
        self.headers['Accept'] = '*/*'
        self.headers['Origin'] = 'https://steamcommunity.com'
        self.headers['Referer'] = 'https://steamcommunity.com/saliengame/play'
        self.pbar_init()

    def spost(self, endpoint, form_fields=None, retry=False):
        if not form_fields:
            form_fields = {}
        form_fields['access_token'] = self.access_token

        data = None
        resp = None
        deadline = time() + 30

        while not data:
            try:
                resp = self.post(self.api_url % endpoint, data=form_fields)

                eresult = int(resp.headers.get('X-eresult', -1))

                if resp.status_code != 200:
                    raise Exception("HTTP %s EResult %s\n%s" % (resp.status_code, eresult, resp.text))

                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("NoJSON EResult %s" % eresult)
            except Exception as exp:
                self.log("^RED-- POST %-46s %s", endpoint, str(exp))

                if resp is None or resp.status_code >= 500:
                    sleep(2)
                    continue
            else:
                self.log("^GRY   POST %-46s HTTP %s EResult %s", endpoint, resp.status_code, eresult)

                if eresult == 93 and time() < deadline:
                    sleep(3)
                    continue

                data = rdata['response']

            if not retry:
                break

            if not data:
                sleep(1)

        return data

    def sget(self, endpoint, query_params=None, retry=False, timeout=15):
        data = None
        resp = None

        while not data:
            try:
                resp = self.get(self.api_url % endpoint, params=query_params, timeout=timeout)

                eresult = resp.headers.get('X-eresult', -1)
                if resp.status_code != 200:
                    raise Exception("HTTP %s EResult %s\n%s" % (resp.status_code, eresult, resp.text))

                rdata = resp.json()
                if 'response' not in rdata:
                    raise Exception("NoJSON EResult %s" % eresult)
            except Exception as exp:
                self.log("^RED-- GET  %-46s %s", endpoint, str(exp))

                if (resp is None and retry) or (resp and resp.status_code >= 500):
                    sleep(2)
                    continue
            else:
                self.log("^GRY   GET  %-46s HTTP %s EResult %s", endpoint, resp.status_code, eresult)
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

    def refresh_planet_info(self, retry=True, timeout=15):
        if 'active_planet' in self.player_info:
            planet = self.get_planet(self.player_info['active_planet'], retry=retry, timeout=timeout)

            if planet is not None:
                self.planet = planet
        else:
            self.planet = {}

        self.pbar_refresh()
        return self.planet

    def get_planet(self, pid, retry=True, timeout=15):
        data = self.sget('ITerritoryControlMinigameService/GetPlanet',
                         {'id': pid, '_': int(time())},
                         retry=retry,
                         timeout=timeout,
                         )
        if data is None:
            return
        else:
            planet = data.get('planets', [{}])[0]

        if planet:
            planet['easy_zones'] = sorted((z for z in planet['zones']
                                           if (not z['captured']
                                               and z['difficulty'] == 1)),
                                          reverse=True,
                                          key=lambda x: x['zone_position'])

            planet['medium_zones'] = sorted((z for z in planet['zones']
                                             if (not z['captured']
                                                 and z['difficulty'] == 2)),
#                                                and z.get('capture_progress', 0) < 0.90)),
                                            reverse=True,
                                            key=lambda x: x['zone_position'])

            planet['hard_zones'] = sorted((z for z in planet['zones']
                                           if (not z['captured']
                                               and z['difficulty'] == 3)),
#                                              and z.get('capture_progress', 0) < 0.95)),
                                          reverse=True,
                                          key=lambda x: x['zone_position'])
            planet['boss_zones'] = sorted((z for z in planet['zones']
                                           if not z['captured'] and z['type'] == 4),
                                          reverse=True,
                                          key=lambda x: x['zone_position'])

            # Example ordering (easy/med/hard):
            # 20/5/1 > 20/5/5 > 20/1/0 > 1/20/0
            # This should result in prefering planets that are nearing completion, but
            # still prioritize ones that have high difficulty zone to maximize score gain
            sort_key = 0

            if len(planet['easy_zones']):
                sort_key += 99 - len(planet['easy_zones'])
            if len(planet['medium_zones']):
                sort_key += 10**2 * (99 - len(planet['medium_zones']))
            if len(planet['hard_zones']):
                sort_key += 10**4 * (99 - len(planet['hard_zones']))
            if len(planet['boss_zones']):
                sort_key += 10**6 * (99 - len(planet['boss_zones']))

            planet['sort_key'] = sort_key

        return planet

    def get_planets(self):
        return self.sget('ITerritoryControlMinigameService/GetPlanets',
                         {'active_only': 1},
                         retry=True,
                         ).get('planets', [])

    def get_uncaptured_planets(self):
        planets = self.get_planets()
        return sorted((game.get_planet(p['id']) for p in planets if not p['state']['captured']),
                      reverse=True,
                      key=lambda x: x['sort_key'],
                      )

    def represent_clan(self, clan_id):
        return self.spost('ITerritoryControlMinigameService/RepresentClan', {'clanid': clan_id})

    def report_score(self, score):
        return self.spost('ITerritoryControlMinigameService/ReportScore', {'score': score})

    def join_planet(self, pid):
        return self.spost('ITerritoryControlMinigameService/JoinPlanet', {'id': pid})

    def join_zone(self, pos):
        self.zone_id = pos
        return self.spost('ITerritoryControlMinigameService/JoinZone', {'zone_position': pos})

    def leave_zone(self, clear_rate=True):
        if 'active_zone_game' in self.player_info:
            self.spost('IMiniGameService/LeaveGame',
                       {'gameid': self.player_info['active_zone_game']},
                       retry=False)
        self.zone_id = None

        if clear_rate:
            self.zone_capture_rate = 0

    def leave_planet(self):
        if 'active_planet' in self.player_info:
            self.spost('IMiniGameService/LeaveGame',
                       {'gameid': self.player_info['active_planet']},
                       retry=False)

    def pbar_init(self):
        self.level_pbar = tqdm(ascii=True,
                               dynamic_ncols=True,
                               desc="Player Level",
                               total=0,
                               initial=0,
                               bar_format='{desc:<18} {percentage:3.0f}% |{bar}| {n_fmt}/{total_fmt} | {remaining:>9}',
                               )
        self.level_pbar.rate_psec = 0
        self.planet_pbar = tqdm(ascii=True,
                                dynamic_ncols=True,
                                desc="Planet progress",
                                total=0,
                                initial=0,
                                smoothing=0.3,
                                bar_format='{desc:<18} {percentage:3.0f}%% |{bar}|%s {remaining:>9}',
                                )
        self.planet_pbar.bar_format_tmpl = self.planet_pbar.bar_format
        self.planet_pbar.rate_psec = 0
        self.zone_pbar = tqdm(ascii=True,
                              dynamic_ncols=True,
                              desc="Zone progress",
                              total=0,
                              initial=0,
                              smoothing=0.3,
                              bar_format='{desc:<18} {percentage:3.0f}%% |{bar}|%s {remaining:>9}',
                              )
        self.zone_pbar.bar_format_tmpl = self.zone_pbar.bar_format
        self.zone_pbar.rate_psec = 0

    def end(self):
        self.level_pbar.close()
        self.planet_pbar.close()
        self.zone_pbar.close()

    def pbar_refresh(self):
        dmap = {
            1: 'Easy',
            2: 'Medium',
            3: 'Hard',
            }

        if not self.player_info:
            return

        player_info = self.player_info

        def avg_time(pbar, n):
            curr_t = pbar._time()
            rate_psec = 0

            if pbar.n == 0:
                pbar.avg_time = 0
                pbar.last_print_t = curr_t
            else:
                delta_n = n - pbar.n
                delta_t = curr_t - pbar.last_print_t

                if delta_n and delta_t:
                    curr_avg_time = delta_t / delta_n

                    if (delta_n / delta_t) >= 0:
                        rate_psec = (pbar.smoothing * (delta_n / delta_t)
                                     + (1-pbar.smoothing) * pbar.rate_psec)

                    pbar.avg_time = (pbar.smoothing * curr_avg_time
                                     + (1-pbar.smoothing) * (pbar.avg_time
                                                             if pbar.avg_time
                                                             else curr_avg_time))
                    pbar.last_print_t = curr_t

            if pbar.n and rate_psec and getattr(pbar, 'bar_format_tmpl', None):
                pbar.rate_psec = rate_psec
                rate = ' +{:.2f}% |'.format(rate_psec * 110 * 100)
                pbar.bar_format = pbar.bar_format_tmpl % rate

            pbar.n = n

        # level progress bar
        self.level_pbar.desc = "Level {level}".format(**player_info)
        self.level_pbar.total = int(player_info['next_level_score'])
        avg_time(self.level_pbar, int(player_info['score']))
        self.level_pbar.refresh()

        # planet capture progress bar
        if self.planet:
            planet = self.planet
            state = planet['state']
            planet_progress = (1.0 if state['captured']
                               else state.get('capture_progress', 0))
            self.planet_pbar.desc = "Planet #{}".format(planet['id'])
            self.planet_pbar.total = 1.0
            avg_time(self.planet_pbar, planet_progress)
        else:
            self.planet_pbar.desc = "Planet"
            self.planet_pbar.n = 0
            self.planet_pbar.total = 0
            self.planet_pbar.rate_psec = 0
            self.planet_pbar.last_print_t = time()
            self.planet_pbar.bar_format = self.planet_pbar.bar_format_tmpl % ''

        self.planet_pbar.refresh()

        # zone capture progress bar
        if self.planet and self.zone_id is not None:
            zone = self.planet['zones'][self.zone_id]
            zone_progress = (1.0 if zone['captured']
                             else zone.get('capture_progress', 0))
            self.zone_pbar.desc = "Zone #{} - {}".format(zone['zone_position'],
                                                         dmap.get(zone['difficulty'],
                                                                  zone['difficulty']))
            self.zone_pbar.total = 1.0
            avg_time(self.zone_pbar, zone_progress)
            self.zone_capture_rate = self.zone_pbar.rate_psec * 110
        else:
            self.zone_pbar.desc = "Zone"
            self.zone_pbar.n = 0
            self.zone_pbar.total = 0
            self.zone_pbar.last_print_t = time()
            self.zone_pbar.bar_format = self.zone_pbar.bar_format_tmpl % ''

        self.zone_pbar.refresh()

    _plog_c = 0
    _plog_text = None

    def log(self, text, *args):
        text = text % args
        text += "^NOR"

        for k, v in self.colors:
            text = text.replace(k, v)

        max_collapsed = 10

        if text == self._plog_text:
            self._plog_c += 1

        if ((text == self._plog_text and self._plog_c >= max_collapsed)
           or (text != self._plog_text and self._plog_c > 0)):
                ptext = self._plog_text + " x" + str(self._plog_c)
                self.level_pbar.write(datetime.now().strftime("%H:%M:%S") + " | " + ptext)
                self._plog_c = 0

        if text != self._plog_text:
            self.level_pbar.write(datetime.now().strftime("%H:%M:%S") + " | " + text)
            self._plog_c = 0

        self._plog_text = text
        self.pbar_refresh()

    def print_planet(self, planet):
        planet_id = planet['id']
        planet_name = planet['state']['name'].split('Planet', 1)[1].replace('_', ' ')
        curr_players = planet['state'].get('current_players', 0)
        n_boss = len(planet['boss_zones'])
        n_hard = len(planet['hard_zones'])
        n_med = len(planet['medium_zones'])
        n_easy = len(planet['easy_zones'])

        status = ('yes' if planet['state']['captured']
                  else "{:>5.2f}%%".format(planet['state'].get('capture_progress', 0) * 100))

        game.log("^YEL>>^NOR Planet ^GRN#{:>3}^NOR - ^YEL{:>2}^NOR / ^YEL{:>2}^NOR / ^YEL{:>2}^NOR "
                 "/ ^YEL{:>2}^NOR B/H/M/E - Captured: ^YEL{}^NOR Players: ^YEL{:>7,}^NOR ^GRN({})"
                 "".format(planet_id,
                           n_boss, n_hard, n_med, n_easy,
                           status,
                           curr_players,
                           planet_name,
                           )
                 )


# ----- MAIN -------


access_token = get_access_token()
game = Saliens(access_token)

# display current stats
game.log("^GRN++^NOR Getting player info...")
game.refresh_player_info()

# fair play
game.log("^GRN-- Welcome to SalienCheat for SteamDB")

if 'clan_info' not in game.player_info:
    game.log("^GRN-- You are currently not representing any clan, so you are now part of SteamDB")
    game.log("^GRN-- Make sure to join ^YELhttps://steamcommunity.com/groups/steamdb^GRN on Steam")
    game.represent_clan(4777282)

elif game.player_info['clan_info']['accountid'] != 4777282:
    game.log("^GRN-- If you want to support us, join our group")
    game.log("^GRN-- ^YELhttps://steamcommunity.com/groups/steamdb")
    game.log("^GRN-- and set us as your clan on")
    game.log("^GRN-- ^YELhttps://steamcommunity.com/saliengame/play/")
    game.log("^GRN-- Happy farming!")

game.log("^GRN++^NOR Scanning for planets...")
game.refresh_planet_info()

# show planet info
planets = game.get_uncaptured_planets()
game.log("^GRN++^NOR Found %s uncaptured planets: %s",
         len(planets),
         [int(x['id']) for x in planets])

for planet in planets:
    game.print_planet(planet)

# join battle
try:
    while True:
        if not planets:
            game.log("^GRN++ No planets left. Hmm? Gonna keep checkin...")
            sleep(10)
            planets = game.get_uncaptured_planets()
            continue

        planet_id = planets[0]['id']
        # ensures we are not stuck in a zone
        game.leave_zone()

        # determine which planet to join
        if not game.planet or game.planet['id'] != planet_id:
            game.log("^GRN++^NOR Joining toughest planet ^GRN%s^NOR..", planets[0]['id'])

            # join planet and confirm it was success, otherwise retry
            for i in range(3):
                game.join_planet(planet_id)
                sleep(1)
                game.refresh_player_info()

                if game.player_info.get('active_planet') == planet_id:
                    break

                game.log("^RED-- Failed to join planet. Retrying...")
                game.leave_planet()

            if i >= 2 and game.player_info.get('active_planet') != planet_id:
                continue

        else:
            game.log("^GRN++^NOR Remaining on current planet")

        game.refresh_planet_info()

        # show planet info
        giveaway_appds = game.planet.get('giveaway_apps', [])
        top_clans = [c['clan_info']['url'] for c in game.planet.get('top_clans', []) if 'url' in c.get('clan_info', {})][:5]

        game.print_planet(game.planet)
        game.log("^YEL>>^NOR Giveaway AppIDs: %s", giveaway_appds)
        if top_clans:
            game.log("^YEL>>^NOR Top clans: %s", ', '.join(top_clans))
        if 'clan_info' not in game.player_info or game.player_info['clan_info']['accountid'] != 0O022162502:
            game.log("^YEL>>^NOR Join SteamDB: https://steamcommunity.com/groups/SteamDB")

        # selecting zone
        while game.planet and game.planet['id'] == planets[0]['id']:
            # retry represent on free agents
            if 'clan_info' not in game.player_info:
                game.represent_clan(4777282)

            zones = (game.planet['boss_zones']
                     + game.planet['hard_zones']
                     + game.planet['medium_zones']
                     + game.planet['easy_zones'])

#           # filter out zones that are very close to getting captured
#           while (zones
#                  and zones[0]['difficulty'] > 1
#                  and (zones[0].get('capture_progress', 0)
#                       + min(game.zone_capture_rate, 0.2) >= 1)):
#               zones.pop(0)

            if not zones:
                game.log("^GRN++^NOR No open zones left on planet")
                game.player_info.pop('active_planet')
                break

            # choose highest priority zone
            zone_id = zones[0]['zone_position']
            difficulty = zones[0]['difficulty']

            deadline = time() + 60 * 10  # rescan planets every 10min

            dmap = {
                1: 'easy',
                2: 'medium',
                3: 'hard',
                }

            game.log("^GRN++^NOR Selecting %szone ^YEL%s^NOR (%s)....",
                     '^REDboss^NOR ' if game.planet['zones'][zone_id]['type'] == 4 else '',
                     zone_id,
                     dmap.get(difficulty, difficulty),
                     )

            # fight in the zone
            while (game.planet
                   and time() < deadline
                   and not game.planet['zones'][zone_id]['captured']
                   ):

#               # skip if zone is likely to get captured while we wait, except easy zones
#               if (game.planet['zones'][zone_id]['difficulty'] > 1
#                  and (game.planet['zones'][zone_id].get('capture_progress', 0)
#                       + min(game.zone_capture_rate, 0.2) >= 1)):
#                   game.log("^GRN++^NOR Zone likely to complete early. Moving on...")
#                   break

                game.log("^GRN++^NOR Fighting in ^YEL%szone^NOR %s (^YEL%s^NOR) for ^YEL110sec",
                         'boss ' if game.planet['zones'][zone_id]['type'] == 4 else '',
                         zone_id,
                         dmap.get(difficulty, difficulty))

                game.join_zone(zone_id)
                stoptime = time() + 109.6
                game.refresh_player_info()

                # refresh progress bars while in battle
                for i in count(start=1):
                    # stop when battle is finished or zone was captured
                    if time() >= stoptime:  # or game.planet['zones'][zone_id]['captured']:
                        break

                    sleep(1)

                    if (i % 11) == 0:
                        game.refresh_planet_info(retry=False, timeout=max(0, stoptime - time()))
                        game.pbar_refresh()

#               if game.planet['zones'][zone_id]['captured']:
#                   game.log("^RED-- Zone was captured before we could submit score")
#               else:
                score = 120 * (5 * (2**(difficulty - 1)))
                game.log("^GRN++^NOR Submitting score of ^GRN%s^NOR...", score)
                game.report_score(score)
                game.refresh_player_info()
                game.refresh_planet_info()

                # incase user gets stuck
                game.leave_zone(False)

            # Rescan planets after zone is finished
            game.log("^GRN++^NOR Rescanning planets...")
            planets = game.get_uncaptured_planets()

            for planet in planets:
                game.print_planet(planet)

            game.refresh_planet_info()

except KeyboardInterrupt:
    game.close()
    sys.exit()

# end game
game.log("^GRN++^NOR No uncaptured planets left. We done!")
game.close()
