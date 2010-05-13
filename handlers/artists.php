<?php

/** 
 * Implementation of register_handler
 * @ingroup register_handler
 */
function register_artists()
{
	return array(
		'name' => 'Artists',
		'description' => 'Wrapper for displaying artist and album information.',
		'columns' => array('id', 'SongCount', 'Artist', 'Filepath'),
		'wrapper' => 'audio',
	);
}

/**
 * Implementation of get_handler
 * @ingroup get_handler
 */
function get_artists($request, &$count)
{
	
	if(isset($request['dir']) && ($request['dir'] == '' || $request['dir'] == '/'))
	{
		unset($request['dir']);
	}
	
	if(isset($request['dir']))
	{
		$request['dir'] = str_replace('\\', '/', $request['dir']);
		if($request['dir'][0] == '/') $request['dir'] = substr($request['dir'], 1);
		if($request['dir'][strlen($request['dir'])-1] == '/') $request['dir'] = substr($request['dir'], 0, strlen($request['dir'])-1);
		if(strpos($request['dir'], '/') !== false)
		{
			$dirs = split('/', $request['dir']);
			if($dirs[0] == '$Unknown$')
				$dirs[0] = '';
			if($dirs[1] == '$Unknown$')
				$dirs[1] = '';
			$request['search_Artist'] = '=' . $dirs[0] . '=';
			$request['search_Album'] = '=' . $dirs[1] . '=';
			unset($request['dir']);
			
			// set to db_file which will handle the request
			$files = get_db_audio($request, $count, 'db_audio');
		}
		else
		{
			// modify some request stuff
			$request['order_by'] = 'Album';
			$request['order_trimmed'] = true;
			$request['group_by'] = 'Album';

			if($request['dir'] == '$Unknown$')
				$request['dir'] = '';
			$request['search_Artist'] = '=' . $request['dir'] . '=';
			unset($request['dir']);
			
			$files = get_db_audio($request, $count, 'db_audio');
			
			// make some changes
			foreach($files as $i => $file)
			{
				if($files[$i]['Artist'] == '')
					$files[$i]['Artist'] = '$Unknown$';
				if($files[$i]['Album'] == '')
					$files[$i]['Album'] = '$Unknown$';
				$files[$i]['Filetype'] = 'FOLDER';
				$files[$i]['Filesize'] = '0';
				$files[$i]['Filepath'] = '/' . $files[$i]['Artist'] . '/' . $files[$i]['Album'] . '/';
				$files[$i]['Filename'] = $files[$i]['Album'];
				$files[$i]['SongCount'] = $files[$i]['count(*)'];
				unset($files[$i]['Title']);
				unset($files[$i]['Track']);
				unset($files[$i]['Bitrate']);
				unset($files[$i]['Length']);
				unset($files[$i]['Album']);
			}
		}
	}
	else
	{
		// modify some request stuff
		$request['order_by'] = 'Artist';
		$request['order_trimmed'] = true;
		$request['group_by'] = 'Artist';
		
		$files = get_db_audio($request, $count, 'db_audio');
		
		// make some changes
		foreach($files as $i => $file)
		{
			if($files[$i]['Artist'] == '')
				$files[$i]['Artist'] = '$Unknown$';
			$files[$i]['Filetype'] = 'FOLDER';
			$files[$i]['Filesize'] = '0';
			$files[$i]['Filepath'] = '/' . $files[$i]['Artist'] . '/';
			$files[$i]['Filename'] = $files[$i]['Artist'];
			$files[$i]['SongCount'] = $files[$i]['count(*)'];
			unset($files[$i]['Title']);
			unset($files[$i]['Track']);
			unset($files[$i]['Bitrate']);
			unset($files[$i]['Length']);
			unset($files[$i]['Album']);
		}
	}
	
	return $files;
}

