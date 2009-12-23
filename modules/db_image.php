<?php

$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

require_once LOCAL_ROOT . 'modules' . DIRECTORY_SEPARATOR . 'db_file.php';

// include the id handler
require_once LOCAL_ROOT . 'include' . DIRECTORY_SEPARATOR . 'getid3' . DIRECTORY_SEPARATOR . 'getid3.php';

// set up id3 reader incase any files need it
$GLOBALS['getID3'] = new getID3();

// music handler
class db_image extends db_file
{
	const DATABASE = 'image';
	
	const NAME = 'Images from Database';

	static function columns()
	{
		return array_keys(self::struct());
	}
	
	static function struct()
	{
		return array(
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
		);
	}
	
	// this is the priority of sections to check for picture information
	// from most accurate --> least accurate
	static function PRIORITY()
	{
		return array('COMPUTED', 'WINXP', 'IFD0', 'EXIF', 'THUMBNAIL');
	}

	// COMPUTED usually contains the most accurate height and width values
	// IFD0 contains the make and model we are looking for
	// WINXP contains comments we should copy
	// EXIF contains a cool exposure time
	// THUMBNAIL just incase the thumbnail has some missing information
	

	static function handles($file)
	{
				
		// get file extension
		$ext = getExt(basename($file));
		$type = getExtType($ext);
		
		if( $type == 'image' )
		{
			return true;
		}
		
		return false;

	}

	static function handle($file, $force = false)
	{
		$file = str_replace('\\', '/', $file);
		
		if(self::handles($file))
		{
			// check to see if it is in the database
			$db_image = $GLOBALS['database']->query(array(
					'SELECT' => self::DATABASE,
					'COLUMNS' => 'id',
					'WHERE' => 'Filepath = "' . addslashes($file) . '"',
					'LIMIT' => 1
				)
			, false);
			
			// try to get image information
			if( count($db_image) == 0 )
			{
				return self::add($file);
			}
			elseif($force)
			{
				return self::add($file, $db_image[0]['id']);
			}

		}
		return false;
	}
	
	static function getInfo($file)
	{
		$file = str_replace('/', DIRECTORY_SEPARATOR, $file);
		
		$priority = array_reverse(self::PRIORITY());
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

	static function add($file, $image_id = NULL)
	{
		$fileinfo = self::getInfo($file);
	
		if( $image_id == NULL )
		{
			log_error('Adding image: ' . $file);
			
			// add to database
			$id = $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo), false);
			
			return $id;
		}
		else
		{
			log_error('Modifying image: ' . $file);
			
			// update database
			$id = $GLOBALS['database']->query(array('UPDATE' => self::DATABASE, 'VALUES' => $fileinfo, 'WHERE' => 'id=' . $image_id), false);
		
			return $image_id;
		}
			
	}
	
	static function get($request, &$count, &$error)
	{
		return parent::get($request, $count, $error, get_class());
	}
	
	static function remove($file)
	{
		parent::remove($file, get_class());
	}
	
	static function cleanup()
	{
		// call default cleanup function
		parent::cleanup(get_class());
	}

}

?>