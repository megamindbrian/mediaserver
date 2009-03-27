<?php

//$no_setup = true;
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'common.php';

// control lower level handling of each database
// things to consider:
// audio-database (for storing artist, album, track fields)
// file-database (used primarily by the virtualfs to storing file information)
// picture-database (for storing picture information)
// video-database (for storing all video information)
// watch-database (a list of directories that should be watched for media files)

// everything should fit into the main 3 mediums (music,pictures,videos) and everything else is just a file
// scalability (add a calendar handler? rss-handler?)

// pretty self explanator handler class for sql databases
class sql_global
{
	var $db_connect_id;
	var $rowset = array();
	var $num_queries = 0;

//=============================================
//  sql_db($SQL_server, $SQL_Username, $SQL_password, $SQL_database)
//=============================================
//  When the sql_db object is created it does a
//    few things
//  Variables for logging into the database are
//    passed through
//  Also switch to needed table if it is defined
//=============================================
	function sql_global()
	{
	}
	
	// install function
	function install()
	{
		// create module tables
		$tables_created = array();
		foreach($GLOBALS['modules'] as $i => $module)
		{
			if($module != 'fs_file')
			{
				$query = 'CREATE TABLE IF NOT EXISTS ' . DB_PREFIX . constant($module . '::DATABASE') . ' (';
				$struct = call_user_func($module . '::struct');
				if(is_array($struct) && !in_array(constant($module . '::DATABASE'), $tables_created))
				{
					$tables_created[] = constant($module . '::DATABASE');
					if(!in_array('id', array_keys($struct)))
						$query .= 'id BIGINT NOT NULL AUTO_INCREMENT, PRIMARY KEY (id),';
					foreach($struct as $column => $type)
					{
						if(strpos($type, ' ') === false)
							$query .= ' ' . $column . ' ' . $type . ' NOT NULL,';
						else
							$query .= ' ' . $column . ' ' . $type . ',';
					}
					// remove last comma
					$query[strlen($query)-1] = ')';
					
					// query database
					$this->query($query) or print_r(mysql_error());
				}
			}
		}
		
		// alter table to match the struct
		
		$ids_tables = array();
		$ids = $this->query(array('SELECT' => db_ids::DATABASE, 'LIMIT' => 1)) or print_r(mysql_error());
		print_r($ids);
		if(count($ids) > 0)
		{
			foreach(array_keys($ids[0]) as $i => $key)
			{
				if(substr($key, strlen($key)-3) == '_id')
				{
					$ids_tables[] = substr($key, 0, strlen($key) - 3);
				}
			}
			
			// go through tables and find missing
			foreach($GLOBALS['tables'] as $i => $db)
			{
				if(!in_array($db, $ids_tables))
				{
					// alter the table
					$this->query('ALTER TABLE ' . DB_PREFIX . db_ids::DATABASE . ' ADD ' . $db . '_id BIGINT NOT NULL') or print_r(mysql_error());
				}
			}
		}
	}
	
	// variables that can be defined in the request are validated here
	//   these are general SQL variables, ones specific to the module should be validated there
	//   after validation they will be set in the passed in props which can be sent to the query function
	static function validate(&$request, &$props, $module)
	{
		$columns = call_user_func($module . '::columns');
		
		if(!is_array($props)) $props = array();
		
		if( !isset($request['start']) || !is_numeric($request['start']) || $request['start'] < 0 )
			$request['start'] = 0;
		if( !isset($request['limit']) || !is_numeric($request['limit']) || $request['limit'] < 0 )
			$request['limit'] = 15;
		if( !isset($request['order_by']) || !in_array($request['order_by'], $columns) )
		{
			// make sure if it is a list that it is all valid columns
			$columns = split(',', (isset($request['order_by'])?$request['order_by']:''));
			foreach($columns as $i => $column)
			{
				if(!in_array($column, call_user_func($module . '::columns')))
					unset($columns[$i]);
			}
			if(count($columns) == 0)
				$request['order_by'] = 'Filepath';
			else
				$request['order_by'] = join(',', $columns);
		}
		if( !isset($request['direction']) || ($request['direction'] != 'ASC' && $request['direction'] != 'DESC') )
			$request['direction'] = 'ASC';
		if( isset($request['group_by']) && !in_array($request['group_by'], $columns) )
		{
			// make sure if it is a list that it is all valid columns
			$columns = split(',', $request['group_by']);
			foreach($columns as $i => $column)
			{
				if(!in_array($column, call_user_func($module . '::columns')))
					unset($columns[$i]);
			}
			if(count($columns) == 0)
				unset($request['group_by']);
			else
				$request['group_by'] = join(',', $columns);
		}
			
		// which columns to search
		if( isset($request['columns']) && !in_array($request['columns'], $columns) )
		{
			// make sure if it is a list that it is all valid columns
			$columns = split(',', $request['columns']);
			foreach($columns as $i => $column)
			{
				if(!in_array($column, call_user_func($module . '::columns')))
					unset($columns[$i]);
			}
			if(count($columns) == 0)
				unset($request['columns']);
			else
				$request['columns'] = join(',', $columns);
		}
		if( isset($request['id']) )
			$request['item'] = $request['id'];
			
		getIDsFromRequest($request, $request['selected']);
		if(isset($request['group_by'])) $props['GROUP'] = $request['group_by'];
		if(isset($request['order_trimmed']) && $request['order_trimmed'] == true)
		{
			$props['ORDER'] = 'TRIM(LEADING "a " FROM TRIM(LEADING "an " FROM TRIM(LEADING "the " FROM LOWER( ' . 
								join(' )))), TRIM(LEADING "a " FROM TRIM(LEADING "an " FROM TRIM(LEADING "the " FROM LOWER( ', split(',', $request['order_by'])) . 
								' ))))' . ' ' . $request['direction'];
		}
		else
		{
			$props['ORDER'] = $request['order_by'] . ' ' . $request['direction'];
		}
		$props['LIMIT'] = $request['start'] . ',' . $request['limit'];
	}
	
	
	// compile the statmeent based on an abstract representation
	static function statement_builder($props)
	{
		if(is_string($props))
		{
			return $props;
		}
		elseif(is_array($props))
		{
			if(!isset($props['WHERE'])) $where = '';
			elseif(is_array($props['WHERE'])) $where = 'WHERE ' . join(' AND ', $props['WHERE']);
			elseif(is_string($props['WHERE'])) $where = 'WHERE ' . $props['WHERE'];
				
			if(!isset($props['GROUP'])) $group = '';
			elseif(is_string($props['GROUP'])) $group = 'GROUP BY ' . $props['GROUP'];
				
			if(!isset($props['HAVING'])) $having = '';
			elseif(is_array($props['HAVING'])) $having = 'HAVING ' . join(' AND ', $props['HAVING']);
			elseif(is_string($props['HAVING'])) $having = 'HAVING ' . $props['HAVING'];
			
			if(!isset($props['ORDER'])) $order = '';
			elseif(is_string($props['ORDER'])) $order = 'ORDER BY ' . $props['ORDER'];
			
			if(!isset($props['LIMIT'])) $limit = '';
			elseif(is_numeric($props['LIMIT'])) $limit = 'LIMIT ' . $props['LIMIT'];
			elseif(is_string($props['LIMIT'])) $limit = 'LIMIT ' . $props['LIMIT'];
				
			if(isset($props['INSERT']))
			{
				$insert = 'INSERT INTO ' . (in_array($props['INSERT'], $GLOBALS['tables'])?(DB_PREFIX . $props['INSERT']):$props['INSERT']);
				
				if(!isset($props['COLUMNS']) && isset($props['VALUES']) && is_array($props['VALUES']))
				{
					$props['COLUMNS'] = array_keys($props['VALUES']);
					$props['VALUES'] = array_values($props['VALUES']);
				}
				
				if(!isset($props['COLUMNS'])) return false;
				elseif(is_array($props['COLUMNS'])) $columns = '(' . join(', ', $props['COLUMNS']) . ')';
				elseif(is_string($props['COLUMNS'])) $columns = '(' . $props['COLUMNS'] . ')';
				
				if(!isset($props['VALUES']) && !isset($props['SELECT'])) return false;
				
				if(!isset($props['VALUES'])) $values = $props['SELECT'];
				elseif(is_array($props['VALUES'])) $values = 'VALUES ("' . join('", "', $props['VALUES']) . '")';
				elseif(is_string($props['VALUES'])) $values = 'VALUES (' . $props['VALUES'] . ')';
	
				$statement = $insert . ' ' . $columns . ' ' . $values;
			}
			elseif(isset($props['SELECT']))
			{
				if(!isset($props['COLUMNS'])) $columns = '*';
				elseif(is_array($props['COLUMNS'])) $columns = join(', ', $props['COLUMNS']);
				elseif(is_string($props['COLUMNS'])) $columns = $props['COLUMNS'];
				
				$select = 'SELECT ' . $columns . ' FROM ' . (in_array($props['SELECT'], $GLOBALS['tables'])?(DB_PREFIX . $props['SELECT']):$props['SELECT']);
				
				$statement = $select . ' ' . $where . ' ' . $group . ' ' . $having . ' ' . $order . ' ' . $limit;
			}
			elseif(isset($props['UPDATE']))
			{
				$update = 'UPDATE ' . (in_array($props['UPDATE'], $GLOBALS['tables'])?(DB_PREFIX . $props['UPDATE']):$props['UPDATE']) . ' SET';
				
				if(!isset($props['COLUMNS']) && isset($props['VALUES']) && is_array($props['VALUES']))
				{
					$props['COLUMNS'] = array_keys($props['VALUES']);
					$props['VALUES'] = array_values($props['VALUES']);
				}
				
				if(!isset($props['COLUMNS'])) return false;
				elseif(is_array($props['COLUMNS'])) $columns = $props['COLUMNS'];
				elseif(is_string($props['COLUMNS'])) $columns = split(',', $props['COLUMNS']);
	
				if(!isset($props['VALUES'])) return false;
				elseif(is_array($props['VALUES'])) $values = $props['VALUES'];
				elseif(is_string($props['VALUES'])) $values = split(',', $props['VALUES']);
				
				$set = array();
				foreach($columns as $i => $key)
				{
					$set[] = $key . ' = "' . $values[$i] . '"';
				}
	
				$statement = $update . ' ' . join(', ', $set) . ' ' . $where . ' ' . $order . ' ' . $limit;
			}
			elseif(isset($props['DELETE']))
			{
				$delete = 'DELETE FROM ' . (in_array($props['DELETE'], $GLOBALS['tables'])?(DB_PREFIX . $props['DELETE']):$props['DELETE']);
	
				$statement = $delete . ' ' . $where . ' ' . $order . ' ' . $limit;
			}
			else
			{
				return $props;
			}
			
			return $statement;
		}
	}
	
	// function for making calls on the database, this is what is called by the rest of the site
	function query($props)
	{
		$query = SQL::statement_builder($props);
		
		if(isset($_REQUEST['log_sql']) && $_REQUEST['log_sql'] == true)
			log_error('DATABASE: ' . $query);
			
		if(isset($props['CALLBACK']))
		{
			$result = $this->db_query_callback($query);
		}
		else
		{
			$result = $this->db_query($query);
		}
		
		if($result !== false && is_array($props) && (isset($props['SELECT']) || isset($props['SHOW'])))
		{
			// this is used for queries too large for memory
			if(isset($props['CALLBACK']))
			{
				$this->result_callback($props['CALLBACK']['FUNCTION'], $props['CALLBACK']['ARGUMENTS']);
			}
			else
			{
				return $this->result();
			}
		}
		elseif($result !== false && is_array($props) && (isset($props['INSERT']) || isset($props['UPDATE']) || isset($props['REPLACE']) || isset($props['DELETE'])))
		{
			if(isset($props['INSERT']))
			{
				return $this->getid();
			}
			else
			{
				return $this->affected();
			}
		}
		else
		{
			return $result;
		}
	}
	
	
//=============================================
//  getid()
//=============================================
//  returns the id from the last insert operations
//=============================================
	function getid()
	{

	}

//=============================================
//  result()
//=============================================
//  returns the results from the previous query in associated array form
//=============================================
	function result()
	{

	}
	
//=============================================
//  count()
//=============================================
//  returns the number of rows selected from the database
//=============================================
	function numrows()
	{

	}
	
//=============================================
//  numrows()
//=============================================
//  returns the number of affected rows from the database
//=============================================
	function affected()
	{

	}

//=============================================
//  query($query = "")
//=============================================
//  Just a handler for database queries specific to the objects connect id
//=============================================
	function db_query($query = "")
	{
		
	}
	function db_query_callback($query = "")
	{
		
	}

	function error()
	{
		
	}
	
//=============================================
//  close()
//=============================================
//  close the connection
//=============================================
	function close()
	{

	}
	
}



?>
