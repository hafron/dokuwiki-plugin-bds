<?php
/**
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */
 
if(!defined('DOKU_INC')) die();
 
class action_plugin_bds extends DokuWiki_Action_Plugin {
	
	private $mongo = NULL;

	private $helper;
	//Validation feedback for issue forms
	private $vald = array();
	//Validanion feedback for comments
	private $vald_comment = array();
	private $issue_types = array();
	private $event_types = array();
	private $task_classes = array();
	private $blocking_states = array();

	private $anchor = '';

 
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

		$this->task_classes = array('mong', 'eu');

		$this->blocking_states = array(2, 3, 4);

		$this->event_types[0] = 'change';
		$this->event_types[1] = 'comment';
		$this->event_types[2] = 'task';

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
	private function user_is_moderator($user=NULL) {
		global $INFO;
		global $auth;
		if ($user == NULL)
			$user = $INFO['client'];


		$data = $auth->getUserData($user);
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
	private function user_exists($user=NULL) {
		global $INFO;
		global $auth;

		$data = $auth->getUserData($user);
		if ($data == false) {
			return false;
		} else {
			return true;
		}
	}

	private function wiki_parse($content) {
		$info = array();
		return p_render('xhtml',p_get_instructions($content), $info);
	}
	private function html_generate_report_form($action, $default=array()) {
		global $auth;
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

		if (isset($_POST['coordinator'])) {
			$value['coordinator'] = $_POST['coordinator'];
		} elseif (isset($default['coordinator'])) {
			$value['coordinator'] = $default['coordinator'];
		} else {
			$value['coordinator'] = '';
		}

		if (isset($_POST['state'])) {
			$value['state'] = $_POST['state'];
		} elseif (isset($default['state'])) {
			$value['state'] = $default['state'];
		} else {
			$value['state'] = '';
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

		//edit case
		if (count($default) > 0) {
			if ($this->user_is_moderator()) {
				$users = $auth->retrieveUsers();
				echo '<label for="coordinator">'.$this->getLang('coordinator').':</label>';
				echo '<select name="coordinator" id="coordinator">';
				foreach ($users as $key => $data) {
					if ($this->user_is_moderator($key)) {
						$name = $data['name'];
						echo '<option';
						if ($value['coordinator'] == $key) {
							echo ' selected';
						}
						echo ' value="'.$key.'">'.$name.'</opiton>';
					}
				}
				echo '</select>';

				echo '<label for="state">'.$this->getLang('state').':</label>';
				echo '<select name="state" id="state">';
				foreach ($this->issue_states as $key => $state) {
					echo '<option';
					if ($value['state'] == $key) {
						echo ' selected';
					}
					echo ' value="'.$key.'">'.$state.'</opiton>';
				}
				echo '</select>';
			}
		}
		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
	}

	//$this->html_generate_event_form($action, $cursor, 
	//			array('submit' => $this->getLang('comment'), 'header' => array('name' => 'comment_form', 'value' => $this->getLang('add_comment'))));
	//		$default - default options for fields
	function html_generate_event_form($action, $cursor, $data, $default=array()) {
		global $auth;
	
		$type = $data['header']['name'];
		echo '<a name="'.$data['header']['name'].'"><h1>'.$data['header']['value'].'</h1></a>';

		if ($data['event'] == $data['do']) {
			var_dump($this->vald_comment);
		}
		$value = array();

		if (isset($data['replay_to'])) {
			$action .= '&replay_to='.$data['replay_to'];
		}	
		echo '<form action="'.$action.'#'.$data['header']['name'].'" method="POST">';
		echo '<input type="hidden" name="event" value="comment">';
		echo '<textarea name="content" id="content">';

		if (isset($data['replay_to']) && !isset($_POST['content'])) {
			echo $this->get_event_replay_content($cursor['_id'], (int) $data['replay_to']);
		} else if (isset($_POST['content']) && count($this->vald_comment) > 0) {
			echo $_POST['content'];
		}
		echo '</textarea>';

		if ($this->user_is_moderator() && $type == 'task_form') {
			if (isset($_POST['state'])) {
				$value['state'] = $_POST['state'];
			} elseif (isset($default['state'])) {
				$value['state'] = $default['state'];
			} else {
				$value['state'] = '';
			}

			if (isset($_POST['class'])) {
				$value['class'] = $_POST['class'];
			} elseif (isset($default['class'])) {
				$value['class'] = $default['class'];
			} else {
				$value['class'] = '';
			}

			if (isset($_POST['executor'])) {
				$value['executor'] = $_POST['state'];
			} elseif (isset($default['executor'])) {
				$value['executor'] = $default['state'];
			} else {
				$value['executor'] = '';
			}

			if (isset($_POST['cost'])) {
				$value['cost'] = $_POST['cost'];
			} elseif (isset($default['cost'])) {
				$value['cost'] = $default['cost'];
			} else {
				$value['cost'] = '';
			}

			echo '<label for="executor">'.$this->getLang('executor').':</label>';
			echo '<select name="executor" id="executor">';
			$users = $auth->retrieveUsers();
			foreach ($users as $key => $user_data) {
				$name = $user_data['name'];
				echo '<option';
				if ($value['executor'] == $key) {
					echo ' selected';
				}
				echo ' value="'.$key.'">'.$name.'</opiton>';
			}
			echo '</select>';

			echo '<label for="class">'.$this->getLang('class').':</label>';
			echo '<select name="class" id="class">';
			foreach ($this->task_classes as $key => $name) {
				echo '<option';
				if ($value['class'] == $name) {
					echo ' selected';
				}
				echo ' value="'.$name.'">'.$name.'</opiton>';
			}
			echo '</select>';

			echo '<label for="cost">'.$this->getLang('cost').':</label>';
			echo '<input name="cost" id="cost" value="'.$value['cost'].'">';

		}
		echo '<input type="submit" value="'.$data['submit'].'">';
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
	private function html_anchor_to_event($issue_id, $replay_to) {
		return '#'.$issue_id.':'.$replay_to;
	}
	private function string_time_to_now($date) {
		return $this->string_format_field('date', $date);
	}
	private function string_get_full_name($name) {
		return $this->string_format_field('name', $name);
	}
	private function string_format_field($type, $value) {
		global $auth;
		switch ($type) {
			case 'type':
				return $this->issue_types[$value];
				break;
			case 'state':
				return $this->issue_states[$value];
				break;
			case 'date':
				$diff = time() - $value;
				if ($diff < 5) {
					return $this->getLang('just_now');
				}
				$time_str = '';
				$minutes = floor($diff/60);
				if ($minutes > 0) {
					$hours = floor($minutes/60);
					if ($hours > 0) {
						$days = floor($hours/24);
						if ($days > 0) {
							$time_str = $days.' '.$this->getLang('days');
						} else {
							$time_str = $hours.' '.$this->getLang('hours');
						}
					} else {
						$time_str = $minutes.' '.$this->getLang('minutes');
					}
				} else {
					$time_str = $diff.' '.$this->getLang('seconds');
				}
				$time_str .= ' '.$this->getLang('ago');
				return $time_str;
				break;
			case 'name':
			case 'coordinator':
				$data = $auth->getUserData($value);
				if ($data == false) {
					return '';
				} else {
					return $data['name'];
				}
				break;
			default:
				return $value;
				break;
		}
	}

	private function get_event_replay_content($issue_id, $event_id) {
		$cursor = $this->issues()->findOne(array('_id' => $issue_id));
		$content = '';
		foreach ($cursor['events'] as $event) {
			if ($event['id'] == $event_id) {
				if (isset($event['content'])) {
					$content = "\n".$event['content'];
				}
			}
		}

		$content = $this->getLang('replay_to').' #'.$issue_id.':'.$event_id.$content;
		$quoted_content = '';
		$content_lines = explode("\n", $content);
		foreach ($content_lines as $line) {
			$quoted_content .= "\n>".$line;
		}

		return $quoted_content;
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
			echo $this->issue_types[$cursor['type']];
			echo ' (';
			echo $this->issue_states[$cursor['state']];
			echo ')';
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
			if (isset($cursor['last_mod_date'])) {
				echo $this->getLang('last_modified');
				echo ': ';
				echo $this->string_time_to_now($cursor['last_mod_date']);
				echo '</span>';
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

			if (isset($cursor['last_description_author'])) {
				$desc_author = $cursor['last_description_author'];
			} else {
				$desc_author = $cursor['reporter'];
			}
			echo '<h2>';
			echo $this->getLang('description');
			echo  '('.$this->getLang('last_modified_by').' '.$this->string_get_full_name($desc_author).')';
			echo '</h2>';

			echo $this->wiki_parse($cursor['description']);


			echo '</div>';

			echo '<h1>'.$this->getLang('changes_history').'</h1>';
			if (isset($cursor['events'])) {
				foreach ($cursor['events'] as $event) {
					//create anchor
					echo '<a name="'.$event['id'].'">';
					echo '<h2>';
					switch ($event['event']) {
						case 'comment':
							echo $this->getLang('comment_added');
							break;
						case 'task':
							echo $this->getLang('task_added');
							break;
					}
					echo ' ';
					echo $this->string_time_to_now($event['date']);
					echo ' ';
					echo $this->getLang('by');
					echo ' ';
					echo $this->string_get_full_name($event['author']);
					echo '</h2>';
					echo '</a>';
					if (isset($event['replay_to'])) {
						echo '<h3>';
						echo $this->getLang('replay_to');
						echo ' ';
						echo $this->html_anchor_to_event($cursor['_id'], $event['replay_to']);
						echo '</h3>';
					}
					if (isset($event['quoted_in'])) {
						echo '<h3>';
						echo $this->getLang('quoted_in');
						echo ': ';
						foreach ($event['quoted_in'] as $event_id) {
							echo $this->html_anchor_to_event($cursor['_id'], $event_id);
							echo ' ';
						}
						echo '</h3>';
					}
					echo '<a href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_to='.$event['id'].'#comment_form">'.$this->getLang('replay').'</a>';
					echo ' ';
					if ($this->user_is_moderator()) {
						echo '<a href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_by_task='.$event['id'].'#task_form">'.$this->getLang('replay_by_task').'</a>';
						echo ' ';
					}
					echo '<a href="?do=bds_issue_change_event&bds_issue_id='.$cursor['_id'].'&event_id='.$event['id'].'">'.$this->getLang('edit').'</a>';

					switch ($event['type']) {
						case 'change':
						echo '<ul>';
						foreach($event['new'] as $field => $new) {
							echo '<li>';
							echo '<strong>';
							echo $this->getLang($field);
							echo '</strong>';
							echo ' ';
							if ($field == 'description') {
								echo $this->getLang('modified');
								echo '(';
								echo $this->getLang('diff');
								echo ')';
							} else {
								echo ' ';
								echo $this->getLang('changed_field');
								echo ' ';
								echo $this->getLang('from');
								echo ' ';
								echo '<em>';
								echo $this->string_format_field($field, $event['prev'][$field]);
								echo '</em>';
								echo ' ';
								echo $this->getLang('to');
								echo ' ';
								echo '<em>';
								echo $this->string_format_field($field, $new);
								echo '</em>';
							}
							echo '</li>';
						}
						echo '</ul>';
						break;
					}
					if (isset($event['content'])) {
						echo $this->wiki_parse($event['content']);
					}
				}
			}


			if (in_array($cursor['state'], $this->blocking_states)) {
				echo '<hr>';
				echo '<p>';
				$com = str_replace('%d', $this->string_format_field('date', $cursor['last_mod_date']), $this->getLang('issue_closed'));
				$com = str_replace('%u', $this->string_format_field('name', $cursor['last_mod_author']), $com);
				echo $com;
				echo '</p>';
				if ($this->user_is_moderator()) {
					echo '<h1>'.$this->getLang('reopen_issue').'</h1>';
					$action = '?do=bds_issue_reopen&bds_issue_id='.$cursor['_id'].'';
					var_dump($this->vald);
					echo '<form action="'.$action.'" method="post">';
					echo '<label for="state">'.$this->getLang('state').':</label>';
					echo '<select name="state" id="state">';
					foreach ($this->issue_states as $key => $state) {
						echo '<option';
						if ($value['state'] == $key) {
							echo ' selected';
						}
						echo ' value="'.$key.'">'.$state.'</opiton>';
					}
					echo '</select>';
					echo '<input type="submit" value="'.$this->getLang('save').'">';
					echo '</form>';
				}
			} else {

				$action = '?do=bds_issue_add_event&bds_issue_id='.$cursor['_id'].'';
				$this->html_generate_event_form($action, $cursor, 
					array('submit' => $this->getLang('comment'), 'header' => array('name' => 'comment_form', 'value' => $this->getLang('add_comment')), 'event' => $_GET['do'], 'do' => 'bds_issue_add_event', 'replay_to' => $_GET['replay_to']));

				if ($this->user_is_moderator() ) {
					$action = '?do=bds_issue_add_task&bds_issue_id='.$cursor['_id'].'';
					$this->html_generate_event_form($action, $cursor, 
						array('submit' => $this->getLang('add'), 'header' => array('name' => 'task_form', 'value' => $this->getLang('add_task')), 'event' => $_GET['do'], 'do' => 'bds_issue_add_task', 'replay_to' => $_GET['replay_by_task']));
				}


				echo '<h1>'.$this->getLang('change_issue').'</h1>';
				$action = '?do=bds_issue_change&bds_issue_id='.$cursor['_id'];
				$this->html_generate_report_form($action, $cursor);
			}
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
			case 'bds_issue_reopen':
			case 'bds_issue_add_event':
			case 'bds_issue_add_task':
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

				//check only state when user is reopening issue
				case 'bds_issue_reopen':
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
						if ($this->user_is_moderator($_POST['coordinator'])) {
							$post['coordinator'] = $_POST['coordinator'];
						} else {
							$this->vald['type'] = $this->getLang('vald_coordinator_required');
						}

					//check only state when user is reopening issue
					case 'bds_issue_reopen':
						if ( ! array_key_exists((int)$_POST['state'], $this->issue_states)) {
							$this->vald['state'] = $this->getLang('vald_state_required');
						} else {
							$post['state'] = (int)$_POST['state'];
						}

						if (count($this->vald) == 0) {
							$issues = $this->issues();
							if ($issues == false) {
								$event->data = 'bds_error';
							} else {
								try {
									$id = (int)$_GET['bds_issue_id'];

									if ($event->data == 'bds_issue_reopen') {
										//reopening user become corodinatro
										$post['coordinator'] = $INFO['client'];
									}

									//determine changes
									$cursor = $this->issues()->findOne(array('_id' => $id));
									$new = array_diff_assoc($post, $cursor);
									$prev = array_diff_assoc($cursor, $post);
									//something was changed
									if (count($new) > 0) {
										$events = array();

										//start from 1
										$events['id'] = count($cursor['events']) + 1;
										$events['type'] = 'change';
										$events['author'] = $INFO['client'];
										$events['date'] = time();
										$events['new'] = $new;
										$events['prev'] = $prev;

										$post['last_mod_author'] = $INFO['client'];
										$post['last_mod_date'] = time();

										if ($post['description'] != $cursor['description']) {
											$post['last_description_author'] = $INFO['client'];
										}

										$issues->update(array('_id' => $id), array('$set' => $post)); 
										
										$issues->update(array('_id' => $id), array('$push' => 
												array('events' => $events)
											));
									}
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
			case 'bds_issue_add_task':
				if ($this->user_is_moderator()) {
					if ($this->user_exists($_POST['executor'])) {
						$post['executor'] = $_POST['executor'];
					} else {
						$this->vald_comment['executor'] = $this->getLang('vald_executor_not_exists');
					}


					//cost is not required
					if ($_POST['cost'] != '') {
						//remove not nessesery chars
						$separators = array(' ', $this->getConf('numbers_separator'));	
						$fract_sep = $this->getConf('fractional_separator');

						$cost = str_replace($separators, '', $_POST['cost']);
						$cost_ex = explode($fract_sep, $cost);

						if (count($cost_ex) > 2 || ! ctype_digit($cost_ex[0])) {
							$this->vald_comment['cost'] = $this->getLang('vald_cost_wrong_format');
						} elseif (isset($cost_ex[1]) && ! ctype_digit($cost_ex[1])) {
							$this->vald_comment['cost'] = $this->getLang('vald_cost_wrong_format');
						} elseif ( (int)implode('.', $cost) > (int)$this->getConf('cost_max')) {
							$this->vald_comment['cost'] = str_replace('%d', $this->getConf('cost_max'), $this->getLang('vald_cost_too_big'));
						} else {
							$post['cost'] = $_POST['cost'];
						}
					}

					if ( ! in_array($_POST['class'], $this->task_classes)) {
						$this->vald_comment['class'] = $this->getLang('vald_class_required');
					} else {
						$post['class'] = $_POST['class'];
					}

				} else {
					$this->error = 'error_task_add';
					$event->data = 'bds_error';
				}
				$post['event'] = 'task';
				//FALL THROUGH
			case 'bds_issue_add_event':
				if ( ! isset($post['event'])) {
					$post['event'] = 'comment';
				}
				//_id -> $_GET['bds_issue_id'];

				$_POST['content'] = trim($_POST['content']);
				if (strlen($_POST['content']) == 0) {
					$this->vald_comment['content'] = $this->getLang('vald_content_required');
				} else if (strlen($_POST['content']) > $this->getConf('desc_max_len')) {
					$this->vald_comment['content'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_content_too_long'));
				} else {
					$post['content'] = $_POST['content'];
				}
				
				try {
					$id = (int)$_GET['bds_issue_id'];
					$cursor = $this->issues()->findOne(array('_id' => $id));

					if (isset($_GET['replay_to'])) {
						$replay_to = (int) $_GET['replay_to'];
						$found = false;
						foreach ($cursor['events'] as $key => $ev) {
							if ($ev['id'] == $replay_to) {
								$found = true;
								break;
							}
						}
						if ($found == false) {
							$this->vald_comment['event'] = $this->getLang('vald_replay_to_not_exists');
						} else {
							$post['replay_to'] = $replay_to;
						}
					}

					if (count($this->vald_comment) == 0) {
						$issues = $this->issues();
						if ($issues == false) {
							$event->data = 'bds_error';
						} else {
							//start from 1;
							$post['id'] = count($cursor['events']) + 1;
							$post['author'] = $INFO['client'];
							$post['date'] = time();

							$issue['last_mod_author'] = $INFO['client'];
							$issue['last_mod_date'] = time();

							$issues->update(array('_id' => $id), array('$set' => $issue)); 

							if (isset($_GET['replay_to'])) {
								$quoted['events.$.quoted_in'] = $post['id'];
								$issues->update(array('_id' => $id, 'events.id' => $replay_to), 
													array('$push' => $quoted)); 
							}
							
							$issues->update(array('_id' => $id), array('$push' => 
										array('events' => $post)
									));

							$event->data = 'bds_issue_show';
							//scroll down to new one
							$this->anchor = $post['id'];
						}
					} else {
						$event->data = 'bds_issue_show';
					}
				} catch(MongoException $e) {
					$this->error = 'error_issue_update';
					$event->data = 'bds_error';
				}
		}
		//need relocating
		if ($this->anchor != '') {
			$get = array();
			foreach ($_GET as $k => $v) {
				$get[$k] = $v;
			}
			//remember about event->data
			$get['do'] = $event->data;

			//some special changes
			if (count($this->vald_comment) == 0) {
				unset($get['replay_to']);
			}
			$url = '?';
			foreach ($get as $k => $v) {
				$url .= urlencode($k).'='.urlencode($v).'&';
			}
			//remove last &
			$url = substr($url, 0, -1);
			$url .= '#'.urlencode($this->anchor);
			header('Location: '.$url);
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

