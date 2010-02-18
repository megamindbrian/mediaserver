<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="Expires" content="-1">
	<title><?php echo HTML_NAME?>: Tools</title>
    <link rel="stylesheet" href="<?php echo HTML_ROOT . LOCAL_BASE; ?>css/tools.css" type="text/css"/>
</head>
<body>
	<p>View different types of reports by selecting the link and following the instructions.</p>
<?php
	foreach($tool_names as $i => $name)
	{
		$tool = $tools[$name];
		?><div class="section"><a href="<?php echo HTML_ROOT; ?>admin/tools.php?tool=<?php echo htmlspecialchars($name); ?>">[view]</a> <span><?php echo $tool_names[$i]; ?>: </span><br /><?php echo $tool_descs[$i];

		$tool = preg_replace('/\<warning label="([^"]*)"\>/i', '<div class="warning"><span>$1: </span>', $tool);
		$tool = preg_replace('/\<\/warning\>/i', '</div>', $tool);
		
		$tool = preg_replace('/\<info label="([^"]*)"\>/i', '<div class="info"><span>$1: </span>', $tool);
		$tool = preg_replace('/\<\/info\>/i', '</div>', $tool);
		
		$tool = preg_replace('/\<section label="([^"]*)"\>/i', '<div class="section"><span>$1: </span>', $tool);
		$tool = preg_replace('/\<\/section\>/i', '</div>', $tool);
		
		$tool = preg_replace('/\<text\>/i', '<p>', $tool);
		$tool = preg_replace('/\<\/text\>/i', '</p>', $tool);
		
		$tool = preg_replace('/\<note\>/i', '<div class="note">', $tool);
		$tool = preg_replace('/\<\/note\>/i', '</div>', $tool);
		print $tool;
		?></div><br /><?php
	}
?>
</body>
</html>