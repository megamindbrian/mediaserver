<?php

$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

require_once LOCAL_ROOT . 'modules' . DIRECTORY_SEPARATOR . 'db_file.php';

// include the id handler
require_once LOCAL_ROOT . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';
require_once LOCAL_ROOT . 'include' . DIRECTORY_SEPARATOR . 'File' . DIRECTORY_SEPARATOR . 'Archive.php';

// set up id3 reader incase any files need it
$GLOBALS['getID3'] = new getID3();

// music handler
class db_archive extends db_file
{
	const DATABASE = 'archive';
	
	const NAME = 'Archives from Database';

	static function columns()
	{
		return array_keys(self::struct());
	}
	
	static function struct()
	{
		return array(
			'Filepath' 		=> 'TEXT',
			'Filename'		=> 'TEXT',
			'Compressed'	=> 'BIGINT',
			'Filesize'		=> 'BIGINT',
			'Filemime'		=> 'TEXT',
			'Filedate'		=> 'DATETIME ',
			'Filetype'		=> 'TEXT',
		);
	}

	static function handles($file)
	{
		$file = str_replace('\\', '/', $file);
		if(USE_ALIAS == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
		
		// parse through the file path and try to find a zip
		$paths = split('/', $file);
		$last_path = '';
		$last_ext = '';
		foreach($paths as $i => $tmp_file)
		{
			// this will continue until either the end of the requested file (a .zip extension for example)
			// or if the entire path exists then it must be an actual folder on disk with a .zip in the name
			if(file_exists(str_replace('/', DIRECTORY_SEPARATOR, $last_path . $tmp_file)) || $last_path == '')
			{
				$last_ext = getExt($last_path . $tmp_file);
				$last_path = $last_path . $tmp_file . '/';
			}
			else
			{
				// if the last path exists and the last $ext is an archive then we know the path is inside an archive
				if(file_exists(str_replace('/', DIRECTORY_SEPARATOR, $last_path)))
				{
					// we can break
					break;
				}
			}
		}
		
		switch($last_ext)
		{
			case 'zip':
			case 'rar':
			case 'tgz':
			case 'gz':
			case 'bz2':
			case 'tbz':
			case 'ar':
			case 'deb':
			case 'szip':
			case 'tar':
			case '7z':
				return true;
		}
		
		return false;

	}

	static function handle($file, $force = false)
	{
		$file = str_replace('\\', '/', $file);
		
		parseInner($file, $last_path, $inside_path);
		
		$file = $last_path;

		if(self::handles($file))
		{
			// check to see if it is in the database
			$db_archive = $GLOBALS['database']->query(array(
					'SELECT' => self::DATABASE,
					'COLUMNS' => 'id',
					'WHERE' => 'Filepath = "' . addslashes($file) . '"'
				)
			);
			
			// try to get music information
			if( count($db_archive) == 0 )
			{
				return self::add($file);
			}
			elseif($force)
			{
				return self::add($file, $db_archive[0]['id']);
			}

		}
		return false;
	}

	static function add($file, $archive_id = NULL)
	{
		// pull information from $info
		parseInner($file, $last_path, $inside_path);
		
		// do a little cleanup here
		// if the archive changes remove all it's inside files from the database
		if( $archive_id != NULL )
		{
			log_error('Removing archive: ' . $file);
			self::remove($last_path . '/', get_class());
		}
		
		// set up empty ids array since we know archive_id will be the only entry
		$ids = array();
		foreach(db_ids::columns() as $i => $column)
		{
			$ids[$column] = false;
		}
		
		// loop through files
		$source = File_Archive::read($last_path . '/');
		$total_size = 0;
		while($source->next())
		{
			$stat = $source->getStat();
			$fileinfo = array();
			$fileinfo['Filepath'] = addslashes($last_path . '/' . $source->getFilename());
			$fileinfo['Filename'] = basename($source->getFilename());
			$fileinfo['Compressed'] = 0;
			if($fileinfo['Filename'][strlen($fileinfo['Filename'])-1] == '/')
			{
				$fileinfo['Filetype'] = 'FOLDER';
				$fileinfo['Filesize'] = 0;
			}
			else
			{
				$fileinfo['Filetype'] = getExt($fileinfo['Filename']);
				$fileinfo['Filesize'] = @$stat['size'];
			}
			if($fileinfo['Filetype'] === false)
				$fileinfo['Filetype'] = 'FILE';
			else
				$fileinfo['Filetype'] = strtoupper($fileinfo['Filetype']);
				
			$fileinfo['Filemime'] = @$source->getMime();
			$fileinfo['Filedate'] = @$stat['mtime'];
			
			$total_size += $fileinfo['Filesize'];
			
			log_error('Adding file in archive: ' . $fileinfo['Filepath']);
			$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo));
			$ids[self::DATABASE . '_id'] = $id;
			db_ids::handle($fileinfo['Filepath'], true, $ids);
		}
					
		$last_path = str_replace('/', DIRECTORY_SEPARATOR, $last_path);
		// get entire archive information
		$fileinfo = array();
		$fileinfo['Filepath'] = addslashes(str_replace('\\', '/', $last_path));
		$fileinfo['Filename'] = basename($last_path);
		$fileinfo['Compressed'] = filesize($last_path);
		$fileinfo['Filetype'] = getFileType($last_path);
		$fileinfo['Filesize'] = $total_size;
		$fileinfo['Filemime'] = getMime($last_path);
		$fileinfo['Filedate'] = date("Y-m-d h:i:s", filemtime($last_path));

		// print status
		if( $archive_id == NULL )
		{
			log_error('Adding archive: ' . $fileinfo['Filepath']);
			
			// add to database
			$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo));
			
			return $id;
		}
		else
		{
			log_error('Modifying archive: ' . $fileinfo['Filepath']);
			
			// update database
			$id = $GLOBALS['database']->query(array('UPDATE' => self::DATABASE, 'VALUES' => $fileinfo, 'WHERE' => 'id=' . $archive_id));
		
			return $archive_id;
		}
		
	}

	static function out($file)
	{
		$file = str_replace('\\', '/', $file);
		
		if(USE_ALIAS == true)
			$file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
			
		parseInner($file, $last_path, $inside_path);

		if(is_file(str_replace('/', DIRECTORY_SEPARATOR, $last_path)))
		{
			return db_file::out($last_path);
		}

		return false;
	}
	
	static function get($request, &$count, &$error)
	{
		if(isset($request['dir']))
		{
			$request['dir'] = str_replace('\\', '/', $request['dir']);
			if(USE_ALIAS == true) $request['dir'] = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $request['dir']);

			parseInner($request['dir'], $last_path, $inside_path);
			if(strlen($inside_path) == 0 || $inside_path[0] != '/') $inside_path = '/' . $inside_path;
			$request['dir'] = $last_path . $inside_path;
			
			if(!is_file(str_replace('/', DIRECTORY_SEPARATOR, $last_path)))
			{
				unset($_REQUEST['dir']);
				$error = 'Directory does not exist!';
			}
		}
		
		$files = db_file::get($request, $count, $error, 'db_archive');
		
		return $files;
	}
	
	static function remove($file)
	{
		// db_file can handle inside paths
		parent::remove($file, get_class());
	}

	static function cleanup()
	{
		// call default cleanup function
		parent::cleanup(get_class());
	}
}

?>
