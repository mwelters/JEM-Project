<?php
/**
 * Version 0.2
 * @copyright	Copyright (C) 2014 Thamesmog.
 * @license		http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * Based on Eventlist11 tag and JEM specific code by Jojo Murer
 */
defined('_JEXEC') or die;

include_once(ACYMAILING_ROOT.'components'.DS.'com_jem'.DS.'helpers'.DS.'route.php');

$result .= '<div class="acymailing_content">';
if (!empty($event->datimage)) {
	$imageFile = file_exists(ACYMAILING_ROOT.'images'.DS.'jem'.DS.'events'.DS.'small'.DS.$event->datimage) ? ACYMAILING_LIVE.'images/jem/events/small/'.$event->datimage : ACYMAILING_LIVE.'images/jem/events/'.$event->datimage;
	$result .= '<table cellspacing="5" cellpadding="0" border="0"><tr><td valign="top"><a style="text-decoration:none;border:0" target="_blank" href="'.$link.'" ><img src="'.$imageFile.'"/></a></td><td style="padding-left:5px" valign="top">';
} else {
	$result .= '<table cellspacing="5" cellpadding="0" border="0"><tr><td valign="top"></td><td style="padding-left:5px" valign="top">';
}
$result .= '<a style="text-decoration:none;" name="event-'.$event->id.'" target="_blank" href="'.$link.'"><h2 class="acymailing_title">'.$event->title;
if (!empty($event->custom1)) {
	$result .= '<br/><em>'.$event->custom1.'</em>';
}
$result .= '</h2></a>';
$result .= '<span class="eventdate">'.$date.'</span>';
$result .= '<br/>'.$event->venue;
/* Kontakt */
if (!empty($event->conname)) {
//	$result .= '<div style="display:block;float:left;">';
	$result .= '<p>';
	$contact = $event->conname;
	$needle = 'index.php?option=com_contact&view=contact&id=' . $event->conid;
	$menu = JFactory::getApplication()->getMenu();
	$item = $menu->getItems('link', $needle, true);
	$cntlink2 = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;
	$result .= '<br/>Email: <a href="'.$cntlink2.'">'.$contact.'</a>';
	//if ($event->conemail_to) {
	//	$result .= '<br/><a href="mailto:'.$event->conemail_to.'">'.$event->conemail_to.'</a>';
	//}
	//if ($event->contelephone) {
	//	$result .= '<br/>Tel:'.$event->contelephone;
	//}
	if ($event->conmobile) {
		$result .= '<br/>SMS:'.$event->conmobile;
	}
	$result .= '</p>';
//	$result .= '</div>';
}
if (!empty($event->datimage)) {
	$result .= '</td></tr></table>';
} else {
	$result .= '</td></tr></table>';
}
$result .= '<hr style="clear:both"/>';
$result .= '</div>';