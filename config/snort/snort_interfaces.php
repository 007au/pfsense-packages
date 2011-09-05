<?php
/* $Id$ */
/*

originally part of m0n0wall (http://m0n0.ch/wall)
Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
Copyright (C) 2008-2009 Robert Zelaya.
Copyright (C) 2011 Ermal Luci
All rights reserved.

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

/* TODO: redo check if snort is up */
$nocsrf = true;
require_once("guiconfig.inc");
require_once("/usr/local/pkg/snort/snort_gui.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g;

$id = $_GET['id'];
if (isset($_POST['id']))
	$id = $_POST['id'];

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
$a_nat = &$config['installedpackages']['snortglobal']['rule'];
$id_gen = count($config['installedpackages']['snortglobal']['rule']);

if (isset($_POST['del_x'])) {
	/* delete selected rules */
	if (is_array($_POST['rule'])) {
		conf_mount_rw();

		foreach ($_POST['rule'] as $rulei) {
				
			/* convert fake interfaces to real */
			$if_real = snort_get_real_interface($a_nat[$rulei]['interface']);
			$snort_uuid = $a_nat[$rulei]['uuid'];

			/* cool code to check if any snort is up */
			$snort_up_ck = exec("/bin/ps -auwx | /usr/bin/grep -v grep | /usr/bin/grep snort | /usr/bin/awk '{print \$2;}' | sed 1q");
				
			if ($snort_up_ck != "")
			{
					
				$start_up_pre = exec("/usr/bin/top -a -U snort -u | grep -v grep | grep \"R {$snort_uuid}\" | awk '{print \$1;}'");
				$start_up_s = exec("/usr/bin/top -U snort -u | grep snort | grep {$start_up_pre} | awk '{ print $1; }'");
				$start_up_r = exec("/usr/bin/top -U root -u | grep snort | grep {$start_up_pre} | awk '{ print $1; }'");
					
				$start2_upb_pre = exec("/bin/cat /var/run/barnyard2_{$snort_uuid}_{$if_real}.pid");
				$start2_upb_s = exec("/usr/bin/top -U snort -u | grep barnyard2 | grep {$start2_upb_pre} | awk '{ print $1; }'");
				$start2_upb_r = exec("/usr/bin/top -U root -u | grep barnyard2 | grep {$start2_upb_pre} | awk '{ print $1; }'");

					
				if ($start_up_s != "" || $start_up_r != "" || $start2_upb_s != "" || $start2_upb_r != "") {
					/* remove only running instances */
					if ($start_up_s != "") {
						exec("/bin/kill {$start_up_s}");
						exec("/bin/rm /var/run/snort_{$snort_uuid}_{$if_real}*");
					}

					if ($start2_upb_s != "") {
						exec("/bin/kill {$start2_upb_s}");
						exec("/bin/rm /var/run/barnyard2_{$snort_uuid}_{$if_real}*");
					}

					if ($start_up_r != "") {
						exec("/bin/kill {$start_up_r}");
						exec("/bin/rm /var/run/snort_{$snort_uuid}_{$if_real}*");
					}

					if ($start2_upb_r != "") {
						exec("/bin/kill {$start2_upb_r}");
						exec("/bin/rm /var/run/barnyard2_{$snort_uuid}_{$if_real}*");
					}
				}
					
			}

			/* for every iface do these steps */
			exec("/bin/rm /var/log/snort/snort.u2_{$snort_uuid}_{$if_real}*");
			exec("/bin/rm -r /usr/local/etc/snort/snort_{$snort_uuid}_{$if_real}");

			unset($a_nat[$rulei]);
		}
		conf_mount_ro();
	  
		write_config();
		sleep(2);
	  
		/* if there are no ifaces do not create snort.sh */
		if (isset($config['installedpackages']['snortglobal']['rule'][0]['enable'])) {
			create_snort_sh();
		}else{
			conf_mount_rw();
			exec('/bin/rm /usr/local/etc/rc.d/snort.sh');
			conf_mount_ro();
		}
	  
		sync_snort_package_empty();
	  
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header("Location: /snort/snort_interfaces.php");
		exit;
	}

}


/* start/stop snort */
if ($_GET['act'] == 'toggle' && is_numeric($id)) {

	$if_real = snort_get_real_interface($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
	$snort_uuid = $config['installedpackages']['snortglobal']['rule'][$id]['uuid'];

	/* Log Iface stop */
	exec("/usr/bin/logger -p daemon.info -i -t SnortStartup 'Toggle for {$snort_uuid}_{$if_real}...'");

	sync_snort_package_all($id, $if_real, $snort_uuid);

	$tester2 = Running_Ck($snort_uuid, $if_real, $id);

	if ($tester2 == 'yes') {

		/* Log Iface stop */
		exec("/usr/bin/logger -p daemon.info -i -t SnortStartup '{$tester2} yn for {$snort_uuid}_{$if_real}...'");

		Running_Stop($snort_uuid, $if_real, $id);

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );

	}else{

		Running_Start($snort_uuid, $if_real, $id);

		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
	}
	sleep(4); // So the GUI reports correctly
	header("Location: /snort/snort_interfaces.php");
	exit;
}


$pgtitle = "Services: $snort_package_version";
include_once("head.inc");

?>
<body link="#000000" vlink="#000000" alink="#000000">

<?php
echo "{$snort_general_css}\n";
echo "$snort_interfaces_css\n";

include_once("fbegin.inc");
if ($pfsense_stable == 'yes')
	echo '<p class="pgtitle">' . $pgtitle . '</p>';
?>

<noscript>
<div class="alert" ALIGN=CENTER><img
	src="../themes/<?php echo $g['theme']; ?>/images/icons/icon_alert.gif" /><strong>Please
enable JavaScript to view this content
</CENTER></div>
</noscript>

<form action="snort_interfaces.php" method="post" enctype="multipart/form-data" name="iform" id="iform">
<?php
	/* Display Alert message */
	if ($input_errors)
		print_input_errors($input_errors); // TODO: add checks

	if ($savemsg)
		print_info_box2($savemsg);

	//if (file_exists($d_snortconfdirty_path)) {
	if ($d_snortconfdirty_path_ls != '') {
		echo '<p>';

		if($savemsg)
			print_info_box_np2("{$savemsg}");
		else {
			print_info_box_np2('
			The Snort configuration has changed for one or more interfaces.<br>
			You must apply the changes in order for them to take effect.<br>
			');
		}
	}
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
        $tab_array = array();
        $tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
        $tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
        $tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
        $tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
        $tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
        $tab_array[5] = array(gettext("Whitelists"), false, "/snort/snort_interfaces_whitelist.php");
        $tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
        $tab_array[7] = array(gettext("Help"), false, "/snort/help_and_info.php");
        display_top_tabs($tab_array);
?>
</td></tr>
<tr>
	<td>
		<div id="mainarea2">
		<table class="tabcont" width="100%" border="0" cellpadding="0"
			cellspacing="0">
			<tr id="frheader">
				<td width="5%" class="list">&nbsp;</td>
				<td width="1%" class="list">&nbsp;</td>
				<td width="10%" class="listhdrr">If</td>
				<td width="10%" class="listhdrr">Snort</td>
				<td width="10%" class="listhdrr">Performance</td>
				<td width="10%" class="listhdrr">Block</td>
				<td width="10%" class="listhdrr">Barnyard2</td>
				<td width="50%" class="listhdr">Description</td>
				<td width="3%" class="list">
				<table border="0" cellspacing="0" cellpadding="1">
					<tr>
						<td width="17"></td>
						<td><a href="snort_interfaces_edit.php?id=<?php echo $id_gen;?>"><img
							src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
							width="17" height="17" border="0"></a></td>
					</tr>
				</table>
				</td>
			</tr>
			<?php $nnats = $i = 0; foreach ($a_nat as $natent): ?>
			<tr valign="top" id="fr<?=$nnats;?>">
			<?php

			/* convert fake interfaces to real and check if iface is up */
			/* There has to be a smarter way to do this */
			$if_real = snort_get_real_interface($natent['interface']);
			$snort_uuid = $natent['uuid'];
			
			$tester2 = Running_Ck($snort_uuid, $if_real, $id);

			if ($tester2 == 'no') {
				$iconfn = 'pass';
				$class_color_up = 'listbg';
			}else{
				$class_color_up = 'listbg2';
				$iconfn = 'block';
			}

			?>
				<td class="listt">
					<a href="?act=toggle&id=<?=$i;?>">
						<img src="../themes/<?= $g['theme']; ?>/images/icons/icon_<?=$iconfn;?>.gif"
						width="13" height="13" border="0"
						title="click to toggle start/stop snort"></a>
					<input type="checkbox" id="frc<?=$nnats;?>" name="rule[]" value="<?=$i;?>" onClick="fr_bgcolor('<?=$nnats;?>')" style="margin: 0; padding: 0;"></td>
				<td class="listt" align="center"></td>
				<td class="<?=$class_color_up;?>" onClick="fr_toggle(<?=$nnats;?>)"
					id="frd<?=$nnats;?>"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
					<?php
					if (function_exists('convert_friendly_interface_to_friendly_descr'))
						echo convert_friendly_interface_to_friendly_descr($natent['interface']);
					else {
						if (!$natent['interface'] || ($natent['interface'] == "wan"))
						echo "WAN";
						else if(strtolower($natent['interface']) == "lan")
						echo "LAN";
						else if(strtolower($natent['interface']) == "pppoe")
						echo "PPPoE";
						else if(strtolower($natent['interface']) == "pptp")
						echo "PPTP";
						else
						echo strtoupper($natent['interface']);
					}
					?></td>
				<td class="listr" onClick="fr_toggle(<?=$nnats;?>)"
					id="frd<?=$nnats;?>"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
					<?php
					$check_snort_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['enable'];
					if ($check_snort_info == "on")
					{
						$check_snort = enabled;
					} else {
						$check_snort = disabled;
					}
					?> <?=strtoupper($check_snort);?></td>
				<td class="listr" onClick="fr_toggle(<?=$nnats;?>)"
					id="frd<?=$nnats;?>"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
					<?php
					$check_performance_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['performance'];
					if ($check_performance_info != "") {
						$check_performance = $check_performance_info;
					}else{
						$check_performance = "lowmem";
					}
					?> <?=strtoupper($check_performance);?></td>
				<td class="listr" onClick="fr_toggle(<?=$nnats;?>)"
					id="frd<?=$nnats;?>"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
					<?php
					$check_blockoffenders_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['blockoffenders7'];
					if ($check_blockoffenders_info == "on")
					{
						$check_blockoffenders = enabled;
					} else {
						$check_blockoffenders = disabled;
					}
					?> <?=strtoupper($check_blockoffenders);?></td>
					<?php

					$color2_upb = Running_Ck_b($snort_uuid, $if_real, $id);

					if ($color2_upb == 'yes') {
						$class_color_upb = 'listbg2';
					}else{
						$class_color_upb = 'listbg';
					}

					?>
				<td class="<?=$class_color_upb;?>" onClick="fr_toggle(<?=$nnats;?>)"
					id="frd<?=$nnats;?>"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
					<?php
					$check_snortbarnyardlog_info = $config['installedpackages']['snortglobal']['rule'][$nnats]['barnyard_enable'];
					if ($check_snortbarnyardlog_info == "on")
					{
						$check_snortbarnyardlog = strtoupper(enabled);
					}else{
						$check_snortbarnyardlog = strtoupper(disabled);
					}
					?> <?php echo "$check_snortbarnyardlog";?></td>
				<td class="listbg3" onClick="fr_toggle(<?=$nnats;?>)"
					ondblclick="document.location='snort_interfaces_edit.php?id=<?=$nnats;?>';">
				<font color="#ffffff"> <?=htmlspecialchars($natent['descr']);?>&nbsp;
				</td>
				<td valign="middle" class="list" nowrap>
				<table border="0" cellspacing="0" cellpadding="1">
					<tr>
						<td><a href="snort_interfaces_edit.php?id=<?=$i;?>"><img
							src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
							width="17" height="17" border="0" title="edit rule"></a></td>
					</tr>
				</table>
			
			</tr>
			<?php $i++; $nnats++; endforeach; ?>
			<tr>
				<td class="list" colspan="8"></td>
				<td class="list" valign="middle" nowrap>
				<table border="0" cellspacing="0" cellpadding="1">
					<tr>
						<td><?php if ($nnats == 0): ?><img
							src="../themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif"
							width="17" height="17" title="delete selected rules" border="0"><?php else: ?><input
							name="del" type="image"
							src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
							width="17" height="17" title="delete selected mappings"
							onclick="return confirm('Do you really want to delete the selected Snort Rule?')"><?php endif; ?></td>
					</tr>
				</table>
				</td>
			</tr>
		</table>
		</div>
		</td>
	</tr>
</table>

<br>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
		<div id="mainarea4">
		<table class="tabcont" width="100%" border="0" cellpadding="0"
			cellspacing="0">
			<tr id="frheader">
				<td width="100%"><span class="red"><strong>Note:</strong></span> <br>
				This is the <strong>Snort Menu</strong> where you can see an over
				view of all your interface settings. <br>
				Please edit the <strong>Global Settings</strong> tab before adding
				an interface. <br>
				<br>
				<span class="red"><strong>Warning:</strong></span> <br>
				<strong>New settings will not take effect until interface restart.</strong>
				<br>
				<br>
				<strong>Click</strong> on the <img
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif"
					width="17" height="17" border="0" title="Add Icon"> icon to add a
				interface.<strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Click</strong>
				on the <img
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_pass.gif"
					width="13" height="13" border="0" title="Start Icon"> icon to <strong>start</strong>
				snort and barnyard2. <br>
				<strong>Click</strong> on the <img
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_e.gif"
					width="17" height="17" border="0" title="Edit Icon"> icon to edit a
				interface and settings.<strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Click</strong>
				on the <img
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_block.gif"
					width="13" height="13" border="0" title="Stop Icon"> icon to <strong>stop</strong>
				snort and barnyard2. <br>
				<strong> Click</strong> on the <img
					src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif"
					width="17" height="17" border="0" title="Delete Icon"> icon to
				delete a interface and settings.</td>
			</tr>
		</table>
		</div>
	
	</tr>
	</td>
</table>

			<?php
			if ($pkg['tabs'] <> "") {
				echo "</td></tr></table>";
			}
			?></form>
</div>

<br>
<br>
<br>

<style type="text/css">
#footer2 {
	position: relative;
	background-color: transparent;
	background-image: url("./images/logo22.png");
	background-repeat: no-repeat;
	background-attachment: scroll;
	background-position: 0% 0%;
	top: 10px;
	left: 0px;
	width: 770px;
	height: 60px;
	color: #000000;
	text-align: center;
	font-size: 0.8em;
	padding-top: 40px;
	margin-bottom: -35px;
	clear: both;
}
</style>

<div id="footer2">SNORT registered � by Sourcefire, Inc, Barnyard2
registered � by securixlive.com, Orion registered � by Robert Zelaya,
Emergingthreats registered � by emergingthreats.net, Mysql registered �
by Mysql.com</div>
<!-- Footer DIV -->

			<?php

			include("fend.inc");

			echo $snort_custom_rnd_box;

			?>



</body>
</html>
