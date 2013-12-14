<?php
/**
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */
 
if(!defined('DOKU_INC')) die();
 
class action_plugin_bds extends DokuWiki_Action_Plugin {
	
	private $mongo;
 
	/**
	 * Register its handlers with the DokuWiki's event controller
	 */
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TEMPLATE_SITETOOLS_DISPLAY', 'BEFORE', $this,
								   'add_menu_item');
		$controller->register_hook('TPL_ACTION_GET', 'BEFORE', $this,
								   'add_action');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this,
								   'handle_act_preprocess');
		$controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handle_act_unknown');
	}
	public function __construct() {
		try {
			$m = new MongoClient();
			$this->mongo = $m->bds;
		} catch (Exception $e) {
			die("action_plugin_bds:__construct: MongoDB: Cannot connect with 'bds' collection.");
		}
	}

	private function _handle_main() {
		return true;
	}
	private function _handle_report_issue() {
		$issues_col = $this->mongo->issues;
		$cursor = $issues_col->find(array(), array('_id' => 1))->sort(array('_id' => -1))->limit(1)->current();	

		if ($cursor == NULL) {
			$min_nr = 1;
		} else {
			$min_nr = $cursor->_id;
		}

		echo '<h1>'.$this->getLang('report_issue').'</h1>';
		echo '<form action="?do=bds_problem_add" method="POST">';
		echo '<label>'.$this->getLang('id').': <span>#'.$min_nr.'</span></label>';
		echo '<label for="type">'.$this->getLang('type').':</label>';
		echo '<select name="type" id="type">';
		echo '</select>';
		echo '<label for="title">'.$this->getLang('title').':</label>';
		echo '<input name="title" id="title">';
		echo '<label for="desc">'.$this->getLang('description').':</label>';
		echo '<textarea name="desc" id="desc"></textarea>';
		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
		return true;
	}

	public function handle_act_preprocess(&$event, $param) {
		switch($event->data) {
			case 'bds_main':
			case 'bds_report_issue':

			case 'bds_problem_add':
				$event->stopPropagation();
				$event->preventDefault();
				break;
		}
		switch($event->data) {
			case 'bds_problem_add':
				$event->data = 'bds_show_issue';
				break;
		}
	}

	public function handle_act_unknown(& $event, $param) {
		switch ($event->data) {
			case 'bds_main':
			case 'bds_report_issue':
			case 'bds_show_issue':
				$event->stopPropagation(); 
				$event->preventDefault();  
				break;
		}
		switch ($event->data) {
			case 'bds_main':
				$this->_handle_main();
			break;
			case 'bds_report_issue':
				$this->_handle_report_issue();
			break;
			case 'bds_show_issue':
				var_dump($_POST);
				break;
		}
	}
 
	public function add_menu_item(&$event, $param) {
		global $lang;
		$lang['btn_bds_main'] = $this->getLang('bds_main');
		$lang['btn_bds_issues'] = $this->getLang('bds_issues');
		$lang['btn_bds_report_issue'] = $this->getLang('bds_report_issue');
		$lang['btn_bds_reports'] = $this->getLang('bds_reports');

		$event->data['items']['separator'] = '<li>|</li>';

		$event->data['items']['bds_main'] = tpl_action('bds_main', 1, 'li', 1);
		$event->data['items']['bds_issues'] = tpl_action('bds_issues', 1, 'li', 1);
		$event->data['items']['bds_report_issue'] = tpl_action('bds_report_issue', 1, 'li', 1);
		$event->data['items']['bds_reports'] = tpl_action('bds_reports', 1, 'li', 1);
	}
	public function add_action(&$event, $param) {
		$data = &$event->data;

		switch($data['type']) {
			case 'bds_main':
			case 'bds_issues':
			case 'bds_report_issue':
			case 'bds_reports':
				$event->preventDefault();
		}

	}
}

