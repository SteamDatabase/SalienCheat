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

Msg( 'This script will not work until you have joined our group:' );
Msg( 'https://steamcommunity.com/groups/SteamDB' );
echo PHP_EOL . PHP_EOL;

$SkippedPlanets = [];
$CurrentPlanetName = '??';

lol_using_goto_in_2018:

$LastRestart = time();

do
{
	$CurrentPlanet = GetFirstAvailablePlanet( $SkippedPlanets );
}
while( !$CurrentPlanet && sleep( 5 ) === 0 );

// Leave current game before trying to switch planets (it will report InvalidState otherwise)
LeaveCurrentGame( $Token, true );

SendPOST( 'ITerritoryControlMinigameService/JoinPlanet', 'id=' . $CurrentPlanet . '&access_token=' . $Token );

// Set the planet to what Steam thinks is the active one, even though we sent JoinPlanet request
$CurrentPlanet = LeaveCurrentGame( $Token, false );

do
{
	// Check for a new planet every hour
	if( time() - $LastRestart > 3600 )
	{
		Msg( '!! Idled this planet for one hour, restarting to check for new planets' );

		goto lol_using_goto_in_2018;
	}

	do
	{
		$Zone = GetFirstAvailableZone( $CurrentPlanet );
	}
	while( $Zone === null && sleep( 5 ) === 0 );

	if( $Zone === false )
	{
		$SkippedPlanets[ $CurrentPlanet ] = true;

		Msg( '!! There are no zones to join in this planet, restarting...' );

		goto lol_using_goto_in_2018;
	}

	// Find a new planet if there are no hard zones left
	$HardZones = $Zone[ 'hard_zones' ];
	$PlanetCaptured = $Zone[ 'planet_captured' ];
	$PlanetPlayers = $Zone[ 'planet_players' ];

	if( !$HardZones && time() - $LastRestart > 60 )
	{
		Msg( '!! This planet does not have any hard zones left, restarting...' );

		goto lol_using_goto_in_2018;
	}

	$Zone = SendPOST( 'ITerritoryControlMinigameService/JoinZone', 'zone_position=' . $Zone[ 'zone_position' ] . '&access_token=' . $Token );

	if( empty( $Zone[ 'response' ][ 'zone_info' ] ) )
	{
		Msg( '!! Failed to join a zone, waiting 15 seconds and trying again' );

		sleep( 15 );

		goto lol_using_goto_in_2018;
	}

	$Zone = $Zone[ 'response' ][ 'zone_info' ];

	Msg(
		'>> Planet ' . $CurrentPlanet . ' (' . $CurrentPlanetName . ')' .
		' - Players: ' . number_format( $PlanetPlayers ) .
		' - Captured: ' . number_format( $PlanetCaptured * 100, 2 ) . '%' .
		' - Hard zones: ' . $HardZones
	);

	Msg(
		'>> Zone ' . $Zone[ 'zone_position' ] .
		' - Captured: ' . number_format( empty( $Zone[ 'capture_progress' ] ) ? 0 : ( $Zone[ 'capture_progress' ] * 100 ), 2 ) . '%' .
		' - Difficulty: ' . $Zone[ 'difficulty' ]
	);

	if( isset( $Zone[ 'top_clans' ] ) )
	{
		Msg(
			'>> Top clans: ' . implode(', ', array_map( function( $Clan )
			{
				return $Clan[ 'url' ];
			}, $Zone[ 'top_clans' ] ) )
		);
	}

	sleep( 120 );
	
	$Data = SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );

	if( isset( $Data[ 'response' ][ 'new_score' ] ) )
	{
		$Data = $Data[ 'response' ];

		Msg(
			'>> Score: ' . $Data[ 'old_score' ] . ' => ' . $Data[ 'new_score' ] .
			' (next level: ' . $Data[ 'next_level_score' ] .
			' in ' . ( $Data[ 'next_level_score' ] - $Data[ 'new_score' ] ) . ') - Current level: ' . $Data[ 'new_level' ] .
			' (' . number_format( $Data[ 'new_score' ] / $Data[ 'next_level_score' ] * 100, 2 ) . '%)',
			PHP_EOL . PHP_EOL
		);
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

function GetFirstAvailableZone( $Planet )
{
	$Zones = SendGET( 'ITerritoryControlMinigameService/GetPlanet', 'id=' . $Planet . '&language=english' );

	if( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) )
	{
		return null;
	}

	global $CurrentPlanetName;
	$CurrentPlanetName = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'name' ];

	$PlanetCaptured = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'capture_progress' ];
	$PlanetPlayers = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'state' ][ 'current_players' ];
	$Zones = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ];
	$CleanZones = [];
	$HardZones = 0;
	
	foreach( $Zones as $Zone )
	{
		if( $Zone[ 'captured' ] )
		{
			continue;
		}

		if( $Zone[ 'difficulty' ] === 3 )
		{
			$HardZones++;
		}

		// Always join boss zone
		if( $Zone[ 'type' ] == 4 )
		{
			return $Zone;
		}
		else if( $Zone[ 'type' ] != 3 )
		{
			Msg( '!! Unknown zone type: ' . $Zone[ 'type' ] );
		}

		$CleanZones[] = $Zone;
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
	$Zone[ 'planet_captured' ] = $PlanetCaptured;
	$Zone[ 'planet_players' ] = $PlanetPlayers;

	return $Zone;
}

function GetFirstAvailablePlanet( $SkippedPlanets )
{
	$Planets = SendGET( 'ITerritoryControlMinigameService/GetPlanets', 'active_only=1&language=english' );

	if( empty( $Planets[ 'response' ][ 'planets' ] ) )
	{
		return null;
	}

	$Planets = $Planets[ 'response' ][ 'planets' ];

	foreach( $Planets as &$Planet )
	{
		do
		{
			$Zones = SendGET( 'ITerritoryControlMinigameService/GetPlanet', 'id=' . $Planet[ 'id' ] . '&language=english' );
		}
		while( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) );

		$Planet[ 'hard_zones' ] = 0;

		foreach( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] as $Zone )
		{
			if( !$Zone[ 'captured' ] && $Zone[ 'difficulty' ] === 3 )
			{
				$Planet[ 'hard_zones' ]++;
			}
		}

		Msg( '>> Planet ' . $Planet[ 'id' ] . ' (' . $Planet[ 'state' ][ 'name' ] . ') has ' . $Planet[ 'hard_zones' ] . ' hard zones' );
	}

	usort( $Planets, function( $a, $b )
	{
		if( $b[ 'hard_zones' ] === $a[ 'hard_zones' ] )
		{
			return $a[ 'state' ][ 'current_players' ] - $b[ 'state' ][ 'current_players' ];
		}
		
		return $b[ 'hard_zones' ] - $a[ 'hard_zones' ];
	} );

	foreach( $Planets as $Planet )
	{
		if( isset( $SkippedPlanets[ $Planet[ 'id' ] ] ) )
		{
			continue;
		}

		if( !$Planet[ 'state' ][ 'captured' ]  )
		{
			Msg(
				'>> Selected planet ' . $Planet[ 'id' ] . ' (' . $Planet[ 'state' ][ 'name' ] . ')' .
				' - Players: ' . number_format( $Planet[ 'state' ][ 'current_players' ] ) .
				' - Hard zones: ' . $Planet[ 'hard_zones' ]
			);

			return $Planet[ 'id' ];
		}
	}
}

function LeaveCurrentGame( $Token, $LeaveCurrentPlanet )
{
	do
	{
		$Data = SendPOST( 'ITerritoryControlMinigameService/GetPlayerInfo', 'access_token=' . $Token );

		if( !isset( $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] ) || $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] != 4777282 )
		{
			// Please do not change our clanid if you are going to use this script
			// If you want to cheat for your own group, come up with up with your own approach, thank you
			SendPOST( 'ITerritoryControlMinigameService/RepresentClan', 'clanid=4777282&access_token=' . $Token );
		}
		else
		{
			break;
		}
	}
	while( true );

	if( isset( $Data[ 'response' ][ 'active_zone_game' ] ) )
	{
		SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $Data[ 'response' ][ 'active_zone_game' ] );
	}

	if( !isset( $Data[ 'response' ][ 'active_planet' ] ) )
	{
		return 0;
	}

	if( $LeaveCurrentPlanet )
	{
		SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $Data[ 'response' ][ 'active_planet' ] );
	}

	return $Data[ 'response' ][ 'active_planet' ];
}

function SendPOST( $Method, $Data )
{
	$c = curl_init( );

	curl_setopt_array( $c, [
		CURLOPT_URL            => 'https://community.steam-api.com/' . $Method . '/v0001/',
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_HEADER         => 1,
		CURLOPT_POST           => 1,
		CURLOPT_POSTFIELDS     => $Data,
		CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
		CURLOPT_HTTPHEADER     =>
		[
			'Accept: */*',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Origin: https://steamcommunity.com',
			'Referer: https://steamcommunity.com/saliengame/play',
		],
	] );

	do
	{
		Msg( 'Sending ' . $Method . '...', ' ' );

		$Data = curl_exec( $c );

		$HeaderSize = curl_getinfo( $c, CURLINFO_HEADER_SIZE );
		$Header = substr( $Data, 0, $HeaderSize );
		$Data = substr( $Data, $HeaderSize );

		preg_match( '/X-eresult: ([0-9]+)/', $Header, $EResult ) === 1 ? $EResult = (int)$EResult[ 1 ] : $EResult = 0;

		if( $EResult === 1 )
		{
			echo 'OK' . PHP_EOL;
		}
		else
		{
			echo 'EResult: ' . $EResult . ' - ' . $Data . PHP_EOL;
		}

		$Data = json_decode( $Data, true );
	}
	while( !isset( $Data[ 'response' ] ) && sleep( 1 ) === 0 );

	curl_close( $c );
	
	return $Data;
}

function SendGET( $Method, $Data )
{
	$c = curl_init( );

	curl_setopt_array( $c, [
		CURLOPT_URL            => 'https://community.steam-api.com/' . $Method . '/v0001/?' . $Data,
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
		CURLOPT_HTTPHEADER     =>
		[
			'Accept: */*',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			'Origin: https://steamcommunity.com',
			'Referer: https://steamcommunity.com/saliengame/play',
		],
	] );

	do
	{
		Msg( 'Sending ' . $Method . '...' );
		
		$Data = curl_exec( $c );
		$Data = json_decode( $Data, true );
	}
	while( !isset( $Data[ 'response' ] ) && sleep( 1 ) === 0 );

	curl_close( $c );
	
	return $Data;
}

function Msg( $Message, $EOL = PHP_EOL )
{
	echo date( DATE_RSS ) . ' - ' . $Message . $EOL;
}
