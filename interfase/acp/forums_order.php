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

_auth('founder');

//
// Do the job ...
//
if ($submit)
{
	$list = request_var('listContainer', array(0));
	
	$orderid = 10;
	foreach ($list as $catid) {
		$sql = 'UPDATE _forums SET forum_order = ?
			WHERE forum_id = ?';
		sql_query(sql_filter($sql, $orderid, $catid));
		
		$orderid += 10;
	}
	
	_die('Update.');
}

?><!DOCTYPE HTML>
<html>
<head>
<title>Forum order</title>
<link rel="stylesheet" type="text/css" href="style.css">
<script src="/net/scripts/prototype.js"></script>
<script src="/net/scripts/scriptaculous.js"></script>

<script>
Event.observe(window, 'load', init, false);

function init() {
	Sortable.create('listContainer',{tag:'div',onUpdate:updateList});
}

function updateList(container) {
	var url = '_acp.forumorder.php';
	var params = Sortable.serialize(container.id);
	var ajax = new Ajax.Request(url,{
		method: 'post',
		parameters: 'submit=1&' + params,
		onLoading: function(){$('workingMsg').show()},
		onLoaded: function(){$('workingMsg').hide()}
	});
}
</script>
</head>

<body>
<div id="listContainer">
	<?php
	
	$sql = 'SELECT forum_id, forum_name
		FROM _forums
		ORDER BY forum_order ASC';
	$result = sql_rowset($sql);
	
	foreach ($result as $row) {
		echo '<div id="item_' . $row['forum_id'] . '">' . $row['forum_name'] . '</div>';
	}
	?>
</div>

<div id="workingMsg" style="display:none;">Actualizando...</div>
</body>
</html>