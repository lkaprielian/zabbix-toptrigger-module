<?php declare(strict_types = 1);
 
namespace Modules\TopTriggers;
 
use APP;
 
class Module extends \Zabbix\Core\CModule {
	public function init(): void {
		// Initialize main menu (CMenu class instance).
		APP::Component()->get('menu.main')
			->findOrAdd(_('Reports'))
				->getSubmenu()
					->insertAfter('Availability report', (new \CMenuItem(_('Top triggers')))
						->setAction('reports.toptriggers')
					);
	}
}
?>
