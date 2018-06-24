<?php

set_time_limit( 0 );

if( !file_exists( __DIR__ . '/cacert.pem' ) )
{
	Msg( 'You forgot to download cacert.pem file' );
	exit( 1 );
}

$EnvToken = getenv('TOKEN');

if( $argc === 2 )
{
	$Token = $argv[ 1 ];
}
else if( is_string( $EnvToken ) )
{
	// if the token was provided as an env var, use it
	$Token = $EnvToken;
}
else
{
	// otherwise, read it from disk
	$Token = trim( file_get_contents( __DIR__ . '/token.txt' ) );
	$ParsedToken = json_decode( $Token, true );
	
	if( is_string( $ParsedToken ) )
	{
		$Token = $ParsedToken;
	}
	else if( isset( $ParsedToken[ 'token' ] ) )
	{
		$Token = $ParsedToken[ 'token' ];
	}
	
	unset( $ParsedToken );
}

unset( $EnvToken );

if( strlen( $Token ) !== 32 )
{
	Msg( 'Failed to find your token. Verify token.txt' );
	exit( 1 );
}

$WaitTime = 110;
$KnownPlanets = [];
$SkippedPlanets = [];
$CurrentPlanetName = '??';
$ZonePaces =
[
	'Planet' => 0,
];

lol_using_goto_in_2018:

$LastDifficulty = -1;
$LastRestart = time();

do
{
	$CurrentPlanet = GetFirstAvailablePlanet( $SkippedPlanets, $KnownPlanets );
}
while( !$CurrentPlanet && sleep( 5 ) === 0 );

do
{
	// Leave current game before trying to switch planets (it will report InvalidState otherwise)
	$SteamThinksPlanet = LeaveCurrentGame( $Token, $CurrentPlanet );

	if( $CurrentPlanet !== $SteamThinksPlanet )
	{
		SendPOST( 'ITerritoryControlMinigameService/JoinPlanet', 'id=' . $CurrentPlanet . '&access_token=' . $Token );

		$SteamThinksPlanet = LeaveCurrentGame( $Token );
	}
}
while( $CurrentPlanet !== $SteamThinksPlanet );

do
{
	echo PHP_EOL;

	// Scan planets every 10 minutes
	if( time() - $LastRestart > 60 * 10 )
	{
		Msg( '{lightred}!! Restarting to re-scan for new planets...' );

		goto lol_using_goto_in_2018;
	}

	do
	{
		$Zone = GetFirstAvailableZone( $CurrentPlanet, $ZonePaces, $WaitTime );
	}
	while( $Zone === null && sleep( 5 ) === 0 );

	if( $Zone === false )
	{
		$SkippedPlanets[ $CurrentPlanet ] = true;

		Msg( '{lightred}!! There are no zones to join in this planet, restarting...' );

		goto lol_using_goto_in_2018;
	}

	$PreviousDifficulty = $LastDifficulty;
	$LastDifficulty = $Zone[ 'difficulty' ];

	if( $PreviousDifficulty > $LastDifficulty )
	{
		Msg( '{lightred}!! Difficulty has been lowered (from ' . $PreviousDifficulty . ' to ' . $LastDifficulty . ') on this planet, restarting...' );

		goto lol_using_goto_in_2018;
	}

	// Find a new planet if there are no hard zones left
	$HardZones = $Zone[ 'hard_zones' ];
	$MediumZones = $Zone[ 'medium_zones' ];
	$EasyZones = $Zone[ 'easy_zones' ];
	$PlanetCaptured = $Zone[ 'planet_captured' ];
	$PlanetPlayers = $Zone[ 'planet_players' ];

	if( !$HardZones && IsThereAnyNewPlanets( $KnownPlanets ) )
	{
		Msg( '{lightred}!! Detected a new planet, restarting...' );

		goto lol_using_goto_in_2018;
	}

	$Zone = SendPOST( 'ITerritoryControlMinigameService/JoinZone', 'zone_position=' . $Zone[ 'zone_position' ] . '&access_token=' . $Token );

	if( empty( $Zone[ 'response' ][ 'zone_info' ] ) )
	{
		Msg( '{lightred}!! Failed to join a zone, restarting in 15 seconds...' );

		sleep( 15 );

		goto lol_using_goto_in_2018;
	}

	$Zone = $Zone[ 'response' ][ 'zone_info' ];

	Msg(
		'>> Planet {green}' . $CurrentPlanet .
		'{normal} - Captured: {yellow}' . number_format( $PlanetCaptured * 100, 2 ) . '%' .
		'{normal} - Hard: {yellow}' . $HardZones .
		'{normal} - Medium: {yellow}' . $MediumZones .
		'{normal} - Easy: {yellow}' . $EasyZones .
		'{normal} - Players: {yellow}' . number_format( $PlanetPlayers ) .
		'{green} (' . $CurrentPlanetName . ')'
	);

	Msg(
		'>> Zone {green}' . $Zone[ 'zone_position' ] .
		'{normal} - Captured: {yellow}' . number_format( $Zone[ 'capture_progress' ] * 100, 2 ) . '%' .
		'{normal} - Difficulty: {yellow}' . GetNameForDifficulty( $Zone )
	);

	if( isset( $Zone[ 'top_clans' ] ) )
	{
		Msg(
			'-- Top clans: ' . implode(', ', array_map( function( $Clan )
			{
				return $Clan[ 'url' ];
			}, $Zone[ 'top_clans' ] ) )
		);
	}

	Msg( '   {grey}Waiting ' . $WaitTime . ' seconds for this round to end...' );

	sleep( $WaitTime );
	
	$Data = SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );

	if( isset( $Data[ 'response' ][ 'new_score' ] ) )
	{
		$Data = $Data[ 'response' ];

		Msg(
			'>> Score: {lightred}' . number_format( $Data[ 'old_score' ] ) . '{normal} XP => {green}' . number_format( $Data[ 'new_score' ] ) .
			'{normal} XP - Current level: {green}' . $Data[ 'new_level' ] .
			'{normal} (' . number_format( $Data[ 'new_score' ] / $Data[ 'next_level_score' ] * 100, 2 ) . '%)'
		);
		
		$Time = ( $Data[ 'next_level_score' ] - $Data[ 'new_score' ] ) / GetScoreForZone( [ 'difficulty' => $Zone[ 'difficulty' ] ] ) * ( $WaitTime / 60 );
		$Hours = floor( $Time / 60 );
		$Minutes = $Time % 60;
		
		Msg(
			'>> Next level: {yellow}' . number_format( $Data[ 'next_level_score' ] ) .
			'{normal} XP - Remaining: {yellow}' . number_format( $Data[ 'next_level_score' ] - $Data[ 'new_score' ] ) .
			'{normal} XP - ETA: {green}' . $Hours . 'h ' . $Minutes . 'm'
		);
	}
	else
	{
		// If ReportScore failed (e.g. zone got captured), rescan planets
		goto lol_using_goto_in_2018;
	}

	// Some users get stuck in games after calling ReportScore, so we manually leave to fix this
	if( LeaveCurrentGame( $Token ) !== $CurrentPlanet )
	{
		Msg( '{lightred}!! Wrong current planet, restarting...' );

		goto lol_using_goto_in_2018;
	}
}
while( true );

function GetScoreForZone( $Zone )
{
	switch( $Zone[ 'difficulty' ] )
	{
		case 1: $Score = 5; break;
		case 2: $Score = 10; break;
		case 3: $Score = 20; break;
	}
	
	return $Score * 120;
}

function GetNameForDifficulty( $Zone )
{
	$Boss = $Zone[ 'type' ] === 4 ? 'BOSS - ' : '';
	$Difficulty = $Zone[ 'difficulty' ];

	switch( $Zone[ 'difficulty' ] )
	{
		case 2: $Difficulty = 'Medium'; break;
		case 3: $Difficulty = 'Hard'; break;
		case 1: $Difficulty = 'Low'; break;
	}

	return $Boss . $Difficulty;
}

function GetFirstAvailableZone( $Planet, &$ZonePaces, $WaitTime )
{
	$Zones = SendGET( 'ITerritoryControlMinigameService/GetPlanet', 'id=' . $Planet . '&language=english' );

	if( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) )
	{
		return null;
	}

	if( $ZonePaces[ 'Planet' ] != $Planet )
	{
		$ZonePaces =
		[
			'Planet' => $Planet,
			'Zones' => [],
		];
	}

	global $CurrentPlanetName;
	$CurrentPlanetName = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'name' ];

	$PlanetCaptured = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'capture_progress' ];
	$PlanetPlayers = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'current_players' ];
	$Zones = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ];
	$CleanZones = [];
	$HardZones = 0;
	$MediumZones = 0;
	$EasyZones = 0;
	
	foreach( $Zones as &$Zone )
	{
		if( empty( $Zone[ 'capture_progress' ] ) )
		{
			$Zone[ 'capture_progress' ] = 0.0;
		}

		if( $Zone[ 'captured' ] )
		{
			continue;
		}

		// Always join boss zone
		if( $Zone[ 'type' ] == 4 )
		{
			return $Zone;
		}
		else if( $Zone[ 'type' ] != 3 )
		{
			Msg( '{lightred}!! Unknown zone type: ' . $Zone[ 'type' ] );
		}

		$PaceCutoff = 0.97;

		if( isset( $ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ] ) )
		{
			$Paces = $ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ];
			$Paces[] = $Zone[ 'capture_progress' ];
			$Differences = [];

			for( $i = count( $Paces ) - 1; $i > 0; $i-- )
			{
				$Differences[] = $Paces[ $i ] - $Paces[ $i - 1 ];
			}

			$PaceCutoff = array_sum( $Differences ) / count( $Differences );

			if( $PaceCutoff > 0.02 )
			{
				$PaceTime = ceil( ( 1 - $Zone[ 'capture_progress' ] ) / $PaceCutoff * $WaitTime );
				$Minutes = floor( $PaceTime / 60 );
				$Seconds = $PaceTime % 60;

				Msg( '-- Current pace for Zone {green}' . $Zone[ 'zone_position' ] . '{normal} is {green}+' . number_format( $PaceCutoff * 100, 2 ) . '%{normal} ETA: {green}' . $Minutes . 'm ' . $Seconds . 's' );
			}

			$PaceCutoff = 0.98 - $PaceCutoff;
		}

		// If a zone is close to completion, skip it because Valve does not reward points
		// and replies with 42 NoMatch instead
		if( $Zone[ 'capture_progress' ] > $PaceCutoff )
		{
			continue;
		}

		switch( $Zone[ 'difficulty' ] )
		{
			case 3: $HardZones++; break;
			case 2: $MediumZones++; break;
			case 1: $EasyZones++; break;
		}

		$CleanZones[] = $Zone;
	}

	unset( $Zone );

	foreach( $Zones as $Zone )
	{
		if( !isset( $ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ] ) )
		{
			$ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ] = [ $Zone[ 'capture_progress' ] ];
		}
		else
		{
			if( count( $ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ] ) > 4 )
			{
				array_shift( $ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ] );
			}

			$ZonePaces[ 'Zones' ][ $Zone[ 'zone_position' ] ][] = $Zone[ 'capture_progress' ];
		}
	}

	if( empty( $CleanZones ) )
	{
		return false;
	}

	usort( $CleanZones, function( $a, $b )
	{
		if( $b[ 'difficulty' ] === $a[ 'difficulty' ] )
		{
			return $b[ 'zone_position' ] - $a[ 'zone_position' ];
		}
		
		return $b[ 'difficulty' ] - $a[ 'difficulty' ];
	} );

	$Zone = $CleanZones[ 0 ];
	$Zone[ 'hard_zones' ] = $HardZones;
	$Zone[ 'medium_zones' ] = $MediumZones;
	$Zone[ 'easy_zones' ] = $EasyZones;
	$Zone[ 'planet_captured' ] = $PlanetCaptured;
	$Zone[ 'planet_players' ] = $PlanetPlayers;

	return $Zone;
}

function IsThereAnyNewPlanets( $KnownPlanets )
{
	Msg( '   {grey}Checking for any new planets...' );

	$Planets = SendGET( 'ITerritoryControlMinigameService/GetPlanets', 'active_only=1&language=english' );

	if( empty( $Planets[ 'response' ][ 'planets' ] ) )
	{
		return false;
	}

	foreach( $Planets[ 'response' ][ 'planets' ] as $Planet )
	{
		if( !isset( $KnownPlanets[ $Planet[ 'id' ] ] ) )
		{
			return true;
		}
	}

	return false;
}

function GetFirstAvailablePlanet( $SkippedPlanets, &$KnownPlanets )
{
	Msg( '   {grey}Finding the best planet...' );

	$Planets = SendGET( 'ITerritoryControlMinigameService/GetPlanets', 'active_only=1&language=english' );

	if( empty( $Planets[ 'response' ][ 'planets' ] ) )
	{
		return null;
	}

	$Planets = $Planets[ 'response' ][ 'planets' ];

	foreach( $Planets as &$Planet )
	{
		$KnownPlanets[ $Planet[ 'id' ] ] = true;

		do
		{
			$Zones = SendGET( 'ITerritoryControlMinigameService/GetPlanet', 'id=' . $Planet[ 'id' ] . '&language=english' );
		}
		while( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) );

		$Planet[ 'hard_zones' ] = 0;
		$Planet[ 'medium_zones' ] = 0;
		$Planet[ 'easy_zones' ] = 0;

		$HasBossZone = false;

		foreach( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] as $Zone )
		{
			if( !empty( $Zone[ 'capture_progress' ] ) && $Zone[ 'capture_progress' ] > 0.93 )
			{
				continue;
			}

			if( $Zone[ 'captured' ] )
			{
				continue;
			}

			// Always join boss zone
			if( $Zone[ 'type' ] == 4 )
			{
				$HasBossZone = true;
			}
			else if( $Zone[ 'type' ] != 3 )
			{
				Msg( '{lightred}!! Unknown zone type: ' . $Zone[ 'type' ] );
			}

			switch( $Zone[ 'difficulty' ] )
			{
				case 3: $Planet[ 'hard_zones' ]++; break;
				case 2: $Planet[ 'medium_zones' ]++; break;
				case 1: $Planet[ 'easy_zones' ]++; break;
			}
		}

		Msg(
			'>> Planet {green}%3d{normal} - Hard: {yellow}%2d{normal} - Medium: {yellow}%2d{normal} - Easy: {yellow}%2d{normal} - Captured: {yellow}%5s%%{normal} - Players: {yellow}%8s {green}(%s)',
			PHP_EOL,
			[
				$Planet[ 'id' ],
				$Planet[ 'hard_zones' ],
				$Planet[ 'medium_zones' ],
				$Planet[ 'easy_zones' ],
				number_format( empty( $Planet[ 'state' ][ 'capture_progress' ] ) ? 0 : ( $Planet[ 'state' ][ 'capture_progress' ] * 100 ), 2 ),
				number_format( $Planet[ 'state' ][ 'current_players' ] ),
				$Planet[ 'state' ][ 'name' ],
			]
		);

		if( $HasBossZone )
		{
			Msg( '{green}>> This planet has a boss zone, selecting this planet' );

			return $Planet[ 'id' ];
		}
	}

	// https://bugs.php.net/bug.php?id=71454
	unset( $Planet );

	$Priority = [ 'hard_zones', 'medium_zones', 'easy_zones' ];

	usort( $Planets, function( $a, $b ) use ( $Priority )
	{
		// Sort planets by least amount of zones
		for( $i = 0; $i < 3; $i++ )
		{
			$Key = $Priority[ $i ];

			if( $a[ $Key ] !== $b[ $Key ] )
			{
				return $a[ $Key ] - $b[ $Key ];
			}
		}

		return $a[ 'id' ] - $b[ 'id' ];
	} );

	// Loop three times - first loop tries to find planet with hard zones, second loop - medium zones, and then easies
	for( $i = 0; $i < 3; $i++ )
	foreach( $Planets as &$Planet )
	{
		if( isset( $SkippedPlanets[ $Planet[ 'id' ] ] ) )
		{
			continue;
		}

		if( !$Planet[ $Priority[ $i ] ] )
		{
			continue;
		}

		if( !$Planet[ 'state' ][ 'captured' ]  )
		{
			Msg( '>> Selected planet {green}' . $Planet[ 'id' ] . ' (' . $Planet[ 'state' ][ 'name' ] . ')' );

			return $Planet[ 'id' ];
		}
	}

	return $Planets[ 0 ][ 'id' ];
}

function LeaveCurrentGame( $Token, $LeaveCurrentPlanet = 0 )
{
	do
	{
		$Data = SendPOST( 'ITerritoryControlMinigameService/GetPlayerInfo', 'access_token=' . $Token );

		if( isset( $Data[ 'response' ][ 'active_zone_game' ] ) )
		{
			SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $Data[ 'response' ][ 'active_zone_game' ] );
		}

		if( !isset( $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] ) || $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] != 0x48E542 )
		{
			// Please do not change our clanid if you are going to use this script
			// If you want to cheat for your own group, come up with up with your own approach, thank you
			SendPOST( 'ITerritoryControlMinigameService/RepresentClan', 'clanid=4' . 777 . '282&access_token=' . $Token );
		}
	}
	while( !isset( $Data[ 'response' ][ 'score' ] ) );

	if( !isset( $Data[ 'response' ][ 'active_planet' ] ) )
	{
		return 0;
	}

	$ActivePlanet = $Data[ 'response' ][ 'active_planet' ];

	if( $LeaveCurrentPlanet > 0 && $LeaveCurrentPlanet !== $ActivePlanet )
	{
		Msg( '   Leaving planet {yellow}' . $ActivePlanet . '{normal} because we want to be on {yellow}' . $LeaveCurrentPlanet );
	
		SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $ActivePlanet );
	}

	return $ActivePlanet;
}

function SendPOST( $Method, $Data )
{
	return ExecuteRequest( 'https://community.steam-api.com/' . $Method . '/v0001/', $Data );
}

function SendGET( $Method, $Data )
{
	return ExecuteRequest( 'https://community.steam-api.com/' . $Method . '/v0001/?' . $Data );
}

function ExecuteRequest( $URL, $Data = [] )
{
	$c = curl_init( );

	curl_setopt_array( $c, [
		CURLOPT_URL            => $URL,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => empty( $Data ) ? 10 : 60,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_HEADER         => 1,
		CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
		CURLOPT_HTTPHEADER     =>
		[
			'Accept: */*',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Origin: https://steamcommunity.com',
			'Referer: https://steamcommunity.com/saliengame/play',
		],
	] );

	if( !empty( $Data ) )
	{
		curl_setopt( $c, CURLOPT_POST, 1 );
		curl_setopt( $c, CURLOPT_POSTFIELDS, $Data );
	}

	if ( !empty( $_SERVER[ 'LOCAL_ADDRESS' ] ) )
	{
		curl_setopt( $c, CURLOPT_INTERFACE, $_SERVER[ 'LOCAL_ADDRESS' ] );
	}

	do
	{
		$Data = curl_exec( $c );

		$HeaderSize = curl_getinfo( $c, CURLINFO_HEADER_SIZE );
		$Header = substr( $Data, 0, $HeaderSize );
		$Data = substr( $Data, $HeaderSize );

		preg_match( '/X-eresult: ([0-9]+)/', $Header, $EResult ) === 1 ? $EResult = (int)$EResult[ 1 ] : $EResult = 0;

		if( $EResult !== 1 )
		{
			Msg( '{lightred}!! ' . $Method . ' failed - EResult: ' . $EResult . ' - ' . $Data );

			if( $EResult === 15 && $Method === 'ITerritoryControlMinigameService/RepresentClan' )
			{
				echo PHP_EOL;

				Msg( '{green}You need to join the group for this script to work:' );
				Msg( '{yellow}https://steamcommunity.com/groups/SteamDB' );

				sleep( 10 );
			}
			else if( $EResult === 42 && $Method === 'ITerritoryControlMinigameService/ReportScore' )
			{
				Msg( '{lightred}-- EResult 42 means zone has been captured while you were in it' );
			}
			else if( $EResult === 0 || $EResult === 11 )
			{
				Msg( '{lightred}-- This problem should resolve itself, wait for a couple of minutes' );
			}
			else if( $EResult === 10 )
			{
				Msg( '{lightred}-- EResult 10 means Steam is busy' );

				sleep( 3 );
			}
		}

		$Data = json_decode( $Data, true );
	}
	while( !isset( $Data[ 'response' ] ) && sleep( 1 ) === 0 );

	curl_close( $c );

	return $Data;
}

function Msg( $Message, $EOL = PHP_EOL, $printf = [] )
{
	$Message = str_replace(
		[
			'{normal}',
			'{green}',
			'{yellow}',
			'{lightred}',
			'{grey}',
		],
		[
			"\033[0m",
			"\033[0;32m",
			"\033[1;33m",
			"\033[1;31m",
			"\033[0;36m",
		],
	$Message, $Count );

	if( $Count > 0 )
	{
		$Message .= "\033[0m";
	}

	$Message = '[' . date( 'H:i:s' ) . '] ' . $Message . $EOL;

	if( !empty( $printf ) )
	{
		printf( $Message, ...$printf );
	}
	else
	{
		echo $Message;
	}
}
