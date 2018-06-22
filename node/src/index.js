/**
 * MIT License
 *
 * Copyright (C) 2018 Alex Gabites
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

const delay = require('delay');
const fetch = require('fetch-retry');

const baseUrl = 'https://community.steam-api.com/';

const getUrl = (method, params = '') => `${baseUrl}/${method}${params ? '/?' : ''}${params}`;

const getOptions = (options = {}) => {
  return {
    retries: 3,
    retryDelay: 1000,
    headers: {
      'Accept': '*/*',
      'Origin': 'https://steamcommunity.com',
      'Referer': 'https://steamcommunity.com/saliengame/play/',
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/67.0.3396.87 Safari/537.36',
    },
    ...options,
  };
};

const getScoreForZone = (zone) => {
  let score = 0;

  switch (zone.difficulty) {
    case 1: score = 5; break;
    case 2: score = 10; break;
    case 3: score = 20; break;
  }

  return score * 120;
};

async function getFirstAvailablePlanetId() {
  console.log('Attempting to get first open planet...');

  const request = await fetch(getUrl('ITerritoryControlMinigameService/GetPlanets/v0001', 'active_only=1'), getOptions());
  const response = await request.json();

  if (!response || !response.response.planets) {
    console.log('Didn\'t find any planets.');

    return null;
  }

  const firstOpen = response.response.planets.filter(planet => !planet.state.captured)[0];

  console.log('First open planet id:', firstOpen.id);

  return firstOpen.id;
};

async function getPlayerInfo(token) {
  console.log('Getting player info...');

  const request = await fetch(getUrl('ITerritoryControlMinigameService/GetPlayerInfo/v0001', `access_token=${token}`), getOptions({ method: 'POST' }));
  const response = await request.json();

  if (!response || !response.response) {
    console.log('Didn\'t get any player info.');

    return null;
  }

  console.log('Got player info!');

  return response.response;
};

async function leaveCurrentGame(token, leaveCurrentPlanet) {
  let playerInfo = null;

  while (!playerInfo) {
    playerInfo = await getPlayerInfo(token);
  }

  // Please do not change our clanid if you are going to use this script
  // If you want to cheat for your own group, come up with up with your own approach, thank you
  if (!playerInfo['clan_info']['accountid'] || playerInfo['clan_info']['accountid'] != 4777282) {
    await fetch(getUrl('ITerritoryControlMinigameService/RepresentClan/v0001', `clanid=4777282&access_token=${token}`), getOptions({ method: 'POST' }));
  }

  if (playerInfo['active_zone_game']) {
    console.log('Leaving `active_zone_game`...');

    await fetch(getUrl('IMiniGameService/LeaveGame/v0001', `access_token=${token}&gameid=${playerInfo['active_zone_game']}`), getOptions({ method: 'POST' }));

    console.log('Success!');
  }

  if (!playerInfo['active_planet']) {
    return 0;
  }

  if (leaveCurrentPlanet) {
    console.log('Leaving `active_planet`...');

    await fetch(getUrl('IMiniGameService/LeaveGame/v0001', `access_token=${token}&gameid=${playerInfo['active_planet']}`), getOptions({ method: 'POST' }));

    console.log('Success!');
  }

  return playerInfo['active_planet'];
}

async function joinPlanet(token, planetId) {
  console.log('Attempting to join planet id:', planetId);

  await fetch(getUrl('ITerritoryControlMinigameService/JoinPlanet/v0001', `id=${planetId}&access_token=${token}`), getOptions({ method: 'POST' }));

  console.log('Joined!');

  return;
}

async function getFirstAvailableZone(planetId) {
  console.log(`Requesting zones for planet ${planetId}...`);

  const request = await fetch(getUrl('ITerritoryControlMinigameService/GetPlanet/v0001', `id=${planetId}`), getOptions());
  const response = await request.json();

  if (!response.response.planets[0].zones) {
    return null;
  }

  let zones = response.response.planets[0].zones;
  let cleanZones = [];
  let bossZone = null;

  zones.some(zone => {
    if (zone.captured) {
      return;
    }

    if (zone.type === 4) {
      bossZone = zone;
    } else if (zone.type != 3) {
      console.log('Unknown zone type:', zone.type);
    }

    if (zone['capture_progress'] < 0.95) {
      cleanZones.push(zone);
    }
  })

  if (bossZone) {
    return bossZone;
  }

  if (cleanZones.length < 0) {
    return null;
  }

  cleanZones.sort((a, b) => {
    if (a.difficulty === b.difficulty) {
      return b['zone_position'] - a['zone_position'];
    }

    return b.difficulty - a.difficulty;
  });

  return cleanZones[0];
}

async function joinZone(token, position) {
  console.log('Attempting to join zone position:', position);

  const request = await fetch(getUrl('ITerritoryControlMinigameService/JoinZone/v0001', `zone_position=${position}&access_token=${token}`), getOptions({ method: 'POST' }));
  const response = await request.json();

  if (!response || !response.response['zone_info']) {
    console.log('Failed to join a zone.');

    return null;
  }

  console.log('Got player info!');

  return response.response;
}

async function reportScore(token, score) {
  console.log('Attempting to send score...');

  const request = await fetch(getUrl('ITerritoryControlMinigameService/ReportScore/v0001', `access_token=${token}&score=${score}&language=english`), getOptions({ method: 'POST' }));
  const response = await request.json();

  if (response.response['new_score']) {
    const data = response.response;

    console.log(`Score: ${data['old_score']} => ${data['new_score']} (next level: ${data['next_level_score']}) - Current level: ${data['new_level']}`);
  }

  return;
}

class SalienCheat {
  constructor({ token }) {
    this.token = token;
    this.currentPlanetId = null;
  }

  async run() {
    console.log('This script will not work until you have joined our group:');
    console.log('https://steamcommunity.com/groups/SteamDB');

    while (!this.currentPlanetId) {
      this.currentPlanetId = await getFirstAvailablePlanetId();

      if (!this.currentPlanetId) {
        console.log('Trying to get another PlanetId in 5 seconds...');

        await delay(5000);
      }
    }

    await leaveCurrentGame(this.token, true);

    await joinPlanet(this.token, this.currentPlanetId);

    this.currentPlanetId = await leaveCurrentGame(this.token, false);

    while (true) {
      let zone = null;
      let joinedZone = null;

      while (!zone) {
        zone = await getFirstAvailableZone(this.currentPlanetId);

        if (!zone) {
          console.log('Trying to get another ZoneId in 5 seconds...');

          await delay(5000);
        }
      }

      while (!joinedZone) {
        console.log('Attempting to join zone:', zone['zone_position']);

        joinedZone = await joinZone(this.token, zone['zone_position']);

        if (!joinedZone) {
          console.log('Trying to get another Zone Position in 15 seconds...');

          await delay(15000);
        }
      }

      console.log(`Joined zone ${zone['zone_position']} - Captured: ${(zone['capture_progress'] * 100).toFixed(2)}% - Difficulty ${zone.difficulty}`);

      console.log('Waiting 120 seconds for game to end...');

      await delay(120000);

      console.log('Game complete!');

      await reportScore(this.token, getScoreForZone(zone));
    }
  };
}

module.exports = SalienCheat;
