<?php
/*
 * vpn_ipsec_keys.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * originally based on m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

##|+PRIV
##|*IDENT=page-vpn-ipsec-listkeys
##|*NAME=VPN: IPsec: Pre-Shared Keys List
##|*DESCR=Allow access to the 'VPN: IPsec: Pre-Shared Keys List' page.
##|*MATCH=vpn_ipsec_keys.php*
##|-PRIV

require_once("functions.inc");
require_once("guiconfig.inc");
require_once("ipsec.inc");
require_once("vpn.inc");
require_once("filter.inc");

$userkeys = array();
foreach (config_get_path('system/user', []) as $id => $user) {
	if (!empty($user['ipsecpsk'])) {
		$userkeys[] = array('ident' => $user['name'], 'type' => 'PSK', 'pre-shared-key' => $user['ipsecpsk'], 'id' => $id);;
	}
}

if (isset($_POST['apply'])) {
	ipsec_configure();
	/* reload the filter in the background */
	$retval = 0;
	$retval |= filter_configure();
	if (is_subsystem_dirty('ipsec')) {
		clear_subsystem_dirty('ipsec');
	}
}

if ($_POST['act'] == "del") {
	if (!empty(config_get_path('ipsec/mobilekey/' . $_POST['id']))) {
		config_del_path('ipsec/mobilekey/' . $_POST['id']);
		write_config(gettext("Deleted IPsec Pre-Shared Key"));
		mark_subsystem_dirty('ipsec');
		header("Location: vpn_ipsec_keys.php");
		exit;
	}
}

$pgtitle = array(gettext("VPN"), gettext("IPsec"), gettext("Pre-Shared Keys"));
$pglinks = array("", "vpn_ipsec.php", "@self");
$shortcut_section = "ipsec";

include("head.inc");

if ($_POST['apply']) {
	print_apply_result_box($retval);
}

if (is_subsystem_dirty('ipsec')) {
	print_apply_box(gettext("The IPsec tunnel configuration has been changed.") . "<br />" . gettext("The changes must be applied for them to take effect."));
}

$tab_array = array();
$tab_array[] = array(gettext("Tunnels"), false, "vpn_ipsec.php");
$tab_array[] = array(gettext("Mobile Clients"), false, "vpn_ipsec_mobile.php");
$tab_array[] = array(gettext("Pre-Shared Keys"), true, "vpn_ipsec_keys.php");
$tab_array[] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Pre-Shared Keys')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Identifier"); ?></th>
						<th><?=gettext("Type"); ?></th>
						<th><?=gettext("Pre-Shared Key"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
<?php $i = 0; foreach ($userkeys as $secretent): ?>
					<tr>
						<td>
							<?php
							if ($secretent['ident'] == 'allusers') {
								echo gettext("ANY USER");
							} else {
								echo htmlspecialchars($secretent['ident']);
							}
							?>
						</td>
						<td>
							<?php
							if (empty($secretent['type'])) {
								echo 'PSK';
							} else {
								echo htmlspecialchars($secretent['type']);
							}
							?>
						</td>
						<td>
							<?=htmlspecialchars($secretent['pre-shared-key'])?>
						</td>
						<td>
							<a class="fa-solid fa-pencil"	title="<?=gettext('Edit user')?>"	href="system_usermanager.php?act=edit&amp;userid=<?=$secretent['id']?>"></a>
						</td>
					</tr>
<?php $i++; endforeach; ?>

<?php $i = 0; foreach (config_get_path('ipsec/mobilekey', []) as $secretent): ?>
					<tr>
						<td>
							<?=htmlspecialchars($secretent['ident'])?>
						</td>
						<td>
							<?php
							if (empty($secretent['type'])) {
								echo 'PSK';
							} else {
								echo htmlspecialchars($secretent['type']);
							}
							?>
						</td>
						<td>
							<?=htmlspecialchars($secretent['pre-shared-key'])?>
						</td>
						<td>
							<a class="fa-solid fa-pencil"	title="<?=gettext('Edit key')?>" href="vpn_ipsec_keys_edit.php?id=<?=$i?>"></a>
							<a class="fa-solid fa-trash-can"	title="<?=gettext('Delete key')?>" href="vpn_ipsec_keys.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php $i++; endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="vpn_ipsec_keys_edit.php">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>
<div class="infoblock">
<?php
print_info_box(gettext("PSK for any user can be set by using an identifier of any."), 'info', false);
?>
</div>
<?php include("foot.inc"); ?>
