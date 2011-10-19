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
if (!defined('IN_NUCLEO')) exit;

class __activate extends mac {
	public function __construct() {
		parent::__construct();
		
		$this->auth('founder');
	}
	
	public function home() {
		global $user;
		
		if ($this->submit)
		{
			$name = request_var('name', '');
			
			$sql = 'SELECT *
				FROM _artists
				WHERE name = ?';
			if (!$a_data = sql_fieldrow(sql_filter($sql, $name))) {
				fatal_error();
			}
			
			$emails = array();
			if (!empty($a_data['email'])) {
				$emails[] = $a_data['email'];
			}
			
			$sql = 'SELECT m.user_id, m.user_email
				FROM _artists_auth a, _members m
				WHERE a.ub = ?
					AND a.user_id = m.user_id';
			$result = sql_rowset(sql_filter($sql, $a_data['ub']));
			
			$mods = array();
			foreach ($result as $row) {
				$emails[] = $row['user_email'];
				$mods[] = $row['user_id'];
			}
			
			if (count($mods))
			{
				foreach ($mods as $i => $each)
				{
					$sql = 'SELECT COUNT(user_id) AS total
						FROM _artists_auth
						WHERE user_id = ?';
					$total = sql_field(sql_filter($sql, $each), 'total', 0);
					
					if ($total > 1) {
						unset($mods[$i]);
					}
				}
			}
			
			if (count($mods))
			{
				$sql = 'UPDATE _members SET user_auth_control = 0
					WHERE user_id IN (??)';
				$d_sql[] = sql_filter($sql, implode(',', $mods));
			}
			
			$d_sql = array();
			
			$ary_sql = array(
				'DELETE FROM _artists_auth WHERE ub = ?',
				'DELETE FROM _artists_fav WHERE ub = ?',
				'DELETE FROM _artists_images WHERE ub = ?',
				'DELETE FROM _artists_log WHERE ub = ?',
				'DELETE FROM _artists_lyrics WHERE ub = ?',
				'DELETE FROM _artists_posts WHERE post_ub = ?',
				'DELETE FROM _artists_stats WHERE ub = ?',
				'DELETE FROM _artists_viewers WHERE ub = ?',
				'DELETE FROM _artists_voters WHERE ub = ?',
				'DELETE FROM _artists_votes WHERE ub = ?',
				'DELETE FROM _forum_topics WHERE topic_ub = ?',
				'DELETE FROM _dl WHERE ub = ?'
			);
			
			foreach ($ary_sql as $row) {
				$d_sql[] = sql_filter($sql, $a_data['ub']);
			}
			
			$sql = 'SELECT topic_id
				FROM _forum_topics
				WHERE topic_ub = ?';
			$topics = sql_rowset(sql_filter($sql, $a_data['ub']), false, 'topic_id');
			
			if (count($topics))
			{
				$d_sql[] = sql_filter('DELETE FROM _forum_posts
					WHERE topic_id IN (??)', implode(',', $topics));
			}
			
			$sql = 'SELECT id
				FROM _dl
				WHERE ub = ?';
			if ($downloads = sql_rowset(sql_filter($sql, $a_data['ub']), false, 'id')) {
				$s_downloads = implode(',', $downloads);
				
				$ary_sql = array(
					'DELETE FROM _dl_fav WHERE dl_id IN (??)',
					'DELETE FROM _dl_posts WHERE download_id IN (??)',
					'DELETE FROM _dl_vote WHERE ud IN (??)',
					'DELETE FROM _dl_voters WHERE ud IN (??)'
				);
				
				foreach ($ary_sql as $row) {
					$d_sql[] = sql_filter($row, $s_downloads);
				}
			}
			
			$d_sql[] = sql_filter('DELETE FROM _artists
				WHERE ub = ?', $a_data['ub']);
			
			if (!s_dir('../data/artists/' . $a_data['ub']))
			{
				echo 'error en carpetas';
				return;
			}
			
			sql_query($d_sql);
			
			//
			// Send email
			//
			if (count($emails))
			{
				require('./interfase/emailer.php');
				$emailer = new emailer();
				
				//
				$a_emails = array_unique($emails);
				
				$emailer->from('info@rockrepublik.net');
				$emailer->use_template('artist_deleted');
				$emailer->email_address($a_emails[0]);
				$emailer->bcc('info@rockrepublik.net');
				
				$cc_emails = array_splice($a_emails, 1);
				foreach ($cc_emails as $each_email)
				{
					$emailer->cc($each_email);
				}
				
				$emailer->assign_vars(array(
					'ARTIST' => $a_data['name'])
				);
				$emailer->send();
				$emailer->reset();
			}
			
			// Cache
			$cache->delete('ub_list', 'a_last_images');
			
			echo 'La banda ha sido eliminada y notificada.';
			
			echo '<pre>';
			print_r($a_emails);
			echo '</pre>';
			
			die();
		}
	}
	
	function s_dir($path) {
		if (!@file_exists($path)) {
			echo 'No folder ' . $path;
			return false;
		}
		
		$fp = @opendir($path);
		while ($file = @readdir($fp)) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			
			$current_full_path = $path . '/' . $file;
			
			if (is_dir($current_full_path)) {
				$this->s_dir($current_full_path);
				continue;
			}
			
			if (!unlink($current_full_path)) {
				return false;
			}
		}
		@closedir($fp);
		
		if (!rmdir($path)) {
			return false;
		}
		
		return true;
	}
}

?>

<form action="<?php echo $u; ?>" method="post">
<input type="text" name="name" value="" />
<input type="submit" name="submit" value="Eliminar artista" />
</form>