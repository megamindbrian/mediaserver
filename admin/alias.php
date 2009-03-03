<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Alias Editor</title>
</head>

<body>
Alaises are very complex and should only be used by advanced users with a deep understanding of php and regular expressions.<br />
Aliases are used in preg_replace() functions in order to replace specified parts of the filepath with a different path; sort of like a symbolic link on linux filesystems.<br />
Here are some basic rules that allow aliases to work correctly:<br />
There must be an alias for return a root path, for example when the dir=/ request variable is used the / is the root which must match aliased paths, otherwise no files will be returned and it will make the site unbrowsable.<br />
All processed aliased paths must be accessible, when a path resolves it must start with / (&lt;-root) and each directory in the tree should be accessible.<br />
For example, if a path /home/share/Pictures/ is resolved with the alias to /Shared/Pictures/, this will break the site because there is no way to access Pictures when / root is browsed.<br />
Instead the proper solution for this is to match /home/share/ and resolve it to /Shared/, however the site will still be broken because the /home/share/ folder is not watched and will never be added to the database.<br />
When aliases are used the paths leading up to the watched directories are NOT added to the database automatically, therefore a special modifier must be used on the alias matching.<br />
At least one alias must match the / root directory, in order to access the other aliased paths, for example the /Shared/ alias will also match just a / which is the root directory.<br />
Now if /Shared/ is browsed, /home/share/ will be resolved, AND if / is browsed it will still resolve to /home/share/.<br />
All aliases should be url friendly!<br />
Finally, the 4 columns for aliases are described below:<br />
Paths - This is the path on the local filesystem, this is the path to replace with an alias.<br />
Paths_regexp - This is a regular expression that can match all the folders in the Paths definition.<br />
Alias - This is the name of the alias, or path to replace with.<br />
Alias_regexp - This is a regular expression that matches the entire Alias path.<br />
It is suggested that at least one alias match the / root directory for browsing.  If this is not done, some template will not function properly.<br />
Here is a full example of an alias in use:<br />
<code>
+--------------+----------+-----------------------+---------------------+------+<br />
| Paths &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | Alias &nbsp;&nbsp; | Paths_regexp &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | Alias_regexp &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | Hard |<br />
+--------------+----------+-----------------------+---------------------+------+<br />
| /home/share/ | /Shared/ | /\/home\/(share\/)?/i | /^\/$|^\/Shared\//i | &nbsp;&nbsp; 0 |<br />
+--------------+----------+-----------------------+---------------------+------+<br />
</code>
The breakdown:<br />
The Paths_regexp column will match /home/ or /home/share/ at the beginning of the filepath.<br />
The Alias_regexp column will match / (the root directory) and /Shared/ at the beginning of the filepath.<br />
Additionally, the site will automatically add paths between the defined alias and the watched directories.<br />
For exmaple, if an alias /home/share/ is defined, and there is a watch directory /home/share/Pictures/Other/, /home/share/Pictures/Other/ will be added to the database because it is a watched directory,<br />
Additionally /home/share/Picture/ will be added to the database to simplify browsing, but /home/share/ will not be added, so matching the / root directory is still required.<br />
Hard and Soft links explained:<br />
Contrary to linux symbolic links, Soft links are only meant to replace paths on output of the Filepath.<br />
Hard links are meant to replace paths when inputting to the database, this can be usefull when adding files across network shares.<br />
For example, if a Windows or Samba share is mounted on /home/share/Remote/, and that path exists on the remote computer under C:\Documents\Shared Files\<br />
An alias can be used to replace C:\Documents\Shared Files\ with /home/share/Remote/, then a cron job can be run on the remote system, but files can still be accessed by the webserver using the /home/share/Remote/ path.<br />
For security reasons, only the Soft aliases are accessible from the site, Hard aliases are only used internally.<br />
Note: Hard aliases can also be used to help some path processing like in the db_playlist module.
</body>
</html>