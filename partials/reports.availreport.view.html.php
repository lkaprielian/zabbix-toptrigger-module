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

$allowed_ui_problems = CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS);
$triggers = $data['triggers'];

$tags = makeTags($triggers, true, 'triggerid', ZBX_TAG_COUNT_DEFAULT);
foreach ($triggers as &$trigger) {
	$trigger['tags'] = $tags[$trigger['triggerid']];
}
unset($trigger);



$trigger_hostids = [];
$triggersEventCount = [];


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
		$triggersEventCount[$triggerId]
		// $trigger['cnt_event']
	]);
}

$form->addItem([$table,	$data['paging']]);

echo $form;
?>
