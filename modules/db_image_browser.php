<?php

$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

require_once LOCAL_ROOT . 'modules' . DIRECTORY_SEPARATOR . 'db_image.php';

// music handler
class db_image_browser extends db_image
{
	const DATABASE = 'image';
	
	const NAME = 'Browser Image';

	static function handles($file)
	{
				
		// get file extension
		$ext = getExt($file);
		$type = getExtType($ext);
		
		if( $type == 'image' )
		{
			switch($ext)
			{
				case 'bmp':
				case 'gif':
				case 'png':
				case 'jpeg':
				case 'jpg':
					return true;
				default:
					return false;
			}
		}
		
		return false;

	}

	static function handle($mysql, $file)
	{
	}
	
	static function get($mysql, $props)
	{
		return array();
	}


	static function cleanup($mysql, $watched)
	{
	}
}

?>