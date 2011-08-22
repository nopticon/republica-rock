<?php
/*
<Orion, a web development framework for RK.>
Copyright (C) <2011>  <Orion>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
define('IN_NUCLEO', true);
require('./interfase/common.php');

//
// Cancel 
//
if (isset($_POST['cancel'])) {
	redirect(s_link('my', 'dc'));
}

//
// Init member data
//
$user->init();

//
// Check if member is logged in
//
if (!$user->data['is_member']) {
	if ($user->data['is_bot']) {
		redirect(s_link());
	}
	do_login();
}

require('./interfase/comments.php');
$comments = new _comments();

//
// Delete
//
$mark	= request_var('mark', array(0));

if (isset($_POST['delete']) && $mark) {
	if (isset($_POST['confirm'])) {
		$comments->dc_delete($mark);
	} else {
		$s_hidden = array('delete' => true);
		
		$i = 0;
		foreach ($mark as $item) {
			$s_hidden += array('mark[' . $i++ . ']' => $item);
		}
		
		//
		// Setup user
		//
		$user->setup();
		
		//
		// Output to template
		//
		$template_vars = array(
			'MESSAGE_TEXT' => (sizeof($mark) == 1) ? $user->lang['CONFIRM_DELETE_PM'] : $user->lang['CONFIRM_DELETE_PMS'], 

			'S_CONFIRM_ACTION' => s_link('my', 'dc'),
			'S_HIDDEN_FIELDS' => s_hidden($s_hidden)
		);
		page_layout('DCONVS', 'confirm_body', $template_vars);
	}
	
	redirect(s_link('my', 'dc'));
}

//
// Setup user
//
$user->setup();

//
// Submit
//
$submit = (isset($_POST['post'])) ? TRUE : 0;
$msg_id = intval(request_var('p', 0));
$mode = request_var('mode', '');
$error = array();

if ($submit || $mode == 'start' || $mode == 'reply') {
	$member = '';
	$dc_subject = '';
	$dc_message = '';
	
	if ($submit) {
		if ($mode == 'reply') {
			$parent_id = request_var('parent', 0);
			
			$sql = 'SELECT *
				FROM _dc
				WHERE msg_id = ' . (int) $parent_id . '
					AND (privmsgs_to_userid = ' . $user->data['user_id'] . ' OR privmsgs_from_userid = ' . $user->data['user_id'] . ')';
			$result = $db->sql_query($sql);
			
			if (!$to_userdata = $db->sql_fetchrow($result)) {
				fatal_error();
			}
			$db->sql_freeresult($result);
			
			$privmsgs_to_userid = ($user->data['user_id'] == $to_userdata['privmsgs_to_userid']) ? 'privmsgs_from_userid' : 'privmsgs_to_userid';
			$to_userdata['user_id'] = $to_userdata[$privmsgs_to_userid];
		} else {
			$member = request_var('member', '');
			if (!empty($member)) {
				$member = get_username_base(phpbb_clean_username($member), true);
				if ($member !== false) {
					$sql = "SELECT user_id, username, username_base, user_email
						FROM _members
						WHERE username_base = '" . $db->sql_escape($member) . "'
							AND user_type <> " . USER_IGNORE;
					$result = $db->sql_query($sql);
					
					if (!$to_userdata = $db->sql_fetchrow($result)) {
						$error[] = 'NO_SUCH_USER';
					}
					$db->sql_freeresult($result);

					if (!sizeof($error) && $to_userdata['user_id'] == $user->data['user_id']) {
						$error[] = 'NO_AUTO_DC';
					}
				} else {
					$error[] = 'NO_SUCH_USER';
					$member = '';
				}
			} else {
				$error[] = 'EMPTY_USER';
			}
			
			$dc_subject = request_var('subject', '');
			if (empty($dc_subject)) {
				$error[] = 'EMPTY_DC_SUBJECT';
			}
		}
		
		if (isset($to_userdata) && isset($to_userdata['user_id'])) {
			// Check blocked member
			$sql = 'SELECT ban_id
				FROM _members_ban
				WHERE user_id = ' . (int) $to_userdata['user_id'] . '
					AND banned_user = ' . (int) $user->data['user_id'];
			$result = $db->sql_query($sql);
			
			if ($ban_profile = $db->sql_fetchrow($result)) {
				$error[] = 'BLOCKED_MEMBER';
			}
			$db->sql_freeresult($result);
		}
		
		$dc_message = request_var('message', '');
		if (empty($dc_message)) {
			$error[] = 'EMPTY_MESSAGE';
		}
		
		if (!sizeof($error)) {
			$dc_id = $comments->store_dc($mode, $to_userdata, $user->data, $dc_subject, $dc_message, true, true);
			
			redirect(s_link('my', array('dc', 'read', $dc_id)) . '#' . $dc_id);
		}
	}
}

//
// Start error handling
//
if (sizeof($error)) {
	$error = preg_replace('#^([A-Z_]+)$#e', "(!empty(\$user->lang['\\1'])) ? \$user->lang['\\1'] : '\\1'", $error);
	
	$template->assign_block_vars('error', array(
		'MESSAGE' => implode('<br />', $error))
	);
	
	if ($mode == 'reply') {
		$mode = 'read';
	}
}

$s_hidden_fields = array();

switch ($mode) {
	//
	// Start new conversation
	//
	case 'start':
		if (!$submit) {
			$member = request_var('member', '');
			if ($member != '') {
				$member = get_username_base(phpbb_clean_username($member));
				
				$sql = "SELECT user_id, username, username_base
					FROM _members
					WHERE username_base = '" . $db->sql_escape($member) . "'
						AND user_type <> " . USER_IGNORE;
				$result = $db->sql_query($sql);
				
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
			}
		}
		
		$template->assign_block_vars('dc_start', array(
			'MEMBER' => $member,
			'SUBJECT' => $dc_subject,
			'MESSAGE' => $dc_message)
		);
		
		$s_hidden_fields = array('mode' => 'start');
		break;
	//
	// Show selected conversation
	//
	case 'read':
		if (!$msg_id) {
			fatal_error();
		}
		
		$sql = 'SELECT *
			FROM _dc
			WHERE msg_id = ' . (int) $msg_id . '
				AND (privmsgs_to_userid = ' . $user->data['user_id'] . ' OR privmsgs_from_userid = ' . $user->data['user_id'] . ')
				AND msg_deleted <> ' . (int) $user->data['user_id'];
		$result = $db->sql_query($sql);
		
		if (!$msg_data = $db->sql_fetchrow($result)) {
			fatal_error();
		}
		$db->sql_freeresult($result);
		
		//
		// Get all messages for this conversation
		//
		$sql = 'SELECT c.*, m.user_id, m.username, m.username_base, m.user_color, m.user_avatar, m.user_sig, m.user_rank, m.user_gender, m.user_posts
			FROM _dc c, _members m
			WHERE c.parent_id = ' . (int) $msg_data['parent_id'] . '
				AND c.privmsgs_from_userid = m.user_id
			ORDER BY c.privmsgs_date';
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result)) {
			$template->assign_block_vars('conv', array(
				'SUBJECT' => $row['privmsgs_subject'])
			);
			
			do{
				$template->assign_block_vars('conv.item', array());
				
				if ($msg_id == $row['msg_id']) {
					$block = 'message';
					$user_profile = $comments->user_profile($row);
					
					$dc_messages = array(
						'USERNAME' => $user_profile['username'],
						'AVATAR' => $user_profile['user_avatar'],
						'USER_RANK' => $user_profile['user_rank'],
						'SIGNATURE' => ($row['user_sig'] != '') ? $comments->parse_message($row['user_sig'], 'bold orange') : '',
						'PROFILE' => $user_profile['profile'],
						'USER_COLOR' => $user_profile['user_color'],
						'MESSAGE' => $comments->parse_message($row['privmsgs_text'], 'bold orange'),
						'CAN_REPLY' => $row['msg_can_reply']
					);
				} else {
					$block = 'header';
					
					$dc_messages = array(
						'READ_MESSAGE' => s_link('my', array('dc', 'read', $row['msg_id'])) . '#' . $row['msg_id'],
						'USERNAME' => $row['username'],
						'USER_COLOR' => $row['user_color'],
					);
				}
				
				$dc_messages += array(
					'POST_ID' => $row['msg_id'],
					'POST_DATE' => $user->format_date($row['privmsgs_date'])
				);
				
				$template->assign_block_vars('conv.item.' . $block, $dc_messages);
			}
			while ($row = $db->sql_fetchrow($result));
			$db->sql_freeresult($result);
		} else {
			fatal_error();
		}
		
		$s_hidden_fields = array('mark[]' => $msg_data['parent_id'], 'p' => $msg_id, 'parent' => $msg_data['parent_id'], 'mode' => 'reply');
		break;
	//
	// Get all conversations for this member
	//
	default:
		$offset = request_var('offset', 0);
		
		$sql_tot = 'SELECT COUNT(msg_id) AS total
			FROM _dc
			WHERE (privmsgs_to_userid = ' . $user->data['user_id'] . ' OR privmsgs_from_userid = ' . $user->data['user_id'] . ')
				AND msg_id = parent_id
				AND msg_deleted <> ' . (int) $user->data['user_id'];
		$result = $db->sql_query($sql_tot);
		
		$total_conv = ($row = $db->sql_fetchrow($result)) ? $row['total'] : 0;
		$db->sql_freeresult($result);
		
		$sql = 'SELECT c.msg_id, c.parent_id, c.last_msg_id, c.root_conv, c.privmsgs_date, c.privmsgs_subject, c2.privmsgs_date as last_privmsgs_date, m.user_id, m.username, m.username_base, m.user_color, m2.user_id as user_id2, m2.username as username2, m2.username_base as username_base2, m2.user_color as user_color2
			FROM _dc c, _dc c2, _members m, _members m2
			WHERE (c.privmsgs_to_userid = ' . $user->data['user_id'] . ' OR c.privmsgs_from_userid = ' . $user->data['user_id'] . ')
				AND c.msg_id = c.parent_id
				AND c.msg_deleted <> ' . (int) $user->data['user_id'] . '
				AND c.privmsgs_from_userid = m.user_id
				AND c.privmsgs_to_userid = m2.user_id
				AND (IF(c.last_msg_id,c.last_msg_id,c.msg_id) = c2.msg_id)
			ORDER BY c2.privmsgs_date DESC 
			LIMIT ' . (int) $offset . ', ' . (int) $config['posts_per_page'];
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result)) {
			$template->assign_block_vars('messages', array());
			
			do {
				$dc_with = ($user->data['user_id'] == $row['user_id']) ? '2' : '';
				if (!$row['last_msg_id']) {
					$row['last_msg_id'] = $row['msg_id'];
					$row['last_privmsgs_date'] = $row['privmsgs_date'];
				}
				
				$template->assign_block_vars('messages.item', array(
					'S_MARK_ID' => $row['parent_id'],
					'SUBJECT' => $row['privmsgs_subject'],
					'U_READ' => s_link('my', array('dc', 'read', $row['last_msg_id'])) . '#' . $row['last_msg_id'],
					'POST_DATE' => $user->format_date($row['last_privmsgs_date']),
					'ROOT_CONV' => $row['root_conv'],
					
					'DC_USERNAME' => $row['username'.$dc_with],
					'DC_PROFILE' => s_link('m', $row['username_base'.$dc_with]),
					'DC_COLOR' => $row['user_color'.$dc_with])
				);
			}
			while ($row = $db->sql_fetchrow($result));
			$db->sql_freeresult($result);
			
			build_num_pagination(s_link('my', array('dc', 's%d')), $total_conv, $config['posts_per_page'], $offset);
		} else if ($total_conv) {
			redirect(s_link('my', 'dc'));
		} else {
			$template->assign_block_vars('no_messages', array());
		}
		
		$template->assign_block_vars('dc_total', array(
			'TOTAL' => $total_conv)
		);
		break;
}

if ($mode != '') {
	$template->assign_block_vars('back_dc', array(
		'URL' => s_link('my', 'dc'))
	);
}

//
// Get friends for this member
//
$sql = 'SELECT DISTINCT m.user_id, m.username, m.username_base, m.user_color
	FROM _members_friends f, _members m
	WHERE (f.user_id = ' . (int) $user->data['user_id'] . ' AND f.buddy_id = m.user_id)
		OR (f.buddy_id = ' . (int) $user->data['user_id'] . ' AND f.user_id = m.user_id)
	ORDER BY m.username';
$result = $db->sql_query($sql);

$template->assign_block_vars('sdc_friends', array(
	'DC_START' => s_link('my', array('dc', 'start')))
);

if ($row = $db->sql_fetchrow($result)) {
	do {
		$template->assign_block_vars('sdc_friends.item', array(
			'USERNAME' => $row['username'],
			'URL' => s_link('my', array('dc', 'start', $row['username_base'])),
			'USER_COLOR' => $row['user_color'])
		);
	}
	while ($row = $db->sql_fetchrow($result));
	$db->sql_freeresult($result);
}

//
// Output template
//
$page_title = ($mode == 'read') ? $user->lang['DCONV_READ'] : $user->lang['DCONVS'];

$template_vars = array(
	'L_CONV' => $page_title,
	'S_ACTION' => s_link('my', 'dc'),
	'S_HIDDEN_FIELDS' => s_hidden($s_hidden_fields)
);

page_layout($page_title, 'dconv_body', $template_vars);

?>