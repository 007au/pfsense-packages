<?php
/*
 system_edit.php
 Copyright (C) 2004, 2005 Scott Ullrich
 All rights reserved.

 Adapted for FreeNAS by Volker Theile (votdev@gmx.de)
 Copyright (C) 2006-2009 Volker Theile

 Adapted for Pfsense Snort package by Robert Zelaya
 Copyright (C) 2008-2009 Robert Zelaya

 Using dp.SyntaxHighlighter for syntax highlighting
 http://www.dreamprojections.com/SyntaxHighlighter
 Copyright (C) 2004-2006 Alex Gorbatchev. All rights reserved.

 Redistribution and use in source and binary forms, with or without
 modification, are permitted provided that the following conditions are met:

 1. Redistributions of source code must retain the above copyright notice,
 this list of conditions and the following disclaimer.

 2. Redistributions in binary form must reproduce the above copyright
 notice, this list of conditions and the following disclaimer in the
 documentation and/or other materials provided with the distribution.

 THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort.inc");
require_once("/usr/local/pkg/snort/snort_gui.inc");

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}

//nat_rules_sort();
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

$ids = $_GET['ids'];
if (isset($_POST['ids']))
	$ids = $_POST['ids'];

if (isset($id) && $a_nat[$id]) {
	$pconfig['enable'] = $a_nat[$id]['enable'];
	$pconfig['interface'] = $a_nat[$id]['interface'];
	$pconfig['rulesets'] = $a_nat[$id]['rulesets'];
}

/* convert fake interfaces to real */
$if_real = convert_friendly_interface_to_real_interface_name2($pconfig['interface']);


$file = $_GET['openruleset'];

//read snort file
$filehandle = fopen($file, "r");

//get rule id
$lineid = $_GET['ids'];

//read file into string, and get filesize also chk for empty files
if (filesize($file) > 0 ) {
	$contents2 = fread($filehandle, filesize($file));
}else{
	$contents2 = '';
}

//close handler
fclose ($filehandle);

//delimiter for each new rule is a new line
$delimiter = "\n";

//split the contents of the string file into an array using the delimiter
$splitcontents = explode($delimiter, $contents2);

//copy rule contents from array into string
$tempstring = $splitcontents[$lineid];

if (!function_exists('write_rule_file')) {
function write_rule_file($content_changed, $received_file)
{
	//read snort file with writing enabled
	$filehandle = fopen($received_file, "w");

	//delimiter for each new rule is a new line
	$delimiter = "\n";

	//implode the array back into a string for writing purposes
	$fullfile = implode($delimiter, $content_changed);

	//write data to file
	fwrite($filehandle, $fullfile);

	//close file handle
	fclose($filehandle);

}
}



if($_POST['highlight'] <> "") {
	if($_POST['highlight'] == "yes" or
	$_POST['highlight'] == "enabled") {
		$highlight = "yes";
	} else {
		$highlight = "no";
	}
} else {
	$highlight = "no";
}

if($_POST['rows'] <> "")
	$rows = $_POST['rows'];
else
	$rows = 1;

if($_POST['cols'] <> "")
	$cols = $_POST['cols'];
else
	$cols = 66;

if ($_POST)
{
	if ($_POST['save']) {

		/* get the changes */
		$rule_content2 = $_POST['code'];

		//copy string into file array for writing
		$splitcontents[$lineid] = $rule_content2;

		//write the new .rules file
		write_rule_file($splitcontents, $file);

		header("Location: /snort/snort_view_edit.php?id=$id&openruleset=$file&ids=$ids");
		exit;
	}
}

$pgtitle = array(gettext("Advanced"), gettext("File Editor"));

//
?>

<?php include("head.inc");?>

<body link="#000000" vlink="#000000" alink="#000000">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td class="tabcont">
		<form
			action="snort_view_edit.php?id=<?=$id; ?>&openruleset=<?=$file; ?>&ids=<?=$ids; ?>"
			method="post"><?php if ($savemsg) print_info_box($savemsg);?> <?php 
			if ($file != '/usr/local/etc/snort/snort_update.log')
			{

				echo '
				<table width="100%" cellpadding="9" cellspacing="9" bgcolor="#eeeeee">
					<tr>
						<td>
							<input name="save" type="submit" class="formbtn" id="save" value="save" /> <input type="button" class="formbtn" value="Cancel" onclick="history.back()">
							<hr noshade="noshade" />
					' . "\n";

				echo 'Disable original rule :';
					

				echo '	<input id="highlighting_enabled" name="highlight2" type="radio" value="yes"'; if($highlight == "yes") echo " checked=\"checked\""; echo '/>
							<label for="highlighting_enabled">'; gettext("Enabled"); echo '</label>
							<input id="highlighting_disabled" name="highlight2" type="radio" value="no"'; if($highlight == "no") echo " checked=\"checked\""; echo '	/>
							<label for="highlighting_disabled">'; gettext("Disabled"); echo '	</label>
						</td>
					</tr>
				</table>
				
				<table width="100%">
					<tr>
						<td valign="top" class="label">
							<div style="background: #eeeeee;" id="textareaitem">
							<!-- NOTE: The opening *and* the closing textarea tag must be on the same line. -->
							<textarea wrap="off" style="width: 98%; margin: 7px;" class="'; echo $language; echo '	:showcolumns" rows="'; echo $rows; echo '	" cols="'; echo $cols;	echo '	" name="code">'; echo $tempstring; echo ' </textarea>
							</div>
						</td>
					</tr>
				</table>';

			}
			?>

		<table width='100%'>
			<tr>
				<td valign="top" class="label">
				<div style="background: #eeeeee;" id="textareaitem"><!-- NOTE: The opening *and* the closing textarea tag must be on the same line. -->
				<textarea
				<? if ($file != '/usr/local/etc/snort/snort_update.log') { echo 'disabled'; } ?>
					wrap="off" style="width: 98%; margin: 7px;"
					class="<?php echo $language; ?>:showcolumns" rows="33"
					cols="<?php echo $cols; ?>" name="code2"><?php echo $contents2;?></textarea>
				</div>
				</td>
			</tr>
		</table>
		<?php // include("formend.inc");?></form>
		<? echo "$file\n"; ?></td>
	</tr>
</table>

		<?php //include("fend.inc");?>

</body>
</html>
