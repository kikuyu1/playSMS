<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

if (!auth_isadmin()) {
	auth_block();
}

switch (_OP_) {
	case "firewall_list":
		$search_category = array(
			_('IP address') => 'ip_address' 
		);
		$base_url = 'index.php?app=main&inc=feature_firewall&op=firewall_list';
		$search = themes_search($search_category, $base_url);
		$keywords = $search['dba_keywords'];
		$count = dba_count(_DB_PREF_ . '_featureFirewall', '', $keywords);
		$nav = themes_nav($count, $search['url']);
		$extras = array(
			'ORDER BY' => 'id',
			'LIMIT' => $nav['limit'],
			'OFFSET' => $nav['offset'] 
		);
		$list = dba_search(_DB_PREF_ . '_featureFirewall', '*', '', $keywords, $extras);
		
		$content = "
			<h2>" . _('Firewall') . "</h2>
			<p>" . $search['form'] . "</p>
			<form name=fm_firewall_list id=fm_firewall_list action='index.php?app=main&inc=feature_firewall&op=actions' method=post>
			" . _CSRF_FORM_ . "
			<div class=actions_box>
				<div class=pull-left>
					<a href='" . _u('index.php?app=main&inc=feature_firewall&op=firewall_add') . "'>" . $icon_config['add'] . "</a>
				</div>
				<script type='text/javascript'>
					$(document).ready(function() {
						$('#action_go').click(function(){
							$('#fm_firewall_list').submit();
						});
					});
				</script>
				<div class=pull-right>
					<select name=go class=search_input_category>
						<option value=>" . _('Select') . "</option>
						<option value=delete>" . _('Delete') . "</option>
					</select>
					<a href='#' id=action_go>" . $icon_config['go'] . "</a>
				</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=25%>" . _('IP address') . "</th>
				<th width=25%>" . _('Reversed lookup') . "</th>
				<th width=5%><input type=checkbox onclick=CheckUncheckAll(document.fm_firewall_list)></th>
			</tr>
			</thead>
			<tbody>";
		
		$i = $nav['top'];
		$j = 0;
		for ($j = 0; $j < count($list); $j++) {
			$pid = $list[$j]['id'];
			$ip_address = $list[$j]['ip_address'];
			$i--;
			$c_i = "<a href=\"" . _u('index.php?app=main&inc=feature_firewall&op=firewall_edit&id=' . $pid) . "\">" . $i . ".</a>";
			if ($list[$j]['uid'] == $user_config['uid']) {
				$name = "<a href='" . _u('index.php?app=main&inc=feature_firewall&op=firewall_edit&pid=' . $pid) . "'>" . $name . "</a>";
			}
			$content .= "
				<tr>
					<td>$ip_address</td>
					<td></td>
					<td>
						<input type=hidden name=itemid[" . $j . "] value=\"$pid\">
						<input type=checkbox name=checkid[" . $j . "]>
					</td>
				</tr>";
		}
		
		$content .= "
			</tbody>
			</table>
			</div>
			<div class=pull-right>" . $nav['form'] . "</div>
			</form>";
		
		if ($err = $_SESSION['error_string']) {
			_p("<div class=error_string>$err</div>");
		}
		_p($content);
		break;
	
	case "firewall_del":
		$rid = $_REQUEST['rid'];
		$ip_address = firewall_getip($rid);
		$_SESSION['error_string'] = _('Fail to delete IP address') . " (" . _('IP address') . ": $ip_address)";
		$db_query = "DELETE FROM " . _DB_PREF_ . "_featureFirewall WHERE id='$rid'";
		if (@dba_affected_rows($db_query)) {
			$_SESSION['error_string'] = _('IP address has been deleted') . " (" . _('IP address') . ": $ip_address)";
		}
		header("Location: " . _u('index.php?app=main&inc=feature_firewall&op=firewall_list'));
		exit();
		break;
	
	case "actions":
		$checkid = $_REQUEST['checkid'];
		$itemid = $_REQUEST['itemid'];
		
		$items = array();
		foreach ($checkid as $key => $val) {
			if (strtoupper($val) == 'ON') {
				if ($itemid[$key]) {
					$items[] = $itemid[$key];
				}
			}
		}
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'delete':
				foreach ($items as $item) {
					$conditions = array(
						'id' => $item 
					);
					dba_remove(_DB_PREF_ . '_featureFirewall', $conditions);
				}
				$search = themes_search_session();
				$nav = themes_nav_session();
				
				$_SESSION['error_string'] = _('IP addreses has been deleted');
				$ref = $search['url'] . '&search_keyword=' . $search['keyword'] . '&search_category=' . $search['category'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
				header("Location: " . _u($ref));
				break;
		}
		break;
	
	case "firewall_add":
		if ($err = $_SESSION['error_string']) {
			$content = "<div class=error_string>$err</div>";
		}
		$content .= "
			<h2>" . _('add blocked IPs') . "</h2>
			<form action='index.php?app=main&inc=feature_firewall&op=firewall_add_yes' method='post'>
			" . _CSRF_FORM_ . "
			<table class=playsms-table>
			<tr>
				<td class=label-sizer>" . _mandatory(_('IP addresses')) . "</td><td><textarea name='add_ip_address' required></textarea></td>
			</tr>
			</table>
			<input type='submit' class='button' value='" . _('Save') . "'>
			</form>
			" . _back('index.php?app=main&inc=feature_firewall&op=firewall_list');
		_p($content);
		break;
	
	case "firewall_add_yes":
		$add_ip_address = $_POST['add_ip_address'];
		if ($add_ip_address) {
			foreach (explode(',', str_replace(' ', '', $add_ip_address)) as $ip) {
				if (blacklist_ifipexists($ip)) {
					$_SESSION['error_string'] = _('IPs exist');
				} else {
					blacklist_addip($ip);
					$_SESSION['error_string'] = _('IPs has been added') . " (" . _('IP addresses') . ": $add_ip_address)";
				}
			}
		} else {
			$_SESSION['error_string'] = _('You must fill all fields');
		}
		header("Location: " . _u('index.php?app=main&inc=feature_firewall&op=firewall_add'));
		exit();
		break;
}