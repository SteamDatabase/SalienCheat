#!/usr/bin/env php
<?php

set_time_limit( 0 );

if( !file_exists( __DIR__ . '/cacert.pem' ) )
{
	Msg( 'You forgot to download cacert.pem file' );
	exit( 1 );
}

if( $argc === 2 )
{
	$Token = $argv[ 1 ];
}
else if( isset( $_SERVER[ 'TOKEN' ] ) )
{
	// if the token was provided as an env var, use it
	$Token = $_SERVER[ 'TOKEN' ];
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

if( strlen( $Token ) !== 32 )
{
	Msg( 'Failed to find your token. Verify token.txt' );
	exit( 1 );
}

if( isset( $_SERVER[ 'IGNORE_UPDATES' ] ) && (bool)$_SERVER[ 'IGNORE_UPDATES' ] )
{
	$UpdateCheck = false;
}
else
{
	$UpdateCheck = true;
	$LocalScriptHash = sha1( trim( file_get_contents( __FILE__ ) ) );
	$RepositoryScriptETag = '';
	$RepositoryScriptHash = GetRepositoryScriptHash( $RepositoryScriptETag, $LocalScriptHash );
}

// 10/10 code
$DisableColors = !(
	( function_exists( 'sapi_windows_vt100_support' ) && sapi_windows_vt100_support( STDOUT ) ) ||
	( function_exists( 'stream_isatty' ) && stream_isatty( STDOUT ) ) ||
	( function_exists( 'posix_isatty' ) && posix_isatty( STDOUT ) )
);

if( isset( $_SERVER[ 'DISABLE_COLORS' ] ) )
{
	$DisableColors = (bool)$_SERVER[ 'DISABLE_COLORS' ];
}

$GameVersion = 1;
$WaitTime = 110;
$ZonePaces = [];
$OldScore = 0;
$LastKnownPlanet = 0;
$LastKnownZone = 0;

Msg( "{background-blue}Welcome to SalienCheat for SteamDB" );

if( ini_get( 'precision' ) < 18 )
{
	Msg( '{grey}Fixed php float precision (was ' . ini_get( 'precision' ) . ')' );
	ini_set( 'precision', '18' );
}

do
{
	$Data = SendPOST( 'ITerritoryControlMinigameService/GetPlayerInfo', 'access_token=' . $Token );

	if( isset( $Data[ 'response' ][ 'score' ] ) )
	{
		if( !isset( $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] ) )
		{
			Msg( '{green}-- You are currently not representing any clan, so you are now part of SteamDB' );
			Msg( '{green}-- Make sure to join{yellow} https://steamcommunity.com/groups/steamdb {green}on Steam' );
	
			SendPOST( 'ITerritoryControlMinigameService/RepresentClan', 'clanid=4777282&access_token=' . $Token );
		}
		else if( $Data[ 'response' ][ 'clan_info' ][ 'accountid' ] != 4777282 )
		{
			Msg( '{green}-- If you want to support us, join our group' );
			Msg( '{green}--{yellow} https://steamcommunity.com/groups/steamdb' );
			Msg( '{green}-- and set us as your clan on' );
			Msg( '{green}--{yellow} https://steamcommunity.com/saliengame/play' );
			Msg( '{green}-- Happy farming!' );
		}
	}
}
while( !isset( $Data[ 'response' ][ 'score' ] ) && sleep( 1 ) === 0 );

do
{
	$BestPlanetAndZone = GetBestPlanetAndZone( $ZonePaces, $WaitTime );
}
while( !$BestPlanetAndZone && sleep( 5 ) === 0 );

do
{
	echo PHP_EOL;

	// Only get player info and leave current planet if it changed
	if( $LastKnownPlanet !== $BestPlanetAndZone[ 'id' ] )
	{
		do
		{
			// Leave current game before trying to switch planets (it will report InvalidState otherwise)
			$SteamThinksPlanet = LeaveCurrentGame( $Token, $BestPlanetAndZone[ 'id' ] );
		
			if( $BestPlanetAndZone[ 'id' ] !== $SteamThinksPlanet )
			{
				SendPOST( 'ITerritoryControlMinigameService/JoinPlanet', 'id=' . $BestPlanetAndZone[ 'id' ] . '&access_token=' . $Token );
		
				$SteamThinksPlanet = LeaveCurrentGame( $Token );
			}
		}
		while( $BestPlanetAndZone[ 'id' ] !== $SteamThinksPlanet && sleep( 1 ) === 0 );

		$LastKnownPlanet = $BestPlanetAndZone[ 'id' ];
	}

	$Zone = SendPOST( 'ITerritoryControlMinigameService/JoinZone', 'zone_position=' . $BestPlanetAndZone[ 'best_zone' ][ 'zone_position' ] . '&access_token=' . $Token );
	$PlanetCheckTime = microtime( true );

	// Rescan planets if joining failed
	if( empty( $Zone[ 'response' ][ 'zone_info' ] ) )
	{
		Msg( '{lightred}!! Failed to join a zone, rescanning and restarting...' );

		sleep( 1 );

		do
		{
			$BestPlanetAndZone = GetBestPlanetAndZone( $ZonePaces, $WaitTime );
		}
		while( !$BestPlanetAndZone && sleep( 5 ) === 0 );

		continue;
	}

	$Zone = $Zone[ 'response' ][ 'zone_info' ];

	if( empty( $Zone[ 'response' ][ 'zone_info' ][ 'capture_progress' ] ) )
	{
		$Zone[ 'response' ][ 'zone_info' ][ 'capture_progress' ] = 0.0;
	}

	Msg(
		'++ Joined Zone {yellow}' . $Zone[ 'zone_position' ] .
		'{normal} on Planet {green}' . $BestPlanetAndZone[ 'id' ] .
		'{normal} - Captured: {yellow}' . number_format( $Zone[ 'capture_progress' ] * 100, 2 ) . '%' .
		'{normal} - Difficulty: {yellow}' . GetNameForDifficulty( $Zone )
	);

	$SkippedLagTime = curl_getinfo( $c, CURLINFO_TOTAL_TIME ) - curl_getinfo( $c, CURLINFO_STARTTRANSFER_TIME );
	$SkippedLagTime -= fmod( $SkippedLagTime, 0.1 );
	$LagAdjustedWaitTime = $WaitTime - $SkippedLagTime;
	$WaitTimeBeforeFirstScan = 50 + ( 50 - $SkippedLagTime );

	if( $UpdateCheck )
	{
		if( $LocalScriptHash === $RepositoryScriptHash )
		{
			$RepositoryScriptHash = GetRepositoryScriptHash( $RepositoryScriptETag, $LocalScriptHash );
		}

		if( $LocalScriptHash !== $RepositoryScriptHash )
		{
			Msg( '-- {lightred}Script has been updated on GitHub since you started this script, please make sure to update.' );
		}
	}

	Msg( '   {grey}Waiting ' . number_format( $WaitTimeBeforeFirstScan, 3 ) . ' (+' . number_format( $SkippedLagTime, 3 ) . ' second lag) seconds before rescanning planets...' );

	usleep( $WaitTimeBeforeFirstScan * 1000000 );

	do
	{
		$BestPlanetAndZone = GetBestPlanetAndZone( $ZonePaces, $WaitTime );
	}
	while( !$BestPlanetAndZone && sleep( 5 ) === 0 );

	$LagAdjustedWaitTime -= microtime( true ) - $PlanetCheckTime;

	if( $LagAdjustedWaitTime > 0 )
	{
		Msg( '   {grey}Waiting ' . number_format( $LagAdjustedWaitTime, 3 ) . ' remaining seconds before submitting score...' );

		usleep( $LagAdjustedWaitTime * 1000000 );
	}

	$Data = SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );

	if( $Data[ 'eresult' ] == 93 )
	{
		$LagAdjustedWaitTime = min( 10, round( $SkippedLagTime ) );

		Msg( '{lightred}-- Time is out of sync, trying again in ' . $LagAdjustedWaitTime . ' seconds...' );

		sleep( $LagAdjustedWaitTime );

		$Data = SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );
	}

	if( isset( $Data[ 'response' ][ 'new_score' ] ) )
	{
		$Data = $Data[ 'response' ];

		echo PHP_EOL;

		// Store our own old score because the API may increment score while giving an error (e.g. a timeout)
		if( !$OldScore )
		{
			$OldScore = $Data[ 'old_score' ];
		}

		Msg(
			'++ Your Score: {lightred}' . number_format( $Data[ 'new_score' ] ) .
			'{yellow} (+' . number_format( $Data[ 'new_score' ] - $OldScore ) . ')' .
			'{normal} - Current Level: {green}' . $Data[ 'new_level' ] .
			'{normal} (' . number_format( GetNextLevelProgress( $Data ) * 100, 2 ) . '%)'
		);
		
		$OldScore = $Data[ 'new_score' ];
		$WaitTimeSeconds = $WaitTime / 60;
		$Time = ( ( $Data[ 'next_level_score' ] - $Data[ 'new_score' ] ) / GetScoreForZone( [ 'difficulty' => $Zone[ 'difficulty' ] ] ) * $WaitTimeSeconds ) + $WaitTimeSeconds;
		$Hours = floor( $Time / 60 );
		$Minutes = $Time % 60;
		$Date = date_create();
		
		date_add( $Date, date_interval_create_from_date_string( $Hours . " hours + " . $Minutes . " minutes" ) );
		
		Msg(
			'>> Next Level: {yellow}' . number_format( $Data[ 'next_level_score' ] ) .
			'{normal} XP - Remaining: {yellow}' . number_format( $Data[ 'next_level_score' ] - $Data[ 'new_score' ] ) .
			'{normal} XP - ETA: {green}' . $Hours . 'h ' . $Minutes . 'm (' . date_format( $Date , "jS H:i T" ) . ')'
		);
	}
}
while( true );

function CheckGameVersion( $Data )
{
	global $GameVersion;

	if( !isset( $Data[ 'response' ][ 'game_version' ] ) || $GameVersion >= $Data[ 'response' ][ 'game_version' ] )
	{
		return;
	}

	Msg( '{lightred}!! Game version changed to ' . $Data[ 'response' ][ 'game_version' ] );
}

function GetNextLevelProgress( $Data )
{
	$ScoreTable =
	[
		0,       // Level 1
		1200,    // Level 2
		2400,    // Level 3
		4800,    // Level 4
		12000,   // Level 5
		30000,   // Level 6
		72000,   // Level 7
		180000,  // Level 8
		450000,  // Level 9
		1200000, // Level 10
		2400000, // Level 11
		3600000, // Level 12
		4800000, // Level 13
		6000000, // Level 14
		7200000, // Level 15
		8400000, // Level 16
		9600000, // Level 17
		10800000, // Level 18
		12000000, // Level 19
		13200000, // Level 20
	];

	$PreviousLevel = $Data[ 'new_level' ] - 1;

	if( !isset( $ScoreTable[ $PreviousLevel ] ) )
	{
		Msg( '{lightred}!! Score for next level is unknown, you probably should update the script.' );
		return 0;
	}

	return ( $Data[ 'new_score' ] - $ScoreTable[ $PreviousLevel ] ) / ( $Data[ 'next_level_score' ] - $ScoreTable[ $PreviousLevel ] );
}

function GetScoreForZone( $Zone )
{
	switch( $Zone[ 'difficulty' ] )
	{
		case 1: $Score = 5; break;
		case 2: $Score = 10; break;
		case 3: $Score = 20; break;

		// Set fallback score equal to high zone score to avoid uninitialized
		// variable if new zone difficulty is introduced (e.g., for boss zones)
		default: $Score = 20;
	}

	return $Score * 120;
}

function GetNameForDifficulty( $Zone )
{
	$Boss = $Zone[ 'type' ] == 4 ? 'BOSS - ' : '';
	$Difficulty = $Zone[ 'difficulty' ];

	switch( $Zone[ 'difficulty' ] )
	{
		case 3: $Difficulty = 'High'; break;
		case 2: $Difficulty = 'Medium'; break;
		case 1: $Difficulty = 'Low'; break;
	}

	return $Boss . $Difficulty;
}

function GetPlanetState( $Planet, &$ZonePaces, $WaitTime )
{
	$Zones = SendGET( 'ITerritoryControlMinigameService/GetPlanet', 'id=' . $Planet . '&language=english' );

	if( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) )
	{
		return null;
	}

	$Zones = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ];
	$CleanZones = [];
	$HighZones = 0;
	$MediumZones = 0;
	$LowZones = 0;
	$BossZones = [];
	$ZoneMessages = [];

	$ZonePaces[ $Planet ][ 'times' ][] = microtime( true );
	$CurrentTimes = $ZonePaces[ $Planet ][ 'times' ];

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

		// Store boss zone separately to ensure it has priority later
		if( $Zone[ 'type' ] == 4 )
		{
			$BossZones[] = $Zone;
		}
		else if( $Zone[ 'type' ] != 3 )
		{
			Msg( '{lightred}!! Unknown zone type: ' . $Zone[ 'type' ] );
		}

		$Cutoff = 0.99;

		if( isset( $ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ] ) )
		{
			$Paces = $ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ];
			$Paces[] = $Zone[ 'capture_progress' ];
			$Differences = [];
			$DifferenceTimes = [];

			for( $i = count( $Paces ) - 1; $i > 0; $i-- )
			{
				$TimeDelta = $CurrentTimes[ $i ] - $CurrentTimes[ $i - 1 ];
				$DifferenceTimes[] = $TimeDelta;
				$Differences[] = ( $Paces[ $i ] - $Paces[ $i - 1 ] ) / $TimeDelta;
			}

			$TimeDelta = array_sum( $DifferenceTimes ) / count( $DifferenceTimes );
			$PaceCutoff = ( array_sum( $Differences ) / count( $Differences ) ) * $TimeDelta;
			$Cutoff = 1.0 - max( 0.01, $PaceCutoff / 7 );
			$PaceTime = $PaceCutoff > 0 ? ceil( ( 1 - $Zone[ 'capture_progress' ] ) / $PaceCutoff * $WaitTime ) : 1000;

			if( $PaceCutoff > 0.015 )
			{
				$Minutes = floor( $PaceTime / 60 );
				$Seconds = $PaceTime % 60;

				$ZoneMessages[] =
				[
					'     Zone {yellow}%3d{normal} - Captured: {yellow}%5s%%{normal} - Cutoff: {yellow}%5s%%{normal} - Pace: {yellow}%6s%%{normal} - ETA: {yellow}%2dm %2ds{normal}',
					[
						$Zone[ 'zone_position' ],
						number_format( $Zone[ 'capture_progress' ] * 100, 2 ),
						number_format( $Cutoff * 100, 2 ),
						'+' . number_format( $PaceCutoff * 100, 2 ),
						$Minutes,
						$Seconds,
					]
				];
			}
		}

		// If a zone is close to completion, skip it because we want to avoid joining a completed zone
		// Valve now rewards points, if the zone is completed before submission
		if( $Zone[ 'capture_progress' ] >= $Cutoff )
		{
			continue;
		}

		switch( $Zone[ 'difficulty' ] )
		{
			case 3: $HighZones++; break;
			case 2: $MediumZones++; break;
			case 1: $LowZones++; break;
		}

		$CleanZones[] = $Zone;
	}

	unset( $Zone );

	$ShouldTruncate = count( $ZonePaces[ $Planet ][ 'times' ] ) > 1;

	foreach( $Zones as $Zone )
	{
		if( !isset( $ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ] ) )
		{
			$ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ] = [ $Zone[ 'capture_progress' ] ];
		}
		else
		{
			if( $ShouldTruncate )
			{
				array_shift( $ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ] );
			}

			$ZonePaces[ $Planet ][ $Zone[ 'zone_position' ] ][] = $Zone[ 'capture_progress' ];
		}
	}

	if( $ShouldTruncate )
	{
		array_shift( $ZonePaces[ $Planet ][ 'times' ] );
	}

	if( !empty( $BossZones ) )
	{
		$CleanZones = $BossZones;
	}

	if( empty( $CleanZones ) )
	{
		return false;
	}

	usort( $CleanZones, function( $a, $b )
	{
		if( $b[ 'difficulty' ] === $a[ 'difficulty' ] )
		{
			if( (int)( $a[ 'capture_progress' ] * 100 ) !== (int)( $b[ 'capture_progress' ] * 100 ) )
			{
				return (int)( $a[ 'capture_progress' ] * 100000 ) - (int)( $b[ 'capture_progress' ] * 100000 );
			}

			return $b[ 'zone_position' ] - $a[ 'zone_position' ];
		}
		
		return $b[ 'difficulty' ] - $a[ 'difficulty' ];
	} );

	return [
		'high_zones' => $HighZones,
		'medium_zones' => $MediumZones,
		'low_zones' => $LowZones,
		'best_zone' => $CleanZones[ 0 ],
		'messages' => $ZoneMessages,
	];
}

function GetBestPlanetAndZone( &$ZonePaces, $WaitTime )
{
	$Planets = SendGET( 'ITerritoryControlMinigameService/GetPlanets', 'active_only=1&language=english' );

	CheckGameVersion( $Planets );

	if( empty( $Planets[ 'response' ][ 'planets' ] ) )
	{
		return null;
	}

	$Planets = $Planets[ 'response' ][ 'planets' ];

	foreach( $Planets as &$Planet )
	{
		$Planet[ 'sort_key' ] = 0;

		if( empty( $Planet[ 'state' ][ 'capture_progress' ] ) )
		{
			$Planet[ 'state' ][ 'capture_progress' ] = 0.0;
		}

		if( empty( $Planet[ 'state' ][ 'current_players' ] ) )
		{
			$Planet[ 'state' ][ 'current_players' ] = 0;
		}

		if( !isset( $ZonePaces[ $Planet[ 'id' ] ] ) )
		{
			$ZonePaces[ $Planet[ 'id' ] ] =
			[
				'times' => []
			];
		}

		do
		{
			$Zone = GetPlanetState( $Planet[ 'id' ], $ZonePaces, $WaitTime );
		}
		while( $Zone === null && sleep( 5 ) === 0 );

		if( $Zone === false )
		{
			$ZonePaces[ $Planet[ 'id' ] ] = [];
			$Planet[ 'high_zones' ] = 0;
			$Planet[ 'medium_zones' ] = 0;
			$Planet[ 'low_zones' ] = 0;
		}
		else
		{
			$Planet[ 'high_zones' ] = $Zone[ 'high_zones' ];
			$Planet[ 'medium_zones' ] = $Zone[ 'medium_zones' ];
			$Planet[ 'low_zones' ] = $Zone[ 'low_zones' ];
			$Planet[ 'best_zone' ] = $Zone[ 'best_zone' ];
		}

		Msg(
			'>> Planet {green}%3d{normal} - Captured: {green}%5s%%{normal} - High: {yellow}%2d{normal} - Medium: {yellow}%2d{normal} - Low: {yellow}%2d{normal} - Players: {yellow}%8s {green}(%s)',
			PHP_EOL,
			[
				$Planet[ 'id' ],
				number_format( $Planet[ 'state' ][ 'capture_progress' ] * 100, 2 ),
				$Planet[ 'high_zones' ],
				$Planet[ 'medium_zones' ],
				$Planet[ 'low_zones' ],
				number_format( $Planet[ 'state' ][ 'current_players' ] ),
				$Planet[ 'state' ][ 'name' ],
			]
		);

		if( $Zone !== false )
		{
			foreach( $Zone[ 'messages' ] as $Message )
			{
				Msg( $Message[ 0 ], PHP_EOL, $Message[ 1 ] );
			}

			if( $Zone[ 'best_zone' ][ 'type' ] == 4 )
			{
				Msg( '{green}>> This planet has an uncaptured boss, selecting this planet...' );

				return $Planet;
			}

			if( $Planet[ 'low_zones' ] > 0 )
			{
				$Planet[ 'sort_key' ] += 99 - $Planet[ 'low_zones' ];
			}

			if( $Planet[ 'medium_zones' ] > 0 )
			{
				$Planet[ 'sort_key' ] += pow( 10, 2 ) * ( 99 - $Planet[ 'medium_zones' ] );
			}

			if( $Planet[ 'high_zones' ] > 0 )
			{
				$Planet[ 'sort_key' ] += pow( 10, 4 ) * ( 99 - $Planet[ 'high_zones' ] );
			}
		}
	}

	usort( $Planets, function( $a, $b )
	{
		return $b[ 'sort_key' ] - $a[ 'sort_key' ];
	} );

	$Planet = $Planets[ 0 ];

	Msg(
		'>> Next Zone is {yellow}' . $Planet[ 'best_zone' ][ 'zone_position' ] .
		'{normal} (Captured: {yellow}' . number_format( $Planet[ 'best_zone' ][ 'capture_progress' ] * 100, 2 ) . '%' .
		'{normal} - Difficulty: {yellow}' . GetNameForDifficulty( $Planet[ 'best_zone' ] ) .
		'{normal}) on Planet {green}' . $Planet[ 'id' ] .
		' (' . $Planet[ 'state' ][ 'name' ] . ')'
	);

	return $Planet;
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
	}
	while( !isset( $Data[ 'response' ][ 'score' ] ) && sleep( 1 ) === 0 );

	if( !isset( $Data[ 'response' ][ 'active_planet' ] ) )
	{
		return 0;
	}

	$ActivePlanet = $Data[ 'response' ][ 'active_planet' ];

	if( $LeaveCurrentPlanet > 0 && $LeaveCurrentPlanet !== $ActivePlanet )
	{
		Msg( '   Leaving planet {green}' . $ActivePlanet . '{normal} because we want to be on {green}' . $LeaveCurrentPlanet );
		Msg( '   Time accumulated on planet {green}' . $ActivePlanet . '{normal}: {yellow}' . gmdate( 'H\h m\m s\s', $Data[ 'response' ][ 'time_on_planet' ] ) );

		echo PHP_EOL;

		SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $ActivePlanet );
	}

	return $ActivePlanet;
}

function SendPOST( $Method, $Data )
{
	return ExecuteRequest( $Method, 'https://community.steam-api.com/' . $Method . '/v0001/', $Data );
}

function SendGET( $Method, $Data )
{
	return ExecuteRequest( $Method, 'https://community.steam-api.com/' . $Method . '/v0001/?' . $Data );
}

function GetCurl( )
{
	global $c;

	if( isset( $c ) )
	{
		return $c;
	}

	$c = curl_init( );

	curl_setopt_array( $c, [
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3464.0 Safari/537.36',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_HEADER         => 1,
		CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
		CURLOPT_HTTPHEADER     =>
		[
			'Accept: */*',
			'Origin: https://steamcommunity.com',
			'Referer: https://steamcommunity.com/saliengame/play',
			'Connection: Keep-Alive',
			'Keep-Alive: timeout=300'
		],
	] );

	if ( !empty( $_SERVER[ 'LOCAL_ADDRESS' ] ) )
	{
		curl_setopt( $c, CURLOPT_INTERFACE, $_SERVER[ 'LOCAL_ADDRESS' ] );
	}

	if( defined( 'CURL_HTTP_VERSION_2_0' ) )
	{
		curl_setopt( $c, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0 );
	}

	return $c;
}

function ExecuteRequest( $Method, $URL, $Data = [] )
{
	$c = GetCurl( );

	curl_setopt( $c, CURLOPT_URL, $URL );

	if( !empty( $Data ) )
	{
		curl_setopt( $c, CURLOPT_POST, 1 );
		curl_setopt( $c, CURLOPT_POSTFIELDS, $Data );
	}
	else
	{
		curl_setopt( $c, CURLOPT_HTTPGET, 1 );
	}

	do
	{
		$Data = curl_exec( $c );

		$HeaderSize = curl_getinfo( $c, CURLINFO_HEADER_SIZE );
		$Header = substr( $Data, 0, $HeaderSize );
		$Data = substr( $Data, $HeaderSize );

		preg_match( '/[Xx]-eresult: ([0-9]+)/', $Header, $EResult ) === 1 ? $EResult = (int)$EResult[ 1 ] : $EResult = 0;

		if( $EResult !== 1 )
		{
			Msg( '{lightred}!! ' . $Method . ' failed - EResult: ' . $EResult . ' - ' . $Data );

			if( preg_match( '/^[Xx]-error_message: (?:.+)$/m', $Header, $ErrorMessage ) === 1 )
			{
				Msg( '{lightred}!! API failed - ' . $ErrorMessage[ 0 ] );
			}

			if( $EResult === 15 && $Method === 'ITerritoryControlMinigameService/RepresentClan' )  // EResult.AccessDenied
			{
				echo PHP_EOL;

				Msg( '{green}This script was designed for SteamDB' );
				Msg( '{green}If you want to support it, join the group and represent it in game:' );
				Msg( '{yellow}https://steamcommunity.com/groups/SteamDB' );

				sleep( 10 );
			}
			else if( $EResult === 11 ) // EResult.InvalidState
			{
				global $LastKnownPlanet;
				$LastKnownPlanet = 0;
			}
			else if( $EResult === 0 ) // timeout
			{
				Msg( '{lightred}-- This problem should resolve itself, wait for a couple of minutes' );
			}
			else if( $EResult === 10 ) // EResult.Busy
			{
				$Data = '{}'; // Retry this exact request

				Msg( '{lightred}-- EResult 10 means Steam is busy' );

				sleep( 3 );
			}
		}

		$Data = json_decode( $Data, true );
		$Data[ 'eresult' ] = $EResult;
	}
	while( !isset( $Data[ 'response' ] ) && sleep( 1 ) === 0 );

	return $Data;
}

function GetRepositoryScriptHash( &$RepositoryScriptETag, $LocalScriptHash )
{
	$c_r = curl_init( );

	$Time = time();
	$Time = $Time - ( $Time % 10 );

	curl_setopt_array( $c_r, [
		CURLOPT_URL            => 'https://raw.githubusercontent.com/SteamDatabase/SalienCheat/master/cheat.php?_=' . $Time,
		CURLOPT_USERAGENT      => 'SalienCheat (https://github.com/SteamDatabase/SalienCheat/)',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING       => 'gzip',
		CURLOPT_TIMEOUT        => 5,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_CAINFO         => __DIR__ . '/cacert.pem',
		CURLOPT_HEADER         => 1,
		CURLOPT_HTTPHEADER     =>
		[
			'If-None-Match: "' . $RepositoryScriptETag . '"'
		]
	] );

	$Data = curl_exec( $c_r );

	$HeaderSize = curl_getinfo( $c_r, CURLINFO_HEADER_SIZE );
	$Header = substr( $Data, 0, $HeaderSize );
	$Data = substr( $Data, $HeaderSize );

	curl_close( $c_r );

	if( preg_match( '/ETag: "([a-z0-9]+)"/', $Header, $ETag ) === 1 )
	{
		$RepositoryScriptETag = $ETag[ 1 ];
	}

	return strlen( $Data ) > 0 ? sha1( trim( $Data ) ) : $LocalScriptHash;
}

function Msg( $Message, $EOL = PHP_EOL, $printf = [] )
{
	global $DisableColors;

	$Message = str_replace(
		[
			'{normal}',
			'{green}',
			'{yellow}',
			'{lightred}',
			'{grey}',
			'{background-blue}',
		],
		$DisableColors ? '' : [
			"\033[0m",
			"\033[0;32m",
			"\033[1;33m",
			"\033[1;31m",
			"\033[0;36m",
			"\033[37;44m",
		],
	$Message, $Count );

	if( $Count > 0 && !$DisableColors )
	{
		$Message .= "\033[0m";
	}

	$Message = '[' . date( 'H:i:s' ) . '] ' . $Message . $EOL;

	if( !empty( $printf ) )
	{
		array_unshift( $printf, $Message );
		call_user_func_array( 'printf', $printf );
	}
	else
	{
		echo $Message;
	}
}
