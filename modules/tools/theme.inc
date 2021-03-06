<?php

function theme__tool_info()
{
	theme('header');
	
	// output configuration if it is set
	if(isset($GLOBALS['output']['options']))
		theme('admin_modules_configure');
	
	theme('form_object', 'info', array(
		'action' => $GLOBALS['output']['html']['get'],
		'options' => $GLOBALS['output']['infos'],
		'type' => 'form',
	));

	theme('footer');
}

function theme__form_singular($config)
{
	extract($config);
	theme('info_objects', array('type' => 'info', 'text' => $text));

	?><script type="text/javascript">
	var current_ajax = $.get('<?php print $href; ?>',function(data){
		$('#row_<?php print $name; ?>').replaceWith(data);
	}, 'text');
	</script><?php
}

