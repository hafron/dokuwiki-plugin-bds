<?php
/**
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */
 
if(!defined('DOKU_INC')) die();
 
class action_plugin_bds extends DokuWiki_Action_Plugin {
 
	/**
	 * Register its handlers with the DokuWiki's event controller
	 */
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TEMPLATE_SITETOOLS_DISPLAY', 'BEFORE', $this,
								   'add_menu_item');
		$controller->register_hook('TPL_ACTION_GET', 'BEFORE', $this,
								   'add_action');
	}
 
	public function add_menu_item(&$event, $param) {
		global $lang;
		$lang['btn_bds'] = $this->getLang('bds');
		$event->data['items']['bds'] = tpl_action('bds', 1, 'li', 1);
	}
	public function add_action(&$event, $param) {
		$data = &$event->data;
		if ($data['type'] != 'bds')
			return;

		$event->preventDefault();
	}
}

