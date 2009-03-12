<?php
// this controlls all of the inputing of data into the database
// to add extra type handling create a function that inserts data into the database based on a filepath
// add calls to that function in the getfile procedure

// some things to take into consideration:
// Access the database in intervals of files, not every individual file
// Sleep so output can be recorded to disk or downloaded in a browser
// Only update files that don't exist in database, have changed timestamps, have changed in size

// this is an iterator to update the server database and all the media listings

// use some extra code so cron can be run from any directory
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

session_write_close();

// add 30 seconds becase the cleanup shouldn't take any longer then that
set_time_limit(DIRECTORY_SEEK_TIME + FILE_SEEK_TIME + CLEAN_UP_BUFFER_TIME);
ignore_user_abort(1);

// start output buffer so we can save in tmp file
$log_fp = @fopen(TMP_DIR . 'mediaserver.log', 'wb');
ob_start(create_function('$buffer', 'global $log_fp; @fwrite($log_fp, $buffer); return $buffer;'));

$tm_start = array_sum(explode(' ', microtime()));

log_error('Cron Script: ' . VERSION . '_' . VERSION_NAME);

// start the page with a pre to output messages that can be viewed in a browser
?><script language="javascript">var timer=null;var same_count=0;var last_height=0;function body_scroll() {document.body.scrollTop = document.body.scrollHeight;timer=setTimeout('body_scroll()', 100);if(document.body.scrollHeight!=last_height) {last_height=document.body.scrollHeight;same_count=0;} else {same_count++;}if(same_count == 100) {clearTimeout(timer);}}timer=setTimeout('body_scroll()', 100)</script><code>
<?php

// the cron script is useless if it has nowhere to store the information it reads
if(USE_DATABASE == false)
	exit;

// get the directories to watch from the watch database
$database = new sql(DB_SERVER, DB_USER, DB_PASS, DB_NAME);

// get the watched directories
$ignored = db_watch::get($database, array('search_Filepath' => '/^!/'), $count, $error);
$watched = db_watch::get($database, array('search_Filepath' => '/^\\^/'), $count, $error);

log_error('Ignored: ' . serialize($ignored));
log_error('Watched: ' . serialize($watched));

// directories that have already been scanned used to prevent recursion
$dirs = array();

// the directories for the current state so we can start here the next time the script runs
$state = array();

// get previous state if it exists
if( file_exists(LOCAL_ROOT . 'state_dirs.txt') )
	$state = unserialize(implode("", @file(LOCAL_ROOT . "state_dirs.txt")));
elseif( file_exists(TMP_DIR . 'state_dirs.txt') )
	$state = unserialize(implode("", @file(TMP_DIR . "state_dirs.txt")));
if(!is_array($state))
	$state = array();

$clean_count = 0;
$should_clean = false;
// get clean count
if(count($state) > 0)
{
	$first = array_pop($state);
	if(isset($first['clean_count']) && is_numeric($first['clean_count']))
	{
		$clean_count = ++$first['clean_count'];
	}
	else
	{
		array_push($state, $first);
	}
}

log_error('State: ' . serialize($state));

if($clean_count > CLEAN_UP_THREASHOLD)
{
	log_error("Clean Count: " . $clean_count . ', clean up will happen this time!');
	
	$should_clean = true;
	$clean_count = 0;
}
else
{
	log_error("Clean Count: " . $clean_count);
}
	
// check state information
if( isset($state) && is_array($state) ) $state_current = array_pop($state);

$i = 0;
// get starting index
if( isset($state_current) && isset($watched[$state_current['index']]) && $watched[$state_current['index']]['Filepath'] == $state_current['file'] )
{
	$i = $state_current['index'];
}
elseif(isset($state_current))
{
	// if it isn't set in the watched list at all 
	//   something must be wrong with our state so reset it
	$fp = @fopen(LOCAL_ROOT . "state_dirs.txt", "w");
	if($fp === false) // try tmp dir
		$fp = @fopen(TMP_DIR . "state_dirs.txt", "w");
	if($fp !== false)
	{
		log_error("State mismatch: State cleared");
		$state = array();
		array_push($state, array('clean_count' => $clean_count));
		fwrite($fp, serialize($state));
		fclose($fp);
		// remove clean because it will mess up the script further down
		array_pop($state);
	}
	$state = array();
}

if(isset($_REQUEST['entry']) && is_numeric($_REQUEST['entry']) && $_REQUEST['entry'] < count($watched) && $_REQUEST['entry'] >= 0)
	$i = $_REQUEST['entry'];

log_error("Phase 1: Checking for modified Directories; Recursively");

// loop through each watched folder and get a list of all the files
for($i; $i < count($watched); $i++)
{
	if(!file_exists(str_replace('/', DIRECTORY_SEPARATOR, $watched[$i]['Filepath'])))
	{
		log_error("Error: Directory does not exist! " . $watched[$i]['Filepath'] . " is missing!");
		$should_clean = 0;
		continue;
	}
	$watch = '^' . $watched[$i]['Filepath'];

	$status = db_watch::handle($database, $watch);

	// if exited because of time, then save state
	if( $status === false )
	{
		// record the current directory
		array_push($state, array('index' => $i, 'file' => substr($watch, 1)));
		array_push($state, array('clean_count' => $clean_count));
		
		// serialize and save
		log_error("Ran out of Time: State saved");
		$fp = @fopen(LOCAL_ROOT . "state_dirs.txt", "w");
		if($fp === false) // try tmp dir
			$fp = @fopen(TMP_DIR . "state_dirs.txt", "w");
		if($fp !== false)
		{
			fwrite($fp, serialize($state));
			fclose($fp);
		}
		// remove clean because it will mess up the script further down
		array_pop($state);
		
		// since it exited because of time we don't want to continue our for loop
		//   exit out of the loop so it start off in the same place next time
		break;
	}
	else
	{
		// clear state information
		if(file_exists(LOCAL_ROOT . "state_dirs.txt"))
		{
			$fp = @fopen(LOCAL_ROOT . "state_dirs.txt", "w");
			if($fp === false) // try tmp dir
				$fp = @fopen(TMP_DIR . "state_dirs.txt", "w");
			if($fp !== false)
			{
				log_error("Completed successfully: State cleared");
				$state = array();
				array_push($state, array('clean_count' => $clean_count));
				fwrite($fp, serialize($state));
				fclose($fp);
				// remove clean because it will mess up the script further down
				array_pop($state);
			}
		}
		
		// set the last updated time in the watched table
		$database->query(array('UPDATE' => db_watch::DATABASE, 'VALUES' => array('Lastwatch' => date("Y-m-d h:i:s")), 'WHERE' => 'Filepath = "' . $watch . '"'));
	}
	
	if(isset($_REQUEST['entry']) && is_numeric($_REQUEST['entry']) && $_REQUEST['entry'] < count($watched) && $_REQUEST['entry'] >= 0)
		break;
}
log_error("Phase 1: Complete!");

// clean up the watch_list and remove stuff that doesn't exist in watch anymore
db_watch_list::cleanup($database, $watched, $ignored);

// now scan some files
$tm_start = array_sum(explode(' ', microtime()));

log_error("Phase 2: Checking modified directories for modified files");

do
{
	// get 1 folder from the database to search the files for
	$db_dirs = db_watch_list::get($database, array('limit' => 1), $count, $error);
	
	if(count($db_dirs) > 0)
	{
		$dir = $db_dirs[0]['Filepath'];
		if(USE_ALIAS == true)
			$dir = preg_replace($GLOBALS['alias_regexp'], $GLOBALS['paths'], $dir);
		$status = db_watch_list::handle($database, $dir);
	}

	// check if execution time is too long
	$secs_total = array_sum(explode(' ', microtime())) - $tm_start;
	
	if($secs_total > FILE_SEEK_TIME)
		log_error("Ran out of Time: Changed directories still in database");
	
	flush();
	
} while( $secs_total < FILE_SEEK_TIME && count($db_dirs) > 0 );

log_error("Phase 2: Complete!");

// now do some cleanup
//  but only if we need it!
if($should_clean === false)
{
	log_error("Phase 3: Skipping cleaning, count is " . $clean_count);
	@fclose($log_fp);
	exit;
}
elseif($should_clean === 0)
{
	log_error("Phase 3: Skipping cleaning because of error!");
	@fclose($log_fp);
	exit;
}
//exit;

log_error("Phase 3: Cleaning up");

foreach($GLOBALS['modules'] as $i => $module)
{
	if($module != 'fs_file')
		call_user_func_array($module . '::cleanup', array($database, $watched, $ignored));
}

// read all the folders that lead up to the watched folder
// these might be delete by cleanup, so check again because there are only a couple
for($i = 0; $i < count($watched); $i++)
{
	$folders = split('/', $watched[$i]['Filepath']);
	$curr_dir = (realpath('/') == '/')?'/':'';
	
	// don't add the watch directory here because it must be added to the watch list first!
	$length = count($folders);
	$between = false; // directory must be between an aliased path and a watched path
	
	// add the directories leading up to the watch
	for($j = 0; $j < count($folders); $j++)
	{
		if($folders[$j] != '')
		{
			$curr_dir .= $folders[$j] . DIRECTORY_SEPARATOR;
			
			// if using aliases then only add the revert from the watch directory to the alias
			// ex. Watch = /home/share/Pictures/, Alias = /home/share/ => /Shared/
			//     only /home/share/ is added here
			if(!USE_ALIAS || in_array($curr_dir, $GLOBALS['paths']) !== false)
			{
				// this allows for us to make sure that at least the beginning 
				//   of the path is an aliased path
				$between = true;
				
				// if the USE_ALIAS is true this will only add the folder
				//    if it is in the list of aliases
				db_watch_list::handle_file($database, $curr_dir);
			}
			// but make an exception for folders between an alias and the watch path
			elseif(USE_ALIAS && $between)
			{
				db_watch_list::handle_file($database, $curr_dir);
			}
		}
	}
}

log_error("Phase 3: Complete!");

@fclose($log_fp);

?>
</code>
