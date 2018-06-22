<?php

set_time_limit( 0 );

$Token = trim( file_get_contents( __DIR__ . '/token.txt' ) );
$ParsedToken = json_decode( $Token, true );

if( isset( $ParsedToken[ 'token' ] ) )
{
	$Token = $ParsedToken[ 'token' ];
}

unset( $ParsedToken );

// Please do not change our clanid if you are going to use this script
// If you want to cheat for your own group, come up with up with your own approach, thank you
SendPOST( 'ITerritoryControlMinigameService/RepresentClan', 'clanid=4777282&access_token=' . $Token );

lol_using_goto_in_2018:

do
{
	$CurrentPlanet = GetFirstAvailablePlanet();
}
while( !$CurrentPlanet && sleep( 5 ) === 0 );

// Leave current game before trying to switch planets (it will report InvalidState otherwise)
LeaveCurrentGame( $Token, true );

SendPOST( 'ITerritoryControlMinigameService/JoinPlanet', 'id=' . $CurrentPlanet . '&access_token=' . $Token );

// Set the planet to what Steam thinks is the active one, even though we sent JoinPlanet request
$CurrentPlanet = LeaveCurrentGame( $Token, false );

do
{
	do
	{
		$Zone = GetFirstAvailableZone( $CurrentPlanet );
	}
	while( !$Zone && sleep( 5 ) === 0 );

	$Zone = SendPOST( 'ITerritoryControlMinigameService/JoinZone', 'zone_position=' . $Zone[ 'zone_position' ] . '&access_token=' . $Token );

	if( empty( $Zone[ 'response' ][ 'zone_info' ] ) )
	{
		Msg( 'Failed to join a zone, waiting 15 seconds and trying again' );

		sleep( 15 );

		goto lol_using_goto_in_2018;
	}

	$Zone = $Zone[ 'response' ][ 'zone_info' ];
	
	Msg( 'Joined zone ' . $Zone[ 'zone_position' ] . ' - Captured: ' . number_format( $Zone[ 'capture_progress' ] * 100, 2 ) . '% - Difficulty: ' . $Zone[ 'difficulty' ] );
	
	sleep( 120 );
	
	$Data = SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );

	if( isset( $Data[ 'response' ][ 'new_score' ] ) )
	{
		$Data = $Data[ 'response' ];

		Msg( 'Score: ' . $Data[ 'old_score' ] . ' => ' . $Data[ 'new_score' ] . ' (next level: ' . $Data[ 'next_level_score' ] . ') - Current level: ' . $Data[ 'new_level' ] );
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
	$Zones = SendGET( 'GetPlanet', 'id=' . $Planet );

	if( empty( $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ] ) )
	{
		return null;
	}

	$Zones = $Zones[ 'response' ][ 'planets' ][ 0 ][ 'zones' ];
	$CleanZones = [];
	
	foreach( $Zones as $Zone )
	{
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
			Msg( 'Unknown zone type: ' . $Zone[ 'type' ] );
		}

		if( $Zone[ 'capture_progress' ] < 0.95 )
		{
			$CleanZones[] = $Zone;
		}
	}
	
	if( empty( $CleanZones ) )
	{
		return null;
	}

	usort( $CleanZones, function( $a, $b )
	{
		if( $b[ 'difficulty' ] === $a[ 'difficulty' ] )
		{
			return $b[ 'zone_position' ] - $a[ 'zone_position' ];
		}
		
		return $b[ 'difficulty' ] - $a[ 'difficulty' ];
	} );

	return $CleanZones[ 0 ];
}

function GetFirstAvailablePlanet()
{
	$Planets = SendGET( 'GetPlanets', 'active_only=1' );

	if( empty( $Planets[ 'response' ][ 'planets' ] ) )
	{
		return null;
	}

	$Planets = $Planets[ 'response' ][ 'planets' ];

	usort( $Planets, function( $a, $b )
	{
		if( $b[ 'state' ][ 'difficulty' ] === $a[ 'state' ][ 'difficulty' ] )
		{
			return $a[ 'state' ][ 'current_players' ] - $b[ 'state' ][ 'current_players' ];
		}
		
		return $b[ 'state' ][ 'difficulty' ] - $a[ 'state' ][ 'difficulty' ];
	} );

	foreach( $Planets as $Planet )
	{
		if( !$Planet[ 'state' ][ 'captured' ]  )
		{
			Msg( 'Got planet ' . $Planet[ 'id' ] . ' with ' . $Planet[ 'state' ][ 'current_players' ] . ' joined players' );

			return $Planet[ 'id' ];
		}
	}
}

function LeaveCurrentGame( $Token, $LeaveCurrentPlanet )
{
	do
	{
		$Data = SendPOST( 'ITerritoryControlMinigameService/GetPlayerInfo', 'access_token=' . $Token );
	}
	while( !isset( $Data[ 'response' ][ 'clan_info' ] ) );

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
		Msg( 'Sending ' . $Method . '...' );

		$Data = curl_exec( $c );

		$HeaderSize = curl_getinfo( $c, CURLINFO_HEADER_SIZE );
		$Header = substr( $Data, 0, $HeaderSize );
		$Data = substr( $Data, $HeaderSize );

		preg_match( '/X-eresult: ([0-9]+)/', $Header, $EResult ) === 1 ? $EResult = (int)$EResult[ 1 ] : $EResult = 0;

		Msg( 'EResult: ' . $EResult . ' - ' . $Data );

		$Data = json_decode( $Data, true );
	}
	while( !isset( $Data[ 'response' ] ) );

	curl_close( $c );
	
	return $Data;
}

function SendGET( $Method, $Data )
{
	$c = curl_init( );

	curl_setopt_array( $c, [
		CURLOPT_URL            => 'https://community.steam-api.com/ITerritoryControlMinigameService/' . $Method . '/v0001/?' . $Data,
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
	while( !isset( $Data[ 'response' ] ) );

	curl_close( $c );
	
	return $Data;
}

function Msg( $Message )
{
	echo date( DATE_RSS ) . ' - ' . $Message . PHP_EOL;
}
