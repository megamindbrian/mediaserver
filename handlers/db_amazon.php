<?php

// provide links to amazon
// handles audio files, but after information is loaded search amazon for artist and album and compare track listings
// ignore stuff that can't be found, or add it to database and unknown entry for files
// store an album in 1 row with the album art, and save the songId in a cell
// handle movies, and search for movieId
//  use parseFilename to search with
define('AMAZON_DEV_KEY', '1D9T2665M4N4A7ACEZR2');
define('AMAZON_SERVER', 'ecs.amazonaws.com');

include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'db_file.php';

// music handler
class db_amazon extends db_file
{
	const DATABASE = 'amazon';
	
	const NAME = 'Amazon from Database';

	// initialize any extra tools this handler needs
	static function init()
	{
		if(file_exists(setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php'))
		{
			// include the id handler
			include_once setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';
			
			// set up id3 reader incase any files need it
			$GLOBALS['getID3'] = new getID3();
		}
		else
			PEAR::raiseError('getID3() missing from include directory! Archive handlers cannot function properly.', E_DEBUG);
		
		if(file_exists(setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php'))
		{
			// include snoopy to download pages
			include_once setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'Snoopy.class.php';
			
			// set up id3 reader incase any files need it
			$GLOBALS['snoopy'] = new Snoopy();
		}
		else
			PEAR::raiseError('getID3() missing from include directory! Archive handlers cannot function properly.', E_DEBUG);
			
		return false;
	}

	static function columns()
	{
		return array_keys(self::struct());
	}

	static function struct()
	{
		return array(
			'AmazonId' 		=> 'TEXT',
			'Filepath' 		=> 'TEXT',
			'AmazonType' 	=> 'TEXT',
			'AmazonInfo' 	=> 'TEXT',
			'Matches' 		=> 'TEXT',
			'Thumbnail' 	=> 'BLOB'
		);
	}

	static function handles($file)
	{
		$file = str_replace('\\', '/', $file);
		if(USE_ALIAS == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
		
		if(db_audio::handles($file) || db_movies::handles($file))
		{
			return true;
		}
		elseif(is_dir($file))
		{
			// check if it is a directory that can be found
			if($file[strlen($file)-1] == '/') $file = substr($file, 0, strlen($file)-1);
						
			$dirs = split('/', $file);
			$tokens = tokenize($file);
			
			$album = @$dirs[count($dirs)-1];
			$artist = @$dirs[count($dirs)-2];
			
			if(isset($album) && isset($artist) && $album != '' && $artist != '' && $artist != 'Music' && in_array('music', $tokens['Unique']))
			{
				return true;
			}
		}
		
		return false;
	}

	static function handle($file, $force = false)
	{
		$file = str_replace('\\', '/', $file);
		
		if(self::handles($file))
		{
			if(db_audio::handles($file) || is_dir($file))
			{
				if(is_dir($file))
				{
					if($file[strlen($file)-1] == '/') $file = substr($file, 0, strlen($file)-1);
					$dirs = split('/', $file);
					
					$album = @$dirs[count($dirs)-1];
					$artist = @$dirs[count($dirs)-2];
				}
				else
				{
					// get information from database
					$audio = $GLOBALS['database']->query(array(
							'SELECT' => db_audio::DATABASE,
							'WHERE' => 'Filepath = "' . addslashes($file) . '"',
							'LIMIT' => 1
						)
					, false);
					if(count($audio) > 0)
					{
						$artist = $audio[0]['Artist'];
						$album = $audio[0]['Album'];
					}
					// try and get information from file
					else
					{
						$info = $GLOBALS['getID3']->analyze(str_replace('/', DIRECTORY_SEPARATOR, $file));
						getid3_lib::CopyTagsToComments($info);
						
						$artist = @$info['comments_html']['artist'][0];
						$album = @$info['comments_html']['album'][0];
					}
				}
				
				$amazon = $GLOBALS['database']->query(array(
						'SELECT' => self::DATABASE,
						'WHERE' => 'Filepath = "' . addslashes($artist . "\n" . $album) . '"',
						'LIMIT' => 1
					)
				, false);
				
				if( count($amazon) == 0 )
				{
					return self::add_music($artist, $album);
				}
				elseif($force)
				{
					// don't modify because amazon information doesn't change
					return $amazon[0]['id'];
				}
			}
			elseif(db_movies::handles($file))
			{
				// get information from database
				// try and get information from file
			}
		}
		return false;
	}
	
	static function getMusicInfo($artist, $album)
	{
		
		$fileinfo = array();
		$fileinfo['Filepath'] = addslashes($artist . "\n" . $album);
		$fileinfo['AmazonId'] = '';
		$fileinfo['AmazonType'] = 'Music';
		$fileinfo['AmazonInfo'] = '';
		$fileinfo['Matches'] = '';
		$fileinfo['Thumbnail'] = '';
		
		$artist_tokens = tokenize($artist);
		$album_tokens = tokenize($album);
		
		// create url
		// do soundtracks seperately because they will already have a very limit selection
		if(in_array('soundtrack', $album_tokens['All']))
		{
			$tmp_some_tokens = array_unique(array_merge($artist_tokens['Some'], $album_tokens['Some']));
			$url = 'http://' . AMAZON_SERVER . '/onca/xml?Service=AWSECommerceService&Version=2005-03-23&Operation=ItemSearch&ContentType=text%2Fxml&SubscriptionId=' . AMAZON_DEV_KEY;
			$url .= '&SearchIndex=Music&Keywords=' . urlencode(join(' ', $tmp_some_tokens)) . '&ResponseGroup=Images,Similarities,Small,Tracks';
			$GLOBALS['snoopy']->fetch($url);
			$contents = $GLOBALS['snoopy']->results;
		}
		else
		{
			$url = 'http://' . AMAZON_SERVER . '/onca/xml?Service=AWSECommerceService&Version=2005-03-23&Operation=ItemSearch&ContentType=text%2Fxml&SubscriptionId=' . AMAZON_DEV_KEY;
			$url .= '&SearchIndex=Music&Artist=' . urlencode(join(' ', $artist_tokens['Some'])) . '&Title=' . urlencode(join(' ', $album_tokens['Few'])) . '&ResponseGroup=Images,Similarities,Small,Tracks';
			
			$GLOBALS['snoopy']->fetch($url);
			$contents = $GLOBALS['snoopy']->results;
			
			if(preg_match('/\<Errors\>/i', $contents) !== 0)
			{
				$url = 'http://' . AMAZON_SERVER . '/onca/xml?Service=AWSECommerceService&Version=2005-03-23&Operation=ItemSearch&ContentType=text%2Fxml&SubscriptionId=' . AMAZON_DEV_KEY;
				$url .= '&SearchIndex=Music&Keywords=' . urlencode(join(' ', $artist_tokens['Some'])) . '&Title=' . urlencode(join(' ', $album_tokens['Few'])) . '&ResponseGroup=Images,Similarities,Small,Tracks';
				$GLOBALS['snoopy']->fetch($url);
				$contents = $GLOBALS['snoopy']->results;
			}
			
			if(preg_match('/\<Errors\>/i', $contents) !== 0)
			{
				$tmp_some_tokens = array_unique(array_merge($artist_tokens['Some'], $album_tokens['Some']));
				$url = 'http://' . AMAZON_SERVER . '/onca/xml?Service=AWSECommerceService&Version=2005-03-23&Operation=ItemSearch&ContentType=text%2Fxml&SubscriptionId=' . AMAZON_DEV_KEY;
				$url .= '&SearchIndex=Music&Keywords=' . urlencode(join(' ', $tmp_some_tokens)) . '&ResponseGroup=Images,Similarities,Small,Tracks';
				$GLOBALS['snoopy']->fetch($url);
				$contents = $GLOBALS['snoopy']->results;
			}
		}
		
		// check if it was found
		if(preg_match('/\<Errors\>/i', $contents) !== 0)
		{
			return $fileinfo;
		}
		else
		{
			$match = preg_match('/\<Item\>(.*)\<\/Item\>/i', $contents, $matches);
			if(isset($matches[1]))
			{
				$items = preg_split('/\<\/Item\>\<Item\>/i', $matches[1]);
				
				// pick out best match
				$best = '';
				$best_tracks = '';
				$best_count = 0;
				$all_matches = array();
				foreach($items as $i => $item)
				{
					// first get the album and match that
					$match = preg_match('/\<Title\>(.*)\<\/Title\>/i', $item, $matches);
					$title = $matches[1];
					$tmp_album_tokens = tokenize($title);
					
					$album_count = count(array_intersect($tmp_album_tokens['All'], $album_tokens['All']));
					
					// now match artists
					$match = preg_match('/\<Artist\>(.*)\<\/Artist\>/i', $item, $matches);
					if(!isset($matches[1]))
					{
						$artist_count = 0;
					}
					else
					{
						$artists = preg_split('/\<\/Artist\>\<Artist\>/i', $matches[1]);
						
						$artist_count = 0;
						foreach($artists as $i => $tmp_artist)
						{
							$tmp_artist_tokens = tokenize($tmp_artist);
							$tmp_count = count(array_intersect($tmp_artist_tokens['All'], $artist_tokens['All']));
							if($tmp_count > $artist_count) $artist_count = $tmp_count;
						}
					}
					
					// track differences negatively affect the count
					$tracks = db_audio::get(array('search_Artist' => '+' . join(' +', $artist_tokens['Few']), 'search_Album' => '+' . join(' +', $album_tokens['Few'])), $tmp_count);
					
					// track counts are only affected if there is matching audio
					$track_count = 0;
					$match = preg_match('/\<Tracks[^\>]*\>(.*)\<\/Tracks\>/i', $item, $matches);
					$disks = array();
					if(isset($matches[1]))
					{
						$match = preg_match('/\<Disc[^\>]*\>(.*)\<\/Disc\>/i', $matches[1], $matches);
						$disks = preg_split('/\<\/Disc\>\<Disc[^\>]*\>/i', $matches[1]);
						foreach($disks as $i => $disk)
						{
							$match = preg_match('/\<Track[^\>]*\>(.*)\<\/Track\>/i', $disk, $matches);
							$disks[$i] = preg_split('/\<\/Track\>\<Track[^\>]*\>/i', $matches[1]);
							
							if(count($tracks) > 0)
							{
								if(count($disks[$i]) == count($tracks)) $track_count = 1;
								elseif($track_count == 0) $track_count = -1;
							}
						}
					}
					else
					{
						$track_count = -1;
					}
					
					// if there are multiple disks add the words to the album tokens and recount
					if(count($disks) > 1)
					{
						$tmp_artist_tokens['All'][] = 'disk';
						$tmp_artist_tokens['All'][] = 'disc';
						$tmp_artist_tokens['All'][] = 'volume';
						
						$album_count = count(array_intersect($tmp_album_tokens['All'], $album_tokens['All']));
					}
					
					// set the new count
					if($album_count + $artist_count + $track_count > $best_count)
					{
						$best_count = $album_count + $artist_count + $track_count;
						$best = $item;
						$best_tracks = $disks;
					}
					
					// record all results
					$match = preg_match('/\<ASIN\>([a-z0-9]*)\<\/ASIN\>/i', $item, $matches);
					if(isset($matches[1]))
						$all_matches[] = addslashes($matches[1]);
				}
				
				// parse single result
				$match = preg_match('/\<ASIN\>([a-z0-9]*)\<\/ASIN\>/i', $best, $matches);
				if(!isset($matches[1]))
				{
					PEAR::raiseError('Error reading AmazonId: ' . htmlspecialchars($best), E_DEBUG);
				}
				else
				{
					$fileinfo['AmazonId'] = addslashes($matches[1]);
				}
				$fileinfo['Matches'] = join(';', $all_matches);
				
				// parse tracks for later use
				$fileinfo['AmazonInfo'] = addslashes(serialize($best_tracks));
				
				$match = preg_match('/\<LargeImage\>\<URL\>(.*)\<\/URL\>/i', $best, $matches);
				if(isset($matches[1]))
				{
					$GLOBALS['snoopy']->fetch($matches[1]);
					$fileinfo['Thumbnail'] = addslashes($GLOBALS['snoopy']->results);
				}
			}
		}
		
		return $fileinfo;
	}

	static function add_music($artist, $album)
	{
		// check for required snoopy
		if(!isset($GLOBALS['snoopy']))
		self::init();
		
		// pull information from $info
		$fileinfo = self::getMusicInfo($artist, $album);
	
		PEAR::raiseError('Adding Amazon Music: ' . $artist . ' - ' . $album, E_DEBUG);
		
		// add to database
		$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo), false);

		return $id;
	}
	
	static function add_movie($title)
	{
		// pull information from $info
		$fileinfo = self::getMovieInfo($title);

		PEAR::raiseError('Adding Amazon Movie: ' . $title, E_DEBUG);
		
		// add to database
		$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo), false);
		
		return $id;
	}
	
	static function get($request, &$count)
	{
		
		// modify the request
		if(isset($request['file']))
		{
			if(db_audio::handles($request['file']))
			{
				$audio = db_audio::get(array('file' => $request['file'], 'audio_id' => (isset($request['audio_id'])?$request['audio_id']:0)), $tmp_count);
				if(count($audio) > 0)
				{
					$files = $GLOBALS['database']->query(array(
							'SELECT' => self::DATABASE,
							'WHERE' => 'Filepath = "' . addslashes($audio[0]['Artist'] . "\n" . $audio[0]['Album']) . '"',
							'LIMIT' => 1
						)
					, true);
				}
				else
				{
					$files = array();
				}
			}
			elseif(db_movies::handles($request['file']))
			{
				$movie = db_movies::get(array('file' => $request['file'], 'video_id' => (isset($request['video_id'])?$request['video_id']:0)), $tmp_count);
				if(count($movie) > 0)
				{
					$files = $GLOBALS['database']->query(array(
							'SELECT' => self::DATABASE,
							'WHERE' => 'Filepath = "' . addslashes($movie[0]['Title']) . '"',
							'LIMIT' => 1
						)
					, true);
				}
				else
				{
					$files = array();
				}
			}
			elseif(is_dir($request['file']))
			{
				if($request['file'][strlen($request['file'])-1] == '/') $request['file'] = substr($request['file'], 0, strlen($request['file'])-1);
				$dirs = split('/', $request['file']);
				
				$album = $dirs[count($dirs)-1];
				$artist = @$dirs[count($dirs)-2];
				$files = $GLOBALS['database']->query(array(
						'SELECT' => self::DATABASE,
						'WHERE' => 'Filepath = "' . addslashes($artist . "\n" . $album) . '"',
						'LIMIT' => 1
					)
				, true);
			}
			else
			{
				$files = array();
			}
			$count = count($files);
		}
		else
		{
			// change some request vars
			if(isset($request['dir']))
			{
				$request['dir'] = str_replace('\\', '/', $request['dir']);
				if($request['dir'][0] == '/') $request['dir'] = substr($request['dir'], 1);
				if($request['dir'][strlen($request['dir'])-1] == '/') $request['dir'] = substr($request['dir'], 0, strlen($request['dir'])-1);
				
				if(strpos($request['dir'], '/') !== false)
				{
					$dirs = split('/', $request['dir']);
					$title = $dirs[0] . "\n" . $dirs[1];
				}
				else
				{
					$title = $request['dir'];
				}
				unset($request['dir']);
				$amazon = $GLOBALS['database']->query(array(
						'SELECT' => self::DATABASE,
						'WHERE' => 'Filepath = "' . addslashes($title) . '"',
						'LIMIT' => 1
					)
				, true);
				
				if(count($amazon) > 0)
				{
					if($amazon[0]['AmazonType'] = 'Music')
					{
						$title = split("\n", $title);
						$request['search_Artist'] = '=' . $title[0] . '=';
						$request['search_Album'] = '=' . $title[1] . '=';
						$files = db_audio::get($request, $tmp_count);
						
						if($amazon[0]['AmazonId'] != '')
							$amazon[0]['AmazonLink'] = 'http://www.amazon.com/dp/' . $amazon[0]['AmazonId'] . '/?SubscriptionId=' . AMAZON_DEV_KEY;
						$amazon[0]['Handler'] = 'db_audio';
						
						foreach($files as $i => $file)
						{
							$amazon[0]['Filepath'] = $file['Filepath'];
							$files[$i] = $amazon[0];
						}
						
						return $files;
					}
				}
				else
				{
					$files = array();
					$count = 0;
				}
			}
			else
			{
				if(!isset($request['search_AmazonId']))
					$request['search_AmazonId'] = '/[a-z0-9]+/';
				// get files
				$files = parent::get($request, $count, get_class());
			}
		}
		
		// make some changes
		foreach($files as $i => $file)
		{
			if($file['AmazonId'] != '')
				$files[$i]['AmazonLink'] = 'http://www.amazon.com/dp/' . $file['AmazonId'] . '/?SubscriptionId=' . AMAZON_DEV_KEY;
			if(isset($request['file']))
				$files[$i]['Filepath'] = $request['file'];
			else
				$files[$i]['Filepath'] = '/' . join('/', split("\n", $file['Filepath'])) . '/';
			$files[$i]['Filetype'] = 'FOLDER';
		}
		
		return $files;
	}

	static function remove($file, $handler = NULL)
	{
		// remove the amazon entry for whatever is passed in
		//  but only if the artist/album doesn't exist in the database anymore
		//  so only remove when the last file is removed
		//    always remove directories
		//    
	}


	static function cleanup()
	{
	}
	
}
?>