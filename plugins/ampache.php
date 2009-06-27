<?php
set_time_limit(0);

// this script uses a lot of custom queries because we don't need all the information the module api would return
//  we also want it to be pretty fast

// include some common stuff
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

// do some ampache compatibility stuff
$fp = fopen('/tmp/test.txt', 'a');
fwrite($fp, var_export($_SERVER, true));
fclose($fp);

// check for the action
if(isset($_REQUEST['action']))
{
	switch($_REQUEST['action'])
	{
		case 'ping':
		// report the session has expired
		break;
		case 'handshake':
		// send out the ssid information
		// send out some counts instead of running select
		// song count
		$result = $GLOBALS['database']->query(array('SELECT' => 'audio', 'COLUMNS' => 'count(*)'));
		$song_count = $result[0]['count(*)'];
		
		// album count
		$result = $GLOBALS['database']->query(array(
			'SELECT' => '(' . $GLOBALS['database']->statement_builder(array(
				'SELECT' => 'audio',
				'GROUP' => 'Album'
			)) . ') as counter',
			'COLUMNS' => 'count(*)'
		));
		$album_count = $result[0]['count(*)'];
		
		// artist count
		$result = $GLOBALS['database']->query(array(
			'SELECT' => '(' . $GLOBALS['database']->statement_builder(array(
				'SELECT' => 'audio',
				'GROUP' => 'Artist'
			)) . ') as counter',
			'COLUMNS' => 'count(*)'
		));
		$artist_count = $result[0]['count(*)'];
		
		// genre count
		$result = $GLOBALS['database']->query(array(
			'SELECT' => '(' . $GLOBALS['database']->statement_builder(array(
				'SELECT' => 'audio',
				'GROUP' => 'Genre'
			)) . ') as counter',
			'COLUMNS' => 'count(*)'
		));
		$genre_count = $result[0]['count(*)'];
		
		break;
		case 'artists':
		// get a simple list of all the artists
		$result = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'GROUP' => 'Artist,Album',
			'COLUMNS' => 'MIN(id) as id,Artist,count(*) as SongCount'
		));
		
		// go through and merge the artist album and counts
		$files = array();
		foreach($result as $i => $artist)
		{
			if(!isset($artists[$artist['Artist']]))
			{
				$files[$artist['Artist']] = array(
					'Artist' => $artist['Artist'],
					'SongCount' => $artist['SongCount'],
					'AlbumCount' => 1,
					'id' => $artist['id']
				);
			}
			else
			{
				$files[$artist['Artist']]['SongCount'] += $artist['SongCount'];
				$files[$artist['Artist']]['AlbumCount'] += 1;
			}
		}
		
		$files = array_values($files);
		break;
		case 'artist_albums':
		// get a list of albums for a particular artist
		// first look up song by supplied ID
		$result = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'WHERE' => 'id = ' . intval($_REQUEST['filter'])
		));
		
		// get the list of albums
		$files = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'GROUP' => 'Album',
			'COLUMNS' => '*,MIN(id) as id,count(*) as SongCount',
			'WHERE' => 'Artist = "' . addslashes($result[0]['Artist']) . '"'
		));
		
		break;
		case 'album_songs':
		// get a list of songs for a particular album
		// first look up song by supplied ID
		$artist_album = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'WHERE' => 'id = ' . intval($_REQUEST['filter'])
		));
		
		// get the id for genre
		$result = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'COLUMNS' => 'MIN(id) as id',
			'WHERE' => 'Genre = ' . addslashes($artist_album[0]['Genre'])
		));
		$genre_id = $result[0]['id'];
		
		// get the min id for artist
		$result = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'COLUMNS' => 'MIN(id) as id',
			'WHERE' => 'Artist = ' . addslashes($artist_album[0]['Artist'])
		));
		$artist_id = $result[0]['id'];
		
		// get the list of songs
		$files = $GLOBALS['database']->query(array(
			'SELECT' => 'audio',
			'WHERE' => 'Album = "' . addslashes($artist_album[0]['Album']) . '" AND Artist = "' . addslashes($artist_album[0]['Artist']) . '"'
		));
		
		break;
		default:
			$_REQUEST['action'] = 'ping';
	}
}



// display template
// this plugin will probably never use a smarty template
include $GLOBALS['templates']['TEMPLATE_AMPACHE'];




?>