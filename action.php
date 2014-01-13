<?php
/**
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */
 
if(!defined('DOKU_INC')) die();
 
class action_plugin_bds extends DokuWiki_Action_Plugin {
	
	private $mongo = NULL;

	private $helper;
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
		$this->issue_types[0] = $this->getLang('type_client_complaint');
		$this->issue_types[1] = $this->getLang('type_noneconformity');
		$this->issue_types[2] = $this->getLang('type_supplier_complaint');
		$this->issue_types[3] = $this->getLang('type_task');

		$this->helper = $this->loadHelper('bds');
	}
	private function user_can_edit() {
		global $INFO;
		global $auth;

		if ($auth->getUserData($INFO['client']) == true) {
			return true;
		} else {
			return false;
		}
	}
	private function user_can_view() {
		global $INFO;
		global $auth;

		if ($auth->getUserData($INFO['client']) == true) {
			return true;
		} else {
			return false;
		}
	}
	private function user_is_moderator() {
		global $INFO;
		global $auth;

		$data = $auth->getUserData($INFO['client']);
		if ($data == false) {
			return false;
		} elseif (in_array('bds_moderator', $data['grps'])) {
			return true;	
		} elseif (in_array('admin', $data['grps'])) {
			return true;
		} else {
			return false;
		}
	}
	private function bds() {
		if ($this->mongo == NULL) {
			try {
				$m = new MongoClient();
				$this->mongo = $m->bds;
				$this->issues = $this->mongo->issues;
			} catch (MongoException $e) {
				//"action_plugin_bds:__construct: MongoDB: Cannot connect with 'bds' collection."
				return false;
			}
		} 

		return $this->mongo;
	}
	private function issues() {
		$bds = $this->bds();
		if ($bds == false) {
			$this->error = 'error_db_conection';
			return false;
		} else {
			try {
				return $bds->issues;
			} catch (MongoException $e) {
				$this->error = 'error_db_conection';
				return false;
			}
		}
	}

	private function _handle_main() {
		return true;
	}
	private function _handle_issue_report() {
		global $auth;

		var_dump($this->vald);

		echo '<h1>'.$this->getLang('report_issue').'</h1>';
		echo '<form action="?do=bds_issue_add" method="POST">';
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
		if ($this->user_is_moderator()) {
			$users = $auth->retrieveUsers();
			echo '<label for="executor">'.$this->getLang('executor').':</label>';
			echo '<select name="executor" id="executor">';
			//add empty option
			$users = array('' => array('name' => $this->getLang('none'))) + $users;
			foreach ($users as $key => $data) {
				$name = $data['name'];
				echo '<option';
				if (isset($_POST['executor']) && $_POST['executor'] == $key) {
					echo ' selected';
				}
				echo ' value="'.$key.'">'.$name.'</opiton>';
			}
			echo '</select>';
		}
		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
		return true;
	}

	private function _handle_issue_show($id) {
		$doc = $this->issues()->findOne(array('_id' => $id));
		if (count($doc) == 0) {
			return false;
		} else {
			var_dump($doc);
			return true;
		}
	}
	private function _handle_issues() {
		global $auth;
		$issues = $this->issues();
		if ($issues == false) {
			$this->_handle_error($this->getLang($this->error));
		} else {
			$doc = $issues->find()->sort(array('_id' => -1));
			echo '<table>';
			echo '<tr>';	
			echo '<th>'.$this->getLang('id').'</th>';
			echo '<th>'.$this->getLang('type').'</th>';
			echo '<th>'.$this->getLang('title').'</th>';
			//echo '<th>'.$this->getLang('reporter').'</th>';
			echo '<th>'.$this->getLang('coordinator').'</th>';
			echo '</tr>';	
			foreach ($doc as $cursor) {
				echo '<tr>';	
				echo '<td><a href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'">#'.$cursor['_id'].'</a></td>';
				echo '<td>'.$this->issue_types[$cursor['type']].'</td>';
				echo '<td>'.$cursor['title'].'</td>';
				/*$data = $auth->getUserData($cursor['reporter']);
				echo '<td>'.$data['name'].'</td>';*/
				if (isset($cursor['coordinator'])) {
					$data = $auth->getUserData($cursor['coordinator']);
					echo '<td>'.$data['name'].'</td>';
				} else {
					$data = $auth->getUserData($cursor['reporter']);
					if ($data == false) {
						$rep_name = '('.$this->getLang('account_removed').')';
					} else {
						$rep_name = $data['name'];
					}
					echo '<td><em>'.$this->getLang('none').' - '.$this->getLang('proposal').' '.$this->getLang('reported_by').' '.$rep_name.'</em></td>';
					//echo '<td><em>'.$this->getLang('none').'</em></td>';
				}
				/*if (isset($cursor['executor'])) {
					$data = $auth->getUserData($cursor['executor']);
					echo '<td>'.$data['name'].'</td>';
				} else {
					echo '<td><em>'.$this->getLang('executor_not_specified').'</em></td>';
				}*/
				echo '</tr>';	
			}
			echo '</table>';
		}
	}

	private function _handle_error($msg) {
		echo '<span class="bds_error">'.$msg.'</span>';
	}

	public function handle_act_preprocess(&$event, $param) {
		global $INFO;
		global $auth;
		if ( ! $this->user_can_view()) {
			return false;
		}
		switch($event->data) {
			case 'bds_main':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issue_add':
			case 'bds_issues':
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

				//executor
				if ($this->user_is_moderator()) {
					//if '' do not count this filed
					if ($_POST['executor'] != '') {
						$users = $auth->retrieveUsers();
						if ( ! array_key_exists($_POST['executor'], $users)) {
							$this->vald['executor'] = $this->getLang('vald_executor_required');
						} else {
							$post['executor'] = $_POST['executor'];
						}
					}
				}


				if (count($this->vald) == 0) {
					$issues = $this->issues();
					if ($issues == false) {
						$event->data = 'bds_error';
					} else {
						$cursor = iterator_to_array($issues->find(array(), array('_id' => 1))->sort(array('_id' => -1))->limit(1));
						if (count($cursor) == 0) {
							$min_nr = 1;
						} else {
							$cursor = array_pop($cursor);
							$min_nr = $cursor['_id'] + 1;
						}
						$post['_id'] = $min_nr;
						$post['reporter'] = $INFO['client'];
						if ($this->user_is_moderator()) {
							$post['coordinator'] = $INFO['client'];
						}
						$post['date'] = time();
						try {
							$this->issues->insert($post);
							$_GET['bds_issue_id'] = $min_nr;
							$event->data = 'bds_issue_show';
						} catch(MongoException $e) {
							$this->error = 'error_issue_instert';
							$event->data = 'bds_error';
						}
					}
				} else {
					$event->data = 'bds_issue_report';
				}
				break;
		}
	}

	public function handle_act_unknown(& $event, $param) {
		if ( ! $this->user_can_view()) {
			return false;
		}
		switch ($event->data) {
			case 'bds_main':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issues':
			case 'bds_error':
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
				if ( ! isset($_GET['bds_issue_id'])) {
					$this->_handle_error($this->getLang('error_issue_id_not_specifed'));
				} else {
					$id = (int)$_GET['bds_issue_id'];
					if ($this->_handle_issue_show($id) == false) {
						$this->_handle_error($this->getLang('error_issue_id_unknown'));
					}
				}
				break;
			case 'bds_issues':
				$this->_handle_issues();
				break;
			case 'bds_error':
				$this->_handle_error($this->getLang($this->error));
				break;
		}
	}
 
	public function add_menu_item(&$event, $param) {
		global $lang;

		if ( ! $this->user_can_view()) {
			return false;
		}

		$lang['btn_bds_main'] = $this->getLang('bds_main');
		$lang['btn_bds_issues'] = $this->getLang('bds_issues');

		if ($this->user_can_edit()) {
			$lang['btn_bds_issue_report'] = $this->getLang('bds_issue_report');
		}
		$lang['btn_bds_reports'] = $this->getLang('bds_reports');

		$event->data['items']['separator'] = '<li>|</li>';

		$event->data['items']['bds_main'] = tpl_action('bds_main', 1, 'li', 1);
		$event->data['items']['bds_issues'] = tpl_action('bds_issues', 1, 'li', 1);
		if ($this->user_can_edit()) {
			$event->data['items']['bds_issue_report'] = tpl_action('bds_issue_report', 1, 'li', 1);
		}
		$event->data['items']['bds_reports'] = tpl_action('bds_reports', 1, 'li', 1);
	}
	public function add_action(&$event, $param) {
		if ( ! $this->user_can_view()) {
			return false;
		}
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

