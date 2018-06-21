<?php

$Token = trim( file_get_contents( __DIR__ . '/token.txt' ) );

SendPOST( 'ITerritoryControlMinigameService/RepresentClan', 'clanid=4777282&access_token=' . $Token );

lol_using_goto_in_2018:

$Data = SendPOST( 'ITerritoryControlMinigameService/GetPlayerInfo', 'access_token=' . $Token );

if( isset( $Data[ 'response' ][ 'active_zone_game' ] ) )
{
	SendPOST( 'IMiniGameService/LeaveGame', 'access_token=' . $Token . '&gameid=' . $Data[ 'response' ][ 'active_zone_game' ] );
}

do
{
	$CurrentPlanet = GetFirstAvailablePlanet();
}
while( !$CurrentPlanet && sleep( 5 ) === 0 );

SendPOST( 'ITerritoryControlMinigameService/JoinPlanet', 'id=' . $CurrentPlanet . '&access_token=' . $Token );

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
	
	SendPOST( 'ITerritoryControlMinigameService/ReportScore', 'access_token=' . $Token . '&score=' . GetScoreForZone( $Zone ) . '&language=english' );
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
	
	return $Score * 120 - $Score;
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
		if( !$Zone[ 'captured' ] && $Zone[ 'capture_progress' ] < 0.95 )
		{
			$CleanZones[] = $Zone;
		}
	}
	
	if( empty( $CleanZones  ) )
	{
		return null;
	}

	usort( $CleanZones, function( $a, $b )
	{
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

	foreach( $Planets[ 'response' ][ 'planets' ] as $Planet )
	{
		if( !$Planet[ 'state' ][ 'captured' ]  )
		{
			Msg( 'Got planet ' . $Planet[ 'id' ] );

			return $Planet[ 'id' ];
		}
	}
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
		
		Msg( $Data );
		
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
