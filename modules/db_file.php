<?php

// the basic file type

class db_file
{
	// most of these methods should just be static, no need to intantiate the class
	// just good for organization purposes
	const DATABASE = 'files';
	
	const NAME = 'File';
	
	// this function specifies the level of detail for the array of file info, ORDER matters!
	static function DETAILS($detail)
	{
		$details = array(
				1 => array('id'),
				2 => array('Filename'),
				3 => array('Filename', 'Filemime'),
				4 => array('Filename', 'Filemime', 'Filesize'),
				5 => array('Filename', 'Filemime', 'Filesize', 'Filedate'),
				6 => array('Filename', 'Filemime', 'Filesize', 'Filedate'),
				7 => array('Filename', 'Filemime', 'Filesize', 'Filedate', 'Filetype'),
				8 => array('id', 'Filename', 'Filemime', 'Filesize', 'Filedate', 'Filetype'),
				9 => array('id', 'Filename', 'Filemime', 'Filesize', 'Filedate', 'Filetype', 'Filepath'),
				10 => array('id', 'Filename', 'Filemime', 'Filesize', 'Filedate', 'Filetype', 'Filepath')
			);
		
		if(isset($details[$detail]))
		{
			return $details[$detail];
		}
		else
		{
			return;
		}
	}

	
	// return whether or not this module handles trhe specified type of file
	static function handles($file)
	{
		return true;
	}
	
	// this function determines if the file qualifies for this type and handles it according
	static function handle($mysql, $file)
	{
		// files always qualify, we are going to log every single one!
		
		if(db_file::handles($file))
		{
			
			// check if it is in the database
			$db_file = $mysql->get('files', 
				array(
					'SELECT' => array('id', 'Filedate'),
					'WHERE' => 'Filepath = "' . $file . '"'
				)
			);
			
			if( count($db_file) == 0 )
			{
				// always add to file database
				$id = db_file::add($mysql, $file);
			}
			else
			{
	
				// update file if modified date has changed
				if( date("Y-m-d h:i:s", filemtime($file)) != $db_file[0]['Filedate'] )
				{
					$id = db_file::add($mysql, $file, $db_file[0]['id']);
				}
				else
				{
					print 'Skipping file: ' . $file . "\n";
				}
				
			}
			
		}
		
	}
	
	// add a given file to the given database
	// returns the id of the entry
	// if the id is given then it updates the entry
	static function add($mysql, $file, $id = NULL)
	{
	
		// get file extension
		$ext = getExt($file);
		$type = getExtType($ext);
		
		$fileinfo = array();
		$fileinfo['Filepath'] = $file;
		$fileinfo['Filename'] = basename($file);
		$fileinfo['Filesize'] = filesize($file);
		$fileinfo['Filemime'] = getFileType($file);
		$fileinfo['Filedate'] = date("Y-m-d h:i:s", filemtime($file));
		$fileinfo['Filetype'] = $type;
			
		// if the id is set then we are updating and entry
		if( $id != NULL )
		{
			print 'Modifying file: ' . $file . "\n";
			
			// update database
			$fileid = $mysql->set('files', $fileinfo, array('id' => $id));
		
			return $id;
		}
		else
		{
			print 'Adding file: ' . $file . "\n";
			
			// add to database
			$fileid = $mysql->set('files', $fileinfo);
		
			return $fileid;
		}
		
		
		usleep(1);
		
		ob_flush();
		
	}
	
	
	// the mysql can be left null to get the files from a directory, in which case a directory must be specified
	// if the mysql is provided, then the file listings will be loaded from the database
	// DETAIL!!!!111: the level of detail to display the files for see the DETAIL function!
	static function get($mysql, $props)
	{
		$files = array();
		
		if($mysql == NULL)
		{
			if (is_dir($props['DIR']))
			{
				// if we are over 5.0 use the scandir method
				if( version_compare(phpversion(), "5.0.0") >= 0 )
				{
					return scandir($props['DIR']);
				}
				else
				{
					// create file array
					if ($dh = opendir($props['DIR']))
					{
						while (($file = readdir($dh)) !== false)
						{
							$files[] = ($props['DIR'] . $file);
						}
						closedir($dh);
					}
					
				}
				
			}
		}
		else
		{
			// construct where statement
			if(isset($props['DIR']) && !isset($props['WHERE']))
			{
				$props['WHERE'] = 'Filepath REGEXP "^' . $dir . '"';
			}
		
			if(isset($props['DETAIL']))
			{
				$props['SELECT'] = db_file::DETAILS($props['DETAIL']);
			}
			else
			{
				$props['SELECT'] = db_file::DETAILS(10);
			}
		
			// get directory from database
			$files = $mysql->get(db_file::DATABASE, $props);
		}
			
		return $files;
	}
	
	// callback for the sql file list query
	static function cleanup_remove($row, $args)
	{
		if( !file_exists($row['Filepath']) )
		{
			// remove row from database
			$args['SQL']->set($args['DB'], NULL, array('Filepath' => $row['Filepath']));
			
			print 'Removing ' . $args['DB'] . ': ' . $row['Filepath'] . "\n";
			
			// pause so browser can recieve data
			usleep(1);
			
			ob_flush();
		}
	}
	
	
	// cleanup the non-existant files
	static function cleanup($mysql, $watched, $database = NULL)
	{
		if( $database == NULL )
		{
			$db = get_class_const(get_class(), 'DATABASE');
		}
		else
		{
			$db = $database;
		}
	
		// first clear all the items that are no longer in the watch list
		// since the watch is resolved all the items in watch have to start with the watched path
		$where_str = '';
		foreach($watched as $i => $watch)
		{
			$where_str .= ' Filepath REGEXP "^' . $watch['Filepath'] . '" OR';
		}
		// remove last OR
		$where_str = substr($where_str, 0, strlen($where_str)-2);
		$where_str = ' !(' . $where_str . ')';
		
		// remove items
		$mysql->set($db, NULL, $where_str);

		// since all the ones not apart of a watched directory is removed, now just check is every file still in the database exists on disk
		$mysql->query('SELECT Filepath FROM ' . $mysql->table_prefix . $db);
		
		$mysql->result_callback(array('db_file', 'cleanup_remove'), array('SQL' => new sql(DB_SERVER, DB_USER, DB_PASS, DB_NAME), 'DB' => $db));
				
		// remove any duplicates
		$files = $mysql->get($db,
			array(
				'SELECT' => array('MIN(id) as id', 'Filepath', 'COUNT(*) as num'),
				'OTHER' => 'GROUP BY Filepath HAVING num > 1'
			)
		);
		
		// remove first item from all duplicates
		foreach($files as $i => $file)
		{
			$mysql->set($db, NULL, array('id' => $file['id']));
			
			print 'Removing ' . $db . ': ' . $file['Filepath'] . "\n";
			
			// pause so browser can recieve data
			usleep(1);
			
			ob_flush();
		}
		
		print 'Cleanup for ' . (($database==NULL)?'files':$database) . " complete.\n";
		usleep(1);
		ob_flush();
		
	}
	
}

?>