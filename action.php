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

		$this->issue_states[0] = $this->getLang('state_proposal');
		$this->issue_states[1] = $this->getLang('state_opened');
		$this->issue_states[2] = $this->getLang('state_rejected');
		$this->issue_states[3] = $this->getLang('state_effective');
		$this->issue_states[4] = $this->getLang('state_ineffective');

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
	private function html_generate_report_form($action, $default=array()) {
		$value = array();
		if (isset($_POST['type'])) {
			$value['type'] = $_POST['type'];
		} elseif (isset($default['type'])) {
			$value['type'] = $default['type'];
		} else {
			$value['type'] = '';
		}

		if (isset($_POST['title'])) {
			$value['title'] = $_POST['title'];
		} elseif (isset($default['title'])) {
			$value['title'] = $default['title'];
		} else {
			$value['title'] = '';
		}

		if (isset($_POST['description'])) {
			$value['description'] = $_POST['description'];
		} elseif (isset($default['description'])) {
			$value['description'] = $default['description'];
		} else {
			$value['description'] = '';
		}

		var_dump($this->vald);

		echo '<form action="'.$action.'" method="POST">';
		echo '<label for="type">'.$this->getLang('type').':</label>';
		echo '<select name="type" id="type">';
		foreach ($this->issue_types as $key => $name) {
			echo '<option';
			if ($value['type'] == $key) {
				echo ' selected';
			}
			echo ' value="'.$key.'">'.$name.'</opiton>';
		}
		echo '</select>';
		echo '<label for="title">'.$this->getLang('title').':</label>';
		echo '<input name="title" id="title" value="'.$value['title'].'">';
		echo '<label for="description">'.$this->getLang('description').':</label>';
		echo '<textarea name="description" id="description">';
		echo $value['description'];
		echo '</textarea>';
		/*if ($this->user_is_moderator()) {
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
		}*/
		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
	}
	private function html_issue_link($id) {
		return '<a href="?do=bds_issue_show&bds_issue_id='.$id.'">#'.$id.'</a>';
	}
	private function html_coordinator($cursor) {
		if (isset($cursor['coordinator'])) {
			$coordinator = $this->string_get_full_name($cursor['coordinator']);
			if ($coordinator == '') {
				$coordinator = '<em>('.$this->getLang('account_removed').')</em>';
			}
			return $coordinator;
		} else {
			$reporter = $this->string_get_full_name($cursor['reporter']);
			if ($reporter == '') {
				$reporter = '('.$this->getLang('account_removed').')';
			}
			return '<em>'.$this->getLang('none').' - '.$this->getLang('proposal').' '.$this->getLang('reported_by').' '.$reporter.'</em>';
		}
	}
	private function string_time_to_now($date) {
		return $date;
	}
	private function string_get_full_name($name) {
		global $auth;
		$data = $auth->getUserData($name);
		if ($data == false) {
			return '';
		} else {
			return $data['name'];
		}
	}
	private function bds() {
		if ($this->mongo == NULL) {
			try {
				$m = new MongoClient();
				$this->mongo = $m->bds;
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
			return false;
		} else {
			return $bds->issues;
		}
	}

	private function _handle_main() {
		return true;
	}
	private function _handle_issue_report() {
		global $auth;

		echo '<h1>'.$this->getLang('report_issue').'</h1>';
		$this->html_generate_report_form('?do=bds_issue_add');
		return true;
	}

	private function _handle_issue_show($id) {
		global $auth;
		$cursor = $this->issues()->findOne(array('_id' => $id));
		if (count($cursor) == 0) {
			return false;
		} else {
			echo '<div id="bds_issue_box">';
			echo '<h1>';
			echo $this->html_issue_link($id);
			echo ' ';
			echo $this->issue_states[$cursor['state']];
			echo ' ';
			echo $this->issue_types[$cursor['type']];
			echo '</h1>';

			echo '<h1>';
			echo $cursor['title'];
			echo '</h1>';

			echo '<div class="time_box">';
			echo '<span>';
			echo $this->getLang('opened_for');
			echo ': ';
			echo '</span>';
			echo '<span>';
			echo $this->string_time_to_now($cursor['date']);
			if (isset($cursor['last_modified_date'])) {
				echo $this->getLang('last_modified');
				echo ': ';
				echo '</span>';
				echo $this->string_time_to_now($cursor['last_modified_date']);
			}
			echo '</div>';

			echo '<table>';
			echo '<tr>';

			echo '<th>'.$this->getLang('reporter').'</th>';

			echo '<td>'.$this->string_get_full_name($cursor['reporter']).'</td>';

			echo '<th>'.$this->getLang('coordinator').'</th>';
			echo '<td>'.$this->html_coordinator($cursor).'</td>';
			echo '</tr>';	
			echo '</table>';

			$desc_author = $cursor['reporter'];
			echo '<h2>';
			echo $this->getLang('description');
			echo  '('.$this->getLang('last_modified_by').' '.$desc_author.')';
			echo '</h2>';

			echo '<p>'.$cursor['description'].'</p>';


			echo '</div>';

			echo '<h1>'.$this->getLang('changes_history').'</h1>';
			echo '<h1>'.$this->getLang('add_comment').'</h1>';
			echo '<h1>'.$this->getLang('change_issue').'</h1>';
			$this->html_generate_report_form('?do=bds_issue_change&bds_issue_id='.$cursor['_id'], $cursor);
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
			echo '<th>'.$this->getLang('state').'</th>';
			echo '<th>'.$this->getLang('type').'</th>';
			echo '<th>'.$this->getLang('title').'</th>';
			echo '<th>'.$this->getLang('coordinator').'</th>';
			echo '<th>'.$this->getLang('created').'</th>';
			echo '</tr>';	
			foreach ($doc as $cursor) {
				echo '<tr>';	
				echo '<td>'.$this->html_issue_link($cursor['_id']).'</td>';
				echo '<td>'.$this->issue_states[$cursor['state']].'</td>';
				echo '<td>'.$this->issue_types[$cursor['type']].'</td>';
				echo '<td>'.$cursor['title'].'</td>';
				echo '<td>'.$this->html_coordinator($cursor).'</td>';
				echo '<td>'.date($this->getConf('date_format'), $cursor['date']).'</td>';
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
			case 'bds_issue_change':
			case 'bds_issues':
				$event->stopPropagation();
				$event->preventDefault();
				break;
		}
		switch($event->data) {
			case 'bds_issue_add':
			case 'bds_issue_change':
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

				$_POST['description'] = trim($_POST['description']);
				if (strlen($_POST['description']) == 0) {
					$this->vald['description'] = $this->getLang('vald_desc_required');
				} else if (strlen($_POST['description']) > $this->getConf('desc_max_len')) {
					$this->vald['description'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_desc_too_long'));
				} else {
					$post['description'] = $_POST['description'];
				}

				//executor
				/*if ($this->user_is_moderator()) {
					//if '' do not count this filed
					if ($_POST['executor'] != '') {
						$users = $auth->retrieveUsers();
						if ( ! array_key_exists($_POST['executor'], $users)) {
							$this->vald['executor'] = $this->getLang('vald_executor_required');
						} else {
							$post['executor'] = $_POST['executor'];
						}
					}
				}*/


				switch($event->data) {
					case 'bds_issue_add':
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
								if ($this->user_is_moderator()) {
									$post['state'] = 1;
								} else {
									$post['state'] = 0;
								}
								$post['date'] = time();

								try {
									$issues->insert($post);
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
					case 'bds_issue_change':
						//_id -> $_GET['bds_issue_id'];
						if (count($this->vald) == 0) {
							$issues = $this->issues();
							if ($issues == false) {
								$event->data = 'bds_error';
							} else {
								try {
									$id = (int)$_GET['bds_issue_id'];
									$issues->update(array('_id' => $id), array('$set' => $post));
									$event->data = 'bds_issue_show';
									} catch(MongoException $e) {
										$this->error = 'error_issue_update';
										$event->data = 'bds_error';
									}
							}
						} else {
							$event->data = 'bds_issue_show';
						}
						break;
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

