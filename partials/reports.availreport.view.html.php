<?php

$form = (new CForm())->setName('availreport_view');

// $table = (new CTableInfo());

$view_url = $data['view_curl']->getUrl();
// table
$table = (new CTableInfo())->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Number of status changes')]);

foreach ($data['triggers'] as $trigger) {
	$hostId = $trigger['hosts'][0]['hostid'];
	// $triggerId = $trigger['triggerid'];

	$hostName = (new CLinkAction($trigger['hosts'][0]['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));
	// if ($data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) {
	// 	$hostName->addClass(ZBX_STYLE_RED);
	// }
	// print($hostName);

	$triggerDescription = (new CLinkAction($trigger['description']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));

	$table->addRow([
		$hostName,
		$triggerDescription,
		// getSeverityCell($trigger['priority'], $data['config']),
		$trigger['cnt_event']
	]);
}
// $table->setHeader([
// 	(new CColHeader(_('Host'))),
// 	(new CColHeader(_('Trigger'))),
// 	(new CColHeader(_('Problems'))),
// 	(new CColHeader(_('Ok'))),
// 	// (new CColHeader(_('Tags'))),
// 	// make_sorting_header(_('Number of status changes'), 'name', $data['sort'], $data['sortorder'], $view_url),
// 	(new CColHeader(_('Number of status changes')))
// ]);

// $allowed_ui_problems = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
// $triggers = $data['triggers'];

// $tags = makeTags($triggers, true, 'triggerid', ZBX_TAG_COUNT_DEFAULT);
// foreach ($triggers as &$trigger) {
// 	$trigger['tags'] = $tags[$trigger['triggerid']];
// }
// unset($trigger);

// foreach ($triggers as $trigger) {
// 	$hostId = $trigger['hosts'][0]['hostid'];

// 	$hostName = (new CLinkAction($trigger['hosts'][0]['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));
// 	if ($data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) {
// 		$hostName->addClass(ZBX_STYLE_RED);
// 	}
// 	$triggerDescription = (new CLinkAction($trigger['description']))
// 		->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], 0));
// }
// unset($trigger);



// foreach ($triggers as $trigger) {

// 	// $hostId = $trigger['hosts'][0]['hostid'];

// 	// $hostName = (new CLinkAction($trigger['hosts'][0]['name']))->setMenuPopup(CMenuPopupHelper::getHost($hostId));
// 	// if ($data['hosts'][$hostId]['status'] == HOST_STATUS_NOT_MONITORED) {
// 	// 	$hostName->addClass(ZBX_STYLE_RED);
// 	// }

// 	// $triggerDescription = (new CLinkAction($trigger['description']))
// 	// 	->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], 0));
// 	print_r($trigger['description']);
// 	$table->addRow([
// 		$trigger['host_name'],
// 		// $hostName,
// 		// $allowed_ui_problems
// 		// 	? new CLink($trigger['description'],
// 		// 		(new CUrl('zabbix.php'))
// 		// 			->setArgument('action', 'problem.view')
// 		// 			->setArgument('filter_name', '')
// 		// 			->setArgument('triggerids', [$trigger['triggerid']])
// 		// 	)
// 		// 	: $trigger['description'],

// 		$triggerDescription = (new CLinkAction($trigger['description']))->setMenuPopup(CMenuPopupHelper::getTrigger($trigger['triggerid'], 0)),

// 		// $triggerDescription,
// 		($trigger['availability']['true'] < 0.00005)
// 			? ''
// 			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['true'])))->addClass(ZBX_STYLE_RED),
// 		($trigger['availability']['false'] < 0.00005)
// 			? ''
// 			: (new CSpan(sprintf('%.4f%%', $trigger['availability']['false'])))->addClass(ZBX_STYLE_GREEN),
// 		// $trigger['tags'],
// 		// $triggersEventCount[$triggerId]
// 		$trigger['cnt_event']
// 	]);
// }

$form->addItem([$table,	$data['paging']]);

echo $form;
?>
