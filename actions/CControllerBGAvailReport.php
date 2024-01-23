<?php declare(strict_types = 1);

namespace Modules\LMFR\Actions;

use CController;
use CSettingsHelper;
use API;
use CArrayHelper;
use CUrl;
use CPagerHelper;
use CRangeTimeParser;
use CWebUser;

abstract class CControllerBGAvailReport extends CController {

	// Filter idx prefix.
	const FILTER_IDX = 'web.avail_report.filter';
	// Filter fields default values.
	const FILTER_FIELDS_DEFAULT = [
		'name' => '',
		'mode' => AVAILABILITY_REPORT_BY_TEMPLATE,
		'tpl_groupids' => [],
		'templateids' => [],
		'tpl_triggerids' => [],
		'triggerids' => [],
		'hostgroupids' => [],
		'hostids' => [],
		'only_with_problems' => 1,
        'page' => null,
		'from' => '',
		'to' => '',
		'sort' => 'name',
		'sortorder' => ZBX_SORT_DOWN
	];

	protected function getData(array $filter): array {
		$host_group_ids = sizeof($filter['hostgroupids']) > 0 ? $this->getChildGroups($filter['hostgroupids']) : null;

		$generating_csv_flag = 1;
		if (!array_key_exists('action_from_url', $filter) ||
			$filter['action_from_url'] != 'availreport.view.csv') {
			// Generating for UI
			$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
			$generating_csv_flag = 0;
		} else {
			// Generating for CSV report
			$limit = 5001;
		}
		// All CONFIGURED triggers that fall under selected filter
		$num_of_triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value'],
			'monitored' => true,
			'triggerids' => sizeof($filter['triggerids']) > 0 ? $filter['triggerids'] : null,
			'groupids' => $host_group_ids,
			'hostids ' => sizeof($filter['hostids']) > 0 ? $filter['hostids'] : null,
			'filter' => [
				'templateid' => sizeof($filter['tpl_triggerids']) > 0 ? $filter['tpl_triggerids'] : null
			],
			'countOutput' => true
		]);
		$warning = null;
		if ($num_of_triggers > $limit) {
			$warning = 'WARNING: ' . $num_of_triggers . ' triggers found which is more than reasonable limit ' . $limit . ', results below might be not totally accurate. Please add or review current filter conditions.';
		}
		// print $num_of_triggers;

		// Get timestamps from and to
		if ($filter['from'] != '' && $filter['to'] != '') {
			$range_time_parser = new CRangeTimeParser();
			$range_time_parser->parse($filter['from']);
			$filter['from_ts'] = $range_time_parser->getDateTime(true)->getTimestamp();
			$range_time_parser->parse($filter['to']);
			$filter['to_ts'] = $range_time_parser->getDateTime(false)->getTimestamp();
		} else {
			$filter['from_ts'] = null;
			$filter['to_ts'] = null;
		}

		####
		$triggersEventCount = [];

		// get 100 triggerids with max event count
		$sql = 'SELECT e.objectid,count(distinct e.eventid) AS cnt_event'.
				' FROM triggers t,events e'.
				' WHERE t.triggerid=e.objectid'.
					' AND e.source='.EVENT_SOURCE_TRIGGERS.
					' AND e.value='.TRIGGER_VALUE_TRUE.
					' AND e.clock>='.zbx_dbstr($filter['from_ts']).
					' AND e.clock<='.zbx_dbstr($filter['to_ts']);
		
		$sql .= ' AND '.dbConditionInt('t.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
				' GROUP BY e.objectid'.
				' ORDER BY cnt_event DESC';
		$result = DBselect($sql, 100);
		while ($row = DBfetch($result)) {
			$triggersEventCount[$row['objectid']] = $row['cnt_event'];
		}

		$triggerids = [];
		foreach (array_keys($triggersEventCount) as $triggerid) {
			if ((sizeof($filter['triggerids']) > 0)) {
				foreach ($filter['$triggerids'] as $trigger) {
					if ($trigger === $triggerid) {
						$triggerids = $triggerids + $trigger; 
					}
				}
			} 
		}


		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'expression', 'value', 'priority', 'lastchange'],
			'selectHosts' => ['hostid', 'status', 'name'],
			'triggerids' => $triggerids, //added to get top 100  array_keys($triggersEventCount)
			'selectTags' => 'extend',
			'selectFunctions' => 'extend',
			'expandDescription' => true,
			'monitored' => true,
			'groupids' => $host_group_ids,
			'preservekeys' => true,
			'hostids' => sizeof($filter['hostids']) > 0 ? $filter['hostids'] : null,
			'filter' => [
				'templateid' => sizeof($filter['tpl_triggerids']) > 0 ? $filter['tpl_triggerids'] : null
			],
            'limit' => $limit
        ]);

		foreach ($triggers as $triggerId => $trigger) {
			$triggers[$triggerId]['cnt_event'] = $triggersEventCount[$triggerId];
		}

		// if ($filter['only_with_problems']) {
		// Find all triggers that went into PROBLEM state
		// at any time in given time frame
		$triggerids_with_problems = [];
		$sql = 'SELECT e.eventid, e.objectid' .
			' FROM events e'.
			' WHERE e.source='.EVENT_SOURCE_TRIGGERS.
				' AND e.object='.EVENT_OBJECT_TRIGGER.
				' AND e.value='.TRIGGER_VALUE_TRUE;
		if ($filter['from_ts']) {
			$sql .= ' AND e.clock>='.zbx_dbstr($filter['from_ts']);
		}
		if ($filter['to_ts']) {
			$sql .= ' AND e.clock<='.zbx_dbstr($filter['to_ts']);
		}
		$dbEvents = DBselect($sql);
		while ($row = DBfetch($dbEvents)) {
			if (!array_key_exists($row['objectid'], $triggerids_with_problems)) {
				$triggerids_with_problems[$row['objectid']] = [];
			}
			if (!array_key_exists('tags', $triggerids_with_problems[$row['objectid']])) {
				$triggerids_with_problems[$row['objectid']] = ['tags' => []];
			}
			$sql1 = 'SELECT et.tag, et.value' .
				' FROM event_tag et' .
				' WHERE et.eventid=' . $row['eventid'];
			$dbTags = DBselect($sql1);
			while ($row1 = DBfetch($dbTags)) {
				$triggerids_with_problems[$row['objectid']]['tags'][] = [
					'tag' => $row1['tag'],
					'value' => $row1['value']
				];
			}
		}
		#here
		// Find all triggers that were in the PROBLEM state
		// at the start of this time frame
		foreach($triggers as $trigger) {
			$sql = 'SELECT e.eventid, e.objectid, e.value' .
					' FROM events e'.
					' WHERE e.objectid='.zbx_dbstr($trigger['triggerid']).
						' AND e.source='.EVENT_SOURCE_TRIGGERS.
						' AND e.object='.EVENT_OBJECT_TRIGGER.
						' AND e.clock<'.zbx_dbstr($filter['from_ts']).
						' AND e.clock<='.zbx_dbstr($filter['to_ts']);
					' ORDER BY e.eventid DESC';
			if ($row = DBfetch(DBselect($sql, 1))) {
				// Add the triggerid to the array if it is not there
				if ($row['value'] == TRIGGER_VALUE_TRUE &&
					!in_array($row['objectid'], $triggerids_with_problems)) {
					$triggerids_with_problems[$row['objectid']] = ['tags' => []];
					$sql1 = 'SELECT et.tag, et.value' .
						' FROM event_tag et' .
						' WHERE et.eventid=' . $row['eventid'];
					$dbTags = DBselect($sql1);
					while ($row1 = DBfetch($dbTags)) {
						$triggerids_with_problems[$row['objectid']]['tags'][] = [
							'tag' => $row1['tag'],
							'value' => $row1['value']
						];
					}
				}
			}

		}


		$triggers_with_problems = [];
		foreach ($triggers as $trigger) {
			if (array_key_exists($trigger['triggerid'], $triggerids_with_problems)) {
				$trigger['tags'] = $triggerids_with_problems[$trigger['triggerid']]['tags'];
				$triggers_with_problems[] = $trigger;
			}
		}

		
		// Reset all previously selected triggers to only ones with problems
		unset($triggers);
		$triggers = $triggers_with_problems;

		// } // end of if problems

		// Now just prepare needed data.sorted by Number of status changes
		// CArrayHelper::sort($triggers, [
		// 	['field' => 'cnt_event', 'order' => ZBX_SORT_DOWN],
		// 	'host', 'description', 'priority'
		// ]);

		// Now just prepare needed data.
		// CArrayHelper::sort($triggers, ['host_name', 'description'], 'ASC');

		// Now just prepare needed data. Modified to take 2 ways of sorts
		$filter['sortorder'] == 'ASC' ? CArrayHelper::sort($triggers, [
			['field' => 'cnt_event', 'order' => ZBX_SORT_UP],
			'host', 'description', 'priority'
		]): CArrayHelper::sort($triggers, [
			['field' => 'cnt_event', 'order' => ZBX_SORT_DOWN],
			'host', 'description', 'priority'
		]);

		$view_curl = (new CUrl())->setArgument('action', 'availreport.view');

		$rows_per_page = (int) CWebUser::$data['rows_per_page'];
                $selected_triggers = [];
		$i = 0;  // Counter. We need to stop doing expensive calculateAvailability() when results are not visible
		$page = $filter['page'] ? $filter['page'] - 1 : 0;
		$start_idx = $page * $rows_per_page;
		$end_idx = $start_idx + $rows_per_page;
		foreach ($triggers as &$trigger) {
			if ($generating_csv_flag ||
				 ($i >= $start_idx && $i < $end_idx) ) {
				$trigger['availability'] = calculateAvailability($trigger['triggerid'], $filter['from_ts'], $filter['to_ts']);
				if ($filter['only_with_problems']) {
					if ($trigger['availability']['true'] > 0.00005) {
						$selected_triggers[] = $trigger;
					}
				} else {
					$selected_triggers[] = $trigger;
				}
			} else {
				$selected_triggers[] = $trigger;
			}
			$i++;
		}

		// print_r($selected_triggers);
		if (!$generating_csv_flag) {
			// Not exporting data to CSV, just showing the data
			// Split result array and create paging. Only if not generating CSV.
			$paging = CPagerHelper::paginate($filter['page'], $selected_triggers, 'ASC', $view_curl);
		}

		foreach ($selected_triggers as &$trigger) {
			$trigger['host_name'] = $trigger['hosts'][0]['name'];
		}
		unset($trigger);

		// if (!$filter['only_with_problems']) {
		foreach($selected_triggers as &$trigger) {
			// Add host tags
			$hosts = API::Host()->get([
				'output' => ['hostid'],
				'selectTags' => 'extend',
				'hostids' => [$trigger['hosts'][0]['hostid']]
			]);
			if (count($hosts[0]['tags']) > 0) {
				$trigger['tags'][] = $hosts[0]['tags'];
			}

			// Add item(s) tags
			foreach($trigger['functions'] as $function) {
				$sql = 'SELECT it.tag, it.value' .
					' FROM item_tag it' .
					' WHERE it.itemid=' . $function['itemid'];
				$dbTags = DBselect($sql);
				while ($row = DBfetch($dbTags)) {
					$new_tag = [
						'tag' => $row['tag'],
						'value' => $row['value']
					];
					if (!in_array($new_tag, $trigger['tags'])) {
						$trigger['tags'][] = [
							'tag' => $row['tag'],
							'value' => $row['value']
						];
					}
				}
			}
		}
		unset($trigger);
		// } end of if problems

		return [
			'paging' => $paging,
			'triggers' => $selected_triggers,
			'warning' => $warning
		];
	}

	protected function cleanInput(array $input): array {
		if (array_key_exists('filter_reset', $input) && $input['filter_reset']) {
			return array_intersect_key(['filter_name' => ''], $input);
		}
		return $input;
	}

	protected function getAdditionalData($filter): array {
		$data = [];

		if ($filter['tpl_groupids']) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['tpl_groupids']
			]);
			$data['tpl_groups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($groups), ['groupid' => 'id']);
		}

		if ($filter['templateids']) {
			$templates= API::Template()->get([
				'output' => ['templateid', 'name'],
				'templateids' => $filter['templateids']
			]);
			$data['templates_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($templates), ['templateid' => 'id']);
		}

		if ($filter['tpl_triggerids']) {
			$triggers = API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'selectHosts' => 'extend',
				'triggerids' => $filter['tpl_triggerids']
			]);

			foreach($triggers as &$trigger) {
				sizeof($trigger['hosts']) > 0 ?
					$trigger['name'] = $trigger['hosts'][0]['host'] . ': ' . $trigger['description'] :
					$trigger['name'] = $trigger['description'];
				unset($trigger['hosts']);
				unset($trigger['description']);
			}

			$data['tpl_triggers_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($triggers), ['triggerid' => 'id']);
		}

		// Triggers multiselect.
		if ($filter['triggerids']) {
			$triggers = CArrayHelper::renameObjectsKeys(API::Trigger()->get([
				'output' => ['triggerid', 'description'],
				'selectHosts' => ['name'],
				'expandDescription' => true,
				'triggerids' => $filter['triggerids'],
				'monitored' => true
			]), ['triggerid' => 'id', 'description' => 'name']);

			CArrayHelper::sort($triggers, [
				['field' => 'name', 'order' => ZBX_SORT_UP]
			]);

			foreach ($triggers as &$trigger) {
				$trigger['prefix'] = $trigger['hosts'][0]['name'].NAME_DELIMITER;
				unset($trigger['hosts']);
			}
			unset($trigger);

			$data['triggers'] = $triggers;
		}

		if ($filter['hostgroupids']) {
			$hostgroups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => $filter['hostgroupids']
			]);
			$data['hostgroups_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($hostgroups), ['groupid' => 'id']);
		}

		if ($filter['hostids']) {
			$hosts = API::Host()->get([
				'output' => ['hostid', 'name'],
				'hostids' => $filter['hostids']
			]);
			$data['hosts_multiselect'] = CArrayHelper::renameObjectsKeys(array_values($hosts), ['hostid' => 'id']);
		}

		// if ($filter['sort']) {
		// 	$data['sort'] = $filter['sort'];
		// }

		// if ($filter['sortorder']) {
		// 	$data['sortorder'] = $filter['sortorder'];
		// }

		return $data;
	}

	protected function getChildGroups($parent_group_ids): array {
		$all_group_ids = [];
		foreach($parent_group_ids as $parent_group_id) {
			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'groupids' => [$parent_group_id]
			]);
			$parent_group_name = $groups[0]['name'].'/';
			$len = strlen($parent_group_name);

			$groups = API::HostGroup()->get([
				'output' => ['groupid', 'name'],
				'search' => ['name' => $parent_group_name],
				'startSearch' => true
			]);

			$all_group_ids[] = $parent_group_id;
			foreach ($groups as $group) {
				if (substr($group['name'], 0, $len) === $parent_group_name) {
					$all_group_ids[] = $group['groupid'];
				}
			}
		}
		return $all_group_ids;
	}
}
