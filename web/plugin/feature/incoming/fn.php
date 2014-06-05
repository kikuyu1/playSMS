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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Get settings
 * @return array Settings
 *               Available setting keys:
 *               - leave_copy_sandbox
 *               - match_all_sender_id
 */
function incoming_settings_get() {
	
	// settings to leave copy on sandbox
	$data = registry_search(1, 'feature', 'incoming', 'settings_leave_copy_sandbox');
	$settings['leave_copy_sandbox'] = (int)$data['feature']['incoming']['settings_leave_copy_sandbox'];
	
	// settings to match with all approved sender ID
	$data = registry_search(1, 'feature', 'incoming', 'settings_match_all_sender_id');
	$settings['match_all_sender_id'] = (int)$data['feature']['incoming']['settings_match_all_sender_id'];
	
	return $settings;
}

/*
 * intercept on after-process stage for incoming sms and forward it to selected user's inbox
 *
 * @param $sms_datetime
 *   incoming SMS date/time
 * @param $sms_sender
 *   incoming SMS sender
 * @message
 *   incoming SMS message before interepted
 * @param $sms_receiver
 *   receiver number that is receiving incoming SMS
 * @param $feature
 *   feature managed to hook current incoming SMS
 * @param $status
 *   recvsms() status, 0 or FALSE for unhandled
 * @param $uid
 *   keyword owner
 * @return
 *   array $ret
*/
function incoming_hook_recvsms_intercept_after($sms_datetime, $sms_sender, $message, $sms_receiver, $feature, $status, $uid) {
	global $core_config;
	
	$ret = array();
	$users = array();
	$is_routed = FALSE;
	
	if (!$status) {
		
		// get settings
		$settings = incoming_settings_get();
		
		// sandbox match receiver number and sender ID
		if (!$is_routed) {
			$data = registry_search(1, 'feature', 'incoming', 'sandbox_match_sender_id');
			$sandbox_match_sender_id = (int)$data['feature']['incoming']['sandbox_match_sender_id'];
			
			// route sandbox if receiver number matched with default sender ID of users
			if ($sandbox_match_sender_id) {
				$s = array();
				
				if ($settings['match_all_sender_id']) {
					
					// get all approved sender ID
					$s = sendsms_getall_sender();
				} else {
					$data = user_search($sms_receiver, 'sender');
					foreach ($data as $user) {
						
						// get default sender ID
						if ($user['sender']) {
							$s[] = $user['sender'];
							
							// in case an error occured where multiple users own the same sender ID
							break;
						}
					}
				}
				
				// start matching
				foreach ($s as $sender_id) {
					if ($sender_id && $sms_receiver && ($sender_id == $sms_receiver)) {
						
						if ($settings['match_all_sender_id']) {
							
							// get $username who owns $sender_id
							$uid = sender_id_owner($sender_id);
							$username = user_uid2username($uid);
						} else {
							
							$username = $user['username'];
						}
						
						if ($username) {
							_log("sandbox match sender start u:" . $username . " dt:" . $sms_datetime . " s:" . $sms_sender . " r:" . $sms_receiver . " m:" . $message, 3, "incoming");
							recvsms_inbox_add($sms_datetime, $sms_sender, $username, $message, $sms_receiver);
							_log("sandbox match sender end u:" . $username, 3, "incoming");
							$is_routed = TRUE;
							
							// single match only
							break;
						}
					}
				}
			}
		}
		
		// sandbox prefix
		if (!$is_routed) {
			$data = registry_search(1, 'feature', 'incoming', 'sandbox_prefix');
			$sandbox_prefix = trim(strtoupper(core_sanitize_alphanumeric($data['feature']['incoming']['sandbox_prefix'])));
			
			// route sandbox by adding a prefix to message and re-enter it to recvsms()
			if ($sandbox_prefix && trim($message)) {
				$message = $sandbox_prefix . ' ' . trim($message);
				_log("sandbox add prefix start keyword:" . $sandbox_prefix . " dt:" . $sms_datetime . " s:" . $sms_sender . " r:" . $sms_receiver . " m:" . $message, 3, "incoming");
				recvsms($sms_datetime, $sms_sender, $message, $sms_receiver);
				_log("sandbox add prefix end keyword:" . $sandbox_prefix, 3, "incoming");
				$is_routed = TRUE;
			}
		}
		
		// sandbox forward to users
		if (!$is_routed) {
			$data = registry_search(1, 'feature', 'incoming', 'sandbox_forward_to');
			$sandbox_forward_to = array_unique(unserialize($data['feature']['incoming']['sandbox_forward_to']));
			
			foreach ($sandbox_forward_to as $uid) {
				$c_username = user_uid2username($uid);
				if ($c_username) {
					$users[] = $c_username;
				}
			}
			
			// route sandbox to users inbox
			foreach ($users as $username) {
				_log("sandbox to user start u:" . $username . " dt:" . $sms_datetime . " s:" . $sms_sender . " r:" . $sms_receiver . " m:" . $message, 3, "incoming");
				recvsms_inbox_add($sms_datetime, $sms_sender, $username, $message, $sms_receiver);
				_log("sandbox to user end u:" . $username, 3, "incoming");
				$is_routed = TRUE;
			}
		}
		
		// flag the hook if is_routed
		if ($is_routed) {
			$ret['param']['feature'] = 'incoming';
			
			if ($settings['leave_copy_sandbox']) {
				$ret['param']['status'] = 0;
			} else {
				$ret['param']['status'] = 1;
			}
			
			$ret['param']['uid'] = 1;
			$ret['modified'] = TRUE;
		}
	}
	
	return $ret;
}
