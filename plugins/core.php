<?php

// Core plugin validates core request variables
//   since many plugins are related to getting information from the database
//   this plugin will register all the common functions for handling request variables, so the other plugins don't have to

function register_core()
{
	// register permission requirements
	
	// register the request variables we will be providing validators for
	
	// this plugin has no output
	return array(
		'name' => 'Core Functions',
		'description' => 'Adds core functionality to site that other common plugins depend on.',
		'path' => __FILE__
	);
}

// this makes all the variables available for output
function register_output_vars($name, $value, $append = false)
{
	if(isset($GLOBALS['output'][$name]) && $append == false)
	{
		PEAR::raiseError('Variable "' . $name . '" already set!', E_DEBUG);
	}
	if($append == false)
		$GLOBALS['output'][$name] = $value;
	elseif(!isset($GLOBALS['output'][$name]))
		$GLOBALS['output'][$name] = $value;
	elseif(is_string($GLOBALS['output'][$name]))
		$GLOBALS['output'][$name] = array($GLOBALS['output'][$name], $value);
	elseif(is_array($GLOBALS['output'][$name]))
		$GLOBALS['output'][$name][] = $value;
}

function href($request = array(), $not_special = false, $include_domain = false, $return_array = false)
{
	return generate_href($request, $not_special, $include_domain, $return_array);
}

// this function takes a request as input, and based on the .htaccess rules, converts it to a pretty url, or makes no changes if mod_rewrite is off
function generate_href($request = array(), $not_special = false, $include_domain = false, $return_array = false)
{
	if(is_string($request))
	{
		if(strpos($request, '?') !== false)
		{
			$request = explode('?', $request);
			$dirs = split('/', $request[0]);
			$request = $request[1];
		}
		
		$arr = explode('&', $request);
		if(count($arr) == 1 && $arr[0] == '')
			$arr = array();
		$request = array();
		foreach($arr as $i => $value)
		{
			$x = explode('=', $value);
			$request[$x[0]] = isset($x[1])?$x[1]:'';
		}
	}
	if(isset($dirs))
	{
		$request = array_merge($request, parse_path_info($dirs));
	}
	if($return_array)
		return $request;
	
	// rebuild link
	$link = (($include_domain)?HTML_DOMAIN:'') . HTML_ROOT . '?';
	foreach($request as $key => $value)
	{
		$link .= (($link[strlen($link)-1] != '?')?'&':'') . $key . '=' . $value;
	}
	if($not_special)
		return $link;
	else
		return htmlspecialchars($link);
}

function set_output_vars($smarty)
{
	// set a couple more that are used a lot
	
	// most template pieces use the category variable, so set that
	register_output_vars('cat', $_REQUEST['cat']);
	
	// some templates refer to the dir to determine their own location
	if(isset($_REQUEST['dir'])) register_output_vars('dir', $_REQUEST['dir']);
	
	// this is just a helper variable for templates to use that only need to save 1 setting
	if(isset($_REQUEST['extra'])) register_output_vars('extra', $_REQUEST['extra']);
	
	// some templates would like to submit to their own page, generate a string based on the current get variable
	register_output_vars('get', generate_href($_GET, true));
	
	// remove everything else so templates can't violate the security
	//   there is no going back from here
	if(isset($_SESSION)) session_write_close();
	
	$dont_remove = array(
		'GLOBALS',
		'templates',
		'debug_errors',
		'user_errors',
		'smarty',
		'output',
		'template',
		'alias',
		'alias_regexp',
		'paths',
		'paths_regexp',
		'mte',
		'plugin',
		'plugins',
		'_PEAR_default_error_mode',
		'_PEAR_default_error_options',
		'modules',
		'tables',
		'ext_to_mime'
	);
	
	foreach($GLOBALS as $key => $value)
	{
		if(in_array($key, $dont_remove) === false)
			unset($GLOBALS[$key]);
	}
	
	if($smarty)
	{
		foreach($GLOBALS['output'] as $name => $value)
		{
			$GLOBALS['smarty']->assign($name, $value);
		}
	}

	foreach($GLOBALS['output'] as $name => $value)
	{
		$GLOBALS['templates']['vars'][$name] = $value;
	}
	
	unset($GLOBALS['output']);
}

function validate_cat($request)
{
	if(isset($request['cat']) && (substr($request['cat'], 0, 3) == 'db_' || substr($request['cat'], 0, 3)))
		$request['cat'] = ((USE_DATABASE)?'db_':'fs_') . substr($request['cat'], 3);
	if(!isset($request['cat']) || !in_array($request['cat'], $GLOBALS['modules']) || constant($request['cat'] . '::INTERNAL') == true)
		return USE_DATABASE?'db_file':'fs_file';
	return $request['cat'];
}

function validate_start($request)
{
	if( !isset($request['start']) || !is_numeric($request['start']) || $request['start'] < 0 )
		return 0;
	return $request['start'];
}

function validate_limit($request)
{
	if( !isset($request['limit']) || !is_numeric($request['limit']) || $request['limit'] < 0 )
		return 15;
	return $request['limit'];
}

function validate_order_by($request)
{
	$module = validate_cat($request);
	
	$columns = call_user_func($module . '::columns');
	
	if( !isset($request['order_by']) || !in_array($request['order_by'], $columns) )
	{
		if(isset($request['search']))
			return 'Relevance';
			
		// make sure if it is a list that it is all valid columns
		$columns = split(',', (isset($request['order_by'])?$request['order_by']:''));
		foreach($columns as $i => $column)
		{
			if(!in_array($column, call_user_func($module . '::columns')))
				unset($columns[$i]);
		}
		if(count($columns) == 0)
			return 'Filepath';
		else
			return join(',', $columns);
	}
	return $request['order_by'];
}

function validate_group_by($request)
{
	$module = validate_cat($request);
	
	$columns = call_user_func($module . '::columns');
	
	if( isset($request['group_by']) && !in_array($request['group_by'], $columns) )
	{
		// make sure if it is a list that it is all valid columns
		$columns = split(',', $request['group_by']);
		foreach($columns as $i => $column)
		{
			if(!in_array($column, call_user_func($module . '::columns')))
				unset($columns[$i]);
		}
		if(count($columns) == 0)
			return;
		else
			return join(',', $columns);
	}
	return $request['group_by'];
}

function validate_direction($request)
{
	if( !isset($request['direction']) || ($request['direction'] != 'ASC' && $request['direction'] != 'DESC') )
		return 'ASC';
	return $request['direction'];
}

function validate_columns($request)
{
	$module = validate_cat($request);
	
	$columns = call_user_func($module . '::columns');
	
	// which columns to search
	if( isset($request['columns']) && !in_array($request['columns'], $columns) )
	{
		// make sure if it is a list that it is all valid columns
		$columns = split(',', $request['columns']);
		foreach($columns as $i => $column)
		{
			if(!in_array($column, call_user_func($module . '::columns')))
				unset($columns[$i]);
		}
		if(count($columns) == 0)
			return;
		else
			return join(',', $columns);
	}
	return $request['columns'];
}

// Redirect unknown file and folder requests to recognized protocols and other plugins.
function validate_plugin($request)
{
	// remove .php extension
	if(isset($request['plugin']) && substr($request['plugin'], -4) == '.php')
		$request['plugin'] = substr($request['plugin'], 0, -4);
		
	// replace slashes
	if(isset($request['plugin'])) $request['plugin'] = str_replace(array('/', '\\'), '_', $request['plugin']);
	
	// if the plugin is set then return right away
	if(isset($request['plugin']) && isset($GLOBALS['plugins'][$request['plugin']]))
	{
		return $request['plugin'];
	}
	// check for ampache compitibility
	elseif(strpos($_SERVER['REQUEST_URI'], '/server/xml.server.php?') !== false)
	{
		return 'ampache';
	}
	else
	{
		$script = basename($_SERVER['SCRIPT_NAME']);
		$script = substr($script, 0, strpos($script, '.'));
		if(isset($GLOBALS['plugins'][$script]))
			return $script;
		else
			return 'index';
	}
}

function parse_path_info($path_info)
{
	$request = array();

	if(!is_array($path_info))
		$dirs = split('/', $path_info);
	else
		$dirs = $path_info;
	
	// remove empty dirs
	foreach($dirs as $i => $value)
	{
		if($value == '')
			unset($dirs[$i]);
	}
	$dirs = array_values($dirs);
	
	if(count($dirs) > 0)
	{
		// get plugin from path info
		$request['plugin'] = $dirs[0];
		switch(count($dirs))
		{
			case 2:
				$request['search'] = '"' . $dirs[1] . '"';
				break;
			case 3:
				$request['cat'] = $dirs[1];
				$request['id'] = $dirs[2];
				break;
			case 4:
				$request['cat'] = $dirs[1];
				$request['id'] = $dirs[2];
				$request['filename'] = $dirs[3];
				break;
			case 5:
				$request['cat'] = $dirs[1];
				$request['id'] = $dirs[2];
				$request[$request['plugin']] = $dirs[3];
				$request['filename'] = $dirs[4];
				break;
			case 6:
				$request['cat'] = $dirs[1];
				$request['id'] = $dirs[2];
				$request[$request['plugin']] = $dirs[3];
				$request['extra'] = $dirs[4];
				$request['filename'] = $dirs[5];
				break;
		}
	}
	
	return $request;
}

function rewrite($old_var, $new_var, &$request, &$get, &$post)
{
	if(isset($request[$old_var])) $request[$new_var] = $request[$old_var];
	if(isset($get[$old_var])) $get[$new_var] = $get[$old_var];
	if(isset($post[$old_var])) $post[$new_var] = $post[$old_var];
	
	unset($request[$old_var]);
	unset($get[$old_var]);
	unset($post[$old_var]);
}

function rewrite_vars(&$request, &$get, &$post)
{
	$request['plugin'] = validate_plugin($request);
	
	if(isset($request['path_info']))
		$request = array_merge($request, parse_path_info($request['path_info']));
		
	// just about everything uses the cat variable so always validate and add this
	$request['cat'] = validate_cat($request);

	// do some modifications to specific plugins being used
	if($request['plugin'] == 'bt')
	{
		// save the whole request to be used later
		$request['bt_request'] = $request;
	}
	if($request['plugin'] == 'ampache')
	{
		// a valid action is required for this plugin
		$request['action'] = validate_action($request);
		
		// rewrite some variables
		if(isset($request['offset'])) rewrite('offset', 'start', $request, $get, $post);
		if(isset($request['filter']) && $request['action'] != 'search_songs') rewrite('filter', 'id', $request, $get, $post);
		elseif(isset($request['filter']))  rewrite('filter', 'search', $request, $get, $post);
	}
}

function validate_extra($request)
{
	if(isset($request['extra']))
		return $request['extra'];
}

function output_index($request)
{
	// output any index like information
	
	// perform a select so files can show up on the index page
	output_select($request);
}
