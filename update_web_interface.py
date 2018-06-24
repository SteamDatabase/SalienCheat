
from datetime import datetime

def update(token, game, progress_text):
    if (token != ''):
        dmap = {
            1: 'Easy',
            2: 'Medium',
            3: 'Hard',
        }

        f = open(token + ".html", "w")
        webpage = '<html>\n<head>\n'
        webpage = webpage + '<meta charset="utf-8">\n'
        webpage = webpage + '<title>SaliensCheat Progress Page</title>\n'
        webpage = webpage + '<link rel="stylesheet" href="https://steamcommunity-a.akamaihd.net/public/shared/css/motiva_sans.css">\n'
        webpage = webpage + '<style>\nbody {\n\tbackground: #000;\n\tcolor: #fff;\n\tfont-family: "Motiva Sans", Sans-serif;\n\tfont-weight: 300;\n\tfont-size: 12px;\n\tline-height: 20px;\n}\n</style>\n'
        webpage = webpage + '<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">\n'
        webpage = webpage + '<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.1.min.js"></script>\n'
        webpage = webpage + '<script type="text/javascript" src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>\n'
        webpage = webpage + '</head>\n<body>'
        webpage = webpage + 'Latest Update Time: ' + datetime.now().strftime("%m/%d/%y %H:%M:%S")+ '<br />'
        webpage = webpage + 'Token: ' + token + '<br />'

        if (game.player_info is not None):
            webpage = webpage + 'Planet #' + str(game.player_info['active_planet']) + ' Progress: '
            webpage = webpage + '<div id="planet_pbar" style="margin-left: 10px; height: 20px; width: 200px; display: inline-block; position: absolute;"></div><br />'
            
            if (game.planet is not None):
                webpage = webpage + 'Planet Completion: ' + str((1.0 if game.planet['state']['captured'] else game.planet['state'].get('capture_progress', 0)) * 100) + '%<br />'
            else:
                webpage = webpage + 'Planet Completion: N/A%<br />'

            if (game.zone_id is not None):
                webpage = (webpage + 'Zone #' + str(game.planet['zones'][game.zone_id]['zone_position'])
                            + ' - ' + str(dmap.get(game.planet['zones'][game.zone_id]['difficulty'], game.planet['zones'][game.zone_id]['difficulty'])) + '<br />')
            else:
                webpage = webpage + 'Zone # N/A - N/A<br />'

            webpage = webpage + 'Level ' + str(game.player_info['level']) + ' Progress:'
            webpage = webpage + '<div id="score_pbar" style="margin-left: 10px; height: 20px; width: 200px; display: inline-block; position: absolute;"></div><br />'
            webpage = webpage + 'Level Completion: ' + str((float(game.player_info['score']) / float(game.player_info['next_level_score'])) * 100) + '% (' + game.player_info['score'] + '/' + game.player_info['next_level_score'] + ')<br /><br />'
        else:
            webpage = webpage + 'Planet # N/A Progress: N/A<br />'
            webpage = webpage + 'Planet Completion: N/A%<br />'
            webpage = webpage + 'Zone # N/A - N/A<br />'
            webpage = webpage + 'Level N/A Progress: ' + 'N/A<br />'
            webpage = webpage + 'Level Completion: ' + 'N/A%<br /><br />'

        for _, v in game.colors:
            progress_text = progress_text.replace(v, '')
        
        progress_text = progress_text.replace('++', '')

        webpage = webpage + 'Latest Log Statement:\n' + progress_text + '\n<br />\n'
        webpage = webpage + '</body>'

        if ((game.player_info is not None) and (game.planet is not None)):
            webpage = webpage + '<script type="text/javascript">$("#score_pbar").progressbar({value: ' + str((float(game.player_info['score']) / float(game.player_info['next_level_score'])) * 100) + '});</script>\n'
            webpage = webpage + '<script type="text/javascript">$("#score_pbar > div").css({"background": "#000000"});</script>\n'
            webpage = webpage + '<script type="text/javascript">$("#planet_pbar").progressbar({value: ' + str((1.0 if game.planet['state']['captured'] else game.planet['state'].get('capture_progress', 0)) * 100) + '});</script>\n'
            webpage = webpage + '<script type="text/javascript">$("#planet_pbar > div").css({"background": "#000000"});</script>\n'

        webpage = webpage + '<script type="text/javascript" src="reload_page.js"></script>\n'
        webpage = webpage + '</html>'
        f.write(webpage)
        f.flush()
        f.close()
