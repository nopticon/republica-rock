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
if (!defined('IN_APP')) exit;

class __artist_gallery extends mac {
	public function __construct() {
		parent::__construct();
		
		$this->auth('artist');
	}
	
	public function _home() {
		global $config, $user, $cache;
		
		$this->_artist();
		
		if (_button()) {
			return $this->upload();
		}
		
		if (_button('remove')) {
			return $this->remove();
		}
		
		$sql = 'SELECT g.*
			FROM _artists a, _artists_images g
			WHERE a.ub = ?
				AND a.ub = g.ub
			ORDER BY image ASC';
		$result = sql_rowset(sql_filter($sql, $this->object['ub']));
		
		foreach ($result as $i => $row) {
			if (!$i) _style('gallery');
			
			_style('gallery.row', array(
				'ITEM' => $row['image'],
				'URL' => s_link('a', array($this->object['subdomain'], 4, $row['image'], 'view')),
				'U_FOOTER' => s_link_control('a', array('a' => $this->object['subdomain'], 'mode' => $this->mode, 'manage' => 'footer', 'image' => $row['image'])),
				'IMAGE' => SDATA . 'artists/' . $this->object['ub'] . '/thumbnails/' . $row['image'] . '.jpg',
				'RIMAGE' => get_a_imagepath(SDATA . 'artists/' . $this->object['ub'], $row['image'] . '.jpg', array('x1', 'gallery')),
				'WIDTH' => $row['width'],
				'HEIGHT' => $row['height'],
				'TFOOTER' => $row['image_footer'],
				'VIEWS' => $row['views'],
				'DOWNLOADS' => $row['downloads'])
			);
		}

		return;
	}
	
	private function upload() {
		return;
	}
	
	private function remove() {
		return;
	}
}

?>