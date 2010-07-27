<?php

/**
 * Implementation of register
 * @ingroup register
 */
function register_admin_tools()
{
	return array(
		'name' => lang('tools title', 'Tools'),
		'description' => lang('tools description', 'Tools for manipulating the database and viewing different types of information about the system.'),
		'privilage' => 10,
		'path' => __FILE__,
		'modules' => (is_dir('modules' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR)?
			load_modules('modules' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR)
			:
			array()),
		'template' => false,
	);
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return NULL by default, accepts any number greater than zero and less than the number of subtools for the selected tool module
 */
function validate_subtool($request)
{
	$request['module'] = validate($request, 'module');
	if(isset($request['subtool']) && is_numeric($request['subtool']) && $request['subtool'] >= 0 && 
		isset($GLOBALS['modules'][$request['module']]['subtools']) &&
		$request['subtool'] < count($GLOBALS['modules'][$request['module']]['subtools'])
	)
		return $request['subtool'];
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return False by default
 */
function validate_info_singular($request)
{
	return generic_validate_boolean_false($request, 'info_singular');
}

/**
 * Implementation of output
 * @ingroup output
 */
function output_admin_tools($request)
{
	// preffered order is a list for which order the tools should be arranged in, this is completely optional and makes the toolset a little more context aware
	$preffered_order = array('Site Information', 'Log Parser', 'Ascii File Names', 'Excessive Underscores and Periods');

	$request['subtool'] = validate($request, 'subtool');
	
	if(!isset($request['subtool']))
		theme('tools');
	else
		theme('tools_subtools');
	
}

