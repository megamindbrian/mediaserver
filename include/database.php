<?php

//$no_setup = true;
ini_set('include_path', ini_get('include_path') . ':' . dirname(__FILE__));
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'adodb5' . DIRECTORY_SEPARATOR . 'adodb-errorpear.inc.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'adodb5' . DIRECTORY_SEPARATOR . 'adodb.inc.php';

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
class database
{
	var $db_conn;
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
	
	function database($connect_str)
	{
		$this->db_conn = ADONewConnection($connect_str);  # no need for Connect()
		$this->db_conn->SetFetchMode(ADODB_FETCH_ASSOC);
	}
	
	function dropAll()
	{
		// loop through each module and compile a list of databases
		$GLOBALS['tables'] = array();
		foreach($GLOBALS['modules'] as $i => $module)
		{
			if(defined($module . '::DATABASE'))
				$GLOBALS['tables'][] = constant($module . '::DATABASE');
		}
		$GLOBALS['tables'] = array_values(array_unique($GLOBALS['tables']));
		
		// create module tables
		foreach($GLOBALS['tables'] as $i => $table)
		{
			$query = 'DROP TABLE ' . $table;
			$result = $this->db_conn->Execute($query);
		}
	}
	
	function installFirstTimeUsers($secret)
	{
		
		$db_user = $this->query(array(
				'SELECT' => 'users',
				'WHERE' => 'Username = "guest"',
				'LIMIT' => 1
			)
		, false);
		
		if( count($db_user) == 0 )
		{
			// create guest user
			$result = $this->query(array('INSERT' => 'users', 'VALUES' => array(
						'id' => -2,
						'Username' => 'guest',
						'Password' => '',
						'Email' => 'guest@bjcullinan.com',
						'Settings' => serialize(array()),
						'Privilage' => 1,
						'PrivateKey' => md5(microtime())
					)
				)
			, false);
		}
		
		$db_user = $this->query(array(
			'SELECT' => 'users',
			'WHERE' => 'Username = "admin"',
			'LIMIT' => 1
		)
		, false);
		
		if( count($db_user) == 0 )
		{
			// create default administrator
			$this->query(array('INSERT' => 'users', 'VALUES' => array(
					'id' => -1,
					'Username' => 'admin',
					'Password' => md5($secret . 'tmppass'),
					'Email' => 'admin@bjcullinan.com',
					'Settings' => serialize(array()),
					'Privilage' => 10,
					'PrivateKey' => md5(microtime())
				)
			)
			, false);
		}
		else
		{
			// update admin with new secret
			$result = $this->query(array(
				'UPDATE' => 'users',
				'VALUES' => array(
					'Password' => md5($secret . 'tmppass')
				)
			)
			, false);
			
			return true;
		}
		
		return false;
	}
	
	// install function
	function install($callback = NULL)
	{
		// create module tables
		$tables_created = array();
		foreach($GLOBALS['modules'] as $i => $module)
		{
			$query = 'CREATE TABLE ' . constant($module . '::DATABASE') . ' (';
			$struct = call_user_func($module . '::struct');
			if(is_array($struct) && !in_array(constant($module . '::DATABASE'), $tables_created))
			{
				$tables_created[] = constant($module . '::DATABASE');
				if(!isset($struct['id']))
					$query .= 'id INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (id),';
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
				$result = $this->db_conn->Execute($query);
				if($callback !== NULL)
				{
					call_user_func_array($callback, array($result, constant($module . '::DATABASE')));
				}
			}
		}
	}
	
	function upgrade()
	{
		$tables_updated = array();
		foreach($GLOBALS['modules'] as $i => $module)
		{
			$struct = call_user_func($module . '::struct');
			if(is_array($struct) && !in_array(constant($module . '::DATABASE'), $tables_updated))
			{
				$tables_updated[] = constant($module . '::DATABASE');
				
				// first insert a row with id 0 to use for reading
				$ids = $this->query(array('INSERT' => constant($module . '::DATABASE'), 'VALUES' => array('Filepath' => ''))) or print_r(mysql_error());
				
				// alter table to match the struct
				$files = $this->query(array('SELECT' => constant($module . '::DATABASE'), 'WHERE' => 'Filepath=""')) or print_r(mysql_error());
				
				if(count($files) > 0)
				{
					$columns = array_keys($files[0]);
						
					// find missing columns
					$query = 'ALTER TABLE ' . constant($module . '::DATABASE');
					foreach($struct as $column => $type)
					{
						if(!in_array($column, $columns))
						{
							// alter the table
							if(strpos($type, ' ') === false)
								$this->query('ALTER TABLE ' . constant($module . '::DATABASE') . ' ADD ' . $column . ' ' . $type . ' NOT NULL') or print_r(mysql_error());
							else
								$this->query('ALTER TABLE ' . constant($module . '::DATABASE') . ' ADD ' . $column . ' ' . $type) or print_r(mysql_error());
						}
						
						if($column != 'id')
						{
							if(strpos($type, ' ') === false)
								$query .= ' MODIFY ' . $column . ' ' . $type . ' NOT NULL,';
							else
								$query .= ' MODIFY ' . $column . ' ' . $type . ',';
						}
					}
					// remove last comma
					$query = substr($query, 0, strlen($query)-1);
					
					// remove unnessicary columns
					if(!isset($struct['id']))
						$struct['id'] = 'INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)';
					foreach($columns as $i => $key)
					{
						if(!isset($struct[$key]))
						{
							$this->query('ALTER TABLE ' . constant($module . '::DATABASE') . ' DROP ' . $key) or print_r(mysql_error());
						}
					}
				
					// finally modify the table types
					$this->query($query) or print_r(mysql_error());
				
					// remove id 0
					$files = $this->query(array('DELETE' => constant($module . '::DATABASE'), 'WHERE' => 'Filepath=""')) or print_r(mysql_error());
				}
			}
		}
	}
	
	
	// compile the statmeent based on an abstract representation
	static function statement_builder($props, $require_permit)
	{
		if($require_permit)
		{
			$where_security = '(LEFT(Filepath, ' . strlen(LOCAL_USERS) . ') != "' . addslashes(LOCAL_USERS) . '" OR ' . 
								'Filepath = "' . addslashes(LOCAL_USERS) . '" OR ' . 
								'(LEFT(Filepath, ' . strlen(LOCAL_USERS) . ') = "' . addslashes(LOCAL_USERS) . '" AND LOCATE("/", Filepath, ' . (strlen(LOCAL_USERS) + 1) . ') = LENGTH(Filepath)) OR ' . 
								'LEFT(Filepath, ' . strlen(LOCAL_USERS . $_SESSION['username'] . '/') . ') = "' . addslashes(LOCAL_USERS . $_SESSION['username'] . '/') . '" OR ' . 
								'SUBSTR(Filepath, ' . strlen(LOCAL_USERS) . ' + LOCATE("/", SUBSTR(Filepath, ' . (strlen(LOCAL_USERS) + 1) . ')), 8) = "/public/"';
			if(isset($_SESSION['settings']['keys_usernames']) && count($_SESSION['settings']['keys_usernames']) > 0)
			{
				foreach($_SESSION['settings']['keys_usernames'] as $i => $username)
				{
					$where_security .= ' OR LEFT(Filepath, ' . strlen(LOCAL_USERS . $username . '/') . ') = "' . addslashes(LOCAL_USERS . $username . '/') . '"';
				}
			}
			$where_security .= ')';
			
			if(!isset($props['WHERE'])) $where = 'WHERE ' . $where_security;
			elseif(is_array($props['WHERE'])) $where = 'WHERE (' . join(' AND ', $props['WHERE']) . ') AND ' . $where_security;
			elseif(is_string($props['WHERE'])) $where = 'WHERE (' . $props['WHERE'] . ') AND ' . $where_security;
		}
		else
		{
			if(!isset($props['WHERE'])) $where = '';
			elseif(is_array($props['WHERE'])) $where = 'WHERE ' . join(' AND ', $props['WHERE']);
			elseif(is_string($props['WHERE'])) $where = 'WHERE ' . $props['WHERE'];
		}
		
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
			$insert = 'INSERT INTO ' . $props['INSERT'];
			
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
			
			$select = 'SELECT ' . $columns . ' FROM ' . $props['SELECT'];
			
			$statement = $select . ' ' . $where . ' ' . $group . ' ' . $having . ' ' . $order . ' ' . $limit;
		}
		elseif(isset($props['UPDATE']))
		{
			$update = 'UPDATE ' . $props['UPDATE'] . ' SET';
			
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
			$delete = 'DELETE FROM ' . $props['DELETE'];

			$statement = $delete . ' ' . $where . ' ' . $order . ' ' . $limit;
		}
			
		return $statement;
	}
	
	// function for making calls on the database, this is what is called by the rest of the site
	//   for_users tells the script whether or not these results will eventually be used by plugins and template and be printed out
	//   this allows the script to add user permissions filters to the query easily
	function query($props, $require_permit)
	{
		$query = DATABASE::statement_builder($props, $require_permit);
		
		/*if(isset($_REQUEST['log_sql']) && $_REQUEST['log_sql'] == true)
		{
			if(!isset($_REQUEST['full_sql']) || $_REQUEST['full_sql'] != true)
				PEAR::raiseError('DATABASE: ' . substr($query, 0, 512));
			else
		}*/
		PEAR::raiseError('DATABASE: ' . $query, E_DEBUG);
		
		if(isset($props['CALLBACK']))
		{
			$result = $this->db_query_callback($query);
		}
		else
		{
			$result = $this->db_conn->Execute($query);
		}
		
		if($result !== false && is_array($props) && (isset($props['SELECT']) || isset($props['SHOW'])))
		{
			$output = array();
			while (!$result->EOF)
			{
				if(isset($props['CALLBACK']))
				{
					// this is used for queries too large for memory
					call_user_func_array($props['CALLBACK']['FUNCTION'], array($result->fields, &$props['CALLBACK']['ARGUMENTS']));
				}
				else
				{
					$output[] = $result->fields;
					$result->MoveNext();
				}
			}
			
			if(!isset($props['CALLBACK']))
			{
				return $output;
			}
		}
		elseif($result !== false && is_array($props) && (isset($props['INSERT']) || isset($props['UPDATE']) || isset($props['REPLACE']) || isset($props['DELETE'])))
		{
			if(isset($props['INSERT']))
			{
				$result = $this->db_conn->Execute('SELECT MAX(id) as id FROM ' . $props['INSERT'] . ' LIMIT 1');
				return $result->fields['id'];
			}
			else
			{
				$result = $this->db_conn->Affected_Rows();
				return $result;
			}
		}
		else
		{
			return $result;
		}
	}	
}

