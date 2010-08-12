<?php

function bootstrap($mode)
{
	// always include common functionality
	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';
	
	switch($mode)
	{
		case 'libraries':
			// change the include path to the libraries path
			ini_set('include_path', '.');
			
			break;
			
		case 'includes':
			// include all of the needed files to get the system up and running
			load_includes();
			
			break;
		
		case 'database':
			load_modules('include' . DIRECTORY_SEPARATOR . 'database.php');
			
			// add setup trigger so we don't need a register function
			
			
			break;
		
		case 'errors':
			// some special measures need to be taken to get error handling up and working
			load_error_handling();
			
			break;
			
		case 'modules':
			// add functionality from modules
			if(!function_exists('setting_modules_enable'))
			{
				/**
				 * Implementation of setting, validate all module_enable settings
				 * @ingroup setting
				 */
				function setting_modules_enable($settings, $module = 'modules')
				{
					// always return true if module is required
					if(in_array($module, get_required_modules()))
						return true;
						
					// check boolean value
					return generic_validate_boolean_true($settings, $module . '_enable');
				}
				
				// create functions for checking module_enable
				foreach($GLOBALS['modules'] as $module => $config)
				{
					// if the module supplys it's own function for checking if it is enabled
					//   this is useful if the enable state depends on more then the dependencies and needs logic to figure it out
					if(!function_exists('setting_' . $module . '_enable'))
					{
						if(!in_array($module, get_required_modules()))
							$GLOBALS['setting_' . $module . '_enable'] = create_function('$request', 'return setting_modules_enable($request, \'' . $module . '\');');
						else
							$GLOBALS['setting_' . $module . '_enable'] = create_function('$request', 'return true;');
					}
				}
			}
			
			break;
		
		case 'menus':
			load_modules('include' . DIRECTORY_SEPARATOR . 'menu.php');
			
			break;
			
		case 'full':
			bootstrap('errors');
			bootstrap('includes');
			bootstrap('database');
			bootstrap('menus');
			
			// read module list and create a list of available modules	
			load_modules('modules' . DIRECTORY_SEPARATOR);
			//load_modules('handlers' . DIRECTORY_SEPARATOR);
print_r($GLOBALS['modules']);

			bootstrap('modules');

			// set up the modules
			invoke_all_callback('setup', 'disable_module');

			// always validate the request
			validate_request();
	}
}


function load_includes()
{

	// load all the required includes for the system to work
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'module.php';
	require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'triggers.php';
	setup_triggers();
	
	/** require core functionality */
	require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core.php';
	
	// require compatibility
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'compatibility.php';

	// language must be included so that we can translate module definitions
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'lang.php';

	// include the database module, because it acts like a module, but is kept in the includes directory
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'session.php';
	
	// load forms for modules to use for generation
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'forms.php';
	
	// load request processor
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'request.php';

	// include the settings module ahead of time because it contains 1 needed function setting_settings_file()
	require_once setting_local_root() . 'modules' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'settings.php';
	
	// always include files handler
	require_once setting_local_root() . 'include' . DIRECTORY_SEPARATOR . 'files.php';
}


function load_error_handling()
{
	
	/**
	 * @name Error Levels
	 * Error codes so we know which errors to print to the user and which to print to debug
	 */
	//@{
	/** @enum E_DEBUG the DEBUG level error used for displaying errors in the debug template block */
	define('E_DEBUG',					2);
	define('E_VERBOSE',					4);
	/** @enum E_USER USER level errors are printed to the user by the templates */
	define('E_USER',					8);
	/** @enum E_WARN the WARN level error prints a different color in the error block, this is
	 * used by parts of the site that cause problems that may not be intentional */
	define('E_WARN',					16);
	/** @enum E_FATAL the FATAL errors are ones that cause the script to end at an unexpected point */
	define('E_FATAL',					32);
	/** @enum E_NOTE the NOTE error level is used for displaying positive information to users such as
	 * "account has been created" */
	define('E_NOTE',					64);
	//@}
		
	/** require pear for error handling */
	if(include_once 'PEAR.php')
	{
		//include_once 'MIME' . DIRECTORY_SEPARATOR . 'Type.php';
	}
	else
	{
		class PEAR_Error
		{
			var $code = 0;
			var $message = '';
			var $backtrace = array();
		}
		
		// bootstrap pear error handling but don't load any other pear dependencies
		class PEAR
		{
			static function raiseError($message, $code)
			{
				$error = new PEAR_Error();
				$error->code = $code;
				$error->message = $message;
				$error->backtrace = debug_backtrace();
				call_user_func_array(PEAR_ERROR_CALLBACK, array($error));
			}
			
			static function setErrorHandling($type, $error_func)
			{
				if(is_callable($error_func))
				{
					define('PEAR_ERROR_CALLBACK', $error_func);
				}
			}
		}
	}

	/** Set the error handler to use our custom function for storing errors */
	error_reporting(E_ALL);
	
	/** stores a list of all errors */
	$GLOBALS['errors'] = array();
	/** stores a list of all user errors */
	$GLOBALS['user_errors'] = array();
	/** stores a list of all warnings */
	$GLOBALS['warn_errors'] = array();
	/** stores a list of all notices and friendly messages */
	$GLOBALS['note_errors'] = array();
	/** stores a list of all debug information */
	$GLOBALS['debug_errors'] = array();
}