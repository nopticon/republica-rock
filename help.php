<?php
// -------------------------------------------------------------
// $Id: help.php,v 1.4 2006/03/22 23:59:31 Psychopsia Exp $
//
// STARTED   : Mon Oct 31, 2005
// COPYRIGHT : � 2006 Rock Republik
// -------------------------------------------------------------

define('IN_NUCLEO', true);
require('./interfase/common.php');

$user->init();

$help_modules = array();
$help_cat = array();
$help_faq = array();

if (!$help_modules = $cache->get('help_modules'))
{
	$sql = 'SELECT *
		FROM _help_modules
		ORDER BY module_name';
	$result = $db->sql_query($sql);
	
	if ($row = $db->sql_fetchrow($result))
	{
		do
		{
			$help_modules[$row['module_name']] = $row['module_id'];
		}
		while ($row = $db->sql_fetchrow($result));
		$db->sql_freeresult($result);
		
		$cache->save('help_modules', $help_modules);
	}
}

if (!$help_cat = $cache->get('help_cat'))
{
	$sql = 'SELECT *
		FROM _help_cat
		ORDER BY help_order';
	$result = $db->sql_query($sql);
	
	if ($row = $db->sql_fetchrow($result))
	{
		do
		{
			$help_cat[$row['help_id']] = $row;
		}
		while ($row = $db->sql_fetchrow($result));
		$db->sql_freeresult($result);
		
		$cache->save('help_cat', $help_cat);
	}
}

if (!$help_faq = $cache->get('help_faq'))
{
	$sql = 'SELECT *
		FROM _help_faq
		ORDER BY faq_question_es';
	$result = $db->sql_query($sql);
	
	if ($row = $db->sql_fetchrow($result))
	{
		do
		{
			$help_faq[$row['faq_id']] = $row;
		}
		while ($row = $db->sql_fetchrow($result));
		$db->sql_freeresult($result);
		
		$cache->save('help_faq', $help_faq);
	}
}

if (!sizeof($help_modules) || !sizeof($help_cat) || !sizeof($help_faq))
{
	fatal_error();
}

$module = request_var('module', '');
$help = request_var('help', 0);

if ($module != '')
{
	$module_id = (int) $help_modules[$module];
	
	if (!$module_id)
	{
		fatal_error();
	}
}

if ($help)
{
	if (!isset($help_faq[$help]))
	{
		fatal_error();
	}
	
	$module_id = $help_faq[$help]['help_id'];
}

//
// Member setup
//
$user->setup();

//
// Categories
//
$hm_flip = array_flip($help_modules);
$template->assign_block_vars('cat', array());

foreach ($help_cat as $cat_id => $data)
{
	$template->assign_block_vars('cat.item', array(
		'URL' => s_link('help', array($hm_flip[$data['help_module']])),
		'TITLE' => $data['help_es'])
	);
}

//
// Selected category
//
if ($module_id || $help)
{
	if (!$help)
	{
		$this_cat = array();
		foreach ($help_faq as $data)
		{
			if ($data['help_id'] == $module_id)
			{
				$this_cat[] = $data;
			}
		}
	}
	
	$help_name = '';
	foreach ($help_cat as $data)
	{
		if ($data['help_module'] == $module_id)
		{
			$help_name = $data['help_es'];
			break;
		}
	}
	
	$template->assign_block_vars('module', array(
		'HELP' => $help_name)
	);
	
	if (!$help)
	{
		if (sizeof($this_cat))
		{
			$template->assign_block_vars('module.main', array());
			
			foreach ($this_cat as $data)
			{
				$template->assign_block_vars('module.main.item', array(
					'URL' => s_link('help', $data['faq_id']),
					'FAQ' => $data['faq_question_es'])
				);
			}
		}
		else
		{
			$template->assign_block_vars('module.empty', array());
		}
	}
	else
	{
		$dhelp = $help_faq[$help];
		
		include('./interfase/comments.php');
		$comments = new _comments();
		
		$template->assign_block_vars('module.faq', array(
			'CAT' => s_link('help', $hm_flip[$dhelp['help_id']]),
			'QUESTION_ES' => $dhelp['faq_question_es'],
			'QUESTION_EN' => $dhelp['faq_question_e'],
			'ANSWER_ES' => $comments->parse_message($dhelp['faq_answer_es']),
			'ANSWER_EN' => $comments->parse_message($dhelp['faq_answer_en']))
		);
	}
}

page_layout('HELP', 'help_body');

?>