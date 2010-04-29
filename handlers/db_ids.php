<?php
include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'db_file.php';

// id handler
class db_ids extends db_file
{
	const DATABASE = 'ids';
	
	const NAME = 'IDs from Database';
	
	const INTERNAL = true;

	static function columns()
	{
		return array_keys(self::struct());
	}
	
	static function setupTables()
	{
		// loop through each handler and compile a list of databases
		$GLOBALS['tables'] = array();
		foreach($GLOBALS['handlers'] as $i => $handler)
		{
			if(defined($handler . '::DATABASE'))
				$GLOBALS['tables'][] = constant($handler . '::DATABASE');
		}
		$GLOBALS['tables'] = array_values(array_unique($GLOBALS['tables']));
	}
	
	static function struct()
	{
		if(!isset($GLOBALS['tables']))
			self::setupTables();
		
		$struct = array(
			'Filepath' 		=> 'TEXT',
			'Hex'			=> 'TEXT',
		);
		foreach($GLOBALS['tables'] as $i => $table)
		{
			if($table != db_ids::DATABASE && $table != db_watch_list::DATABASE && $table != db_alias::DATABASE && $table != db_watch::DATABASE && $table != db_users::DATABASE)
				$struct[$table . '_id'] = 'INT';
		}
		
		return $struct;
	}
	
	static function handles($file)
	{
		return true;
	}

	static function handle($file, $force = false, $ids = array())
	{
		$file = str_replace('\\', '/', $file);
		
		// check if it is in the database
		$db_ids = $GLOBALS['database']->query(array(
				'SELECT' => self::DATABASE,
				'COLUMNS' => array('id'),
				'WHERE' => 'Filepath = "' . addslashes($file) . '"',
				'LIMIT' => 1
			)
		, false);
		
		// only do this very expensive part if it is not in database or force is true
		$fileinfo = array();
		if(count($db_ids) == 0 || $force == true)
		{
			// get all the ids from all the tables
			$fileinfo['Filepath'] = addslashes($file);
			$fileinfo['Hex'] = bin2hex($file);
			foreach($GLOBALS['tables'] as $i => $table)
			{
				if($table != db_ids::DATABASE && $table != db_watch_list::DATABASE && $table != db_alias::DATABASE && $table != db_watch::DATABASE && $table != db_users::DATABASE)
				{
					if(isset($ids[$table . '_id']))
					{
						if($ids[$table . '_id'] !== false)
							$fileinfo[$table . '_id'] = $ids[$table . '_id'];
					}
					else
					{
						$tmp_ids = $GLOBALS['database']->query(array(
								'SELECT' => $table,
								'COLUMNS' => 'id',
								'WHERE' => 'Filepath = "' . addslashes($file) . '"',
								'LIMIT' => 1
							)
						, false);
						if(isset($tmp_ids[0])) $fileinfo[$table . '_id'] = $tmp_ids[0]['id'];
					}
				}
			}
		}
		
		// only add to database if the filepath exists in another table
		if(count($fileinfo) > 2)
		{
			// add list of ids
			if( count($db_ids) == 0 )
			{
				PEAR::raiseError('Adding id for file: ' . $file, E_DEBUG);
				
				// add to database
				return $GLOBALS['database']->query(array('INSERT' => self::DATABASE, 'VALUES' => $fileinfo), false);
			}
			// update ids
			elseif($force)
			{
				PEAR::raiseError('Modifying id for file: ' . $file, E_DEBUG);
				
				$id = $GLOBALS['database']->query(array('UPDATE' => self::DATABASE, 'VALUES' => $fileinfo, 'WHERE' => 'id=' . $db_ids[0]['id']), false);
				return $db_ids[0]['id'];
			}
		}
		return false;
	}
	
	static function get($request, &$count, $files = array())
	{
		if(!$GLOBALS['settings']['use_database'])
		{
			PEAR::raiseError(get_class() . '::get() called by mistake, use_database is set to false', E_DEBUG);
			$count = 0;
			return array();
		}
		
		if(count($files) > 0 && !isset($request['selected']))
		{
			$request['item'] = '';
			foreach($files as $index => $file)
			{
				$request['item'] .= $file['id'] . ',';
			}
			$request['item'] = substr($request['item'], 0, strlen($request['item'])-1);
		}

		$request['selected'] = validate_selected($request);

		// select an array of ids!
		if(isset($request['selected']) && count($request['selected']) > 0 )
		{
			$return = $GLOBALS['database']->query(array(
					'SELECT' => self::DATABASE,
					'WHERE' => constant($request['cat'] . '::DATABASE') . '_id = ' . join(' OR ' . constant($request['cat'] . '::DATABASE') . '_id = ', $request['selected']),
					'LIMIT' => count($files)
				)
			, true);
			
			if(count($files) == 0)
				return $return;
			
			// replace key for easy lookup
			$ids = array();
			foreach($return as $i => $id)
			{
				$ids[$id[constant($request['cat'] . '::DATABASE') . '_id']] = $id;
			}
			
			// add id information to file
			foreach($files as $index => $file)
			{
				if(!isset($ids[$file['id']]) || $ids[$file['id']]['Filepath'] != preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file['Filepath']))
				{
					// handle file
					$id = self::handle(preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $file['Filepath']), true, array(constant($request['cat'] . '::DATABASE') . '_id' => $file['id']));
					$tmp_id = $GLOBALS['database']->query(array(
							'SELECT' => self::DATABASE,
							'WHERE' => 'id = ' . $id,
							'LIMIT' => 1
						)
					, true);
					
					if(count($tmp_id) == 0)
					{
						PEAR::raiseError('There was an error getting the IDs.', E_USER);
						return array();
					}
					
					$ids[$file['id']] = $tmp_id[0];
				}
				
				// merge with output array
				$files[$index] = array_merge($ids[$file['id']], $files[$index]);
		
				// also set id to centralize id
				$files[$index]['id'] = $ids[$file['id']]['id'];
			}
		}
		elseif(isset($request['file']))
		{
			$files = array();
			foreach($GLOBALS['tables'] as $i => $table)
			{
				if(isset($request[$table . '_id']) && is_numeric($request[$table . '_id']))
				{
					$files = $GLOBALS['database']->query(array(
							'SELECT' => self::DATABASE,
							'WHERE' => $table . '_id = ' . $request[$table . '_id'],
							'LIMIT' => 1
						)
					, true);
					break;
				}
			}
			
			// if the id is not found for the file, add it
			if(count($files) == 0)
			{
				$id = self::handle(preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $request['file']), true);
				$files = $GLOBALS['database']->query(array(
						'SELECT' => self::DATABASE,
						'WHERE' => 'id = ' . $id,
						'LIMIT' => 1
					)
				, true);
				
				if(count($files) == 0)
				{
					PEAR::raiseError('There was an error getting the IDs.', E_USER);
					return array();
				}
			}
		}
		else
		{
			$files = parent::get($request, $count, get_class());
		}
		
		return $files;
	}
	
	static function remove($file, $handler = NULL)
	{
		if($handler != NULL)
		{
			// do the same thing db_file does except update and set handler_id to 0
			$file = str_replace('\\', '/', $file);
			
			// remove files with inside paths like directories
			if($file[strlen($file)-1] != '/') $file_dir = $file . '/';
			else $file_dir = $file;
			
			// all the removing will be done by other handlers
			$GLOBALS['database']->query(array('UPDATE' => self::DATABASE, 'VALUES' => array(constant($handler . '::DATABASE') . '_id' => 0), 'WHERE' => 'Filepath = "' . addslashes($file) . '" OR LEFT(Filepath, ' . strlen($file_dir) . ') = "' . addslashes($file_dir) . '"'), false);	
		}
	}
	
	static function cleanup()
	{
		parent::cleanup(get_class());
		
		// remove empty ids
		$where = '';
		foreach($GLOBALS['tables'] as $i => $db)
		{
			$where .= $db . '_id=0 AND';
		}
		$where = substr($where, 0, strlen($where) - 3);

		$GLOBALS['database']->query(array(
			'DELETE' => self::DATABASE,
			'WHERE' => $where
		), false);
	}

}

?>