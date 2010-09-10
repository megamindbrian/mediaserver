<?php

function theme_live_head($title)
{
	?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php theme('redirect_block'); ?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php print $title . ' : ' . setting('html_name'); ?></title>
<meta name="google-site-verification" content="K3Em8a7JMI3_1ry5CNVKIHIWofDt-2C3ohovDq3N2cQ" />
<script language="javascript" type="text/javascript" src="<?php print url('template/live/scripts'); ?>"></script>
<link rel="stylesheet" href="<?php print url('template/live/styles'); ?>" type="text/css"/>
</head>
	<?php
}

function theme_live_header($title = NULL, $description = NULL, $html_title = NULL)
{
	if(!isset($title))
		$title = htmlspecialchars($GLOBALS['modules'][$GLOBALS['output']['module']]['name']);
	
	if(!isset($GLOBALS['output']['extra']) || $GLOBALS['output']['extra'] != 'inneronly')
	{
		theme('head', $title);
	
		theme('body');
	}
	
	
	theme('container', isset($html_title)?$html_title:$title, $description);
}

function theme_live_breadcrumbs($breadcrumbs = array(), $crumb = NULL)
{
	?>
	<li><a href="<?php print url('select'); ?>"><?php print setting('html_name'); ?></a></li>
	<li><img src="<?php print url('template/live/images/carat.gif'); ?>" class="crumbsep"></li>
	<?php
	if(count($breadcrumbs) == 0)
	{
		?><li><strong><?php print $crumb; ?></strong></li><?php
	}
	else
	{
		$count = 0;
		foreach($breadcrumbs as $path  => $menu)
		{
			if($count != count($breadcrumbs) - 1)
			{
				?>
				<li><a href="<?php print url($path); ?>"><?php print $menu['name']; ?></a></li>
				<li><img src="<?php print url('template/live/images/carat.gif'); ?>" class="crumbsep"></li>
				<?php
			}
			else
			{
				?><li><strong><?php print $menu['name']; ?></strong></li><?php
			}
			$count++;
		}
	}
}

function theme_live_template_block()
{
	?><div class="template_box"><?php
	foreach($GLOBALS['templates'] as $name => $template)
	{
		if(isset($template['name']))
		{
			?><a href="<?php print url('select?template=' . $name, false, true); ?>"><?php print $template['name']; ?></a><?php
		}
	}
	?></div><?php
}

function theme_live_body()
{
	$scheme = live_get_colors();
?>
<body class="colors_<?php print $scheme; ?>">
<?php if(is_module('list')) theme('list_block'); ?>
<div id="bodydiv">
	<div id="sizer">
		<div id="expander">
			<table id="header" cellpadding="0" cellspacing="0" class="colors_bg header">
				<tr>
					<td id="siteTitle"><a href="<?php print url('select'); ?>"><?php print setting('html_name'); ?></a></td>
					<td>
						<?php if(dependency('search') != false) theme('search_block'); ?>
					</td>
					<td id="templates"><?php theme('template_block'); ?></td>
				</tr>
			</table>
<?php if(setting('debug_mode')) theme('debug_block'); ?>
			<?php if(dependency('language') != false) theme('language_block'); ?>
			<div id="loading">&nbsp;</div>
			<?php theme('context_menu'); ?>
			<div id="container">
	<?php
}


function theme_live_container($title = NULL, $description = NULL)
{
	$scheme = live_get_colors();

	?>
	<table width="100%" cellpadding="5" cellspacing="0">
		<tr>
			<td>
				<div id="breadcrumb">
					<ul>
					<?php
					theme('breadcrumbs', $GLOBALS['output']['breadcrumbs'], $title);
					?>
					</ul>
				</div>
			</td>
		</tr>
	</table>
	<div id="content" class="colors_<?php print $scheme; ?>">
		<table id="main" cellpadding="0" cellspacing="0">
			<tr>
				<td class="sideColumn"></td>
				<td id="mainColumn">
					<table id="mainTable" cellpadding="0" cellspacing="0">
						<tr>
							<td>
	<div class="contentSpacing">
	<?php
	
	if(isset($title))
	{
		?><h1 class="title" id="title"><?php print $title; ?></h1><?php
	}
	if(isset($description))
	{
		?><span class="subText"><?php print $description; ?></span><?php
	}
	
	?><div class="titlePadding"></div><?php
	
	theme('errors_block');
}