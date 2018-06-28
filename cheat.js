// Async sleep
function asleep(mls) {
    return new Promise((g) => {
        setTimeout(() => {
            g(true);
        }, mls || 1)
    })
}

function parse_numbers(text) {
    if (text === null) return text;
    let return_this = text.match(/\d+/g);
    return return_this === null ? return_this : return_this[0];
}

const request = ((_http_proxy = null) => {
    let _request_module = require("request");
    if (typeof _http_proxy === "string" && _http_proxy.indexOf("http://") === 0) _request_module = _request_module.defaults({'proxy': _http_proxy})
    
    const querystring = require('querystring');
    
    const lexicon = {
        list: {
            "GetPlanets": {
                type: "get",
                _family_id: 0,
                need_token: false,
            },
            "GetPlanet": {
                type: "get",
                _family_id: 0,
                need_token: false,
                
            },
            "GetPlayerInfo": {
                type: "post",
                _family_id: 0,
                need_token: true,
                
            },
            "LeaveGame": {
                type: "post",
                _family_id: 1,
                need_token: true,
                
            },
            "JoinPlanet": {
                type: "post",
                _family_id: 0,
                need_token: true,
                
            },
            "JoinZone": {
                type: "post",
                _family_id: 0,
                need_token: true,
                
            },
            "ReportScore": {
                type: "post",
                need_token: true,
                _family_id: 0,
                
            },
            "RepresentClan": {
                type: "post",
                need_token: true,
                _family_id: 0,
                
            }
        },
        family: [
            {
                endpoint: "ITerritoryControlMinigameService",
                version: "v0001",
            },
            {
                endpoint: "IMiniGameService",
                version: "v0001",
            }
        ]
    };
    const send = async (_method_name = null, _data_from_url = {}, _data_to_body = undefined) => {
        if (_data_to_body === null) _data_to_body = "";
        
        const method = lexicon.list[_method_name];
        if (method === undefined) throw `No template for method "${_method_name}"`;
        if (method.parse !== "function") method.parse = () => Object.assign({});
        method.family = lexicon.family[method._family_id];
        
        const params_to_request = {
            url: {
                options: {language: "english"},
                link: {
                    basic: undefined,
                    query: undefined,
                    full: undefined,
                }
            },
            value: JSON.stringify(_data_to_body)
        };
        params_to_request.url.options = Object.assign(params_to_request.url.options, _data_from_url);
        if (method.need_token === true) params_to_request.url.options.access_token = player.token;
        
        params_to_request.url.link.basic = "https://community.steam-api.com/" + method.family.endpoint + "/" + _method_name + "/" + method.family.version + "/";
        params_to_request.url.link.query = querystring.encode(params_to_request.url.options);
        params_to_request.url.link.full = params_to_request.url.link.basic + "?" + params_to_request.url.link.query;
        
        //console.log("params_to_request:: update options", params_to_request);
        
        
        let collected_answer = {
            success: false,
            code: null,
            msg: null,
            data: {}
        };
        
        let resp_from_steam = await new Promise((g) => {
            _request_module({url: params_to_request.url.link.full, headers: {}, body: params_to_request.value, method: method.type}, (err, document) => g(document))
        });
        
        //console.log("resp_from_steam::", resp_from_steam);
        resp_from_steam.json_body = (() => {
            let res = {};
            try {
                res = JSON.parse(resp_from_steam.body).response;
            } catch (e) {
            
            }
            return res;
        })();
        
        collected_answer.code = +resp_from_steam.headers["x-eresult"] || null;
        if (collected_answer.code === 1) collected_answer.success = true;
        
        collected_answer.msg = resp_from_steam.headers["x-error_message"] || null;
        collected_answer.data = Object.assign({}, resp_from_steam.json_body, method.parse(collected_answer.msg));
        
        return collected_answer;
    };
    return {
        send
    }
})(process.argv[3]);

const player = ((_token = null) => {
    if (_token === null || _token.length !== 32) throw "Failed to find your token";
    const _default_info = {
        playground: {
            planet: {
                id: null,
                stay_duration: null,
                timestamp: null,
            },
            battle: {
                id: null,
                cell: null,
                stay_duration: null,
                timestamp: null,
            },
        },
        level: {
            id: null,
            score: {
                current: null,
                next: null
            }
        },
        clan: {
            account_id: null,
            title: null,
            avatar: null,
            system_name: null,
        }
    };
    let token = _token;
    const default_clan_id = 4777282;
    let last_info = Object.assign({}, _default_info);
    const request_info = async () => {
        let resp_from_steam = await request.send("GetPlayerInfo");
        if (resp_from_steam.success !== true) throw resp_from_steam;
        
        let player_origin_object = Object.assign({clan_info: {}}, resp_from_steam.data);
        //console.log("request_info::player_origin_object::", player_origin_object);
        
        const returning_info = Object.assign({}, _default_info);
        
        returning_info.playground.planet.id = Number.isSafeInteger(+player_origin_object["active_planet"]) ? +player_origin_object["active_planet"] : null;
        returning_info.playground.planet.stay_duration = Number.isSafeInteger(+player_origin_object["time_on_planet"]) ? Math.floor(player_origin_object["time_on_planet"] * 1000) : null;
        returning_info.playground.planet.timestamp = Date.now();
        
        returning_info.playground.battle.id = Number.isSafeInteger(+player_origin_object["active_zone_game"]) ? +player_origin_object["active_zone_game"] : null;
        returning_info.playground.battle.stay_duration = Number.isSafeInteger(+player_origin_object["time_in_zone"]) ? Math.floor(player_origin_object["time_in_zone"] * 1000) : null;
        returning_info.playground.battle.cell = Number.isSafeInteger(+player_origin_object["active_zone_position"]) ? +player_origin_object["active_zone_position"] : null;
        returning_info.playground.battle.timestamp = Date.now();
        
        returning_info.level.id = Number.isSafeInteger(+player_origin_object["level"]) ? +player_origin_object["level"] : null;
        returning_info.level.score.current = Number.isSafeInteger(+player_origin_object["score"]) ? +player_origin_object["score"] : null;
        returning_info.level.score.next = Number.isSafeInteger(+player_origin_object["next_level_score"]) ? +player_origin_object["next_level_score"] : null;
        
        returning_info.clan.account_id = Number.isSafeInteger(+player_origin_object.clan_info["accountid"]) ? +player_origin_object.clan_info["accountid"] : null;
        returning_info.clan.system_name = player_origin_object.clan_info["url"] || null;
        returning_info.clan.title = player_origin_object.clan_info["name"] || null;
        returning_info.clan.avatar = player_origin_object.clan_info["avatar"] || null;
        
        //console.log("User info::", last_info);
        console.log(`USER:: LVL ${last_info.level.id}, EXP ${((last_info.level.score.current / last_info.level.score.next) * 100).toFixed(2)}%`);
        return last_info = returning_info;
    };
    
    const join_to_clan = async (clan_id = default_clan_id, force = false) => {
        if (last_info.clan.account_id !== null && force === false) {
            if (last_info.clan.account_id !== clan_id) console.log("If you want to support first author, join his group: https://steamcommunity.com/groups/steamdb");
            return true;
        }
        
        let resp_from_steam = await request.send("RepresentClan", {clanid: clan_id}); // steam does not notify about errors
        if (resp_from_steam.success === true) {
            console.log("You are currently not representing any clan, so you are now part of SteamDB\nMake sure to join https://steamcommunity.com/groups/steamdb on Steam")
            last_info.clan.account_id = clan_id;
        } else {
            console.log("Error with join to clan, but it's okay");
        }
        
        return resp_from_steam;
    };
    
    return {
        get token() {
            return token;
        },
        fetch_info: request_info,
        join_to_clan,
        get info() {
            return last_info;
        }
    }
})(process.argv[2]);

const score = (() => {
    const calc = difficulty => {
        switch (difficulty) {
            case 1:
                return 5;
            case 2:
                return 10;
            case 3:
                return 20;
            default:
                return 20;
        }
    };
    const round_duration = 120 * 1000;
    
    const save = async (difficulty) => {
        let result = calc(difficulty);
        if (Number.isSafeInteger(result) === false) throw "Invalid `score` number";
        
        const plus_score = result * Math.floor(round_duration / 1000);
        const resp_from_steam = await request.send("ReportScore", {score: plus_score});
        if (resp_from_steam.success === false) throw resp_from_steam;
        player.info.level.id = resp_from_steam.data["new_level"];
        player.info.level.id = Number.isSafeInteger(+resp_from_steam.data["new_level"]) ? +resp_from_steam.data["new_level"] : null;
        player.info.score.current = Number.isSafeInteger(+resp_from_steam.data["new_score"]) ? +resp_from_steam.data["new_score"] : null;
        
        return plus_score;
    };
    
    return {
        calc, save, round_duration
    }
})();

const battle = (() => {
    const leave = async (id = player.info.playground.battle.id) => {
        if (id === null) return true;
        
        await request.send("LeaveGame", {gameid: id}); // steam does not notify about errors
        player.info.playground.battle.id = null;
        player.info.playground.battle.stay_duration = null;
        player.info.playground.battle.cell = null;
        player.info.playground.battle.timestamp = Date.now();
        
        return true;
    };
    const join = async (cell = player.info.playground.battle.cell, force = false) => {
        if (cell === null && force === false) throw "Empty zone `id`, cant start game";
        
        const resp_from_steam = await request.send("JoinZone", {zone_position: cell});
        if (resp_from_steam.success !== true) {
            if (resp_from_steam.msg.indexOf("Already in zone game") === 0) {
                player.info.playground.battle.id = parse_numbers(resp_from_steam.msg);
            }
            
            throw resp_from_steam;
        }
        await player.fetch_info();
        
        return true;
    };
    
    /**
     * approximate value
     * @returns {*}
     */
    const calc_stay_duration = () => {
        if (player.info.playground.battle.id === null || player.info.playground.battle.timestamp === null) return 0;
        return player.info.playground.battle.stay_duration + (Date.now() - player.info.playground.battle.timestamp);
    };
    
    return {
        leave,
        join,
        get stay_duration() {
            return calc_stay_duration()
        },
    }
})();

const planets = (() => {
    const leave = async (id = player.info.playground.planet.id, battle_id = player.info.playground.battle.id) => {
        if (battle_id !== null) await battle.leave(battle_id);
        
        await request.send("LeaveGame", {gameid: id}); // steam does not notify about errors
        player.info.playground.planet.id = null;
        player.info.playground.planet.stay_duration = null;
        player.info.playground.planet.timestamp = Date.now();
        
        return true;
    };
    const join = async (id, force = false) => {
        if (id === player.info.playground.planet.id && force !== true) return true;
        await leave();
        
        const resp_from_steam = await request.send("JoinPlanet", {id});
        if (resp_from_steam.success !== true) {
            if (resp_from_steam.msg.indexOf("Already on planet") === 0) {
                player.info.playground.planet.id = parse_numbers(resp_from_steam.msg);
            }
            
            throw resp_from_steam;
        }
        player.info.playground.planet.id = id;
        player.info.playground.planet.stay_duration = 0;
        player.info.playground.planet.timestamp = Date.now();
        
        return true;
    };
    const get = async (id = player.info.playground.planet.id) => {
        if (id === null) throw "Empty planet `id`, cant get info about planet";
        
        const resp_from_steam = await request.send("GetPlanet", {id});
        if (resp_from_steam.success !== true) throw resp_from_steam;
        
        return resp_from_steam.data["planets"][0];
    };
    
    const gets = async (active_only = 1) => {
        const resp_from_steam = await request.send("GetPlanets", {active_only});
        if (resp_from_steam.success !== true) throw resp_from_steam;
        
        return resp_from_steam.data["planets"].reduce((total, cur) => {
            total[cur.id] = cur;
            return total
        }, {});
        
        /*const planets = {
            list: null,
            sorted: null
        };
        planets.list = resp_from_steam.data["planets"].reduce((total, cur) => {
            total[cur.id] = cur;
            return total
        }, {});
        
        planets.list = await(async () => {
            let loaded = {};
            for (let planet of resp_from_steam.data["planets"]) {
                let full_data = await get(planet.id);
                full_data.zones = full_data.zones.filter(zone => zone["captured"] === false);
                if (full_data.state["captured"] === true || full_data.state["active"] === false || full_data.zones.length <= 0) continue;
                
                loaded[full_data.id] = full_data;
                loaded.push(full_data);
            }
            return loaded;
        })();
        
        planets.priority = await (async () => {
            let loaded = [];
            for (let planet of resp_from_steam.data["planets"]) {
                let full_data = await get(planet.id);
                full_data.zones = full_data.zones.filter(zone => zone["captured"] === false);
                if (full_data.state["captured"] === true || full_data.state["active"] === false || full_data.zones.length <= 0) continue;
                
                full_data.sorted = sort_brutal_zones(full_data.zones);
                
                loaded.push(full_data);
            }
            
            return loaded.sort((a, b) => a.sorted[0] - b.sorted[0]);
        })();*/
    };
    //const sort_brutal_zones = zones => zones.sort((a, b) => a.difficulty - b.difficulty);
    
    /**
     * approximate value
     * @returns {*}
     */
    const calc_stay_duration = () => {
        if (player.info.playground.planet.id === null || player.info.playground.planet.timestamp === null) return 0;
        return player.info.playground.planet.stay_duration + (Date.now() - player.info.playground.planet.timestamp);
    };
    
    return {
        leave,
        join,
        gets,
        get,
        get stay_duration() {
            return calc_stay_duration
        },
    }
})();

// Main process
(async () => {
    let round_num = 1;
    const play_with_round = async () => {
        console.log("Player `fetch_info`");
        await player.fetch_info();
        await player.join_to_clan();
        let used = {
            planet: {
                id: null
            },
            zone: {
                id: null,
                difficulty: null
            }
        };
        
        if (player.info.playground.battle.id === null) {
            console.log("Planets get list");
            let list_of_planets = await planets.gets();
            
            console.log("Planets load zones");
            // Load zones in planets
            
            for (let cell in list_of_planets) {
                let planet = list_of_planets[cell];
                
                planet.full = await planets.get(planet.id);
                planet.full.zones.forEach(zone => {
                    if (zone["captured"] === true) return false;
                    if (zone.difficulty <= used.zone.difficulty) return false;
                    used.planet.id = planet.id;
                    used.zone.id = zone["zone_position"];
                    used.zone.difficulty = zone.difficulty;
                });
                
            }
            if (used.zone.id === null) throw "Cant find brutal zone in planet";
            
            await planets.join(used.planet.id);
            console.log(`Joined Zone ${used.zone.id} on Planet ${used.planet.id} - Captured: ${((list_of_planets[used.planet.id].state["capture_progress"]) * 100).toFixed(2)}% - Difficulty lvl: ${used.zone.difficulty}`);
            await battle.join(used.zone.id);
        } else {
            const planet_full = await planets.get(player.info.playground.planet.id);
            used.planet.id = player.info.playground.planet.id;
            used.zone.id = player.info.playground.battle.cell;
            used.zone.difficulty = planet_full.zones[player.info.playground.battle.cell];
        }
        
        const time_wait = 1200 + (score.round_duration - battle.stay_duration);
        console.log(`Wait ${(time_wait / 1000).toFixed(2)} sec before end`);
        await asleep(time_wait);
        
        console.log(`Update score +${score.calc(used.zone.difficulty)} points`);
        await score.save(used.zone.difficulty);
        
        return true;
    };
    while (true) {
        await play_with_round().then(console.log, console.log).then(() => console.log("Round №" + (round_num++)));
    }
})();
