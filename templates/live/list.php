<?php


function theme_live_list()
{
	theme('header',
		'Lists',
		'Select the type of list you would like to view below.'
	);
	
	foreach($GLOBALS['lists'] as $type => $list)
	{
		?>
		<div class="nothover" onMouseOver="this.className='hover';" onMouseOut="this.className='nothover';">
			<a href="<?php print url('list/' . $type); ?>" style="font-size:14px;"><?php print $list['name']; ?></a><br /><br />
			Format: <?php print $list['encoding']; ?><br />
			Extension: <?php print $type; ?>
			<br /><br />
		</div>
		<?php
	}
	
	theme('footer');
}

function theme_live_list_block()
{
	?>
	<div class="list_block colors_bg">
	<?php 
	if($GLOBALS['output']['user']['Username'] == 'guest') 
	{
		theme('login_block');
	}
	else
	{
		?>
		<div style="height:40px;">
			<div class="filemedium FOLDER" id="user_folder">
				<table class="itemTable" cellpadding="0" cellspacing="0">
					<tr>
						<td>
							<div class="thumbmedium file_ext_FOLDER file_type_">
								<img src="<?php print url('templates/live/images/s.gif'); ?>" alt="FOLDER" height="24" width="24" />
							</div>
						</td>
					</tr>
				</table>
				<a class="itemLink" href="<?php print url('select/files/' . setting('local_users')); ?>"><span><?php print lang('User Directory', 'user directory'); ?></span></a>
			</div>
			<div class="filemedium FOLDER" id="collapser">
				<table class="itemTable" cellpadding="0" cellspacing="0">
					<tr>
						<td>
							<div class="thumbmedium">
								<img src="<?php print url('templates/live/images/s.gif'); ?>" alt="FOLDER" height="24" width="24" />
							</div>
						</td>
					</tr>
				</table>
				<a class="itemLink" href="#"><span><?php print lang('Collapse', 'collapse'); ?></span></a>
			</div>
		</div>
		<div id="playlist-outer" class="colors_outer">
			<div id="playlist" class="colors_inner" style="height:32px; width:421px;"></div>
		</div>
		<?php
		//<video width="450" height="256">
		//	<source src="/encode/webm/454168/files/test.webm" type="video/x-webm"></source>
		//<video>
		//<a style="display:block;width:425px;height:30px;" id="player"></a>
	}
	?>
	</div>
	<?php
}
