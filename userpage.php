<?php
// -------------------------------------------------------------
// $Id: userpage.php,v 1.6 2006/02/16 04:58:15 Psychopsia Exp $
//
// STARTED   : Sun Oct 09, 2005
// COPYRIGHT : � 2006 Rock Republik
// -------------------------------------------------------------

define('IN_NUCLEO', true);
require('./interfase/common.php');

$user->init();

if (!$user->data['is_member'])
{
	if ($user->data['is_bot'])
	{
		redirect(s_link());
	}
	do_login();
}

//
//  Get profile username
$viewprofile = request_var('member', '');
$mode = request_var('mode', '');

if (empty($viewprofile))
{
	fatal_error();
}

$viewprofile = phpbb_clean_username($viewprofile);

$sql = "SELECT *
	FROM _members
	WHERE username_base = '" . $db->sql_escape($viewprofile) . "' 
		AND user_type NOT IN (" . USER_INACTIVE . ", " . USER_IGNORE . ")
		AND user_id NOT IN (
			SELECT user_id
			FROM _members_ban
			WHERE banned_user = " . (int) $user->data['user_id'] . "
		)
		AND user_id NOT IN (
			SELECT ban_userid
			FROM _banlist
		)";
$result = $db->sql_query($sql);

if (!$profiledata = $db->sql_fetchrow($result))
{
	fatal_error();
}
$db->sql_freeresult($result);

//
if (empty($mode))
{
	$mode = 'main';
}

$user->setup();

require('./interfase/comments.php');
$comments = new _comments();

$current_time = time();
$epbi = $user->_team_auth('all', $profiledata['user_id']);
$epbi2 = $user->_team_auth('all');

//
// UPDATE LAST PROFILE VIEWERS LIST
// 1 && (0 || 1 && 0 && 1) && 1
if ($user->data['is_member'] && $user->data['user_id'] != $profiledata['user_id'] && !in_array($mode, array('friend', 'ban')))
{
	$is_blocked_member = false;
	if (!$epbi)
	{
		$sql = 'SELECT ban_id
			FROM _members_ban
			WHERE user_id = ' . (int) $user->data['user_id'] . '
				AND banned_user = ' . (int) $profiledata['user_id'];
		$result = $db->sql_query($sql);
		
		$banned_user_lang = 'BLOCKED_MEMBER_ADD';
		if ($banned_row = $db->sql_fetchrow($result))
		{
			$is_blocked_member = true;
			$banned_user_lang = 'BLOCKED_MEMBER_REMOVE';
		}
		$db->sql_freeresult($result);
		
		$template->assign_block_vars('block_member', array(
			'URL' => s_link('m', array($profiledata['username_base'], 'ban')),
			'LANG' => $user->lang[$banned_user_lang])
		);
	}
	
	$update_viewer_log = true;
	if ($is_blocked_member || ($user->data['user_hideuser'] && !$epbi) || ($user->_team_auth('founder') && $user->data['user_hideuser']))
	{
		$update_viewer_log = false;
	}
	
	if ($update_viewer_log)
	{
		$sql = 'UPDATE _members_viewers
			SET datetime = ' . $current_time . ", user_ip = '" . $db->sql_escape($user->ip) . "'
			WHERE user_id = " . $profiledata['user_id'] . ' 
				AND viewer_id = ' . $user->data['user_id'];
		$db->sql_query($sql);
		
		if (!$db->sql_affectedrows())
		{
			$sql = 'INSERT INTO _members_viewers (user_id, viewer_id, user_ip, datetime)
				VALUES (' . (int) $profiledata['user_id'] . ', ' . (int) $user->data['user_id'] . ", '" . $db->sql_escape($user->ip) . "', " . (int) $current_time . ')';
			$db->sql_query($sql);
			
			$sql = 'SELECT viewer_id
				FROM _members_viewers
				WHERE user_id = ' . (int) $profiledata['user_id'] . '
				ORDER BY datetime DESC
				LIMIT 9, 1';
			$result = $db->sql_query($sql);
			
			if ($row = $db->sql_fetchrow($result))
			{
				$sql = 'DELETE FROM _members_viewers
					WHERE user_id = ' . (int) $profiledata['user_id'] . '
						AND viewer_id = ' . (int) $row['viewer_id'];
				$db->sql_query($sql);
			}
			$db->sql_freeresult($result);
		}
	}
}

$memberdays = max(1, round(($current_time - $profiledata['user_regdate']) / 86400));

//
// Get extra information for this member
//
$profile_fields = $comments->user_profile($profiledata);

switch ($mode)
{
	case 'friend':
		if (!$user->data['is_member'])
		{
			if ($user->data['is_bot'])
			{
				redirect(s_link());
			}
			do_login();
		}
		
		if ($user->data['user_id'] == $profiledata['user_id'])
		{
			redirect(s_link('m', $profiledata['username_base']));
		}
		
		$sql = 'SELECT *
			FROM _members_friends
			WHERE user_id = ' . (int) $user->data['user_id'] . '
				AND buddy_id = ' . (int) $profiledata['user_id'];
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$db->sql_freeresult($result);
			
			$sql = 'DELETE FROM _members_friends
				WHERE user_id = ' . (int) $user->data['user_id'] . '
					AND buddy_id = ' . (int) $profiledata['user_id'];
			$db->sql_query($sql);
			
			if ($row['friend_time'])
			{
				//$user->points_remove(1);
			}
			
			$user->delete_unread($profiledata['user_id'], $user->data['user_id']);
			
			redirect(s_link('m', $profiledata['username_base']));
		}
		//
		
		$sql = 'INSERT INTO _members_friends (user_id, buddy_id, friend_time) 
			VALUES (' . (int) $user->data['user_id'] . ', ' . (int) $profiledata['user_id'] . ', ' . time() . ')';
		$db->sql_query($sql);
		
		//$user->points_add(1);
		$user->save_unread(UH_FRIEND, $user->data['user_id'], 0, $profiledata['user_id']);
		
		redirect(s_link('m', array($user->data['username_base'], 'friends')));
		break;
	case 'ban':
		if (!$user->data['is_member'])
		{
			if ($user->data['is_bot'])
			{
				redirect(s_link());
			}
			do_login();
		}
		
		if ($user->data['user_id'] == $profiledata['user_id'])
		{
			redirect(s_link('m', $profiledata['username_base']));
		}
		
		if ($epbi)
		{
			fatal_error();
		}
		
		$sql = 'SELECT ban_id
			FROM _members_ban
			WHERE user_id = ' . (int) $user->data['user_id'] . '
				AND banned_user = ' . (int) $profiledata['user_id'];
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$sql = 'DELETE FROM _members_ban
				WHERE ban_id = ' . (int) $row['ban_id'];
			$db->sql_query($sql);
			
			redirect(s_link('m', $profiledata['username_base']));
		}
		$db->sql_freeresult($result);
		
		$sql = 'INSERT INTO _members_ban (user_id, banned_user, ban_time)
			VALUES (' . $user->data['user_id'] . ', ' . $profiledata['user_id'] . ', ' . (int) $user->time . ')';
		$db->sql_query($sql);
		
		$sql = 'DELETE FROM _members_friends
			WHERE user_id = ' . (int) $user->data['user_id'] . '
				AND buddy_id = ' . (int) $profiledata['user_id'];
		$db->sql_query($sql);
		
		$sql = 'DELETE FROM _members_friends
			WHERE user_id = ' . (int) $profiledata['user_id'] . '
				AND buddy_id = ' . (int) $user->data['user_id'];
		$db->sql_query($sql);
		
		$sql = 'DELETE FROM _members_viewers
			WHERE user_id = ' . (int) $profiledata['user_id'] . '
				AND viewer_id = ' . (int) $user->data['user_id'];
		$db->sql_query($sql);
		
		redirect(s_link('m', $profiledata['username_base']));
		break;
	case 'favs':
		break;
	
	//
	// Default page
	//
	case 'main':
	default:
		$template->assign_block_vars('main', array());
		//
		// Main data
		//
		
		//
		// Get artists where this member is a moderator
		//
		$sql = 'SELECT au.user_id, a.ub, a.name, a.subdomain, a.images, a.local, a.location, a.genre
			FROM _artists_auth au, _artists a
			WHERE au.user_id = ' . (int) $profiledata['user_id'] . '
				AND au.ub = a.ub
			ORDER BY a.name';
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$selected_artists = array();
			do
			{
				$selected_artists[$row['ub']] = $row;
			}
			while ($row = $db->sql_fetchrow($result));
			$db->sql_freeresult($result);
			
			$sql = 'SELECT ub, image
				FROM _artists_images
				WHERE ub IN (' . implode(',', array_keys($selected_artists)) . ')
				ORDER BY RAND()';
			$result = $db->sql_query($sql);
			
			$random_images = array();
			while ($row = $db->sql_fetchrow($result))
			{
				if (!isset($random_images[$row['ub']]))
				{
					$random_images[$row['ub']] = $row['image'];
				}
			}
			$db->sql_freeresult($result);
			
			a_thumbnails($selected_artists, $random_images, 'USERPAGE_MOD', 'thumbnails');
		}
		
		//
		// GET MEMBER FAV ARTISTS
		//
		$sql = 'SELECT f.user_id, a.ub, a.name, a.subdomain, a.images, a.local, a.location, a.genre
			FROM _artists_fav f, _artists a
			WHERE f.user_id = ' . (int) $profiledata['user_id'] . '
				AND f.ub = a.ub
			ORDER BY RAND()';
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$total_a = 0;
			$selected_artists = array();
			do
			{
				if ($total_a < 6)
				{
					$selected_artists[$row['ub']] = $row;
				}
				$total_a++;
			}
			while ($row = $db->sql_fetchrow($result));
			$db->sql_freeresult($result);
			
			$sql = 'SELECT ub, image
				FROM _artists_images
				WHERE ub IN (' . implode(',', array_keys($selected_artists)) . ')
				ORDER BY RAND()';
			$result = $db->sql_query($sql);
			
			$random_images = array();
			while ($row = $db->sql_fetchrow($result))
			{
				if (!isset($random_images[$row['ub']]))
				{
					$random_images[$row['ub']] = $row['image'];
				}
			}
			$db->sql_freeresult($result);
			
			a_thumbnails($selected_artists, $random_images, 'USERPAGE_AFAVS', 'thumbnails');
			
			if ($total_a > 6)
			{
				$template->assign_block_vars('main.thumbnails.all', array());
			}
		}
		
		// Latest board posts
		$sql = "SELECT DISTINCT(t.topic_title), p.post_id, p.post_time
			FROM _forum_topics t, _forum_posts p
			WHERE p.poster_id = " . (int) $profiledata['user_id'] . "
				AND p.forum_id NOT IN (14,15,16,17,20,22,38)
				AND t.topic_id = p.topic_id
			GROUP BY p.topic_id
			ORDER BY p.post_time DESC
			LIMIT 10";
		$result = $db->sql_query($sql);
		
		$i = 0;
		while ($row = $db->sql_fetchrow($result))
		{
			if (!$i)
			{
				$template->assign_block_vars('main.lastboard', array());
				$i++;
			}
			
			$template->assign_block_vars('main.lastboard.row', array(
				'URL' => s_link('post', $row['post_id']) . '#' . $row['post_id'],
				'TITLE' => $row['topic_title'],
				'TIME' => $user->format_date($row['post_time'], 'd M @ H:i'))
			);
		}
		$db->sql_freeresult($result);
		
		//
		// GET USERPAGE MESSAGES
		//
		$comments_ref = s_link('m', array($profiledata['username_base'], 'messages'));
		
		if ($user->data['is_member'])
		{
			$template->assign_block_vars('main.post_comment_box', array(
				'REF' => $comments_ref)
			);
		}
		else
		{
			$template->assign_block_vars('main.post_comment_members', array());
		}
		
		//
		// User age & birthday
		//
		$birthday = '';
		$age = 0;
		if ($profiledata['user_birthday'])
		{
			$bd_month = gmmktime(0, 0, 0, substr($profiledata['user_birthday'], 4, 2) + 1, 0, 0);
			$birthday = substr($profiledata['user_birthday'], 6, 2) . ' ' . $user->format_date($bd_month, 'F') . ' ' . substr($profiledata['user_birthday'], 0, 4);
			
			$age = date('Y', $current_time) - intval(substr($profiledata['user_birthday'], 0, 4));
			if ( intval(substr($profiledata['user_birthday'], 4, 4)) > date('md', $current_time) )
			{
				$age--;
			}
			$age .= ' ' . $user->lang['YEARS'];
		}
		
		switch ($profiledata['user_gender'])
		{
			case 0:
				$gender = 'NO_GENDER';
				break;
			case 1:
				$gender = 'MALE';
				break;
			case 2:
				$gender = 'FEMALE';
				break;
		}
		
		$gender = $user->lang[$gender];
		
		$user_fields = array(
			//'JOINED' => ($profiledata['user_regdate'] && (!$profiledata['user_hideuser'] || $epbi2)) ? $user->format_date($profiledata['user_regdate']) . sprintf($user->lang['JOINED_SINCE'], $memberdays) : '',
			'LAST_LOGON' => ($profiledata['user_lastvisit'] && (!$profiledata['user_hideuser'] || $epbi2)) ? $user->format_date($profiledata['user_lastvisit']) : '',
			'GENDER' => $gender,
			'AGE' => $age,
			'BIRTHDAY' => $birthday,
			'FAV_GENRES' => $profiledata['user_fav_genres'],
			'FAV_BANDS' => $profiledata['user_fav_artists'],
			'LOCATION' => $profiledata['user_location'],
			'OCCUPATION' => $profiledata['user_occ'],
			'INTERESTS' => $profiledata['user_interests'],
			'MEMBER_OS' => $profiledata['user_os']
		);
		
		$m = 0;
		foreach ($user_fields as $key => $value)
		{
			if ($value == '')
			{
				continue;
			}
			
			if (!$m)
			{
				$template->assign_block_vars('main.general', array());
				$m = 1;
			}
			
			$template->assign_block_vars('main.general.item', array(
				'KEY' => $user->lang[$key],
				'VALUE' => $value)
			);
		}
		
		//
		// GET LAST.FM FEED
		//
		// http://ws.audioscrobbler.com/1.0/user//recenttracks.xml
		if (!empty($profiledata['user_lastfm']))
		{
			include_once('./interfase/scrobbler.php');
			
			$scrobbler = new EasyScrobbler($profiledata['user_lastfm']);
			$list = $scrobbler->getRecentTracs();
			
			if (sizeof($list))
			{
				$template->assign_block_vars('main.lastfm', array(
					'NAME' => $profiledata['user_lastfm'],
					'URL' => 'http://www.last.fm/user/' . $profiledata['user_lastfm'] . '/')
				);
				
				foreach ($list as $row)
				{
					$template->assign_block_vars('main.lastfm.row', array(
						'ARTIST' => $row['ARTIST'],
						'NAME' => $row['NAME'],
						'ALBUM' => $row['ALBUM'],
						'URL' => $row['URL'],
						'TIME' => $user->format_date($row['DATE_UTS'], 'H:i'))
					);
				}
			}
		}
		
		//
		// GET LAST USERPAGE VIEWERS
		//
		$sql = 'SELECT v.datetime, u.user_id, u.username, u.username_base, u.user_color, u.user_avatar
			FROM _members_viewers v, _members u
			WHERE v.user_id = ' . (int) $profiledata['user_id'] . '
				AND v.viewer_id = u.user_id
			ORDER BY datetime DESC';
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('main.viewers', array());
			
			$col = 0;
			do
			{
				$profile = $comments->user_profile($row);
				
				if (!$col)
				{
					$template->assign_block_vars('main.viewers.row', array());
				}
				
				$template->assign_block_vars('main.viewers.row.col', array(
					'PROFILE' => $profile['profile'],
					'USERNAME' => $profile['username'],
					'COLOR' => $profile['user_color'],
					'AVATAR' => $profile['user_avatar'],
					'DATETIME' => $user->format_date($row['datetime']))
				);
				
				$col = ($col == 2) ? 0 : $col + 1;
			}
			while ($row = $db->sql_fetchrow($result));
			
			$db->sql_freeresult($result);
		}
		break;
	case 'friends':
	case 'friendof':
		//
		// GET USER FRIENDS
		//
		if ($mode == 'friends')
		{
			$sql = 'SELECT b.buddy_id AS user_id, u.username, u.username_base, u.user_color, u.user_avatar, u.user_rank, u.user_gender, u.user_posts
				FROM _members_friends b, _members u
				WHERE b.user_id = ' . (int) $profiledata['user_id'] . '
					AND b.buddy_id = u.user_id
				ORDER BY u.username';
		}
		else
		{
			$sql = 'SELECT u.user_id, u.username, u.username_base, u.user_color, u.user_avatar, u.user_rank, u.user_gender, u.user_posts
				FROM _members_friends b, _members u
				WHERE b.buddy_id = ' . (int) $profiledata['user_id'] . '
					AND b.user_id = u.user_id
				ORDER BY u.username';
		}
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('friends', array());
			
			$tcol = 0;
			do
			{
				$friend_profile = $comments->user_profile($row);
				
				if (!$tcol)
				{
					$template->assign_block_vars('friends.row', array());
				}
				
				$template->assign_block_vars('friends.row.col', array(
					'PROFILE' => $friend_profile['profile'],
					'USERNAME' => $friend_profile['username'],
					'COLOR' => $friend_profile['user_color'],
					'AVATAR' => $friend_profile['user_avatar'],
					'RANK' => $friend_profile['user_rank'])
				);
				
				$tcol = ($tcol == 3) ? 0 : $tcol + 1;
			}
			while ($row = $db->sql_fetchrow($result));
			
			$db->sql_freeresult($result);
		}
		break;
	case 'messages':
		//
		// GET USERPAGE MESSAGES
		//
		$template->assign_block_vars('messages', array());
		
		$comments_ref = s_link('m', array($profiledata['username_base'], 'messages'));
		if ($profiledata['userpage_posts'])
		{
			$comments->reset();
			$comments->ref = $comments_ref;
			
			$comments->data = array(
				'A_LINKS_CLASS' => 'bold red',
				'USER_ID_FIELD' => 'userpage_id',
				'S_DELETE_URL' => s_link('mcp', array('ucm', '%d')),
				
				'SQL' => 'SELECT p.*, u2.user_id, u2.username, u2.username_base, u2.user_color, u2.user_avatar, u2.user_sig, u2.user_rank, u2.user_gender, u2.user_posts
					FROM _members_posts p, _members u, _members u2
					WHERE p.userpage_id = ' . $profiledata['user_id'] . '
						AND p.userpage_id = u.user_id 
						AND p.post_active = 1 
						AND p.poster_id = u2.user_id 
					ORDER BY p.post_time DESC 
					LIMIT 50'
			);
			
			$comments->view(0, '', $profiledata['userpage_posts'], $profiledata['userpage_posts'], 'messages.posts');
		}
		
		if ($user->data['is_member'])
		{
			$template->assign_block_vars('messages.box', array(
				'REF' => $comments_ref)
			);
		}
		else
		{
			$template->assign_block_vars('messages.members', array());
		}
		break;
	case 'stats':
		$user_stats = array(
			'VISITS_COUNT' => $profiledata['user_totallogon'],
			'PAGEVIEWS_COUNT' => $profiledata['user_totalpages'],
			'FORUM_POSTS' => $profiledata['user_posts'],
//			'ARTISTS_POSTS' => $a_posts,
//			'DOWNLOADS_POSTS' => $d_posts
		);
		
		$m = FALSE;
		foreach ($user_stats as $key => $value)
		{
			if ($value == '')
			{
				continue;
			}
			
			if (!$m)
			{
				$template->assign_block_vars('main.stats', array());
				$m = TRUE;
			}
			
			$template->assign_block_vars('main.stats.item', array(
				'KEY' => $user->lang[$key],
				'VALUE' => $value)
			);
		}
		break;
}

$panel_selection = array(
	'main' => array('L' => 'MAIN', 'U' => FALSE)
);

//if ($user->data['is_member'] && $user->data['user_id'] === $profiledata['user_id'])
{
	$panel_selection['dc'] = array('L' => 'DC', 'U' => s_link('my', 'dc'));
}

$panel_selection += array(
	/*'favs' => array('L' => 'FAVS', 'U' => FALSE),*/
	'friends' => array('L' => 'FRIENDS', 'U' => FALSE),
	'friendof' => array('L' => 'FRIENDOF', 'U' => FALSE),
	'messages' => array('L' => 'POSTS', 'U' => FALSE)/*,
	'stats' => array('L' => 'STATS', 'U' => FALSE)*/
);

foreach ($panel_selection as $link => $data)
{
	$template->assign_block_vars('selected_panel', array(
		'LANG' => $user->lang['USERPAGE_' . $data['L']])
	);
	
	if ($mode == $link)
	{
		$template->assign_block_vars('selected_panel.strong', array());
		continue;
	}
	
	$template->assign_block_vars('selected_panel.a', array(
		'URL' => ($data['U'] !== FALSE) ? $data['U'] : s_link('m', array($profiledata['username_base'], (($link != 'main') ? $link : ''))))
	);
}
/*

$panel_selection = array('main' => 'MAIN', 'favs' => 'FAVS', 'friends' => 'FRIENDS', 'friendof' => 'FRIENDOF', 'messages' => 'POSTS','stats' => 'STATS');
foreach ($panel_selection as $link => $lang_key)
{
	$template->assign_block_vars('selected_panel', array(
		'LANG' => $user->lang['USERPAGE_' . $lang_key])
	);
	
	if ($mode == $link)
	{
		$template->assign_block_vars('selected_panel.strong', array());
		continue;
	}
	
	$template->assign_block_vars('selected_panel.a', array(
		'URL' => s_link('m', array($profiledata['username_base'], $link)))
	);
}*/

$sql = 'SELECT MAX(session_time) AS session_time
	FROM _sessions
	WHERE session_user_id = ' . (int) $profiledata['user_id'];
$result = $db->sql_query($sql);

$row = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

$profiledata['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
unset($row);

//
// Calculate the number of days this user has been a member ($memberdays)
//
$email = $profiledata['user_public_email'];

$aim = ($profiledata['user_aim']) ? 'aim:goim?screenname=' . $profiledata['user_aim'] : '';
$yim = ($profiledata['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . $profiledata['user_yim'] . '&amp;.src=pg' : '';
$icq = '';

if ((!$profiledata['user_hideuser'] || ($profiledata['user_hideuser'] && $epbi2)) && $profiledata['session_time'] >= ($current_time - 300))
{
	$online = $user->lang['ONLINE'];
}
else
{
	$online = $user->lang['OFFLINE'];
}

//
// Check buddy
//
if ($user->data['user_id'] != $profiledata['user_id'])
{
	$show_addbuddy_lang = TRUE;
	if ($user->data['is_member'])
	{
		$sql = 'SELECT *
			FROM _members_friends
			WHERE user_id = ' . (int) $user->data['user_id'] . '
				AND buddy_id = ' . (int) $profiledata['user_id'];
		$result = $db->sql_query($sql);
		
		if ($row = $db->sql_fetchrow($result))
		{
			$show_addbuddy_lang = FALSE;
		}
		$db->sql_freeresult($result);
	}
	
	$addbuddy_lang = ($show_addbuddy_lang) ? 'FRIENDS_ADD' : 'FRIENDS_DEL';
	
	$template->assign_block_vars('friend', array(
		'U_FRIEND' => s_link('m', array($profiledata['username_base'], 'friend')),
		'L_FRIENDS_ADD' => $user->lang[$addbuddy_lang])
	);
}

$template->assign_block_vars('customcolor', array(
	'COLOR' => $profiledata['user_color'])
);

//
// Generate page
//
$template_vars = array(
	'USERNAME' => $profiledata['username'],
	'USERNAME_COLOR' => $profiledata['user_color'],
	'POSTER_RANK' => $profile_fields['user_rank'],
	'AVATAR_IMG' => $profile_fields['user_avatar'],
	'USER_ONLINE' => $online,
	
	'PM' => s_link('my', array('dc', 'start', $profiledata['username_base'])),
	'EMAIL' => $email,
	'WEBSITE' => $profiledata['user_website'],
	'ICQ' => $icq, 
	'AIM' => $aim,
	'MSN' => $profiledata['user_msnm'],
	'YIM' => $yim
);

$use_template = 'userpage_body';
$use_m_template = 'profiles/' . $profiledata['username_base'];
if (@file_exists(ROOT . 'template/' . $use_m_template . '.htm'))
{
	$use_template = $use_m_template;
}

page_layout($profiledata['username'], $use_template, $template_vars);

//
// FUNCTIONS
//
function a_thumbnails($selected_artists, $random_images, $lang_key, $block, $item_per_col = 2)
{
	global $user, $template;
	
	$template->assign_block_vars('main.' . $block, array(
		'L_TITLE' => $user->lang[$lang_key])
	);
	
	$col = 0;
	foreach ($selected_artists as $ub => $data)
	{
		$image = ($data['images']) ? $ub . '/thumbnails/' . $random_images[$ub] . '.jpg' : 'default/shadow.gif';
		
		if (!$col)
		{
			$template->assign_block_vars('main.' . $block . '.row', array());
		}
		
		$template->assign_block_vars('main.' . $block . '.row.col', array(
			'NAME' => $data['name'],
			'IMAGE' => SDATA . 'artists/' . $image,
			'URL' => s_link('a', $data['subdomain']),
			'LOCATION' => ($data['local']) ? 'Guatemala' : $data['location'],
			'GENRE' => $data['genre'])
		);
		
		$col = ($col == ($item_per_col - 1)) ? 0 : $col + 1;
	}
}

?>