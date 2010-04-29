<?php

/**
 * control outputting of template files
 *  validate template variable
 */
 
/**
 * Generate a list of templates
 * @ingroup setup
 */
function setup_template()
{
	// load templating system but only if we are using templates

	// get the list of templates
	$GLOBALS['templates'] = array();
	$files = fs_file::get(array('dir' => setting('local_root') . 'templates' . DIRECTORY_SEPARATOR, 'limit' => 32000), $count, true);
	if(is_array($files))
	{
		foreach($files as $i => $file)
		{
			if(is_dir($file['Filepath']) && is_file($file['Filepath'] . 'config.php'))
			{
				include_once $file['Filepath'] . 'config.php';
				
				// determin template based on path
				$template = substr($file['Filepath'], strlen(setting('local_root')));
				
				// remove default directory from module name
				if(substr($template, 0, 10) == 'templates/')
					$template = substr($template, 10);
				
				// remove trailing slash
				if(substr($template, -1) == '/' || substr($template, -1) == '\\')
					$template = substr($template, 0, strlen($template) - 1);
				
				// call register functions
				if(function_exists('register_' . $template))
					$GLOBALS['templates'][$template] = call_user_func_array('register_' . $template, array());
					
				// register template files
				setup_template_files($GLOBALS['templates'][$template]);
			}
		}
	}
	
	$_REQUEST['template'] = validate_template($_REQUEST, isset($_SESSION['template'])?$_SESSION['template']:'');
	
	// don't use a template if they comment out this define, this enables the tiny remote version
	if(!isset($GLOBALS['settings']['local_template']))
	{
		$GLOBALS['settings']['local_template'] = $_REQUEST['template'];
	}
	
	// call the request alter
	if(isset($GLOBALS['templates'][$_REQUEST['template']]['alter request']) && $GLOBALS['templates'][$_REQUEST['template']]['alter request'] == true)
		$_REQUEST = call_user_func_array('alter_request_' . $_REQUEST['template'], array($_REQUEST));
	
	// assign some shared variables
	register_output_vars('tables', $GLOBALS['tables']);
	register_output_vars('modules', $GLOBALS['modules']);
	register_output_vars('handlers', $GLOBALS['handlers']);
	register_output_vars('templates', $GLOBALS['templates']);
	register_output_vars('columns', getAllColumns());
	
}

/**
 * Implementation of register
 * @ingroup register
 */
function register_template()
{
	return array(
		'name' => 'Template Output',
		'description' => 'Display files from the templates directory. Allows for templating CSS and JS files.',
		'privilage' => 1,
		'path' => __FILE__,
		'session' => array('template'),
		'notemplate' => true,
		'settings' => array('local_base', 'local_default', 'local_template'),
	);
}

/**
 * Configure all template options
 * @ingroup configure
 */
function configure_template($request)
{
	$request['local_base'] = validate_local_base($request);
	$request['local_default'] = validate_local_default($request);
	$request['local_template'] = validate_local_template($request);
	
	$options = array();
	
	if(file_exists(setting('local_root') . 'templates' . DIRECTORY_SEPARATOR . $request['local_base']))
	{
		$options['local_base'] = array(
			'name' => 'Template Base',
			'status' => '',
			'description' => array(
				'list' => array(
					'The template base provides a backup/default set of template files. This template supports all possible functionality, in the simplest way.',
					'Default functionality includes things like printing out an XML file, or an M3U playlist instead of a vieable HTML list of files.',
					'The server reports that ' . setting('local_root') . 'template' . DIRECTORY_SEPARATOR . $request['local_base'] . ' does, in fact, exist.',
				),
			),
			'type' => 'text',
			'value' => $request['local_base'],
		);
	}
	else
	{
		$options['local_base'] = array(
			'name' => 'Template Base',
			'status' => 'fail',
			'description' => array(
				'list' => array(
					'The system has detected that the local basic template files are not where they are expected to be.',
					'The template base provides a backup/default set of template files. This template supports all possible functionality, in the simplest way.',
					'Default functionality includes things like printing out an XML file, or an M3U playlist instead of a vieable HTML list of files.',
					'The server reports that ' . setting('local_root') . 'template' . DIRECTORY_SEPARATOR . $request['local_base'] . ' does NOT EXIST.',
				),
			),
			'type' => 'text',
			'value' => $request['convert_path'],
		);
	}
	
	$templates = array();
	foreach($GLOBALS['templates'] as $template => $config)
	{
		$templates[$template] = $config['name'];
	}
	
	$options['local_default'] = array(
		'name' => 'Default Template',
		'status' => '',
		'description' => array(
			'list' => array(
				'The default template is the template displayed to users until they select an alternative template.',
			),
		),
		'type' => 'select',
		'values' => $templates,
	);
	
	$options['local_template'] = array(
		'name' => 'Local Template',
		'status' => '',
		'description' => array(
			'list' => array(
				'If this is set, this template will always be displayed to the users.  They will not be given the option to select their own template.',
			),
		),
		'type' => 'select',
		'values' => array('' => 'Not Set') + $templates,
	);

	return $options;
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return The plain template by default
 */
function validate_local_base($request)
{
	if(isset($request['local_base']) && in_array(basename($request['local_base']), $GLOBALS['templates']))
		return basename($request['local_base']);
	else
		return 'plain';
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return The live template by default
 */
function validate_local_default($request)
{
	if(isset($request['local_default']) && in_array($request['local_default'], $GLOBALS['templates']))
		return $request['local_default'];
	else
		return 'live';
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return blank by default
 */
function validate_local_template($request)
{
	if(isset($request['local_template']) && in_array($request['local_template'], $GLOBALS['templates']))
		return $request['local_template'];
	else
		return '';
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return NULL by default, accepts any valid existing file from the scope of the template directory, throws an error if the file is invalid
 */
function validate_tfile($request)
{
	if(isset($request['tfile']))
	{
		$request['template'] = validate_template($request);
		if(is_file(setting('local_root') . 'templates' . DIRECTORY_SEPARATOR . $request['template'] . DIRECTORY_SEPARATOR . $request['tfile']))
			return $request['tfile'];
		else
			PEAR::raiseError('Template file requested but could not be found!', E_DEBUG|E_WARN);
	}
}

/**
 * Include a list of template files specified by the template config
 * @ingroup setup
 * @param template_config the template config to read the list of files from
 */
function setup_template_files($template_config)
{
	$template_name = basename(dirname($template_config['path']));
	if(isset($template_config['files']))
	{
		foreach($template_config['files'] as $file)
		{
			include setting('local_root') . 'templates' . DIRECTORY_SEPARATOR . $template_name . DIRECTORY_SEPARATOR . $file . '.php';
			if(function_exists('register_' . $template_name . '_' . $file))
			{
				$template = call_user_func_array('register_' . $template_name . '_' . $file, array());
				if(isset($template['scripts']))
				{
					if(is_array($template['scripts']))
					{
						foreach($template['scripts'] as $script)
							register_script($script);
					}
					elseif(is_string($template['scripts']))
						register_script($template['scripts']);
				}
				
				if(isset($template['styles']))
				{
					if(is_array($template['styles']))
					{
						foreach($template['styles'] as $style)
							register_style($style);
					}
					elseif(is_string($template['styles']))
						register_style($template['styles']);
				}
			}
		}
	}
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return setting('local_default') by default, accepts any valid template, attempts to determine the best template based on the HTTP_USER_AGENT
 */
function validate_template($request, $session = '')
{
	if(!isset($request['template']) && $session != '')
		$request['template'] = $session;
		
	// check if it is a valid template specified
	if(isset($request['template']) && $request['template'] != '')
	{
		// remove template directory from beginning of input
		if(substr($request['template'], 0, 10) == 'templates/' || substr($request['template'], 0, 10) == 'templates\\')
			$request['template'] = substr($request['template'], 10);
			
		// remove leading slash if there is one
		if($request['template'][strlen($request['template'])-1] == '/' || $request['template'][strlen($request['template'])-1] == '\\')
			$request['template'] = substr($request['template'], 0, -1);
		
		// check to make sure template is valid
		if(in_array($request['template'], array_keys($GLOBALS['templates'])))
		{
			return $request['template'];
		}
	}
	elseif(isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/.*mobile.*/i', $_SERVER['HTTP_USER_AGENT'], $matches) !== 0)
	{
		return 'mobile';
	}
	return setting('local_default');
}

/**
 * Implementation of session
 * @ingroup session
 * @return the selected template for reference
 */
function session_template($request)
{
	return $request['template'];
}

/**
 * Register a stylesheet for use with a particular template
 * @param request the url to the stylesheet that validate with validate_template and validate_tfile
 * @return true on success, false on failure and throws an error
 */
function register_style($request)
{
	// convert the request string to an array
	if(!is_array($request))
		$request = url($request, true, false, true);
		
	// validate the 2 inputs needed
	$request['template'] = validate_template($request);
	$request['tfile'] = validate_tfile($request);

	// only continue if bath properties are set
	if(isset($request['template']) && isset($request['tfile']))
	{
		register_output_vars('styles', 'module=template&template=' . $request['template'] . '&tfile=' . $request['tfile'], true);
		return true;
	}
	else
		PEAR::raiseError('Style could not be set because of missing arguments.', E_DEBUG|E_WARN);
	return false;
}

/**
 * Register a javascript for use with a particular template
 * @param request the url to the javascript that validate with validate_template and validate_tfile
 * @return true on success, false on failure and throws an error
 */
function register_script($request)
{
	// convert the request string to an array
	if(!is_array($request))
		$request = url($request, true, false, true);
		
	// validate the 2 inputs needed
	$request['template'] = validate_template($request);
	$request['tfile'] = validate_tfile($request);
	
	// only continue if bath properties are set
	if(isset($request['template']) && isset($request['tfile']))
	{
		register_output_vars('scripts', 'module=template&template=' . $request['template'] . '&tfile=' . $request['tfile'], true);
		return true;
	}
	else
		PEAR::raiseError('Script could not be set because of missing arguments.', E_DEBUG|E_WARN);
		
	return false;
}

/**
 * Calls theming functions
 * @param request the name of the theme function to call
 */
function theme($request = '')
{
	// if the theme function is just being called without any input
	//   then call the default theme function
	if($request == '' && isset($_REQUEST['template']))
	{
		$template = $_REQUEST['template'];
		set_output_vars();
		call_user_func_array('output_' . $template, array());
		return;
	}
	
	// if the theme function is being called then the output vars better be set
	if(!isset($GLOBALS['templates']['vars']))
		set_output_vars();
	
	// get the arguments to pass on to theme_ functions
	$args = func_get_args();
	
	// do not pass original theme call argument
	unset($args[0]);
	$args = array_values($args);
	
	// if the request is an array, assume they are setting the template and theme call
	if(is_array($request))
	{
		// the tfile paramet can be used to call the theme_ function
		$request['template'] = validate_template($request);
		$request['tfile'] = validate_tfile($request);
		
		// if the function exists call the theme_ implementation
		if(function_exists('theme_' . $request['template'] . '_' . $request['tfile']))
		{
			// call the function and be done with it
			call_user_func_array('theme_' . $request['template'] . '_' . $request['tfile'], $args);
			return true;
		}
		else
			PEAR::raiseError('Theme function \'theme_' . $request['template'] . '_' . $request['tfile'] . '\' was not found.', E_DEBUG|E_WARN);
	}
	// the request is a string, this is most common
	elseif(is_string($request))
	{
		// check if function exists in current theme
		if(function_exists('theme_' . validate_template(array('template' => setting('local_template'))) . '_' . $request))
		{
			call_user_func_array('theme_' . validate_template(array('template' => setting('local_template'))) . '_' . $request, $args);
			return true;
		}
		// it is possible the whole request
		else
		{
			// parse out the request into an array
			$request = url($request, true, false, true);
			
			// call the theme again
			$result = theme((array)$request);
			if($result == false)
				PEAR::raiseError('Theme function could not be handled because of an unrecognized argument.', E_DEBUG|E_WARN);
			return $result;
		}
	}
	else
		PEAR::raiseError('Theme function could not be handled because of an unrecognized argument.', E_DEBUG|E_WARN);
	return false;
}

/**
 * Implementation of output
 * @ingroup output
 */
function output_template($request)
{
	$request['template'] = validate_template($request);
	$request['tfile'] = validate_tfile($request);

	$file = setting('local_root') . 'templates' . DIRECTORY_SEPARATOR . $request['template'] . DIRECTORY_SEPARATOR . $request['tfile'];

	if(!isset($request['tfile']))
	{
		// if the tfile isn't specified, display the template template
		theme('template');
		
		return;
	}
	
	// set some general headers
	header('Content-Transfer-Encoding: binary');
	header('Content-Type: ' . getMime($file));
	
	// set up the output stream
	$op = fopen('php://output', 'wb');
	
	// get the input stream
	$fp = fopen($file, 'rb');
	
	//-------------------- THIS IS ALL RANAGES STUFF --------------------
	
	// range can only be used when the filesize is known
	
	// check for range request
	if(isset($_SERVER['HTTP_RANGE']))
	{
		list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);

		if ($size_unit == 'bytes')
		{
			// multiple ranges could be specified at the same time, but for simplicity only serve the first range
			// http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
			if(strpos($range_orig, ',') !== false)
				list($range, $extra_ranges) = explode(',', $range_orig, 2);
			else
				$range = $range_orig;
		}
		else
		{
			$range = '-';
		}
	}
	else
	{
		$range = '-';
	}
	
	// figure out download piece from range (if set)
	list($seek_start, $seek_end) = explode('-', $range, 2);

	// set start and end based on range (if set), else set defaults
	// also check for invalid ranges.
	$seek_end = (empty($seek_end)) ? (filesize($file) - 1) : min(abs(intval($seek_end)),(filesize($file) - 1));
	//$seek_end = $file['Filesize'] - 1;
	$seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)),0);
	
	// Only send partial content header if downloading a piece of the file (IE workaround)
	if ($seek_start > 0 || $seek_end < (filesize($file) - 1))
	{
		header('HTTP/1.1 206 Partial Content');
	}

	header('Accept-Ranges: bytes');
	header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . filesize($file));

	//headers for IE Bugs (is this necessary?)
	//header("Cache-Control: cache, must-revalidate");  
	//header("Pragma: public");

	header('Content-Length: ' . ($seek_end - $seek_start + 1));
	
	//-------------------- END RANAGES STUFF --------------------
	
	// close session now so they can keep using the website
	if(isset($_SESSION)) session_write_close();
	
	if(is_resource($fp) && is_resource($op))
	{
		// seek to start of missing part
		if(isset($seek_start))
			fseek($fp, $seek_start);
		
		// output file
		while (!feof($fp)) {
			fwrite($op, fread($fp, setting('buffer_size')));
		}
		
		// close file handles and return succeeded
		fclose($fp);
	}
}