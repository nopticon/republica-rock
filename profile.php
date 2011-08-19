<?php
// -------------------------------------------------------------
// $Id: profile.php,v 1.4 2006/02/16 04:58:14 Psychopsia Exp $
//
// STARTED   : Sat Nov 19, 2005
// COPYRIGHT : � 2006 Rock Republik
// -------------------------------------------------------------

define('IN_NUCLEO', true);
require('./interfase/common.php');

$user->init();

//
// Set init vars
//
$mode = request_var('mode', '');
$submit = (isset($_POST['submit'])) ? TRUE : FALSE;

if (($mode == 'password' || $mode == 'verify'))
{
	if ($user->data['is_bot'])
	{
		redirect(s_link());
	}
	
	if ($user->data['is_member'])
	{
		redirect(s_link('my', 'profile'));
	}
	
	$user->setup();
	
}

switch ($mode)
{
	case 'password':
		$template_vars = array();
		
		if ($submit)
		{
			$email = request_var('address', '');
			if (empty($email))
			{
				fatal_error();
			}
			
			if (!preg_match('/^[a-z0-9&\'\.\-_\+]+@[a-z0-9\-]+\.([a-z0-9\-]+\.)*?[a-z]+$/is', $email))
			{
				fatal_error();
			}
			
			$process = false;
			
			$sql = "SELECT *
				FROM _members
				WHERE user_email = '" . $db->sql_escape($email) . "'
					AND user_type NOT IN (" . USER_INACTIVE . ", " . USER_IGNORE . ", " . USER_FOUNDER . ")
					AND user_active = 1";
			$result = $db->sql_query($sql);
			
			if ($userdata = $db->sql_fetchrow($result))
			{
				$process = true;
			}
			$db->sql_freeresult($result);
			
			$sql = 'SELECT *
				FROM _crypt_confirm
				WHERE crypt_userid = ' . (int) $userdata['user_id'];
			$result = $db->sql_query($sql);
			
			if ($void = $db->sql_fetchrow($result))
			{
				fatal_error();
			}
			$db->sql_freeresult($result);
			
			$sql = 'SELECT *
				FROM _banlist
				WHERE ban_userid = ' . (int) $userdata['user_id'];
			$result = $db->sql_query($sql);
			
			if ($ban_data = $db->sql_fetchrow($result))
			{
				fatal_error();
			}
			$db->sql_freeresult($result);
			
			if ($process)
			{
				require('./interfase/emailer.php');
				$emailer = new emailer();
				
				$verification_code = md5(unique_id());
				
				$insert = array(
					'crypt_userid' => $userdata['user_id'],
					'crypt_code' => $verification_code,
					'crypt_time' => $user->time
				);
				$sql = 'INSERT INTO _crypt_confirm' . $db->sql_build_array('INSERT', $insert);
				$db->sql_query($sql);
				
				// Send email
				$emailer->from('info@rockrepublik.net');
				$emailer->use_template('user_activate_passwd', $config['default_lang']);
				$emailer->email_address($userdata['user_email']);
				
				$emailer->assign_vars(array(
					'USERNAME' => $userdata['username'],
					'U_ACTIVATE' => s_link('my', array('verify', $verification_code)))
				);
				$emailer->send();
				$emailer->reset();
			}
		}
		
		$template_vars += array(
			'PAGE_MODE' => (($process) ? 'submit' : 'nosubmit'),
			'S_ACTION' => s_link('my', 'password')
		);
		
		page_layout('SENDPASSWORD', 'sendpassword_body', $template_vars);
		break;
	case 'verify':
		$code = request_var('code', '');
		if (!preg_match('#([a-z0-9]+)#is', $code))
		{
			fatal_error();
		}
		
		$sql = "SELECT c.*, m.user_id, m.username, m.username_base, m.user_email
			FROM _crypt_confirm c, _members m
			WHERE c.crypt_code = '" . $db->sql_escape($code) . "'
				AND c.crypt_userid = m.user_id";
		$result = $db->sql_query($sql);
		
		if (!$crypt_data = $db->sql_fetchrow($result))
		{
			fatal_error();
		}
		$db->sql_freeresult($result);
		
		if ($submit)
		{
			$password = request_var('newkey', '');
			
			if (!empty($password))
			{
				$crypt_password = user_password($password);
				
				$sql = "UPDATE _members
					SET user_password = '" . $db->sql_escape($crypt_password) . "'
					WHERE user_id = " . (int) $crypt_data['user_id'];
				$db->sql_query($sql);
				
				$sql = "DELETE FROM _crypt_confirm
					WHERE crypt_code = '" . $db->sql_escape($code) . "'
						AND crypt_userid = " . (int) $crypt_data['user_id'];
				$db->sql_query($sql);
				
				// Send email
				require('./interfase/emailer.php');
				$emailer = new emailer();
				
				$emailer->from('info@rockrepublik.net');
				$emailer->use_template('user_confirm_passwd', $config['default_lang']);
				$emailer->email_address($crypt_data['user_email']);
				
				$emailer->assign_vars(array(
					'USERNAME' => $crypt_data['username'],
					'PASSWORD' => $password,
					'U_PROFILE' => s_link('m', $crypt_data['username_base']))
				);
				$emailer->send();
				$emailer->reset();
				
				//
				$template_vars = array(
					'PAGE_MODE' => 'updated'
				);
				page_layout('SENDPASSWORD', 'sendpassword_body', $template_vars);
			}
		}
		
		$template_vars = array(
			'PAGE_MODE' => 'verify',
			'S_ACTION' => s_link('my', array('verify', $code))
		);
		page_layout('SENDPASSWORD', 'sendpassword_body', $template_vars);
		break;
	case 'confirm':
		$code = request_var('code', '');
		if (!preg_match('#([a-z0-9]+)#is', $code))
		{
			fatal_error();
		}
		
		$sql = "SELECT c.*, m.user_id, m.username, m.username_base, m.user_email
			FROM _crypt_confirm c, _members m
			WHERE c.crypt_code = '" . $db->sql_escape($code) . "'
				AND c.crypt_userid = m.user_id";
		$result = $db->sql_query($sql);
		
		if (!$crypt_data = $db->sql_fetchrow($result))
		{
			fatal_error();
		}
		$db->sql_freeresult($result);
		
		$user_id = $crypt_data['user_id'];
		
		$sql = 'UPDATE _members
			SET user_type = ' . USER_NORMAL . '
			WHERE user_id = ' . (int) $user_id;
		$db->sql_query($sql);
		
		$sql = "DELETE FROM _crypt_confirm
			WHERE crypt_code = '" . $db->sql_escape($code) . "'
				AND crypt_userid = " . (int) $user_id;
		$db->sql_query($sql);
		
		// Unread
		$u_topics = array(288, 1455, 2524, 3168, 4121);
		foreach ($u_topics as $v)
		{
			$user->save_unread(UH_T, $v, 0, $user_id);
		}
		$user->save_unread(UH_U, $user_id);
		$user->points_add(3, $user_id);
		
		//
		$sql = 'SELECT *
			FROM _members_ref_assoc
			WHERE ref_uid = ' . (int) $user_id;
		$result = $db->sql_query($sql);
		
		if ($ref_assoc = $db->sql_fetchrow($result))
		{
			if ($user_id != $ref_assoc['ref_orig'])
			{
				$user->points_add(3, $ref_assoc['ref_orig']);
			
				$sql = 'INSERT INTO _members_friends (user_id, buddy_id, friend_time) 
					VALUES (' . (int) $user_id . ', ' . (int) $ref_assoc['ref_orig'] . ', ' . time() . ')';
				$db->sql_query($sql);
				$sql = 'INSERT INTO _members_friends (user_id, buddy_id, friend_time) 
					VALUES (' . (int) $ref_assoc['ref_orig'] . ', ' . (int) $user_id . ', ' . time() . ')';
				$db->sql_query($sql);
			
				$user->save_unread(UH_FRIEND, $user_id, 0, $ref_assoc['ref_orig']);
			}
			
			$sql = 'DELETE FROM _members_ref_assoc
				WHERE ref_id = ' . (int) $ref_assoc['ref_id'];
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
		//
		$sql = "SELECT *
			FROM _members_ref_invite
			WHERE invite_email = '" . $db->sql_escape($crypt_data['user_email']) . "'";
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$sql = "DELETE FROM _members_ref_invite
				WHERE invite_code = '" . $db->sql_escape($row['invite_code']) . "'";
			$db->sql_query($sql);
		}
		$db->sql_freeresult($result);
		
		//
		require('./interfase/emailer.php');
		$emailer = new emailer();
		
		$emailer->from('info@rockrepublik.net');
		$emailer->use_template('user_welcome_confirm');
		$emailer->email_address($crypt_data['user_email']);
		
		$emailer->assign_vars(array(
			'USERNAME' => $crypt_data['username'])
		);
		$emailer->send();
		$emailer->reset();
		
		//
		if (empty($user->data))
		{
			$user->init();
		}
		if (empty($user->lang))
		{
			$user->setup();
		}
		
		$custom_vars = array(
			'S_REDIRECT' => '',
			'MESSAGE_TITLE' => $user->lang['INFORMATION'],
			'MESSAGE_TEXT' => $user->lang['MEMBERSHIP_ADDED_CONFIRM']
		);
		page_layout('INFORMATION', 'message_body', $custom_vars);
		break;
}

//
// Check user session
//
if (!$user->data['is_member'])
{
	do_login();
}

//
// Set user settings
//
$user->setup();

$current_time = time();

//
// MAIN CODE
//
switch ($mode)
{
	case 'profile':
		$user_fields = array(
			'public_email' => (string) $user->data['user_public_email'],
			'timezone' => (double) $user->data['user_timezone'],
			'dateformat' => (string) $user->data['user_dateformat'],
			'location' => (string) $user->data['user_location'],
			'sig' => (string) $user->data['user_sig'],
			'msnm' => (string) $user->data['user_msnm'],
			'yim' => (string) $user->data['user_yim'],
			'aim' => (string) $user->data['user_aim'],
			'icq' => (string) $user->data['user_icq'],
			'lastfm' => (string) $user->data['user_lastfm'],
			'website' => (string) $user->data['user_website'],
			'occ' => (string) $user->data['user_occ'],
			'interests' => (string) $user->data['user_interests'],
			'os' => (string) $user->data['user_os'],
			'fav_genres' => (string) $user->data['user_fav_genres'],
			'fav_artists' => (string) $user->data['user_fav_artists'],
			'rank' => '',
			'avatar' => '',
			'color' => (string) $user->data['user_color']
		);
		
		//if (!$user->data['user_gender'])
		{
			$user_fields['gender'] = (int) $user->data['user_gender'];
		}
		
		//if (!$user->data['user_birthday'])
		{
			$user_fields['birthday_day'] = (int) substr($user->data['user_birthday'], 6, 2);
			$user_fields['birthday_month'] = (int) substr($user->data['user_birthday'], 4, 2);
			$user_fields['birthday_year'] = (int) substr($user->data['user_birthday'], 0, 4);
		}
		
		foreach ($user_fields as $name => $value)
		{
			$$name = $value;
		}
		
		$hideuser = (int) $user->data['user_hideuser'];
		$email_dc = (int) $user->data['user_email_dc'];
		
		$error = array();
		$dateset = array(
			'd M Y H:i',
			'd M Y h:i a',
			'M d, Y H:i',
			'M d, Y h:i a'
		);
		
		if ($submit)
		{
			$e_mb = array('timezone');
			$e2_mb = array();
			foreach ($e_mb as $k)
			{
				$e2_mb[$k] = true;
			}
			
			foreach ($user_fields as $name => $value)
			{
				$multibyte = true;
				if ($e2_mb[$name])
				{
					$multibyte = false;
				}
				$$name = request_var($name, $value, $multibyte);
				//echo $name . ' > ' . $$name . '<br />';
			}
			
			$password1 = request_var('password1', '');
			$password2 = request_var('password2', '');
			$hideuser = (isset($_POST['hideuser'])) ? true : false;
			$email_dc = (isset($_POST['email_dc'])) ? true : false;
			
			if (!empty($password1))
			{
				if (empty($password2))
				{
					$error[] = 'EMPTY_PASSWORD2';
				}
				
				if (!sizeof($error))
				{
					if ($password1 != $password2)
					{
						$error[] = 'PASSWORD_MISMATCH';
					}
					else if (strlen($password1) > 30)
					{
						$error[] = 'PASSWORD_LONG';
					}
				}
			}
			
			$check_length_ary = array('location', 'sig', 'msnm', 'yim', 'aim', 'icq', 'website', 'occ', 'interests', 'os', 'fav_genres', 'fav_artists');
			foreach ($check_length_ary as $name)
			{
				if (strlen($$name) < 3)
				{
					$$name = '';
				}
			}
			
			if ($icq && !preg_match('/[0-9]+$/', $icq))
			{
				$icq = '';
			}
			
			if (!empty($website))
			{
				if (!preg_match('#^http[s]?:\/\/#i', $website))
				{
					$website = 'http://' . $website;
				}
		
				if (!preg_match('#^http[s]?\\:\\/\\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+#i', $website))
				{
					$website = '';
				}
			}

			if (!empty($color))
			{
				$color = substr($color, 1);
				if ($color !== $user->data['user_color'])
				{
					$color = strtoupper($color);
					if (strlen($color) != 6)
					{
						$error[] = 'USERPAGE_ERROR';
					}
					
					if (!sizeof($error))
					{
						$valid_hex = explode(',', '0,1,2,3,4,5,6,7,8,9,A,B,C,D,E,F');
						for ($i = 0, $end = strlen($color); $i < $end; $i++)
						{
							if (!in_array($color[$i], $valid_hex))
							{
								$error[] = 'USERPAGE_ERROR';
							}
						}
					}
				}
			}
			
			if (!empty($rank))
			{
				$rank_word = explode(' ', $rank);
				if (sizeof($rank_word) > 3)
				{
					$error[] = 'RANK_TOO_LONG';
				}
				
				if (!sizeof($error))
				{
					$rank_limit = 15;
					foreach ($rank_word as $each)
					{
						if (preg_match_all('#\&.*?\;#is', $each, $each_preg))
						{
							foreach ($each_preg[0] as $each_preg_each)
							{
								$rank_limit += (strlen($each_preg_each) - 1);
							}
						}
						
						if (strlen($each) > $rank_limit)
						{
							$error[] = 'RANK_TOO_LONG';
							break;
						}
					}
				}
			}
			
			// Rank
			if (!empty($rank) && !sizeof($error))
			{
				$sql = "SELECT *
					FROM _ranks
					WHERE rank_title = '" . $db->sql_escape($rank) . "'";
				$result_rank = $db->sql_query($sql);
				
				$rank_id = 0;
				if ($row_rank = $db->sql_fetchrow($result_rank))
				{
					$rank_id = $row_rank['rank_id'];
				}
				$db->sql_freeresult($result_rank);
				
				if (!$rank_id)
				{
					$insert = array(
						'rank_title' => $rank,
						'rank_min' => -1,
						'rank_max' => -1,
						'rank_special' => 1
					);
					$sql = 'INSERT INTO _ranks' . $db->sql_build_array('INSERT', $insert);
					$db->sql_query($sql);
					
					$rank_id = $db->sql_nextid();
				}
				
				$old_rank = $userdata['user_rank'];
				if ($old_rank)
				{
					$sql = 'SELECT user_id
						FROM _members
						WHERE user_rank = ' . (int) $old_rank;
					$result_by = $db->sql_query($sql);
					
					$by = array();
					while ($row_by = $db->sql_fetchrow($result_by))
					{
						$by[] = $row_by['user_id'];
					}
					$db->sql_freeresult($result_by);
					
					if (sizeof($by) == 1)
					{
						$sql = 'DELETE FROM _ranks
							WHERE rank_id = ' . (int) $old_rank;
						$db->sql_query($sql);
					}
				}
				
				$rank = $rank_id;
				
				$cache->delete('ranks');
			}
			
			//if (!$user->data['user_birthday'])
			{
				if (!$birthday_month || !$birthday_day || !$birthday_year)
				{
					$error[] = 'EMPTY_BIRTH_MONTH';
				}
			}
			
			if (!sizeof($error))
			{
				require('./interfase/functions_avatar.php');
				
				if ($xavatar->process())
				{
					$avatar = $xavatar->file();
				}
			}
			
			if (!sizeof($error))
			{
				require('./interfase/comments.php');
				$comments = new _comments();
				
				if ($sig != '')
				{
					$sig = $comments->prepare($sig);
				}
				
				//if (!$user->data['user_birthday'])
				{
					unset($user_fields['birthday_day'], $user_fields['birthday_month'], $user_fields['birthday_year']);
				}
				
				$dateformat = $dateset[$dateformat];
				$user_fields['hideuser'] = $user->data['user_hideuser'];
				$user_fields['email_dc'] = $user->data['user_email_dc'];
				
				$member_data = array();
				foreach ($user_fields as $name => $value)
				{
					if ($value != $$name)
					{
						$member_data['user_' . $name] = $$name;
					}
				}
				
				//if (!$user->data['user_gender'])
				{
					$member_data['user_gender'] = $gender;
				}
				
				//if (!$user->data['user_birthday'])
				{
					$member_data['user_birthday'] = (string) (leading_zero($birthday_year) . leading_zero($birthday_month) . leading_zero($birthday_day));
				}
				
				if (sizeof($member_data))
				{
					$db->sql_query('UPDATE _members SET ' . $db->sql_build_array('UPDATE', $member_data) . ' WHERE user_id = ' . (int) $user->data['user_id']);
				}
				
				redirect(s_link('m', $user->data['username_base']));
			}
		}
		//
		// END SUBMIT
		//
		
		//unset($user_fields['gender'], $user_fields['birthday_day'], $user_fields['birthday_month'], $user_fields['birthday_year']);
		
		if (sizeof($error))
		{
			$error = preg_replace('#^([0-9A-Z_]+)$#e', "(isset(\$user->lang['\\1'])) ? \$user->lang['\\1'] : '\\1'", $error);
			
			$template->assign_block_vars('error', array(
				'MESSAGE' => (sizeof($error)) ? implode('<br />', $error) : '')
			);
		}
		
		if ($user->data['user_avatar'])
		{
			$template->assign_block_vars('current_avatar', array(
				'IMAGE' => $config['avatar_path'] . '/' . $user->data['user_avatar'])
			);
		}
		
		//if (!$user->data['user_gender'])
		{
			$s_genders_select = '';
			foreach (array(1 => 'MALE', 2 => 'FEMALE') as $id => $value)
			{
				$s_genders_select .= '<option value="' . $id . '"' . (($gender == $id) ? ' selected="true"' : '') . '>' . $user->lang[$value] . '</option>';
			}

			$template->assign_block_vars('gender', array(
				'GENDER_SELECT' => $s_genders_select)
			);
		}
		
		//if (!$user->data['user_birthday'])
		{
			$s_day_select = '<option value="">&nbsp;</option>';
			for ($i = 1; $i < 32; $i++)
			{
				$s_day_select .= '<option value="' . $i . '"' . (($birthday_day == $i) ? ' selected="true"' : '') . '>' . $i . '</option>';
			}
			
			$s_month_select = '<option value="">&nbsp;</option>';
			$months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
			foreach ($months as $id => $value)
			{
				$s_month_select .= '<option value="' . ($id + 1) . '"' . (($birthday_month == ($id + 1)) ? ' selected="true"' : '') . '>' . $user->lang['datetime'][$value] . '</option>';
			}
			
			$s_year_select = '<option value="">&nbsp;</option>';
			for ($i = 2005; $i > 1899; $i--)
			{
				$s_year_select .= '<option value="' . $i . '"' . (($birthday_year == $i) ? ' selected="true"' : '') . '>' . $i . '</option>';
			}
			
			$template->assign_block_vars('birthday', array(
				'DAY' => $s_day_select,
				'MONTH' => $s_month_select,
				'YEAR' => $s_year_select)
			);
		}
		
		$dateformat_select = '';
		foreach ($dateset as $id => $value)
		{
			$dateformat_select .= '<option value="' . $id . '"' . (($value == $dateformat) ? ' selected="selected"' : '') . '>' . $user->format_date($current_time, $value) . '</option>';
		}
		
		$timezone_select = '';
		foreach ($user->lang['zones'] as $id => $value)
		{
			$timezone_select .= '<option value="' . $id . '"' . (($id == $timezone) ? ' selected="selected"' : '') . '>' . $value . '</option>';
		}
		
		unset($user_fields['timezone'], $user_fields['dateformat']);
		
		$output_vars = array(
			'AVATAR_MAXSIZE' => $config['avatar_filesize'],
			'DATEFORMAT' => $dateformat_select,
			'TIMEZONE' => $timezone_select,
			'HIDEUSER_SELECTED' => ($hideuser) ? ' checked="checked"' : '',
			'EMAIL_DC_SELECTED' => ($email_dc) ? ' checked="checked"' : ''
		);
		
		foreach ($user_fields as $name => $value)
		{
			$output_vars[strtoupper($name)] = $$name;
		}
		
		$template->assign_vars($output_vars);
		
		page_layout('MEMBER_OPTIONS', 'profile_body');
		break;
}

//
// FUNCTIONS
//
function leading_zero ($number)
{
	return (($number < 10) ? '0' : '') . $number;
}

?>
