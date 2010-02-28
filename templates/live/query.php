{if $smarty.const.USE_DATABASE}{assign var=prefix value='db_'}{else}{assign var=prefix value='fs_'}{/if}
{php}
$parts = array();
$search = stripslashes($_REQUEST['search']);
if($search[0] != '"' && $search[strlen($search)-1] != '"' && $search[0] != '/' && $search[strlen($search)-1] != '/' && $search[0] != '=' && $search[strlen($search)-1] != '=')
{
	if(isset($_REQUEST['search']))
	{
		$tmp_parts = array_unique(split(' ', stripslashes($_REQUEST['search'])));
		foreach($tmp_parts as $i => $part)
		{
			if($part != '')
			{
				if($part[0] == '+') $part = substr($part, 1);
				$parts[] = '/' . preg_quote(htmlspecialchars($part)) . '/i';
			}
		}
	}
}
elseif($search[0] == '"' && $search[strlen($search)-1] == '"')
{
	$parts = array(0 => '/' . preg_quote(substr($search, 1, strlen($search)-2)) . '/i');
}
elseif($search[0] == '/' && $search[strlen($search)-1] == '/')
{
	$parts = array(0 => $search . 'i');
}
elseif($search[0] == '=' && $search[strlen($search)-1] == '=')
{
	$parts = array(0 => '/^' . preg_quote(substr($search, 1, strlen($search)-2)) . '$/i');
}
if(count($parts) != 0)
	$this->assign('parts', $parts);
$this->assign('newlineregexp', '/([^ ]{25})/i');
{/php}
{assign var=biggest value=0}
{assign var=image_count value=1}
{assign var=video_count value=1}
{assign var=audio_count value=1}
{assign var=files_count value=1}
{section name=file loop=$files}
{if $files[file].Filepath|@handles:'audio'}{assign var=audio_count value=$audio_count+1}
{elseif $files[file].Filepath|@handles:'video'}{assign var=video_count value=$video_count+1}
{elseif $files[file].Filepath|@handles:'image'}{assign var=image_count value=$image_count+1}
{else}{assign var=files_count value=$files_count+1}{/if}
{assign var=info_count value=0}
{foreach from=$columns item=column_value}
{if isset($files[file].$column_value) && $files[file].$column_value ne '' && strlen($files[file].$column_value) <= 200 && $column_value|substr:-3 ne '_id' && $column_value ne 'id' && $column_value ne 'Hex' && $column_value ne 'Filepath' && $column_value ne 'Filename' && $column_value ne 'Filetype'}
{assign var=info_count value=$info_count+1}
{/if}
{/foreach}
{assign var=info_count value=$info_count/2|ceil}
{if $info_count > $biggest}{assign var=biggest value=$info_count}{/if}
{/section}

{include file=$templates.TEMPLATE_HEADER}
		<div class="contentSpacing">
{assign var=dirlen value=$smarty.request.dir|strlen}
{assign var=dirlen value=$dirlen-1}
{if $smarty.request.dir|substr:$dirlen:1 eq '/'}{assign var=dir value=$smarty.request.dir|substr:0:$dirlen}{else}{assign var=dir value=$smarty.request.dir}{/if}
{assign var=crumbs value='/'|split:$dir}
{assign var=crumbcount value=$crumbs|@count}
{assign var=crumbcount value=$crumbcount-1}
{foreach from=$crumbs item=dir key=i}
{if $i eq $crumbcount}{assign var=current value=$dir}{/if}
{/foreach}
			<h1 class="title">{if $current eq ''}{$smarty.const.HTML_NAME|@htmlspecialchars}{else}{$current|@htmlspecialchars}{/if}</h1>
{assign var=page value=$smarty.request.start|intval}
{assign var=item_count value=$files|@count}
			<span class="subText">Click to browse files. Drag to select files, and right click for download options.</span>{if $error eq '' and count($files) > 0}<span class="subText">Displaying items {$smarty.request.start} to {$page+$item_count}{if $total_count > $smarty.request.limit} out of {$total_count} file(s){/if}.</span>{/if}
{include file=$templates.TEMPLATE_PAGES}
			<div class="titlePadding"></div>
			<div class="files" id="files">
{if isset($error) and $error != ''}
					<span style="color:#C00"><b>{$error}</b></span>
{elseif count($files) == 0}
					<b>There are no files to display</b>
{else}
{section name=file loop=$files}
					<div class="file {$files[file].Filetype}" onmousedown="deselectAll(event);fileSelect(this, true, event);return false;" oncontextmenu="showMenu(this);return false;" id="{$files[file].id}"><div class="notselected"></div>
{assign var=encoded value=$files[file].Filepath|urlencode|htmlspecialchars}
{if $files[file].Filetype=="FOLDER" || 
($files[file].Filepath|@handles:'archive' && $smarty.request.cat != $prefix|cat:'archive') || 
($files[file].Filepath|@handles:'playlist' && $smarty.request.cat != $prefix|cat:'playlist') || 
($files[file].Filepath|@handles:'diskimage' && $smarty.request.cat != $prefix|cat:'diskimage')
}
{if $files[file].Filepath|@handles:'archive'}
{assign var=cat value=$prefix|cat:archive}
{elseif $files[file].Filepath|@handles:'diskimage'}
{assign var=cat value=$prefix|cat:diskimage}
{elseif $files[file].Filepath|@handles:'playlist'}
{assign var=cat value=$prefix|cat:playlist}
{else}
{assign var=cat value=$smarty.request.cat}
{/if}
{assign var=link value='/'|cat:$smarty.const.HTML_ROOT|cat:'?dir='|cat:$encoded|cat:'&amp;cat='|cat:$cat}
{elseif $files[file].Filepath|@handles:'archive'}{assign var=link value='/'|cat:$smarty.const.HTML_ROOT|cat:$smarty.const.HTML_PLUGINS|cat:'file.php/'|cat:$prefix|cat:'archive/'|cat:$files[file].id|cat:'/'|cat:$files[file].Filename}
{elseif $files[file].Filepath|@handles:'diskimage'}{assign var=link value='/'|cat:$smarty.const.HTML_ROOT|cat:$smarty.const.HTML_PLUGINS|cat:'file.php/'|cat:$prefix|cat:'diskimage/'|cat:$files[file].id|cat:'/'|cat:$files[file].Filename}
{elseif $files[file].Filepath|@handles:'image_browser'}{assign var=link value='/'|cat:$smarty.const.HTML_ROOT|cat:$smarty.const.HTML_PLUGINS|cat:'file.php/'|cat:$prefix|cat:'image_browser/'|cat:$files[file].id|cat:'/'|cat:$files[file].Filename}
{else}{assign var=link value='/'|cat:$smarty.const.HTML_ROOT|cat:$smarty.const.HTML_PLUGINS|cat:'file.php/'|cat:$smarty.request.cat|cat:'/'|cat:$files[file].id|cat:'/'|cat:$files[file].Filename}{/if}
						<table class="itemTable" cellpadding="0" cellspacing="0" onclick="location.href = '{$link}';">
							<tr>
								<td>
									<div class="thumb file_ext_{$files[file].Filetype} file_type_{'/'|str_replace:' file_type_':$files[file].Filemime}"><img src="/{$smarty.const.HTML_ROOT}{$smarty.const.HTML_TEMPLATE}images/s.gif" alt="{$files[file].Filetype}" height="48" width="48"></div>
								</td>
							</tr>
						</table>
						<a class="itemLink" href="{$link}" onmouseout="this.parentNode.firstChild.className = 'notselected';{literal}if(!loaded){return false;}{/literal} document.getElementById('info_{$files[file].id}').style.display = 'none';document.getElementById('info_{$files[file].id}').style.visibility = 'hidden';" onmouseover="this.parentNode.firstChild.className = 'selected';{literal}if(!loaded){return false;}{/literal} document.getElementById('info_{$files[file].id}').style.display = '';document.getElementById('info_{$files[file].id}').style.visibility = 'visible';"><span>{if isset($parts)}{$parts|@preg_replace:'<b style="background-color:#990">$0</b>':$files[file].Filename}{else}{$files[file].Filename}{/if}</span></a>
					</div>
{/section}
{/if}
			</div>
			<div class="titlePadding"></div>
{include file=$templates.TEMPLATE_PAGES}
		</div>
	</td>
</tr>
<tr>
	<td id="infoBar" style="background-color:{if $audio_count > $image_count && $audio_count > $video_count && $audio_count > $files_count}#900{elseif $image_count > $files_count && $image_count > $video_count && $image_count > $audio_count}#990{elseif $video_count > $files_count && $video_count > $image_count && $video_count > $audio_count}#093{else}#06A{/if}; height:{$biggest+3|max:7}em;">
{section name=file loop=$files}
{assign var=info_count value=0}
{foreach from=$columns item=column_value}
{if isset($files[file].$column_value) && $files[file].$column_value ne '' && strlen($files[file].$column_value) <= 200 && $column_value|substr:-3 ne '_id' && $column_value ne 'id' && $column_value ne 'Hex' && $column_value ne 'Filepath' && $column_value ne 'Filename' && $column_value ne 'Filetype'}
{assign var=info_count value=$info_count+1}
{/if}
{/foreach}
{assign var=info_count value=$info_count/2|ceil}
{if $info_count > $biggest}{assign var=biggest value=$info_count}{/if}
{assign var=itemvalue value=$files[file].Filename|htmlspecialchars}
{assign var=itemvalue value=$newlineregexp|@preg_replace:'$1<br />':$itemvalue}
		<table cellpadding="0" cellspacing="0" border="0" class="fileInfo" id="info_{$files[file].id}" style="display:none; visibility:hidden;">
			<tr>
				<td>
					<table cellpadding="0" cellspacing="0" border="0" class="fileThumb">
						<tr>
							<td><div class="thumb file_ext_{$files[file].Filetype} file_type_{'/'|str_replace:' file_type_':$files[file].Filemime}"><img src="/{$smarty.const.HTML_ROOT}{$smarty.const.HTML_TEMPLATE}images/s.gif" height="48" width="48"></div></td>
							<td class="infoCell">
								<span class="title">{if isset($parts)}{$parts|@preg_replace:'<b style="background-color:#990">$0</b>':$itemvalue}{else}{$itemvalue}{/if}</span><br />
								<span>{$files[file].Filetype}</span>
							</td>
						</tr>
					</table>
				</td>
				<td>
{assign var=count value=0}
{foreach from=$columns item=column_value}
{assign var=itemvalue value=$files[file].$column_value|htmlspecialchars}
{if isset($parts)}
{assign var=itemvalue value=$parts|@preg_replace:'<b style="background-color:#990">$0</b>':$files[file].$column_value}
{else}
{assign var=itemvalue value=$files[file].$column_value}
{/if}
{if isset($files[file].$column_value) && $files[file].$column_value ne '' && strlen($files[file].$column_value) <= 200 && $column_value|substr:-3 ne '_id' && $column_value ne 'id' && $column_value ne 'Hex' && $column_value ne 'Filepath' && $column_value ne 'Filename' && $column_value ne 'Filetype'}
{assign var=count value=$count+1}
					<span class="label" style="color:{if $audio_count > $image_count && $audio_count > $video_count && $audio_count > $files_count}#F66{elseif $image_count > $files_count && $image_count > $video_count && $image_count > $audio_count}#FFA{elseif $video_count > $files_count && $video_count > $image_count && $video_count > $audio_count}#6FA{else}#6CF{/if};">{$column_value}:</span>{if $column_value eq 'Filesize'}{$files[file].$column_value|roundFileSize}{elseif $column_value eq 'Compressed'}{$files[file].$column_value|roundFileSize}{elseif $column_value eq 'Bitrate'}{$files[file].$column_value/1000|round:1} kbs{elseif $column_value eq 'Length'}{$files[file].$column_value/60|floor} minutes {$files[file].$column_value%60|floor} seconds{else}{$itemvalue}{/if}<br />
{if $count eq $info_count && $info_count >= 3}
				</td>
				<td>
{/if}
{/if}
{/foreach}
{if $count < $info_count || $info_count < 3}</td><td>&nbsp;{/if}
				</td>
			</tr>
		</table>
{/section}<script language="javascript">loaded = true;</script>
{include file=$templates.TEMPLATE_FOOTER}
