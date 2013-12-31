<?php
/**
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */
 
if(!defined('DOKU_INC')) die();
 
class action_plugin_bds extends DokuWiki_Action_Plugin {
	
	private $mongo;
	private $vald = array();
	private $issue_types = array();
 
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
		$this->issue_types[0] = $this->getLang('type_client_complaint');
		$this->issue_types[1] = $this->getLang('type_noneconformity');
		$this->issue_types[2] = $this->getLang('type_supplier_complaint');
		$this->issue_types[3] = $this->getLang('type_task');
	}

	private function _handle_main() {
		return true;
	}
	private function _handle_issue_report() {
		$issues_col = $this->mongo->issues;
		$cursor = $issues_col->find(array(), array('_id' => 1))->sort(array('_id' => -1))->limit(1)->current();	

		if ($cursor == NULL) {
			$min_nr = 1;
		} else {
			$min_nr = $cursor->_id;
		}

		var_dump($this->vald);

		echo '<h1>'.$this->getLang('report_issue').'</h1>';
		echo '<form action="?do=bds_issue_add" method="POST">';
		echo '<label>'.$this->getLang('id').': <span>#'.$min_nr.'</span></label>';
		echo '<label for="type">'.$this->getLang('type').':</label>';
		echo '<select name="type" id="type">';
		foreach ($this->issue_types as $key => $name) {
			echo '<option';
			if (isset($_POST['type']) && $_POST['type'] == $key) {
				echo ' selected';
			}
			echo ' value="'.$key.'">'.$name.'</opiton>';
		}
		echo '</select>';
		echo '<label for="title">'.$this->getLang('title').':</label>';
		echo '<input name="title" id="title" value="'.(isset($_POST['title']) ? $_POST['title'] : '').'">';
		echo '<label for="desc">'.$this->getLang('description').':</label>';
		echo '<textarea name="desc" id="desc">';
		if (isset($_POST['desc'])) {
			echo $_POST['desc'];
		}
		echo '</textarea>';
		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
		return true;
	}

	private function _handle_issue_show($id) {
		var_dump($_POST);
	}

	public function handle_act_preprocess(&$event, $param) {
		switch($event->data) {
			case 'bds_main':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issue_add':
				$event->stopPropagation();
				$event->preventDefault();
				break;
		}
		switch($event->data) {
			case 'bds_issue_add':
				$this->vald = array();

				if ( ! array_key_exists((int)$_POST['type'], $this->issue_types)) {
					$this->vald['type'] = $this->getLang('vald_type_required');
				} else {
					$post['type'] = (int)$_POST['type'];
				}

				$_POST['title'] = trim($_POST['title']);
				if (strlen($_POST['title']) == 0) {
					$this->vald['title'] = $this->getLang('vald_title_required');
				} elseif (strlen($_POST['title']) > $this->getConf('title_max_len')) {
					$this->vald['title'] = str_replace('%d', $this->getConf('title_max_len'), $this->getLang('vald_title_too_long'));
				} elseif( ! preg_match('/^[[:alnum:] \-,.]*$/ui', $_POST['title'])) {
					$this->vald['title'] = $this->getLang('vald_title_wrong_chars');
				} else {
					$post['title'] = $_POST['title'];
				}

				$_POST['desc'] = trim($_POST['desc']);
				if (strlen($_POST['desc']) == 0) {
					$this->vald['desc'] = $this->getLang('vald_desc_required');
				} else if (strlen($_POST['desc']) > $this->getConf('desc_max_len')) {
					$this->vald['desc'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_desc_too_long'));
				} else {
					$post['desc'] = $_POST['desc'];
				}

				if (count($this->vald) == 0) {
					$event->data = 'bds_issue_show';
				} else {
					$event->data = 'bds_issue_report';
				}
				break;
		}
	}

	public function handle_act_unknown(& $event, $param) {
		switch ($event->data) {
			case 'bds_main':
			case 'bds_issue_report':
			case 'bds_issue_show':
				$event->stopPropagation(); 
				$event->preventDefault();  
				break;
		}
		switch ($event->data) {
			case 'bds_main':
				$this->_handle_main();
				break;
			case 'bds_issue_report':
				$this->_handle_issue_report();
				break;
			case 'bds_issue_show':
				$this->_handle_issue_show();
				break;
		}
	}
 
	public function add_menu_item(&$event, $param) {
		global $lang;
		$lang['btn_bds_main'] = $this->getLang('bds_main');
		$lang['btn_bds_issues'] = $this->getLang('bds_issues');
		$lang['btn_bds_issue_report'] = $this->getLang('bds_issue_report');
		$lang['btn_bds_reports'] = $this->getLang('bds_reports');

		$event->data['items']['separator'] = '<li>|</li>';

		$event->data['items']['bds_main'] = tpl_action('bds_main', 1, 'li', 1);
		$event->data['items']['bds_issues'] = tpl_action('bds_issues', 1, 'li', 1);
		$event->data['items']['bds_issue_report'] = tpl_action('bds_issue_report', 1, 'li', 1);
		$event->data['items']['bds_reports'] = tpl_action('bds_reports', 1, 'li', 1);
	}
	public function add_action(&$event, $param) {
		$data = &$event->data;

		switch($data['type']) {
			case 'bds_main':
			case 'bds_issues':
			case 'bds_issue_report':
			case 'bds_reports':
				$event->preventDefault();
		}

	}
}

