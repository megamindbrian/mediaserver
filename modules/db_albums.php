<?php
// provide an easy to access interface to all the unique albums

$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

require_once LOCAL_ROOT . 'modules' . DIRECTORY_SEPARATOR . 'db_audio.php';

// music handler
class db_albums extends db_audio
{
	const DATABASE = 'audio';
	
	const NAME = 'Albums from Database';

	static function columns()
	{
		return array('id', 'SongCount', 'Album', 'Filepath');
	}
	
	static function handles($file)
	{
		// we don't want this module to handle any files, it is just a wrapper
		return false;
	}

	static function handle($database, $file)
	{
	}
	
	static function get($database, $request, &$count, &$error)
	{
		$database->validate($request, $props, get_class());
		
		// modify some request stuff
		if(isset($request['dir']))
		{
			$request['dir'] = str_replace('\\', '/', $request['dir']);
			if($request['dir'][0] == '/') $request['dir'] = substr($request['dir'], 1);
			if($request['dir'][strlen($request['dir'])-1] == '/') $request['dir'] = substr($request['dir'], 0, strlen($request['dir'])-1);
			if($request['dir'] == '$Unknown$')
				$request['dir'] = '';
			$request['search_Album'] = '=' . $request['dir'] . '=';
			unset($request['dir']);
			
			$files = parent::get($database, $request, $count, $error, get_class());
		}
		else
		{
			$request['order_by'] = 'Album';
			$request['group_by'] = 'Album';
			if(isset($request['search']))
			{
				$request['search_Album'] = $request['search'];
				unset($request['search']);
			}
			
			$files = parent::get($database, $request, $count, $error, get_class());
			
			// make some changes
			foreach($files as $i => $file)
			{
				if($files[$i]['Album'] == '')
					$files[$i]['Album'] = '$Unknown$';
				$files[$i]['Filetype'] = 'FOLDER';
				$files[$i]['Filesize'] = '0';
				$files[$i]['Filepath'] = '/' . $files[$i]['Album'] . '/';
				$files[$i]['Filename'] = $files[$i]['Album'];
				$files[$i]['SongCount'] = $files[$i]['count(*)'];
			}
		}
		
		return $files;
	}


	static function cleanup($database, $watched, $ignored)
	{
	}

}

?>
