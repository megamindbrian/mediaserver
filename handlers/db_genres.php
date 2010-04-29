<?php
// provide an easy to access interface to all the unique genres
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'db_audio.php';

// music handler
class db_genres extends db_audio
{
	const DATABASE = 'audio';
	
	const NAME = 'Genres from Database';

	static function columns()
	{
		return array('id', 'SongCount', 'Genre', 'Filepath');
	}

	static function handles($file)
	{
		// we don't want this handler to handle any files, it is just a wrapper
		return false;
	}

	static function handle($file, $force = false)
	{
		return false;
	}
	
	static function get($request, &$count)
	{
			
		// modify some request stuff
		if(isset($request['dir']))
		{
			$request['dir'] = str_replace('\\', '/', $request['dir']);
			if($request['dir'][0] == '/') $request['dir'] = substr($request['dir'], 1);
			if($request['dir'][strlen($request['dir'])-1] == '/') $request['dir'] = substr($request['dir'], 0, strlen($request['dir'])-1);
			if($request['dir'] == '$Unknown$')
				$request['dir'] = '';
			$request['search'] = '=' . $request['dir'] . '=';
			$request['columns'] = 'Genre';
			unset($request['dir']);
			
			$files = parent::get($request, $count, 'db_audio');
		}
		else
		{
			$request['order_by'] = 'Genre';
			$request['group_by'] = 'Genre';
			
			$files = parent::get($request, $count, 'db_audio');
			
			// make some changes
			foreach($files as $i => $file)
			{
				if($files[$i]['Genre'] == '')
					$files[$i]['Genre'] = '$Unknown$';
				$files[$i]['Filetype'] = 'FOLDER';
				$files[$i]['Filesize'] = '0';
				$files[$i]['Filepath'] = '/' . $files[$i]['Genre'] . '/';
				$files[$i]['Filename'] = $files[$i]['Genre'];
				$files[$i]['SongCount'] = $files[$i]['count(*)'];
			}
		}
		
		return $files;
	}
	
	static function remove($file)
	{
	}

	static function cleanup()
	{
	}

}

?>