<?php
/*
 * vpn_l2tp_users.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2025 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-vpn-vpnl2tp-users
##|*NAME=VPN: L2TP: Users
##|*DESCR=Allow access to the 'VPN: L2TP: Users' page.
##|*MATCH=vpn_l2tp_users.php*
##|-PRIV

$pgtitle = array(gettext("VPN"), gettext("L2TP"), gettext("Users"));
$pglinks = array("", "vpn_l2tp.php", "@self");
$shortcut_section = "l2tps";

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("vpn.inc");

$pconfig = $_POST;

if ($_POST['act'] == "del") {
	if (config_get_path("l2tp/user/{$_POST['id']}")) {
		config_del_path("l2tp/user/{$_POST['id']}");
		l2tp_users_sort();
		write_config(gettext("Deleted a L2TP VPN user."));
		vpn_l2tp_updatesecret();
		pfSenseHeader("vpn_l2tp_users.php");
		exit;
	}
}

include("head.inc");

if (config_path_enabled('l2tp/radius')) {
	print_info_box(gettext("RADIUS is enabled. The local user database will not be used."));
}

$tab_array = array();
$tab_array[] = array(gettext("Configuration"), false, "vpn_l2tp.php");
$tab_array[] = array(gettext("Users"), true, "vpn_l2tp_users.php");
display_top_tabs($tab_array);
?>
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('L2TP Users')?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-striped table-hover table-rowdblclickedit">
				<thead>
					<tr>
						<th><?=gettext("Username")?></th>
						<th><?=gettext("IP address")?></th>
						<th><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody>
<?php $i = 0; foreach (config_get_path('l2tp/user', []) as $secretent):?>
					<tr>
						<td>
							<?=htmlspecialchars($secretent['name'])?>
						</td>
						<td>
							<?php if ($secretent['ip'] == "") $secretent['ip'] = "Dynamic"?>
							<?=htmlspecialchars($secretent['ip'])?>&nbsp;
						</td>
						<td>
							<a class="fa-solid fa-pencil"	title="<?=gettext('Edit user')?>"	href="vpn_l2tp_users_edit.php?id=<?=$i?>"></a>
							<a class="fa-solid fa-trash-can"	title="<?=gettext('Delete user')?>"	href="vpn_l2tp_users.php?act=del&amp;id=<?=$i?>" usepost></a>
						</td>
					</tr>
<?php $i++; endforeach?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<nav class="action-buttons">
	<a class="btn btn-success btn-sm" href="vpn_l2tp_users_edit.php">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</a>
</nav>

<?php include("foot.inc");
