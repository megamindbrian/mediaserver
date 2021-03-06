<?php
error_reporting(E_ALL);
ini_set('short_open_tag', true);

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'compatibility.inc';

function bootstrap($mode = 'all')
{
	// start timer
	if(!isset($GLOBALS['tm_start']))
		$GLOBALS['tm_start'] = array_sum(explode(' ', microtime()));
	
	if(function_exists('raise_error'))	
		raise_error('Bootstrapping ' . $mode, E_DEBUG);

	switch($mode)
	{
		case 'phpids':

			if(include_path('IDS/Init.php') != false)
			{
				include_once 'IDS/Init.php';
				$request = array(
				  'REQUEST' => $_REQUEST,
				  'GET' => $_GET,
				  'POST' => isset($_POST)?$_POST:NULL,
				  'COOKIE' => $_COOKIE
				);
				$init = IDS_Init::init(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Config.ini');
				$ids = new IDS_Monitor($request, $init);
				$result = $ids->run();
				
				if (!$result->isEmpty()) {
					raise_error('Impact from PHPIDS: ' . $ids->getReport()->getImpact(), E_DEBUG);
				}
			}
			break;
			
		case 'database':
			load_module(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'database.inc');

			if(!is_module('database') || !dependency('database'))
			{
				$GLOBALS['database'] = false;
				raise_error('Database not installed properly!', E_DEBUG|E_USER);
			}
			else
			{
				
			}
			
			break;
			
		case 'language':
			load_module(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'language.inc');
			
			if(!function_exists('lang'))
			{
				define('T_URL', 2);define('T_SKIP', 4);define('T_REPLACE', 8);define('T_NEW_CONTEXT', 16);define('T_IN_ATTRIBUTE', 32);define('T_NO_SPAN', 64);
				function lang($text, $key) { $args = array_filter(array_slice(func_get_args(), 0, func_num_args() - 1), 'is_string'); return (count($args) < 2)?array_shift($args):$args; };
			}
			break;
			
		case 'all':
			$includes = array(
				'error.inc',
				'common.inc',
				'settings.inc',
				'module.inc',
				'file.inc',
				'menu.inc',
				'triggers.inc',
				'request.inc',
				'template.inc',
				'session.inc',
				'users.inc',
				'curl.inc',
				'forms.inc',
			);
			
			// include all required system functionality
			foreach($includes as $i => $include)
			{
				if(!file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $include))
					die('Missing ' . dirname(__FILE__) . DIRECTORY_SEPARATOR . $include . ' : Includes are corrupted, please reinstall!');

				require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . $include;
			}

			preload_error();
			preload_triggers();
			preload_menu();
			preload_template();

            raise_error('Preload complete. Includes loaded.', E_VERBOSE);
			
			// try to run language options
			bootstrap('language');
				
			// run register functions and add to modules
			foreach($includes as $i => $include)
			{
				$config = load_module(dirname(__FILE__) . DIRECTORY_SEPARATOR . $include);
			}
			
			raise_error('Core modules loaded.', E_DEBUG);
			
			if(setting('database_enable'))
				bootstrap('database');
			
			// load all modules in to global
			load_modules(setting_local_root() . 'modules');
			
			raise_error('All module loading complete.', E_DEBUG);

			bootstrap('mime');
		
			// bootstrap the modules and setup
			bootstrap('modules', array_keys($GLOBALS['modules']));
			
			raise_error('Bootstrap complete! Processing request.', E_DEBUG);

			request_validate();
			
			raise_error('Request completed.  Moving on', E_VERBOSE);
			
			break;
		
		case 'mime':
			setup_mime();
			break;
			
		case 'modules':
			// put them in order by dependency
			$modules = array_intersect(get_modules(), func_get_arg(1));
			
			foreach($modules as $i => $module)
			{
                if(dependency($module) || in_array($module, get_required_modules())) {
                    invoke_module('setup', $module);
                }

				bootstrap_or_disable($module);
			}

			break;
	}
		
	if(function_exists('raise_error'))	
		raise_error('Bootstrapping ' . $mode . ' complete', E_DEBUG);
	
}

function bootstrap_or_disable($module)
{
	if(!dependency($module) && !in_array($module, get_required_modules()))
	{
		$GLOBALS['settings'][$module . '_enable'] = false;
		// this prevents us from disabling required modules on accident
		$GLOBALS['settings'][$module . '_enable'] = setting($module . '_enable');
	}
	else
	{
		invoke_all('bootstrap', $module);
	}
}

/** 
 * parse mime types from a mime.types file, 
 * This functionality sucks less then the PEAR mime type library
 * @ingroup setup
 */
function setup_mime()
{
	if(!defined('FOLDER_MIME'))
		define('FOLDER_MIME', 'httpd/unix-directory');
	
	// this will load the mime-types from a linux dist mime.types file stored in includes
	// this will organize the types for easy lookup
	if(file_exists(include_path('mime.types')))
	{
		$handle = fopen(include_path('mime.types'), 'r');
		$mime_text = fread($handle, filesize(include_path('mime.types')));
		fclose($handle);
		
		$mimes = explode("\n", $mime_text);
		
		$ext_to_mime = array();
		foreach($mimes as $index => $mime)
		{
			$mime = preg_replace('/#.*?$/', '', $mime);
			if($mime != '')
			{
				// mime to ext
				$file_types = preg_split('/[\s,]+/', $mime);
				$mime_type = $file_types[0];
				// general type
				$tmp_type = explode('/', $mime_type);	
				$type = $tmp_type[0];
				// unset mime part to get all its filetypes
				unset($file_types[0]);
				
				// ext to mime
				foreach($file_types as $index => $ext)
				{
					$ext_to_mime[strtolower($ext)] = $mime_type;
				}
			}
		}
		
		// set global variables
		$GLOBALS['ext_to_mime'] = $ext_to_mime;
	}
}

