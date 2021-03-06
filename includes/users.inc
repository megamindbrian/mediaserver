<?php

/**
 * add users
 * remove users
 * view a user profile
 * send messages
 */
 
define('PASSWORD_COMPLEXITY', '/\A(?=\S*?[A-Z])(?=\S*?[a-z])(?=\S*?[0-9])\S{6,}\z/');

function register_users()
{
	return array(
		'name' => 'Users',
		'description' => 'Allows for managing and displaying users.',
		'privilage' => 1,
		'session' => array('username' => 'authenticate_users'),
		'settings' => array('local_users', 'username_validation', 'admin_password', 'secret'),
		'depends_on' => 'users',
		'filter' => array('user'),
		'internal' => true,
		'template' => true,
		'package' => 'core',
		'database' => array(
			'Username' => 'TEXT',
			'Password' => 'TEXT',
			'Email' => 'TEXT',
			'Settings' => 'TEXT',
			'Privilage' => 'INT',
			'PrivateKey' => 'TEXT',
			'LastLogin' => 'DATETIME',
			'Filepath' => 'TEXT',
		),
	);
}

function install_users()
{
	install_table('users', get_module('users', 'database'));

	// add default users
	$db_user = db_assoc('SELECT * FROM users WHERE Username = "guest" LIMIT 1');
	
	if( count($db_user) == 0 )
	{
		// create guest user
		$fileinfo = array(
			'id' => -2,
			'Username' => 'guest',
			'Password' => '',
			'Email' => 'guest@bjcullinan.com',
			'Settings' => serialize(array()),
			'Privilage' => 1,
			'PrivateKey' => md5(microtime())
		);
		
		$result = db_insert('INSERT INTO users ' . sql_insert($fileinfo), array_values($fileinfo));
	}
	
	$db_user = db_assoc('SELECT * FROM users WHERE Username = "admin" LIMIT 1');
	
	if( count($db_user) == 0 )
	{
		$fileinfo = array(
			'id' => -1,
			'Username' => 'admin',
			'Password' => validate(array('password' => 'tmppass'), 'password_hash'),
			'Email' => 'admin@bjcullinan.com',
			'Settings' => serialize(array()),
			'Privilage' => 10,
			'PrivateKey' => md5(microtime()),
		);
		// create default administrator
		$result = db_insert('INSERT INTO users ' . sql_insert($fileinfo), array_values($fileinfo));
	}
}

function menu_users()
{
	return array(
		'users/%users' => array(
			'callback' => 'output_users',
		),
		'users/login' => array(
			'name' => 'Login',
			'callback' => 'output_users',
			'arguments' => array('users' => 'login'),
		),
		'users/register' => array(
			'name' => 'Create an account',
			'callback' => 'output_users',
			'arguments' => array('users' => 'register'),
		),
	);
}

/**
 * Set up the current user and get their settings from the database
 * @ingroup setup
 */
function setup_users()
{
	$session_user = session('users');
	if(!isset($session_user))
	{
		// prepare the session that stores user information
		$session_user = users_get_guest();
		session('users', $session_user);
	}

	// this will hold a cached list of the users that were looked up
	$GLOBALS['user_cache'] = array();
	
	// get users associated with the keys
	if(isset($session_user['Settings']['keys']) && is_array($session_user['Settings']['keys']))
	{
		$return = db_assoc('SELECT * FROM users WHERE PrivateKey=' . 
			implode(' OR PrivateKey=', array_fill(0, count($session_user['Settings']['keys']), '?')) . 
			' LIMIT ' . count($session_user['Settings']['keys'])
		, array_values($session_user['Settings']['keys']));
		
		$session_user['Settings']['keys_usernames'] = array();
		foreach($return as $index => $user)
		{
			$session_user['Settings']['keys_usernames'][] = $user['Username'];
			
			unset($return[$index]['Password']);
		}
		
		$session_user['Settings']['keys_users'] = $return;
		
		// save the session user after retrieving keys
		session('users', $session_user);
	}
	
	// output the user so template can print out login or logout stuff
	register_output_vars('user', $session_user);
}

/**
 * Implementation of dependency
 * @ingroup dependency
 */
function dependency_users($settings)
{
	if(!setting_installed() || !setting('database_enable'))
		return array('session', 'template');
	else
		return array('session', 'template', 'database');
}

/**
 * Implementation of status
 * @ingroup status
 */
function status_users()
{
	$status = array();

	if(dependency('database'))
	{
		$status['users'] = array(
			'name' => lang('Users', 'users status title'),
			'status' => 'pass',
			'description' => array(
				'list' => array(
					lang('User functionality is supported because the database is properly installed.', 'users status description'),
				),
			),
			'value' => array(
				'text' => array(
					'User functionality available',
				),
			),
		);
	}
	else
	{
		$status['users'] = array(
			'name' => lang('Users', 'users status title'),
			'status' => 'fail',
			'description' => array(
				'list' => array(
					lang('User functionality is disabled because the database is not configured.', 'users status fail description'),
				),
			),
			'value' => array(
				'text' => array(
					'User functionality disabled',
				),
			),
		);
	}
	
	return $status;
}

/**
 * Implementation of configure
 */
function configure_users($settings, $request)
{
	$settings['local_users'] = setting('local_users');
	$settings['username_validation'] = setting('username_validation');
	
	// load groups from settings and session
	$settings['user_groups'] = setting('user_groups');
	$settings['user_groups'] = setting('user_groups');
	if(($user_groups = session('user_groups')))
		$settings['user_groups'] = $user_groups;

	$options = array();
	
	if(is_writable($settings['local_users']))
	{
		$options['setting_local_users'] = array(
			'name' => 'User Files',
			'status' => 'pass',
			'description' => array(
				'list' => array(
					'This directory will be used for uploaded user files.  This will also be included in the directories that are watched by the server.',
				),
			),
			'type' => 'text',
			'value' => $settings['local_users'],
		);
	}
	else
	{
		$options['setting_local_users'] = array(
			'name' => 'User Files',
			'status' => 'fail',
			'description' => array(
				'list' => array(
					'The system has detected that this directory does not exist or is not writable.',
					'Please correct this error by entering a directory path that exists and is writable by the web server',
				),
			),
			'type' => 'text',
			'value' => $settings['local_users'],
		);
	}

	$options['setting_username_validation'] = array(
		'name' => 'Username Validation',
		'status' => '',
		'description' => array(
			'list' => array(
				'This option allows you to customize the regular expression that accepts usernames in the registration.',
				'This is usefull if you want your usernames to be in a specific format when users register.'
			),
		),
		'type' => 'text',
		'value' => $settings['username_validation'],
	);
	
	// display the authentication methods that are available
	$authentication_methods = array();
	foreach(get_modules_implements('authenticate', true) as $module)
	{
		$authentication_methods['authenticate_' . $module] = get_module($module, 'name');
	}
	$options['setting_authentication_methods'] = array(
		'name' => 'Authentication Methods',
		'status' => '',
		'description' => array(
			'list' => array(
				'Select the authentication methods allowing users to log in and save their profile/playlists.',
				'',
			),
		),
		'type' => 'set',
		'options' => array(
			array(
				'type' => 'checkbox',
				'options' => array('authenticate_users' => 'User session'),
				'disabled' => true,
			),
			array(
				'type' => 'checkbox',
				'options' => $authentication_methods,
			),
		),
	);
	
	// display user groups options
	$options['setting_user_groups'] = array(
		'name' => 'User groups',
		'status' => '',
		'type' => 'set',
		'options' => array(),
	);
	
	$options['setting_group_permissions'] = array(
		'name' => 'Group permissions',
		'status' => '',
		'type' => 'set',
		'options' => array(),
	);

	$groups = array();
	foreach($settings['user_groups'] as $group => $users)
	{
		$options['setting_user_groups']['options'][$group . '[username]'] = array(
			'name' => ucwords($group),
			'status' => '',
			'type' => 'multiselect',
			'options' => $users,
		);
		$groups[$group] = ucwords($group);
	}
		
	// list groups and permissions
	
	foreach(get_modules() as $module)
	{
		$options['setting_group_permissions']['options'][$module] = array(
			'name' => get_module($module, 'name'),
			'status' => '',
			'type' => 'set',
			'options' => array(),
		);
		$values = array();
		foreach($settings['group_permissions'] as $group => $permissions)
		{
			if(in_array($module . '_view', $permissions)) $values[] = $group;
			if(in_array($module . '_configure', $permissions)) $values[] = $group;
		}
		$options['setting_group_permissions']['options'][$module]['options']['users_' . $module . '_view'] = array(
			'name' => 'View',
			'type' => 'checkbox',
			'options' => $groups,
			'value' => $values,
		);
		$options['setting_group_permissions']['options'][$module]['options']['users_' . $module . '_configure'] = array(
			'name' => 'Configure',
			'type' => 'checkbox',
			'options' => $groups,
			'value' => $values,
		);
	}
	
	return array('users' => array(
		'name' => 'User File Settings',
		'type' => 'fieldset',
		'options' => $options
	));
}

function setting_group_permissions($settings)
{
	if(isset($settings['group_permissions']) && is_array($settings['group_permissions']))
	{
		$groups = array();
		foreach($settings['group_permissions'] as $group => $users)
		{
			if(preg_match(setting('username_validation'), $group) != 0)
			{
				$groups[$group] = preg_filter(setting('username_validation'), '$0', $users);
			}
		}
		return $groups;
	}
}

function setting_user_groups($settings)
{
	if(isset($settings['user_groups']) && is_array($settings['user_groups']))
	{
		$groups = array();
		foreach($settings['user_groups'] as $group => $users)
		{
			if(preg_match(setting('username_validation'), $group) != 0)
			{
				$groups[$group] = preg_filter(setting('username_validation'), '$0', $users);
			}
		}
		return $groups;
	}
}

/**
 * Implementation of setting
 * @ingroup setting
 * @return A 'users' directory withing the site root
 */
function setting_local_users($settings)
{
	$settings['local_root'] = setting('local_root');
	
	if(($settings['local_users'] = generic_validate_dir($settings, 'local_users')))
		return $settings['local_users'];
	else
	{
		if(setting('system_type') == 'nix')
			return '/home/';
		elseif(setting('system_type') == 'mac')
			return '/Users/';
		elseif(file_exists(realpath('/') . 'Users'))
			return str_replace(DIRECTORY_SEPARATOR, '/', realpath('/') . 'Users' . DIRECTORY_SEPARATOR);
		else
			return str_replace(DIRECTORY_SEPARATOR, '/', realpath('/') . 'Documents and Settings' . DIRECTORY_SEPARATOR);
	}
}

/**
 * Implementation of setting
 * @ingroup setting
 */
function setting_username_validation($settings)
{
	$settings['username_validation'] = generic_validate_regexp($settings, 'username_validation');
	if(isset($settings['username_validation']))
		return $settings['username_validation'];
	else
		return '/[a-z][a-z0-9]{4}[a-z0-9]*/i';
}

function setting_secret($settings)
{
	if(isset($settings['secret']) && preg_match(PASSWORD_COMPLEXITY, $settings['secret']) != 0)
		return $settings['secret'];
}

/**
 * Implementation of setting
 * @ingroup setting
 */
function setting_admin_password($settings)
{
	if(isset($settings['admin_password']) && preg_match(PASSWORD_COMPLEXITY, $settings['admin_password']) != 0)
		return $settings['admin_password'];
}

function validate_authentication_method($request)
{
	if(isset($request['authentication_method']) && module_implements('authenticate', $request['authentication_method']))
		return $request['authentication_method'];
	elseif(setting_installed() && setting('database_enable'))
		return 'database';
	else
		return 'users';
}
/**
 * Implementation of validate
 * @ingroup validate
 * @return NULL by default
 */
function validate_users($request)
{
	if(isset($request['users']) && in_array($request['users'], array('register', 'remove', 'modify', 'login', 'logout', 'list', 'view')))
		return $request['users'];
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return an sha1 hash of the setting('db_secret') prepended to the inputted password, it can never be decoded or displayed
 */
function validate_password($request)
{
	if(isset($request['password']) && !empty($request['password']))
		return $request['password'];
}

function validate_password_hash($request)
{
	return sha1(setting('secret') . $request['password']);
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return Any e-mail address
 */
function validate_email($request)
{
	return generic_validate_email($request, 'email');
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return Any Username
 */
function validate_username($request)
{
	if(isset($request['username']))
	{
		// if a file is passed in get the username from the path
		if(substr($request['username'], 0, strlen(setting('local_users'))) == setting('local_users'))
		{
			$start = strlen(setting('local_users'));
			$end =  strpos($request['username'], '/', $start);
			
			// remove rest of path
			if($end !== false)
				$request['username'] = substr($request['username'], $start, $end);
			else
				$request['username'] = basename($request['username']);
		}
		
		// a little lax here because it will run through the username validator when registering
		if(preg_match(setting('username_validation'), $request['username']) != 0)
			return $request['username'];
	}
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return Any valid return path to the site
 */
function validate_return($request)
{
	// do not html encode this
	return url(generic_validate_url($request, 'return'), true);
}

/**
 * Implementation of validate
 * @ingroup validate
 * @return (Optional) NULL by default, any number indicated what permission level is required to access a particular module
 */
function validate_required_priv($request)
{
	if(isset($request['required_priv']) && is_numeric($request['required_priv']) && $request['required_priv'] >= 0 && $request['required_priv'] <= 10)
		return $request['required_priv'];
}

/** 
 * Implementation of handles
 * @ingroup handles
 */
function handles_users($file)
{
	// handle directories found in the setting('local_users') directory
	//  automatically create a user entry in the database for those directories
	$username = validate(array('username' => $file), 'username');
	if(isset($username))
		return true;
	else	
		return false;
}

function users_get_guest()
{
	if(setting_installed() && setting('database_enable'))
	{
		// get guest user
		$db_user = db_assoc('SELECT * FROM users WHERE id = -2 LIMIT 1');
		
		if(is_array($db_user) && count($db_user) > 0)
		{
			// just incase a template wants to access the rest of the information; include the user
			unset($db_user[0]['Password']);
			
			$save = $db_user[0];
			$save['Settings'] = unserialize($save['Settings']);
			
			return $save;
		}
	}
	else
		return array(
			'Username' => 'guest',
			'Privilage' => 1
		);
}


/** 
 * Implementation of handle
 * @ingroup handle
 */
function add_users($file, $force = false)
{
	if(handles($file, 'users'))
	{
		$username = validate(array('username' => $file), 'username');

		// check if it is in the database
		$db_user = db_assoc('SELECT * FROM users WHERE Username=? LIMIT 1', array($username));
		
		if( count($db_user) == 0 )
		{
			// just set up the user with default information
			//   if they don't use the module, this creates a system user
			return db_insert('INSERT INTO users (Username, Settings, Privilage, PrivateKey) VALUES (?, ?, ?, ?)', array(
				$username,
				serialize(array()),
				1,
				md5(microtime()),
			));
		}
		elseif($force)
		{
			// not really anything to do here
		}
	}
	
	return false;
}

function filter_users($request)
{
	if(isset($request['user']))
	{
		// do not show any user files by default, then allow otherwise
		$sql = 'LEFT(Filepath, ' . strlen(setting('local_users')) . ') != "' . addslashes(setting('local_users')) . '" OR ' . 
			// allow the user directory to be shown
			'Filepath = "' . addslashes(setting('local_users')) . '" OR ' . 
			// allow folders to be seen in user directory
			'(LEFT(Filepath, ' . strlen(setting('local_users')) . ') = "' . addslashes(setting('local_users')) . '" AND LOCATE("/", Filepath, ' . (strlen(setting('local_users')) + 1) . ') = LENGTH(Filepath)) OR ' . 
			// show currently logged in user files
			'LEFT(Filepath, ' . strlen(setting('local_users') . $request['user']['Username'] . '/') . ') = "' . addslashes(setting('local_users') . $request['user']['Username'] . '/') . '" OR ' . 
			// show all public user files
			'SUBSTR(Filepath, ' . strlen(setting('local_users')) . ' + LOCATE("/", SUBSTR(Filepath, ' . (strlen(setting('local_users')) + 1) . ')), 8) = "/public/"';
		
		// show user files for keys that current user has access to
		if(isset($request['user']['Settings']['keys_usernames']) && count($request['user']['Settings']['keys_usernames']) > 0)
		{
			
			foreach($request['user']['Settings']['keys_usernames'] as $i => $username)
			{
				$where_security .= ' OR LEFT(Filepath, ' . strlen(setting('local_users') . $username . '/') . ') = "' . addslashes(setting('local_users') . $username . '/') . '"';
			}
		}
	
		return array('where' => $sql);
	}
}

/** 
 * Implementation of remove_handler
 * @ingroup remove_handler
 */
function remove_users($file)
{
}

/** 
 * Implementation of cleanup_handler
 * @ingroup cleanup_handler
 */
function cleanup_users()
{
}


/**
 * Implementation of session
 * @ingroup session
 * @return the username and password for user validation and reference
 */
function authenticate_users($request)
{
	// validate username and password
	$request['username'] = validate($request, 'username');
	$request['password'] = validate($request, 'password_hash');
	$request['authentication_method'] = validate($request, 'authentication_method');

	// call another authentication method
	if($request['authentication_method'] != 'users')
	{
		$save = invoke_module('authenticate', $request['authentication_method'], array($request));
	}
	
	// use admin account defined in settings
	if( isset($request['username']) && isset($request['password']) && empty($save))
	{
		// lookup user in admin settings
		if($request['username'] == 'admin' &&
			$request['password'] == sha1(setting('secret') . setting('admin_password')))
		{
			$save = array(
				'id' => '-1',
				'Username' => 'admin',
				'Privilage' => 10,
			);
		}
		else
			raise_error('Invalid username or password.', E_USER);
	}

	// if the save variable hasn't been set yet, use guest account
	if(empty($save))
	{
		$save = users_get_guest();
	}
	else
        new_session(setting('enable_https'));
	// output the user so template can print out login or logout stuff
	register_output_vars('user', $save);
	
	return $save;
}

/**
 * Implementation of output
 * @ingroup output
 */
function output_users($request)
{
	// check for what action we should do
	$request['users'] = validate($request, 'users');
	$request['username'] = validate($request, 'username');
	$request['email'] = validate($request, 'email');
	$request['password'] = validate($request, 'password');
	$request['return'] = validate($request, 'return');
	
	// validate and display regtistration information
	//  also send out registration e-mail here
	switch($request['users'])
	{
		case 'register':
			
			// validate input
			if(handles(setting('local_users') . $request['username'], 'db_users'))
			{
				// make sure the user doesn't already exist
				$db_user = db_assoc('SELECT * FROM users WHERE Username=? LIMIT 1', array($request['username']));
				
				if( count($db_user) > 0 )
					raise_error('User already exists.', E_USER);
			
				// validate other fields
				if(!isset($request['password']) || preg_match(PASSWORD_COMPLEXITY, $request['password']) == 0)
					raise_error('Password not complex enough.', E_USER);
				
				// validate email
				if(!isset($request['email']))
					raise_error('Invalid E-mail address.', E_USER);
				
				// create user folders
				if(!file_exists(setting('local_users') . $request['username']))
				{
					$made = @mkdir(setting('local_users') . $request['username']);
				
					if($made)
					{
						raise_error('Cannot create user directory.', E_DEBUG|E_USER);
					}
				}
			
				@mkdir(setting('local_users') . $request['username'] . DIRECTORY_SEPARATOR . 'public');
				@mkdir(setting('local_users') . $request['username'] . DIRECTORY_SEPARATOR . 'private');
			
				if( count($GLOBALS['user_errors']) == 0 )
				{
					// create database entry
					$user_id = add(setting('local_users') . $request['username'], 'users');
					
					// add password and profile information
					$result = db_query('UPDATE users SET Password=?, Email=? WHERE id=?', array(
						$request['password'],
						$request['email'],
						$user_id,
					));
					
					// send out confirmation email
					mail($request['email'], 'E-Mail Confirmation for ' . setting('html_name'), ob_theme('confirmation'));
				}
			}
		break;
		// allow a user to remove themselves, administrators may also remove themselves
		case 'remove':
			// delete from database
			// remove from filesystem
			// start new session and logout
		break;
		// a variation of register except users may not change certain properties
		case 'modify':
			// cannot modify their username
		break;
		// cache a users login information so they may access the site
		case 'login':
			$user = session('users');
			$request['required_priv'] = validate($request, 'required_priv');
			if( $user['Username'] != 'guest' )
			{
				if( isset($request['return']) && (!isset($request['required_priv']) || $user['Privilage'] >= $request['required_priv']))
				{
					location($request['return']);
				}
				else
					raise_error('Already logged in!', E_USER);
			}
			if(isset($request['required_priv']) && $request['required_priv'] > $user['Privilage'])
			{
				raise_error('You do not have sufficient privilages to view this page!', E_USER);
			}
		break;
		// remove all cookies and session information
		case 'logout':
			// delete current session
			session_destroy();
			
			session_regenerate_id(true);
			
			// login cookies become irrelevant
			$_COOKIE = array();
			
			// create new session
			session_start();
			
			if( isset($request['return']))
				location($request['return']);
		break;
		// show a list of users, this may have different administrator requirements
		case 'list':
			// possibly belongs under admin
		break;
		// view information about a user
		case 'view':
			// allow users to view their profile
		break;
	}
	
	register_output_vars('users', $request['users']);
	if(isset($request['username'])) register_output_vars('username', $request['username']);
	if(isset($request['email'])) register_output_vars('email', $request['email']);
	if(isset($request['return'])) register_output_vars('return', $request['return']);
}

function theme__users()
{
	switch($GLOBALS['output']['users'])
	{
		case 'logout':
			theme('logout');
		break;
		case 'login':
			theme('login');
		break;
		default:
			theme('default');
	}
}

function theme__login()
{
	theme('header');
	
	theme('login_block');
	
	theme('footer');
}

function theme__logout()
{
	theme('header');
	
	theme('logout_block');
	
	theme('footer');
}

function theme__login_block()
{
	if(isset($GLOBALS['output']['return']))
		$return = $GLOBALS['output']['return'];
	else
		$return = $GLOBALS['output']['get'];
	?>	
	<div id="login">
		<div id="auth_users">
			<form action="<?php print url('users/login?return=' . urlencode($return), false, true); ?>" method="post">
				<p>
				<?php print lang('Username', 'username'); ?>: <input type="text" name="username" value="<?php print $GLOBALS['output']['user']['Username']; ?>" /><br />
				</p>
				<p>
				<?php print lang('Password', 'password'); ?>: <input type="password" name="password" value="" /><br />
				</p>
				<p>
				<input type="submit" value="<?php print lang('Login', T_IN_ATTRIBUTE, 'login'); ?>" /><input type="reset" value="<?php print lang('Cancel', T_IN_ATTRIBUTE, 'cancel'); ?>" />
				</p>
			</form>
		</div>
		<div id="auth_google">
			<?php theme('google_block'); ?>
		</div>
	</div>
	<?php
}

function theme__logout_block()
{
	if(isset($GLOBALS['output']['return']))
		$return = $GLOBALS['output']['return'];
	else
		$return = $GLOBALS['output']['get'];
	?>
	<div id="logout">
		<form action="<?php echo url('users/logout?return=' . urlencode($return)); ?>" method="post">
			<input type="submit" value="Logout" />
		</form>
	</div>
	<?php
}

function theme__register()
{
	theme('header');
	
	?>	
	<form action="<?php echo url('users/register', false, true); ?>" method="post">
	
		Username: <input type="text" name="username" value="<?php print isset($GLOBALS['output']['username'])?$GLOBALS['output']['username']:''; ?>" /><br />
		E-mail: <input type="text" name="email" value="<?php print isset($GLOBALS['output']['email'])?$GLOBALS['output']['email']:''; ?>" /><br />
		Password: <input type="password" name="password" value="" /><br />
		<input type="submit" value="Register" /><input type="reset" value="Reset" />
		
	</form>
	<?php
	
	theme('footer');
}

function theme__confirmation()
{
	theme('header');
	
	?>Thanks for signing up!<?php
	
	theme('footer');
}
