<?php
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'db_file.php';

// music handler
class db_audio extends db_file
{
	const DATABASE = 'audio';
	
	const NAME = 'Audio from Database';

	static function init()
	{
		// include the id handler
		include_once setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';
		
		// set up id3 reader incase any files need it
		$GLOBALS['getID3'] = new getID3();
	}

	static function columns()
	{
		return array_keys(self::struct());
	}
	
	static function struct()
	{
		return array(
			'Filepath' 	=> 'TEXT',
			'Title'		=> 'TEXT',
			'Artist' 	=> 'TEXT',
			'Album'		=> 'TEXT',
			'Track'		=> 'INT',
			'Year'		=> 'INT',
			'Genre'		=> 'TEXT',
			'Length'	=> 'DOUBLE',
			'Comments'	=> 'TEXT',
			'Bitrate'	=> 'DOUBLE'
		);
	}

	static function handles($file)
	{
		$file = str_replace('\\', '/', $file);
		if(USE_ALIAS == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
		
		// get file extension
		$type = getExtType($file);
		
		if( $type == 'audio' )
		{
			return true;
		}
	
		return false;

	}

	static function handle($file, $force = false)
	{
		$file = str_replace('\\', '/', $file);
		
		if(self::handles($file))
		{
			// check to see if it is in the database
			$db_audio = $GLOBALS['database']->query(array(
					'SELECT' => self::DATABASE,
					'COLUMNS' => 'id',
					'WHERE' => 'Filepath = "' . addslashes($file) . '"',
					'LIMIT' => 1
				)
			, false);
			
			// try to get music information
			if( count($db_audio) == 0 )
			{
				return self::add($file);
			}
			elseif($force)
			{
				return self::add($file, $db_audio[0]['id']);
			}
		}
		return false;
	}
	
	static function getInfo($file)
	{
		$file = str_replace('/', DIRECTORY_SEPARATOR, $file);
		
		$info = $GLOBALS['getID3']->analyze($file);
		getid3_lib::CopyTagsToComments($info);

		$fileinfo = array();
		$fileinfo['Filepath'] = addslashes(str_replace('\\', '/', $file));
		$fileinfo['Title'] = @addslashes($info['comments_html']['title'][0]);
		$fileinfo['Artist'] = @addslashes($info['comments_html']['artist'][0]);
		$fileinfo['Album'] = @addslashes($info['comments_html']['album'][0]);
		$fileinfo['Track'] = @$info['comments_html']['track'][0];
		$fileinfo['Year'] = @$info['comments_html']['year'][0];
		$fileinfo['Genre'] = @addslashes($info['comments_html']['genre'][0]);
		$fileinfo['Length'] = @$info['playtime_seconds'];
		$fileinfo['Comments'] = @addslashes($info['comments_html']['comments'][0]);
		$fileinfo['Bitrate'] = @$info['bitrate'];
		
		return $fileinfo;
	}

	static function add($file, $audio_id = NULL)
	{
		if(!isset($GLOBALS['getID3']))
			self::init();
		
		// pull information from $info
		$fileinfo = self::getInfo($file);
	
		if( $audio_id == NULL )
		{
			PEAR::raiseError('Adding audio: ' . $file, E_DEBUG);
			
			// add to database
			$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo), false);
			
			return $id;
		}
		else
		{
			PEAR::raiseError('Modifying audio: ' . $file, E_DEBUG);
			
			// update database
			$id = $GLOBALS['database']->query(array('UPDATE' => self::DATABASE, 'VALUES' => $fileinfo, 'WHERE' => 'id=' . $audio_id), false);
		
			return $audio_id;
		}
		
	}
	
	
	static function get($request, &$count)
	{
		return parent::get($request, $count, get_class());
	}
	
	static function remove($file)
	{
		parent::remove($file, get_class());
	}

	static function cleanup()
	{
		// call default cleanup function
		parent::cleanup(get_class());
	}

}

?>