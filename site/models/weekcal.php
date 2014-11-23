<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require_once dirname(__FILE__) . '/eventslist.php';

/**
 * Model-Calendar
 */
class JemModelWeekcal extends JemModelEventslist
{

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		# parent::populateState($ordering, $direction);
		$app 			= JFactory::getApplication();
		$jemsettings	= JemHelper::config();
		$jinput			= JFactory::getApplication()->input;
		$itemid 		= $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
		$task           = $jinput->getCmd('task','');
		$params 		= $app->getParams();
		$top_category 	= $params->get('top_category', 0);
		$startdayonly 	= $params->get('show_only_start', false);
		$numberOfWeeks	= $params->get('nrweeks', '1');
		$firstweekday	= $params->get('firstweekday', 1);

		# params
		$this->setState('params', $params);

		# publish state
		$this->setState('filter.published', 1);

		###########
		## DATES ##
		###########

		#only select events within specified dates. (chosen weeknrs)

		$config = JFactory::getConfig();
		$offset = $config->get('offset');
		$year = date('Y');
		date_default_timezone_set($offset);
		$datetime = new DateTime();
		$datetime->setISODate($year, $datetime->format("W"), 7);

		if ($firstweekday == 1) {
			if(date('N', time()) == 1) {
				#it's monday and monday is startdate;
				$filter_date_from = $datetime->modify('-6 day');
				$filter_date_from = $datetime->format('Y-m-d') . "\n";
				$filter_date_to = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$filter_date_to = $datetime->format('Y-m-d') . "\n";
			} else {
				# it's not monday but monday is startdate;
				$filter_date_from = $datetime->modify('-6 day');
				$filter_date_from = $datetime->format('Y-m-d') . "\n";
				$filter_date_to = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$filter_date_to = $datetime->format('Y-m-d') . "\n";
			}
		}

		if ($firstweekday == 0) {
			if(date('N', time()) == 7) {
				#it's sunday and sunday is startdate;
				$filter_date_from = $datetime->format('Y-m-d') . "\n";
				$filter_date_to = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
				$filter_date_to = $datetime->format('Y-m-d') . "\n";
			} else {
				#it's not sunday and sunday is startdate;
				$filter_date_from = $datetime->modify('-7 day');
				$filter_date_from = $datetime->format('Y-m-d') . "\n";
				$filter_date_to = $datetime->modify('+'.$numberOfWeeks.' weeks');
				$filter_date_to = $datetime->format('Y-m-d') . "\n";
			}
		}

		$where = ' DATEDIFF(IF (a.enddates IS NOT NULL, a.enddates, a.dates), \''. $filter_date_from .'\') >= 0';
		$this->setState('filter.calendar_from',$where);

		$where = ' DATEDIFF(a.dates, \''. $filter_date_to .'\') <= 0';
		$this->setState('filter.calendar_to',$where);


		##################
		## TOP-CATEGORY ##
		##################

		if ($top_category) {
			$children = JEMCategories::getChilds($top_category);
			if (count($children)) {
				$where = 'rel.catid IN ('. implode(',', $children) .')';
				$this->setState('filter.category_top', $where);
			}
		}

		# set filter
		$this->setState('filter.calendar_startdayonly',(bool)$startdayonly);
		$this->setState('filter.groupby','a.id');
	}


	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();

		$items	= parent::getItems();
		if ($items) {
			$items = self::calendarMultiday($items);

			return $items;
		}

		return array();
	}


	/**
	 * @return	JDatabaseQuery
	 */
	function getListQuery()
	{
		$params  = $this->state->params;
		$jinput  = JFactory::getApplication()->input;
		$task    = $jinput->get('task','','cmd');

		// Create a new query object.
		$query = parent::getListQuery();

		$query->select('DATEDIFF(a.enddates, a.dates) AS datesdiff,DAYOFWEEK(a.dates) AS weekday, DAYOFMONTH(a.dates) AS start_day, YEAR(a.dates) AS start_year, MONTH(a.dates) AS start_month, WEEK(a.dates) AS weeknumber');

		return $query;
	}

	/**
	 * create multi-day events
	 */
	function calendarMultiday($items) {

		if (empty($items)) {
			return array();
		}

		$app 			= JFactory::getApplication();
		$params 		= $app->getParams();
		$startdayonly	= $this->getState('filter.calendar_startdayonly');

		foreach($items AS $item) {
			$item->categories = $this->getCategories($item->id);

			//remove events without categories (users have no access to them)
			if (empty($item->categories)) {
				unset($item);
			}
			elseif (!$startdayonly) {
				if (!is_null($item->enddates) && ($item->enddates != $item->dates)) {
					$day = $item->start_day;
					$multi = array();

					$item->multi = 'first';
					$item->multitimes = $item->times;
					$item->multiname = $item->title;
					$item->sort = 'zlast';

					for ($counter = 0; $counter <= $item->datesdiff-1; $counter++) {

						//next day:
						$day++;
						$nextday = mktime(0, 0, 0, $item->start_month, $day, $item->start_year);

						//generate days of current multi-day selection
						$multi[$counter] = clone $item;
						$multi[$counter]->dates = strftime('%Y-%m-%d', $nextday);

						if ($multi[$counter]->dates < $item->enddates) {
							$multi[$counter]->multi = 'middle';
							$multi[$counter]->multistartdate = $item->dates;
							$multi[$counter]->multienddate = $item->enddates;
							$multi[$counter]->multitimes = $item->times;
							$multi[$counter]->multiname = $item->title;
							$multi[$counter]->times = $item->times;
							$multi[$counter]->endtimes = $item->endtimes;
							$multi[$counter]->sort = 'middle';
						} elseif ($multi[$counter]->dates == $item->enddates) {
							$multi[$counter]->multi = 'zlast';
							$multi[$counter]->multistartdate = $item->dates;
							$multi[$counter]->multienddate = $item->enddates;
							$multi[$counter]->multitimes = $item->times;
							$multi[$counter]->multiname = $item->title;
							$multi[$counter]->sort = 'first';
							$multi[$counter]->times = $item->times;
							$multi[$counter]->endtimes = $item->endtimes;
						}
					} // for

					//add generated days to data
					$items = array_merge($items, $multi);

					//unset temp array holding generated days before working on the next multiday event
					unset($multi);
				}
			}
		} // foreach ($items)

		foreach ($items as $index => $item) {
			$date = $item->dates;
			$firstweekday = $params->get('firstweekday',1); // 1 = Monday, 0 = Sunday

			$config = JFactory::getConfig();
			$offset = $config->get('offset');
			$year = date('Y');

			date_default_timezone_set($offset);
			$datetime = new DateTime();
			$datetime->setISODate($year, $datetime->format("W"), 7);
			$numberOfWeeks = $params->get('nrweeks', '1');

			if ($firstweekday == 1) {
				if(date('N', time()) == 1) {
					#it's monday and monday is startdate;
					$startdate = $datetime->modify('-6 day');
					$startdate = $datetime->format('Y-m-d') . "\n";
					$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
					$enddate = $datetime->format('Y-m-d') . "\n";
				} else {
					#it's not monday but monday is startdate;..
					$startdate = $datetime->modify('-6 day');
					$startdate = $datetime->format('Y-m-d') . "\n";
					$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
					$enddate = $datetime->format('Y-m-d') . "\n";
				}
			}

			if ($firstweekday == 0) {
				if(date('N', time()) == 7) {
					#it's sunday and sunday is startdate;
					$startdate = $datetime->format('Y-m-d') . "\n";
					$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
					$enddate = $datetime->format('Y-m-d') . "\n";
				} else {
					#it's not sunday and sunday is startdate;
					$startdate = $datetime->modify('-7 day');
					$startdate = $datetime->format('Y-m-d') . "\n";
					$enddate = $datetime->modify('+'.$numberOfWeeks.' weeks'.'- 1 day');
					$enddate = $datetime->format('Y-m-d') . "\n";
				}
			}

			$check_startdate = strtotime($startdate);
			$check_enddate = strtotime($enddate);
			$date_timestamp = strtotime($date);

			if ($date_timestamp > $check_enddate) {
				unset ($items[$index]);
			} elseif ($date_timestamp < $check_startdate) {
				unset ($items[$index]);
			}
		}

		// Do we still have events? Return if not.
		if (empty($items)) {
			return array();
		}

		foreach ($items as $item) {
			$time[] = $item->times;
			$title[] = $item->title;
			$id[] = $item->id;
			$dates[] = $item->dates;
			$multi[] = (isset($item->multi) ? $item->multi : false);
			$multitime[] = (isset($item->multitime) ? $item->multitime : false);
			$multititle[] = (isset($item->multititle) ? $item->multititle : false);
			$sort[] = (isset($item->sort) ? $item->sort : 'zlast');
		}

		array_multisort($sort, SORT_ASC, $multitime, $multititle, $time, SORT_ASC, $title, $items);

		return $items;

	}


	/**
	 * Method to get the Currentweek
	 *
	 * Info MYSQL WEEK
	 * @link http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_week
	 */
	function getCurrentweek()
	{
		$app = JFactory::getApplication();
		$params =  $app->getParams('com_jem');
		$weekday = $params->get('firstweekday',1); // 1 = Monday, 0 = Sunday

		if ($weekday == 1) {
			$number = 3; // Monday, with more than 3 days this year
		} else {
			$number = 6; // Sunday, with more than 3 days this year
		}

		$today =  Date("Y-m-d");
		$query = 'SELECT WEEK(\''.$today.'\','.$number.')' ;

		$this->_db->setQuery($query);
		$this->_currentweek = $this->_db->loadResult();

		return $this->_currentweek;
	}
}
?>