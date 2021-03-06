<?php


function get_handlers($get_wrappers = true, $get_internals = false, $wrappers_only = false, $internals_only = false)
{
	// get all combos of handlers
	$handlers = array_filter(get_modules(), 'is_handler');
	$wrappers = array_filter($handlers, 'is_wrapper');
	$internals = array_filter($handlers, 'is_internal');
	
	// remove the handlers set to false
	if(!$get_internals && !$internals_only)
		$handlers = array_diff($handlers, $internals);
	if(!$get_wrappers && !$wrappers_only)
		$handlers = array_diff($handlers, $wrappers);
	
	// if they only want certain types of handlers
	if($wrappers_only && $internals_only)
		$handlers = array_intersect($handlers, array_merge($wrappers, $internals));
	elseif($wrappers_only)
		$handlers = array_intersect($handlers, $wrappers);
	elseif($internals_only)
		$handlers = array_intersect($handlers, $internals);
	
	// flip keys back and remerge module configs
	$handlers = array_flip($handlers);

	return array_intersect_key($GLOBALS['modules'], $handlers);
}

function handles_count($files)
{
	$modules = array();
	
	// loop through files and determine how many handlers there are for each file
	foreach($files as $i => $file)
	{
		$handlers = file_handlers($file['Filepath']);
		foreach($handlers as $j => $handler)
		{
			if(!isset($modules[$handler . '_count']))
				$modules[$handler . '_count'] = 1;
			else
				$modules[$handler . '_count']++;
		}
	}
	
	return $modules;
}

function scan_directory($dir, $filter = 'files', $depth = 1)
{
	$files = array();

	// return a flat list of files
	if (is_dir($dir) && ($handle = opendir($dir)))
	{
		// add slash to end
		if(substr($dir, -strlen(DIRECTORY_SEPARATOR)) != DIRECTORY_SEPARATOR && substr($dir, -1) != '/')
			$dir .= DIRECTORY_SEPARATOR;
		
		while (FALSE !== ($file = readdir($handle)))
		{
			// add slash to directories
			if(is_dir($dir . $file))
				$file .= DIRECTORY_SEPARATOR;
			
			// filter files
			if(is_dir($dir . $file) && handles($dir . $file, 'files') && ($depth > 1 || $depth == 0))
			{
				$files = array_merge($files, scan_directory($dir . $file, $filter, ($depth == 0)?$depth:($depth - 1)));
			}
			elseif(is_callable($filter))
			{
				if(call_user_func_array($filter, array($dir . $file)))
					$files[] = str_replace(DIRECTORY_SEPARATOR, '/', $dir . $file);
			}
			elseif(is_handler($filter))
			{
				if(handles($dir . $file, $filter))
					$files[] = str_replace(DIRECTORY_SEPARATOR, '/', $dir . $file);
			}
			elseif(handles($dir . $file, 'files'))
				$files[] = str_replace(DIRECTORY_SEPARATOR, '/', $dir . $file);
		}
	}
	
	sort($files);
	
	return array_unique($files);
}

function directories()
{
	
}

function file_exists_regexp($file)
{
	$dirs = preg_split('/\\\\\/|\\\\\\\\/i', $file);
	$current = 0;
	$possible = array($dirs[0]);
	for($i = 0; $i < count($possible); $i++)
	{
		$current = max(substr_count($possible[$i], '/')-1, 0);
		$possible = array_merge($possible, scan_directory(realpath($possible[$i]), create_function('$file', 'return preg_match("/' . $dirs[$current+1] . '/i", basename($file));')));
	}
	// double check to make sure the file actually matches
	$result = array_pop($possible);
	if(preg_match('/' . $file . '/i', $result))
		return $result;
	else
		return false;
}

function is_internal($handler)
{
	if(!is_handler($handler))
		return;
		
	if(get_module($handler, 'internal'))
	{
		return true;
	}
	elseif(is_wrapper($handler))
	{
		return is_internal(get_module($handler, 'wrapper'));
	}
	else
	{
		return false;
	}
}

/**
 * Check if the specified class is just a wrapper for some parent database
 * @param handler is the catagory or handler to check
 * @return true or false if the class is a wrapper
 */
function is_wrapper($handler)
{
	if(!is_handler($handler))
		return false;
	
	// fs_ handlers are never wrappers
	if(!setting('database_enable'))
		return false;
	if($handler == 'files')
		return false;
	return isset($GLOBALS['modules'][$handler]['wrapper']);
}

function is_handler($handler)
{
	if(is_module($handler) && module_implements('handles', $handler))
		return true;
	elseif(isset($GLOBALS['modules'][$handler]['wrapper']))
		return is_handler($GLOBALS['modules'][$handler]['wrapper']);
	else
		return false;
}

function file_handlers($file)
{
	// loop through handlers and return a list that handles a file
	$handlers = array();
	foreach(get_handlers() as $handler => $config)
	{
		if(handles($file, $handler))
			$handlers[] = $handler;
	}
	return $handlers;
}


function handles_files($file)
{
	return handles($file, 'files');
}
/**
 * Check if a handler handles a certain type of files
 * this is a useful call for templates to use because it provides short syntax
 * @param file The file to test if it is handled
 * @param handler The handler to check if it handles the specified file
 * @return true if the specified handler handles the specified file, false if the handler does not handle that file
 */
function handles($file, $handler = 'files')
{
	// if it isn't a handler at all, return here
	if($handler != 'files' && 
		// check the module is enabled first
		(!is_handler($handler) || !dependency($handler))
	)
		return false;
		
	// is an array is passed in it could be the entire $file array
	if(is_array($file) && isset($file['Filepath']))
		$file = $file['Filepath'];

	// if it is not a string there is nothing more we can do
	if(!is_string($file))
		return false;
	
	// always use forward slash
	$file = str_replace(DIRECTORY_SEPARATOR, '/', $file);

	// replace the file alias with actual path
	if(setting('alias_enable')) $file = alias_replace($file);
		
	// check the handles_ function	
	if($handler == 'files')
	{
		// check if the given file is a directory or file
		if(
			is_dir($file) || 
			(
				is_file($file) && $file[strlen($file)-1] != '/'
			)
		)
		{
			// make sure it isn't a hidden file
			if(strlen(basename($file)) > 0 && substr(basename($file), 0, 1) != '.' || (basename($file) == '' && realpath($file) == realpath('/')))
				return true;
		}
	}
	// wrappers are never handlers because they don't have their own database
	elseif(is_wrapper($handler))
		return false;
		
	// check if there is a handle function
	elseif(module_implements('handles', $handler))
		return invoke_module('handles', $handler, array($file));

	// no handler specified, show debug error
	else
		raise_error('Handles called with \'' . $handler . '\' but no \'handles_' . $handler . '()\' function exists!', E_DEBUG);
	
	return false;
}

function handles_all(&$files, $handler = 'files')
{
	$result = array();
	
	// loop through files and determine which files are handled by the specified module
	foreach($files as $file)
	{
		if(handles($file['Filepath'], $handler))
			$result[] = $file;
	}
	
	return $result;
}

/**
 * Gets the columns from the specified handler
 */
function get_columns($handler, $exclude_blobs = true)
{
	if(is_wrapper($handler, 'wrapper'))
		return get_columns(get_module($handler, 'wrapper'), $exclude_blobs);
	elseif(is_handler($handler))
		$spec = get_module($handler, 'database');
	else
		$spec = get_module('files', 'database');
	
	if($exclude_blobs)
	{
		$columns = array();
		foreach($spec as $column => $type)
		{
			if($type != 'BLOB' && $type != 'MEDIUMBLOB' && $type != 'LONGBLOB')
				$columns[] = $column;
		}
		return $columns;
	}
	else
		return array_keys($spec);
}

/**
 * get all columns from every handlers
 * @return a list of all the columns combined from every handler installed
 */
function get_all_columns()
{
	$columns = array();
	foreach(get_handlers() as $handler => $config)
	{
		$columns = array_merge($columns, array_flip(get_columns($handler)));
	}
	
	$columns = array_keys($columns);

	return $columns;
}





