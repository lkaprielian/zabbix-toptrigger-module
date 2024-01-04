<?php

$form = (new CForm())->setName('availreport_view');

$table = (new CTableInfo());

$view_url = $data['view_curl']->getUrl();

$table->setHeader([
	(new CColHeader(_('Host'))),
	(new CColHeader(_('Trigger'))),
	(new CColHeader(_('Problems'))),
	(new CColHeader(_('Ok'))),
	(new CColHeader(_('Tags'))),
	(new CColHeader(_('Number of status changes')))
]);

// data generation
$triggersEventCount = [];

// get 100 triggerids with max event count
$sql = 'SELECT e.objectid,count(distinct e.eventid) AS cnt_event'.
		' FROM triggers t,events e'.
		' WHERE t.triggerid=e.objectid'.
			' AND e.source='.EVENT_SOURCE_TRIGGERS.
			' AND e.object='.EVENT_OBJECT_TRIGGER.
			' AND e.clock>='.zbx_dbstr($data['filter']['timeline']['from_ts']).
			' AND e.clock<='.zbx_dbstr($data['filter']['timeline']['to_ts']);

if ($data['filter']['severities']) {
	$sql .= ' AND '.dbConditionInt('t.priority', $data['filter']['severities']);
}

if ($hostids) {
	$inHosts = ' AND '.dbConditionInt('i.hostid', $hostids);
}

if ($groupids) {
	$inGroups = ' AND '.dbConditionInt('hgg.groupid', $groupids);
}

if (CWebUser::getType() == USER_TYPE_SUPER_ADMIN && ($groupids || $hostids)) {
	$sql .= ' AND EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
					($hostids ? $inHosts : '').
					($groupids ? $inGroups : '').
			')';
}
elseif (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
	// add permission filter
	$userId = CWebUser::$data['userid'];
	$userGroups = getUserGroupsByUserId($userId);
	$sql .= ' AND EXISTS ('.
				'SELECT NULL'.
				' FROM functions f,items i,hosts_groups hgg'.
				' JOIN rights r'.
					' ON r.id=hgg.groupid'.
						' AND '.dbConditionInt('r.groupid', $userGroups).
				' WHERE t.triggerid=f.triggerid'.
					' AND f.itemid=i.itemid'.
					' AND i.hostid=hgg.hostid'.
					($hostids ? $inHosts : '').
					($groupids ? $inGroups : '').
				' GROUP BY f.triggerid'.
				' HAVING MIN(r.permission)>'.PERM_DENY.
			')';
}
$sql .= ' AND '.dbConditionInt('t.flags', [ZBX_FLAG_DISCOVERY_NORMAL, ZBX_FLAG_DISCOVERY_CREATED]).
		' GROUP BY e.objectid'.
		' ORDER BY cnt_event DESC';
$result = DBselect($sql, 100);
while ($row = DBfetch($result)) {
	$triggersEventCount[$row['objectid']] = $row['cnt_event'];
}

$data['triggers'] = API::Trigger()->get([
	'output' => ['triggerid', 'description', 'expression', 'priority', 'lastchange'],
	'selectHosts' => ['hostid', 'status', 'name'],
	'triggerids' => array_keys($triggersEventCount),
	'expandDescription' => true,
	'preservekeys' => true
]);

$trigger_hostids = [];

foreach ($data['triggers'] as $triggerId => $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];
	$trigger_hostids[$hostId] = $hostId;

	$data['triggers'][$triggerId]['cnt_event'] = $triggersEventCount[$triggerId];
}


$allowed_ui_problems = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
$triggers = $data['triggers'];

$tags = makeTags($triggers, true, 'triggerid', ZBX_TAG_COUNT_DEFAULT);
foreach ($triggers as &$trigger) {
	$trigger['tags'] = $tags[$trigger['triggerid']];
}
unset($trigger);



$trigger_hostids = [];

foreach ($data['triggers'] as $triggerId => $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];
	$trigger_hostids[$hostId] = $hostId;

	$data['triggers'][$triggerId]['cnt_event'] = $triggersEventCount[$triggerId];
}

CArrayHelper::sort($data['triggers'], [
	['field' => 'cnt_event', 'order' => ZBX_SORT_DOWN],
	'host', 'description', 'priority'
]);

foreach ($triggers as $trigger) {
	$table->addRow([
		$trigger['host_name'],
		$allowed_ui_problems
			? new CLink($trigger['description'],
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_name', '')
					->setArgument('triggerids', [$trigger['triggerid']])
			)
			: $trigger['description'],
		($trigger['availability']['true'] < 0.00005)
			? ''
			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['true'])))->addClass(ZBX_STYLE_RED),
		($trigger['availability']['false'] < 0.00005)
			? ''
			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['false'])))->addClass(ZBX_STYLE_GREEN),
		$trigger['tags'],
		$trigger['cnt_event']
	]);
}

$form->addItem([$table,	$data['paging']]);

echo $form;
?>
