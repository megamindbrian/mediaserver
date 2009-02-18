<?php

// handles the watch tables

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'common.php';

// make sure user in logged in
if( loggedIn() )
{
}
else
{
	// redirect to login page
	header('Location: login.php?return=' . $_SERVER['REQUEST_URI']);
	
	exit();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Admin</title>
</head>

<body>
0. Install required services:<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>Image Magick</b> for converting images<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>VLC</b> (optionally just ffmpeg) for encoding video and audio<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>PHP-CLI</b> for running the cron job that watches for changed files<br />
&nbsp;&nbsp;&nbsp;&nbsp;* ctorrent for seeding torrent files<br />
1. Go through settings and change some values:<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>ADMIN_</b> is the all access admin<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>DB_</b> constants control database access<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>LOCAL_</b> controls paths to the local system<br />
&nbsp;&nbsp;&nbsp;&nbsp;* <b>HTML_</b> controls how users access the site<br />
&nbsp;&nbsp;&nbsp;&nbsp;* &lt;COMMAND&gt;_ controlls some programs for the default plugins<br />
2. Install the database:<br />
&nbsp;&nbsp;&nbsp;&nbsp;<a href="install.php">Install</a><br />
3. Add directories to watch for changes:<br />
&nbsp;&nbsp;&nbsp;&nbsp;<a href="watch.php">Watch</a><br />
4. Add a cron job:<br />
&nbsp;&nbsp;&nbsp;&nbsp;Run the <b>&lt;site path&gt;/plugins/cron.php</b> script (optionally run manually) to look for changed directories<br /><code>
&nbsp;&nbsp;&nbsp;&nbsp;0 * * * * /usr/bin/php /&lt;site path&gt;/plugins/cron.php &gt;/dev/null 2&gt;&amp;1<br />
&nbsp;&nbsp;&nbsp;&nbsp;30 * * * * /usr/bin/php /&lt;site path&gt;/plugins/cron.php &gt;/dev/null 2&gt;&amp;1<br /></code>
&nbsp;&nbsp;&nbsp;&nbsp;This would run a job every half hour<br />
&nbsp;&nbsp;&nbsp;&nbsp;You can also use Scheduled Tasks on Windows to run the cron.php script with PHP-CLI
</body>
</html>