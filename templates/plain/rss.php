<?php

function register_plain_rss()
{
	return array(
		'name' => 'RSS Feed',
		'file' => __FILE__,
		'encoding' => 'XML'
	);
}

function theme_plain_rss()
{
	print '<?xml version="1.0" encoding="utf-8"?>';
	?>
	<rss version="2.0">
		<channel>
			<title><?php print HTML_NAME; ?> - <?php print $GLOBALS['templates']['vars']['cat']; ?></title>
			<link><?php print href('', false, true); ?></link>
			<description></description>
            <?php
			foreach($GLOBALS['templates']['vars']['files'] as $i => $file)
			{
				?>
				<item>
					<title><?php print basename($file['Filepath']); ?></title>
					<link><?php print href('plugin=file&cat=' . $GLOBALS['templates']['vars']['cat'] . '&id=' . $file['id'] . '&filename=' . basename($file['Filepath']), false, true); ?></link>
					<description></description>
				</item>
                <?php
			}
			?>
		</channel>
	</rss>
    <?php
}