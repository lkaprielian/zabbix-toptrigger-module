<?php declare(strict_types = 1);
 
namespace Modules\LMFR;
 
use APP;
 
// class Module extends \Zabbix\Core\CModule {
// 	public function init(): void {
// 		// Initialize main menu (CMenu class instance).
// 		APP::Component()->get('menu.main')
// 			->findOrAdd(_('Reports'))
// 				->getSubmenu()
// 					->insertAfter('Availability report', (new \CMenuItem(_('Top Triggers Recurrence')))
// 						->setAction('availreport.view')
// 					);
// 	}
// }


class Module extends \Zabbix\Core\CModule {
    public function init(): void {
        APP::Component()->get('menu.main')
            ->add((new CMenuItem(_('Tools')))
            ->setAction('availreport.view'));
    }
}
?>
