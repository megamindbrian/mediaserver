<?php

$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

require_once LOCAL_ROOT . 'modules' . DIRECTORY_SEPARATOR . 'db_file.php';

// don't actually do anything in this class, this is just used for holding the database, and the get() method
class db_users extends db_file
{
	const DATABASE = 'users';
	
	const NAME = 'Users Paths from Database';
	
	const INTERNAL = true;
	
	// this expression is used to filter out usernames
	const USER_REG = '/[a-z0-9]{4}[a-z0-9]*/i';

	static function columns()
	{
		return array('id', 'Username', 'Email', 'Settings', 'Privilage', 'PrivateKey', 'LastLogin');
	}
	
	// return the structure of the database
	static function struct()
	{
		return array(
			'Username' 		=> 'TEXT',
			'Password' 		=> 'TEXT',
			'Email' 		=> 'TEXT',
			'Settings' 		=> 'TEXT',
			'Privilage'		=> 'INT',
			'PrivateKey'		=> 'TEXT',
			'LastLogin'		=> 'DATETIME'
		);
	}
	
	static function handles($file)
	{
		$file = str_replace('\\', '/', $file);
		if(USE_ALIAS == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
		
		// handle directories found in the LOCAL_USERS directory
		//  automatically create a user entry in the database for those directories
		// extract username from path
		if(substr($file, 0, strlen(LOCAL_USERS)) == LOCAL_USERS)
		{
			$file = substr($file, strlen(LOCAL_USERS));
			
			// remove rest of path
			if(strpos($file, '/') !== false)
				$file = substr($file, 0, strpos($file, '/'));
				
			if(preg_match(self::USER_REG, basename($file)) > 0)
			{
				return true;
			}
		}
		elseif(dirname($file) == '')
		{
			if(preg_match(self::USER_REG, basename($file)) > 0)
			{
				return true;
			}
		}
		
		return false;
	}

	static function handle($path, $force = false)
	{
		$file = str_replace('\\', '/', $file);
		
		if(self::handles($file))
		{
			$username = basename($path);
			
			// check if it is in the database
			$db_user = $GLOBALS['database']->query(array(
					'SELECT' => self::DATABASE,
					'WHERE' => 'Username = "' . addslashes($username) . '"',
					'LIMIT' => 1
				)
			, false);
			
			if( count($db_user) == 0 )
			{
				// just set up the user with default information
				//   if they don't use the plugin, this creates a system user
			}
			elseif($force)
			{
				// not really anything to do here
			}
		}
		
		return false;
	}
	
	static function get($request, &$count, &$error, $files = array())
	{
		if(USE_DATABASE)
		{
			if(count($files) > 0 && !isset($request['selected']))
			{
				$users = array();
				
				// get a list of users to look up
				foreach($files as $index => $file)
				{
					if(self::handles($file['Filepath']))
					{
						// replace virtual paths
						$path = str_replace('\\', '/', $file['Filepath']);
						if(USE_ALIAS == true) $path = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $path);
						
						if(substr($path, 0, strlen(LOCAL_USERS)) == LOCAL_USERS)
						{
							$path = substr($path, strlen(LOCAL_USERS));
							
							// remove rest of path
							if(strpos($path, '/') !== false)
								$path = substr($path, 0, strpos($path, '/'));
						}
						
						// add to list of users to look up
						$files[$index]['Username'] = $path;
						if(!isset($GLOBALS['user_cache'][$path]))
							$users[] = $path;
					}
				}
				$users = array_unique($users);
				
				// perform query to get all the needed users
				if(count($users) > 0)
				{
					$return = $GLOBALS['database']->query(array(
							'SELECT' => self::DATABASE,
							'WHERE' => 'Username = "' . join('" OR Username = "', $users) . '"',
							'LIMIT' => count($users)
						)
					, false);
					
					// replace get for easy lookup
					foreach($return as $i => $user)
					{
						$GLOBALS['user_cache'][$user['Username']] = $user;
					}
					
					// merge user information to each file
					foreach($files as $index => $file)
					{
						if(isset($file['Username']))
							$files[$index] = array_merge($GLOBALS['user_cache'][$file['Username']], $files[$index]);
					}
				}
				
			}
			elseif(isset($request['file']))
			{
				// change some of the default request variables
				$request['order_by'] = 'Username';
				$request['limit'] = 1;
				
				// modify the file variable to use username instead
				$file = str_replace('\\', '/', $request['file']);
				if(USE_ALIAS == true) $file = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file);
				
				if(substr($file, 0, strlen(LOCAL_USERS)) == LOCAL_USERS)
				{
					$file = substr($file, strlen(LOCAL_USERS));
					
					// remove rest of path
					if(strpos($file, '/') !== false)
						$file = substr($file, 0, strpos($file, '/'));
				}
				
				$request['search_Username'] = '=' . $file . '=';
				
				// unset the fields that aren't needed
				unset($request['file']);
				unset($request['files_id']);
				
				// extract user directory from path
				// add a users information to each file
				if(isset($file) && isset($GLOBALS['user_cache']))
				{
					$files = array(0 => $GLOBALS['user_cache']);
				}
				else
				{
					$files = parent::get($request, $count, $error, get_class());
					
					if(isset($file))
						$GLOBALS['user_cache'][$file] = $files[0];
				}
			}
	
			// remove restricted variables
			foreach($files as $i => $file)
			{
				unset($files[$i]['Password']);
			}
		}

		return $files;
	}
	
	static function remove($file)
	{
	}
	
	static function cleanup()
	{
	}

}

?>