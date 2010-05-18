<?php

/** 
 * Implementation of register_handler
 * @ingroup register_handler
 */
function register_image()
{
	return array(
		'name' => 'Images',
		'description' => 'Load information about all images.',
		'database' => array(
			'Filepath' 		=> 'TEXT',
			'Height'		=> 'INT',
			'Width'			=> 'INT',
			'Make'			=> 'TEXT',
			'Model'			=> 'TEXT',
			'Comments'		=> 'TEXT',
			'Keywords'		=> 'TEXT',
			'Title'			=> 'TEXT',
			'Author'		=> 'TEXT',
			'ExposureTime'	=> 'TEXT'
		),
	);
}

/**
 * Implementation of setup_handler
 * @ingroup setup_handler
 */
function setup_image()
{
	if(isset($GLOBALS['getID3']))
		return;
		
	// include the id handler
	include_once setting('local_root') . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';
	
	// set up id3 reader incase any files need it
	$GLOBALS['getID3'] = new getID3();
}
	
/**
 * Implementation of handles
 * @ingroup handles
 */
function handles_image($file)
{
	$file = str_replace('\\', '/', $file);
	if(setting('admin_alias_enable') == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
			
	// get file extension
	$type = getExtType($file);
	if( $type == 'image' )
	{
		return true;
	}
	
	return false;
}


// this is the priority of sections to check for picture information
// from most accurate --> least accurate
function PRIORITY()
{
	return array('COMPUTED', 'WINXP', 'IFD0', 'EXIF', 'THUMBNAIL');
}

// COMPUTED usually contains the most accurate height and width values
// IFD0 contains the make and model we are looking for
// WINXP contains comments we should copy
// EXIF contains a cool exposure time
// THUMBNAIL just incase the thumbnail has some missing information


/**
 * Helper function
 */
function get_image_info($file)
{
	$file = str_replace('/', DIRECTORY_SEPARATOR, $file);
	
	$priority = array_reverse(PRIORITY());
	$info = $GLOBALS['getID3']->analyze($file);
	
	// pull information from $info
	$fileinfo = array();
	$fileinfo['Filepath'] = addslashes(str_replace('\\', '/', $file));
	
	// get information from sections
	if(isset($info['fileformat']) && isset($info[$info['fileformat']]['exif']))
	{
		$exif = $info[$info['fileformat']]['exif'];
		foreach($priority as $i => $section)
		{
			if(isset($exif[$section]))
			{
				foreach($exif[$section] as $key => $value)
				{
					if($key == 'Height' || $key == 'Width' || $key == 'Make' || $key == 'Model' || $key == 'Comments' || $key == 'Keywords' || $key == 'Title' || $key == 'Author' || $key == 'ExposureTime')
					{
						$fileinfo[$key] = $value;
					}
				}
			}
		}
	}

	// do not get thumbnails of image
	//$fileinfo['Thumbnail'] = addslashes(self::makeThumbs($file));
	
	return $fileinfo;
}

