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
	private $root_causes = array();
	private $task_states = array();
	private $blocking_states = array();
	private $entity = array();

	private $anchor = '';

 
	/**
	 * Register its handlers with the DokuWiki's event controller
	 */
	public function register(Doku_Event_Handler $controller) {
		$controller->register_hook('TEMPLATE_SITETOOLS_DISPLAY', 'BEFORE', $this,
								   'add_menu_item');
		$controller->register_hook('TEMPLATE_ACTION_GET', 'BEFORE', $this,
								   'add_action');
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this,
								   'handle_act_preprocess');
		$controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'handle_act_unknown');
	}
	public function __construct() {
		//$lc = $_SESSION[DOKU_COOKIE]['translationlc'];
		$lc = $_COOKIE['newlc'];
		if (isset($lc) && $lc != '') {
			$path = DOKU_PLUGIN.$this->getPluginName().'/lang/';
			$lang = array();
			// don't include once, in case several plugin components require the same language file
			@include($path.$lc.'/lang.php');
			$this->lang = $lang;
			$this->localised = true;
		}

		$this->issue_types[0] = $this->getLang('type_noneconformity');
		$this->issue_types[1] = $this->getLang('type_complaint');
		$this->issue_types[2] = $this->getLang('type_risk');

		$this->issue_states[0] = $this->getLang('state_proposal');
		$this->issue_states[1] = $this->getLang('state_opened');
		$this->issue_states[2] = $this->getLang('state_rejected');
		$this->issue_states[3] = $this->getLang('state_effective');
		$this->issue_states[4] = $this->getLang('state_ineffective');

		$this->task_classes[0] = $this->getLang('correction');
		$this->task_classes[1] = $this->getLang('corrective_action');
		$this->task_classes[2] = $this->getLang('preventive_action');

		$this->root_causes[0] = $this->getLang('none_comment');
		$this->root_causes[1] = $this->getLang('manpower');
		$this->root_causes[2] = $this->getLang('method');
		$this->root_causes[3] = $this->getLang('machine');
		$this->root_causes[4] = $this->getLang('material');
		$this->root_causes[5] = $this->getLang('managment');
		$this->root_causes[6] = $this->getLang('measurement');
		$this->root_causes[7] = $this->getLang('money');
		$this->root_causes[8] = $this->getLang('environment');

		$this->blocking_states = array(2, 3, 4);

		$this->event_types[0] = 'change';
		$this->event_types[1] = 'comment';
		$this->event_types[2] = 'task';

		$this->task_states[0] = $this->getLang('task_opened');
		$this->task_states[1] = $this->getLang('task_done');
		$this->task_states[2] = $this->getLang('task_rejected');

		$this->helper = $this->loadHelper('bds');

		$this->entity = array();
		$entitis_ex = explode(',', $this->getConf('entitis'));
		foreach ($entitis_ex as $entity) {
			$this->entity[] = trim($entity);
		}
	}

	private function localeFN($id,$ext='txt') {
		global $conf;

		$file = DOKU_PLUGIN.'bds/lang/'.$conf['lang'].'/'.$id.'.'.$ext;
		if(!@file_exists($file)){
			  $file = DOKU_PLUGIN.'bds/lang/en/'.$id.'.'.$ext;
		}
		return $file;
	}
	private function rawLocale($id, $ext = 'txt') {
		return io_readFile($this->localeFN($id, $ext));
	}

	private function get_email($user) {
		global $auth;
		$data = $auth->getUserData($user);
		return $data['mail'];

	}

	private function get_name($user) {
		global $auth;
		$data = $auth->getUserData($user);
		return $data['name'];

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

		if (isset($_POST['opinion'])) {
			$value['opinion'] = $_POST['opinion'];
		} elseif (isset($default['opinion'])) {
			$value['opinion'] = $default['opinion'];
		} else {
			$value['opinion'] = '';
		}

		if (isset($_POST['entity'])) {
			$value['entity'] = $_POST['entity'];
		} elseif (isset($default['entity'])) {
			$value['entity'] = $default['entity'];
		} else {
			$value['entity'] = '';
		}

		foreach ($this->vald as $error) {
			echo '<div class="error">';
			echo $error;
			echo '</div>';
		}

		echo '<form action="'.$action.'" method="POST">';
		echo '<filedset class="bds_form">';
		echo '<div class="row">';
		echo '<label for="type">'.$this->getLang('type').':</label>';

		echo '<span>';
		echo '<select name="type" id="type">';
		foreach ($this->issue_types as $key => $name) {
			echo '<option';
			if ($value['type'] == $key) {
				echo ' selected';
			}
			echo ' value="'.$key.'">'.$name.'</opiton>';
		}
		echo '</select>';
		echo '</span>';
		echo '</div>';

		echo '<div class="row">';
		echo '<label for="entity">'.$this->getLang('entity').':</label>';

		echo '<span>';
		echo '<select name="entity" id="entity">';
		foreach ($this->entity as $name) {
			echo '<option';
			if ($value['entity'] == $name) {
				echo ' selected';
			}
			echo ' value="'.$name.'">'.$name.'</opiton>';
		}
		echo '</select>';
		echo '</span>';
		echo '</div>';

		echo '<div class="row">';
		echo '<label for="title">'.$this->getLang('title').':</label>';
		echo '<span>';
		echo '<input name="title" id="title" value="'.$value['title'].'">';
		echo '</span>';
		echo '</div>';

		echo '<div class="row">';
		echo '<label for="description">'.$this->getLang('description').':</label>';
		echo '<span>';
		echo '<textarea name="description" id="description" class="edit">';
		echo $value['description'];
		echo '</textarea>';
		echo '</span>';
		echo '</div>';

		//edit case
		if (count($default) > 0) {
			if ($this->user_is_moderator()) {
				$users = $auth->retrieveUsers();
				echo '<div class="row">';
				echo '<label for="coordinator">'.$this->getLang('coordinator').':</label>';
				echo '<span>';
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
				echo '</span>';
				echo '</div>';

				echo '<div class="row">';
				echo '<label for="state">'.$this->getLang('state').':</label>';
				echo '<span>';
				echo '<select name="state" id="state">';
				foreach ($this->issue_states as $key => $state) {
					echo '<option';
					if ($value['state'] == $key) {
						echo ' selected';
					}
					echo ' value="'.$key.'">'.$state.'</opiton>';
				}
				echo '</select>';
				echo '</span>';
				echo '</div>';

				echo '<div class="row">';
				echo '<label for="opinion">'.$this->getLang('opinion').':</label>';
				echo '<span>';
				echo '<textarea name="opinion" id="opinion" class="edit">';
				echo $value['opinion'];
				echo '</textarea>';
				echo '</span>';
				echo '</div>';

			}
		}
		echo '</filedset>';

		echo '<input type="submit" value="'.$this->getLang('save').'">';
		echo '</form>';
	}

	//$this->html_generate_event_form($action, $cursor, 
	//			array('submit' => $this->getLang('comment'), 'header' => array('name' => 'comment_form', 'value' => $this->getLang('add_comment'))));
	//		$default - default options for fields
	function html_generate_event_form($action, $cursor, $data, $default=array()) {
		global $auth;
	
		$type = $data['header']['name'];

		echo '<div class="bds_block" id="'.$type.'" class="bds_block">';
		echo '<h1>'.$data['header']['value'].'</h1>';
		echo '<div class="bds_block_content">';

		if ($data['event'] == $data['do']) {
			if (isset($this->vald_comment)) {
				foreach($this->vald_comment as $error) {
					echo '<div class="error">'.$error.'</div>';
				}
			}
		}
		$value = array();

		if (isset($data['replay_to'])) {
			$action .= '&replay_to='.$data['replay_to'];
		}	
		echo '<form action="'.$action.'#'.$data['header']['name'].'" method="POST">';
		echo '<filedset class="bds_form">';
		echo '<input type="hidden" name="event" value="comment">';

		if ($type == 'comment_form') {
	
			if (isset($_POST['root_cause'])) {
				$value['root_cause'] = $_POST['root_cause'];
			} elseif (isset($default['root_cause'])) {
				$value['root_cause'] = $default['root_cause'];
			} else {
				$value['root_cause'] = '';
			}

			echo '<div class="row">';
			echo '<label for="root_cause">'.$this->getLang('root_cause').':</label>';
			echo '<span>';
			echo '<select name="root_cause" id="root_cause">';
			foreach ($this->root_causes as $key => $name) {
				echo '<option';
				if ($value['root_cause'] == $key) {
					echo ' selected';
				}
				echo ' value="'.$key.'">'.$name.'</opiton>';
			}
			echo '</select>';
			echo '</span>';
			echo '</div>';
		}

		if ($type == 'comment_form' || $this->user_is_moderator()) {
			echo '<div class="row">';
			echo '<label for="content_'.$type.'">'.$this->getLang('description').':</label>';
			echo '<span>';
			echo '<textarea name="content" id="content_'.$type.'">';

			if (isset($default['content']) && !isset($_POST['content'])) {
				echo $default['content'];
			} elseif (isset($data['replay_to']) && !isset($_POST['content'])) {
				echo $this->get_event_replay_content($cursor['_id'], (int) $data['replay_to']);
			} else if (isset($_POST['content']) && count($this->vald_comment) > 0 && $data['do'] == $_GET['do']) {
				echo $_POST['content'];
			}
			echo '</textarea>';
			echo '</span>';
			echo '</div>';
		}

		if ($type == 'task_form') {
			if ($this->user_is_moderator()) {
				if (isset($_POST['class'])) {
					$value['class'] = $_POST['class'];
				} elseif (isset($default['class'])) {
					$value['class'] = $default['class'];
				} else {
					$value['class'] = '';
				}

				if (isset($_POST['executor'])) {
					$value['executor'] = $_POST['executor'];
				} elseif (isset($default['executor'])) {
					$value['executor'] = $default['executor'];
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
			}

			if (isset($_POST['state'])) {
				$value['state'] = $_POST['state'];
			} elseif (isset($default['state'])) {
				$value['state'] = $default['state'];
			} else {
				$value['state'] = '';
			}

			if (isset($_POST['reason'])) {
				$value['reason'] = $_POST['reason'];
			} elseif (isset($default['reason'])) {
				$value['reason'] = $default['reason'];
			} else {
				$value['reason'] = '';
			}

			if ($this->user_is_moderator()) {
				echo '<div class="row">';
				echo '<label for="executor">'.$this->getLang('executor').':</label>';
				echo '<span>';
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
				echo '</span>';
				echo '</div>';

				echo '<div class="row">';
				echo '<label for="class">'.$this->getLang('class').':</label>';
				echo '<span>';
				echo '<select name="class" id="class">';
				foreach ($this->task_classes as $key => $name) {
					echo '<option';
					if ($value['class'] == $key) {
						echo ' selected';
					}
					echo ' value="'.$key.'">'.$name.'</opiton>';
				}
				echo '</select>';
				echo '</span>';
				echo '</div>';

				echo '<div class="row">';
				echo '<label for="cost">'.$this->getLang('cost').':</label>';
				echo '<span>';
				echo '<input name="cost" id="cost" value="'.$value['cost'].'">';
				echo '</span>';
				echo '</div>';
			}
		}
		if ($data['do'] == 'bds_issue_change_task') {
			echo '<div class="row">';
			echo '<label for="task_state">'.$this->getLang('task_state').':</label>';
			echo '<span>';
			echo '<select name="state" id="task_state">';
			foreach ($this->task_states as $key => $name) {
				echo '<option';
				if ($value['state'] == $key) {
					echo ' selected';
				}
				echo ' value="'.$key.'">'.$name.'</opiton>';
			}
			echo '</select>';
			echo '</span>';
			echo '</div>';
			echo '<div class="row">';
			echo '<label for="reason">'.$this->getLang('reason').':</label>';
			echo '<span>';
			echo '<textarea name="reason" id="reason">';
			echo $value['reason'];
			echo '</textarea>';
			echo '</span>';
			echo '</div>';
		}
		echo '</filedset>';
		echo '<input type="submit" value="'.$data['submit'].'">';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	private function string_issue_href($issue, $event=false, $rev=false) {
		$link = '?do=bds_issue_show&bds_issue_id='.$issue;
		if ($event !== false) {
			if ($rev !== false) {
				$link .= '&rev_ev_id='.$event.'&rev='.$rev;
			}
			$link .= '#'.$event;
		}
		return $link;
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
	private function html_anchor_to_event($issue, $event, $show_issue=false, $only_anchor=false) {
		$bds = $this->loadHelper('bds');
		return $bds->html_anchor_to_event($issue, $event, $show_issue, $only_anchor);
	}
	private function string_time_to_now($date) {
		return $this->string_format_field('date', $date);
	}
	private function string_get_full_name($name) {
		return $this->string_format_field('name', $name);
	}
	private function string_format_field($type, $value, $collection='issues') {
		global $auth;
		switch ($type) {
			case '_id':
				return $this->html_issue_link($value);
			break;
			case 'created':
			case 'true_date':
				return date($this->getConf('date_format'), $value);
			break;
			case 'type':
				return $this->issue_types[$value];
				break;
			case 'root_cause':
				return $this->root_causes[$value];
				break;
			case 'state':
				if ($collection == 'tasks') {
					return $this->task_states[$value];
				} else {
					return $this->issue_states[$value];
				}
				break;
			case 'task_state':
				return $this->task_states[$value];
				break;
			case 'class':
				return $this->task_classes[$value];
				break;
			case 'cost':
				if ($value > 0) 
					return sprintf('%.2f', $value);
				else
					return '<em>'.$this->getLang('ns').'</em>';
			break;
			case 'date':
			case 'last_mod_date':
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
			case 'executor':
				$data = $auth->getUserData($value);
				if ($data == false) {
					if ($type == 'coordinator') {
						return '<em>'.$this->getLang('none').'</em>';
					}
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
				$db_name = preg_replace('/[^a-zA-Z]/', '', $_SERVER['SERVER_NAME']);

				$m = new MongoClient();
				$this->mongo = $m->selectDB($db_name);
				return $this->mongo;
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
	private function timeline() {
		$bds = $this->bds();
		if ($bds == false) {
			return false;
		} else {
			return $bds->timeline;
		}
	}

	function html_format_change($new, $prev, $diff=array(), $collection='issue') {
		$out = '<ul>';
		foreach($new as $field => $new) {
			if (in_array($field, $diff))
				continue;

			$out .= '<li>';
			$out .= '<strong>';
			$out .= $this->getLang($field);
			$out .= '</strong>';
			$out .= ' ';
			if ($field == 'description' || $field == 'opinion' || $field == 'content') {
				$out .= $this->getLang('modified');
				$out .= '(';
				$out .= $this->getLang('diff');
				$out .= ')';
			} else {
				$out .= ' ';
				$out .= $this->getLang('changed_field');
				$out .= ' ';
				if (isset($prev[$field])) {
					$out .= $this->getLang('from');
					$out .= ' ';
					$out .= '<em>';
					$out .= $this->string_format_field($field, $prev[$field], $collection);
					$out .= '</em>';
					$out .= ' ';
				}
				$out .= $this->getLang('to');
				$out .= ' ';
				$out .= '<em>';
				$out .= $this->string_format_field($field, $new, $collection);
				$out .= '</em>';
			}
			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out;
	}

	function html_timeline_date_header($date) {
		echo '<h2>';
		echo date($this->getConf('date_format'), $date);
		echo ':';
		if (date('Ymd') == date('Ymd', $date)) {
			echo ' ';
			echo $this->getLang('today');
		} elseif (date('Ymd', strtotime("-1 days")) == date('Ymd', $date)) {
			echo ' ';
			echo $this->getLang('yesterday');
		}
		echo '</h2>';
	}
	private function _handle_timeline() {
		try {
			//this should be cached somehow
			$map = new MongoCode('function() {
				var values = {
					type: "issue_created",
					date: this.date,
					author: this.reporter,
					title: this.title,
					info: {description: this.description}
				};
				emit(this._id, values);
				for (ev in this.events) {
					var evo = this.events[ev];
					var type = evo.type;
					var id = this._id+":"+evo.id; 

					var fields = ["executor", "cost", "class", "state", "content"];

					if (type === "comment" || type === "task") {
						if (evo.rev) {
							for (rev_id = 0; rev_id < evo.rev.length; rev_id++) {
								var rev = evo.rev[rev_id];
								if (rev.last_mod_date == evo.date) {
									var sub_type = type;
									var info = {content: rev.content}
								} else {
									var sub_type = type+"_rev";
									var info = {content: rev.content, rev_len: evo.rev.length}
								}
								if (type === "task") {
									info.executor = rev.executor;
									info.cost = rev.cost;
									info.class = rev.class;
									info.state = rev.state;

									if (rev_id > 0) {
										var old_rev = evo.rev[rev_id - 1];
										info.old = {};
										for (var j = 0; j < fields.length; j++) {
											if (rev[fields[j]] != old_rev[fields[j]]) {
												info.old[fields[j]] = old_rev[fields[j]];
											}
										}
									}
									if (rev.reason) {
										info.reason = rev.reason;
									}
								}
								var values = {
									type: sub_type,
									date: rev.last_mod_date,
									author: rev.last_mod_author,
									title: this.title, 
									info: info
								};
								emit(this._id+":"+evo.id+":"+rev_id, values);
							}

							var info = {content: evo.content, rev_len: evo.rev.length};
							if (type === "task") {
								var old_rev = evo.rev[0];

								info.old = {};
								for (var j = 0; j < fields.length; j++) {
									if (evo[fields[j]] != old_rev[fields[j]]) {
										info.old[fields[j]] = old_rev[fields[j]];
									}
								}

								info.executor = evo.executor;
								info.cost = evo.cost;
								info.class = evo.class;
								info.state = evo.state;

								if (evo.reason) {
									info.reason = evo.reason;
								}
							}
							type += "_rev";
							id += ":"+"-1";
							var values = {
								type: type,
								date: evo.last_mod_date,
								author: evo.last_mod_author,
								title: this.title, 
								info: info
							};
							emit(id, values);
						} else {
							var info = {content: evo.content};
							if (type === "task") {
								info.executor = evo.executor;
								info.cost = evo.cost;
								info.class = evo.class;
								info.state = evo.state;

								if (evo.reason) {
									info.reason = evo.reason;
								}
							}
							var values = {
								type: type,
								date: evo.date,
								author: evo.author,
								title: this.title, 
								info: info
							};
							emit(id, values);
						}
				} else if (type === "change") {
					var values = {
						type: type,
						date: evo.date,
						author: evo.author,
						title: this.title, 
						info: {new: evo.new, prev: evo.prev}
					};
					emit(id, values);
				}
			}
		}');
			$reduce = new MongoCode('function(key, value) {
				var result = {
					type: value[0]["type"],
					date: value[0]["date"],
					author: value[0]["author"],
					title: value[0]["title"],
					info: value[0]["info"]
				};
				return result;
			}');
			$timeline = $this->bds()->command(array(
						'mapreduce' => 'issues',
						'map' => $map,
						'reduce' => $reduce,
						'out' => array('merge' => 'timeline')));
			$line = $this->timeline()->find()->sort(array('value.date' => -1));
		} catch (MongoException $e) {
			$this->error = 'error_timeline_show';
			$this->_handle_error($this->getLang($this->error));
			return true;
		}
		echo '<h1>'.$this->getLang('bds_timeline').'</h1>';
		echo '<dl id="bds_timeline">';
		$date = mktime(0, 0, 0);
		$this->html_timeline_date_header($date);
		$days = 0;
		foreach ($line as $id => $val) {
			$cursor = $val['value'];

			if ($cursor['date'] < $date) {
				$date -= 24*60*60;
				$days++;

				if ($days >= $this->getConf('timeline_days_shown')) {
					break;
				}
				$this->html_timeline_date_header($date);
			}

			$class = $cursor['type'];
			if (isset($cursor['info']['new']) && isset($cursor['info']['new']['state'])) {
				if (in_array($cursor['info']['new']['state'], $this->blocking_states)) {
					$class = 'issue_closed';
				} else {
					$class = 'issue_created';
				}
			} elseif ($cursor['type'] == 'task_rev') {
				if ($cursor['info']['state'] != $cursor['info']['old']['state']) {
					if ($cursor['info']['state'] != 0) {
						$class = 'task_closed';
					} else {
						$class = 'task';
					}
				}
			}

			echo '<dt class="'.$class.'" >';
			$aid = explode(':', $id);
			switch($cursor['type']) {
				case 'comment':
				case 'change':
				case 'task':
					echo '<a href="'.$this->string_issue_href($aid[0], $aid[1]).'">';
				break;
				case 'comment_rev':
				case 'task_rev':
					if ($aid[2] == -1) {
						echo '<a href="'.$this->string_issue_href($aid[0], $aid[1]).'">';
					} else {
						echo '<a href="'.$this->string_issue_href($aid[0], $aid[1], $aid[2]).'">';
					}
				break;
				case 'issue_created':
					echo '<a href="'.$this->string_issue_href($aid[0]).'">';
				break;
				case 'change':
					echo '<a href="'.$this->string_issue_href($aid[0]).'">';
				break;
				default:
					echo '<a href="#">';
				break;
			}
			echo '<span class="time">';
			echo date('H:i', $cursor['date']);
			echo '</span>';
			echo ' ';
			switch($cursor['type']) {
				case 'comment':
				case 'task':
					if ($cursor['type'] == 'task') {
						echo $this->getLang('task_added');
					} else {
						echo $this->getLang('comment_added');
					}
					echo ' ';
					echo '<span class="id">';
					echo '#'.$aid[0].':'.$aid[1];
					echo '</span>';
					echo ' ';
					echo '(';
					echo $cursor['title'];
					echo ')';
					if ($cursor['type'] == 'task') {
						echo ' ';
						echo $this->getLang('task_for');
						echo ' ';
						echo '<span class="author">';
						echo $this->string_format_field('name', $cursor['info']['executor']);
						echo '</span>';
					}
					echo ' ';
					echo $this->getLang('by');
					echo ' ';
					echo '<span class="author">';
					echo $this->string_format_field('name', $cursor['author']);
					echo '</span>';
					echo '</a>';
					echo '</dt>';
					echo '<dd>';
					echo $this->wiki_parse($cursor['info']['content']);
					echo '</dd>';

				break;
				case 'change':
				case 'change_state':
					$state = $cursor['info']['new']['state']; 
					if (isset($state)) {
						$diff = array('opinion');
						if (in_array($state, $this->blocking_states)) {
							echo $this->getLang('issue_closed');
						} else {
							echo $this->getLang('issue_reopened');
						}
					} else {
						$diff = array();
						echo $this->getLang('change_made');
					}
					echo ' ';
					echo '<span class="id">';
					echo '#'.$aid[0].':'.$aid[1];
					echo '</span>';
					echo ' ';
					echo '(';
					echo $cursor['title'];
					echo ')';
					echo ' ';
					echo $this->getLang('by');
					echo ' ';
					echo '<span class="author">';
					echo $this->string_format_field('name', $cursor['author']);
					echo '</span>';
					echo '</a>';
					echo '</dt>';
					echo '<dd>';
					echo $this->html_format_change($cursor['info']['new'], $cursor['info']['prev'], $diff);
					if (isset($cursor['info']['new']['state'])) {
						echo $this->wiki_parse($cursor['info']['new']['opinion']);
					}
					echo '</dd>';
				break;
				case 'comment_rev':
				case 'task_rev':
					if ($cursor['type'] == 'task_rev') {
						if ($cursor['info']['state'] != $cursor['info']['old']['state']) {
							if ($cursor['info']['state'] == 0) {
								echo $this->getLang('task_reopened');
							} else if ($cursor['info']['state'] == 1) {
								echo $this->getLang('task_closed');
							} else if ($cursor['info']['state'] == 2) {
								echo $this->getLang('task_rejected');
							}
						} else {
							echo $this->getLang('task_changed');
						}
					} else {
						echo $this->getLang('comment_changed');
					}
					echo ' ';
					echo '<span class="id">';
					echo '#'.$aid[0].':'.$aid[1];
					echo '</span>';
					echo ' ';
					echo '(';
					echo $cursor['title'];
					echo ')';
					echo ' ';
					echo lcfirst($this->getLang('version'));
					echo ' ';
					echo '<span class="id">';
					echo $cursor['info']['rev_len'] - $aid[2];
					echo '</span>';
					echo ' ';
					echo $this->getLang('by');
					echo ' ';
					echo '<span class="author">';
					echo $this->string_format_field('name', $cursor['author']);
					echo '</span>';
					echo '</a>';
					echo '</dt>';
					echo '<dd>';
					if ($cursor['type'] == 'task_rev' && isset($cursor['info']['old'])) {
						//remoev unchanged field
						$new = array();
						foreach ($cursor['info']['old'] as $k => $v) {
							if (isset($cursor['info'][$k])) {
								$new[$k] = $cursor['info'][$k];
							}
						}

						echo $this->html_format_change($new, $cursor['info']['old'], array('reason'), 'task');
						echo $this->wiki_parse($cursor['info']['reason']);
					} else {
						echo $this->wiki_parse($cursor['info']['content']);
					}
					echo '</dd>';
				break;
				case 'issue_created':
					echo $this->getLang('issue_created');
					echo ' ';
					echo '<span class="id">';
					echo '#'.$aid[0];
					echo '</span>';
					echo ' ';
					echo '(';
					echo $cursor['title'];
					echo ')';
					echo ' ';
					echo $this->getLang('by');
					echo ' ';
					echo '<span class="author">';
					echo $this->string_format_field('name', $cursor['author']);
					echo '</span>';

					echo '</a>';
					echo '</dt>';
					echo '<dd>';
					echo $this->wiki_parse($cursor['info']['description']);
					echo '</dd>';
				break;
				case 'change':
				break;
				default:
					echo '</a>';
					echo '</dt>';
				break;
			}
		}
		echo '</dl>';
		return true;
	}
	private function _handle_issue_report() {
		global $auth;

		echo '<h1>'.$this->getLang('report_issue').'</h1>';
		$this->html_generate_report_form('?do=bds_issue_add');
		return true;
	}

	function show_tasks_table($cursor, $type) {

		if (count($cursor['tasks'][$type]) == 0) {
			return;
		}
		
		echo '<table>';
		echo '<tr>';
		echo '<th>';
		echo $this->getLang('executor');
		echo '</th>';
		echo '<th>';
		echo $this->getLang('date');
		echo '</th>';
		echo '<th>';
		echo $this->getLang('ended');
		echo '</th>';
		echo '<th>';
		echo $this->getLang('cost');
		echo '</th>';
		echo '</tr>';
		
		$style = '';
		foreach ($cursor['tasks'][$type] as $task) {
			echo '<tr>';
			echo '<td '.$style.'>';
			echo $this->string_format_field('name', $task['executor']);
			echo '</td>';
			echo '<td '.$style.'>';
			echo $this->string_format_field('true_date', $task['date']);
			echo '</td>';
			echo '<td '.$style.'>';
			if ($task['state'] == 0) {
				echo '---';
			} else {
				echo $this->string_format_field('true_date', $task['last_mod_date']);
				echo ' (';
				echo $this->task_states[$task['state']];
				echo ')';
			}
			echo '</td>';
			echo '<td '.$style.'>';
			echo $this->string_format_field('cost', $task['cost']);
			echo '</td>';
			echo '</tr>';

			echo '<tr>';
			echo '<td colspan="4">';
			echo '<strong>';
			echo $this->getLang('description');
			echo ':</strong>';
			echo $this->wiki_parse($task['content']);
			echo '</td>';
			echo '</tr>';
			if ($task['reason'] != '') {
				echo '<tr>';
				echo '<td colspan="4">';
				echo '<strong>';
				switch($task['state']) {
					case 0:
						echo $this->getLang('reason_reopen');
					break;
					case 1:
						echo $this->getLang('reason_done');
					break;
					case 2:
						echo $this->getLang('reason_reject');
					break;
				}
				echo ':</strong>';
				echo $this->wiki_parse($task['reason']);
				echo '</td>';
				echo '</tr>';
			}
			/*change style after first row */
			$style = 'style="border-top: 2px"';
		}
		echo '</table>';
	}

	function generate_8d_html_report($cursor) {
		echo '<h1>';
		echo $this->getLang('8d_report');
		echo '</h1>';

		echo '<table>';
		echo '<tr>';
		echo '<td>';
		echo ucfirst($this->string_format_field('type', $cursor['type']));
		echo ' ';
		echo '<strong>';
		echo '#';
		echo $cursor['_id'];
		echo '</strong>';
		echo '</td>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('entity');
		echo ': ';
		echo '</strong>';
		echo $this->string_format_field('entity', $cursor['entity']);
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('open_date');
		echo ': ';
		echo '</strong>';
		echo $this->string_format_field('true_date', $cursor['date']);
		echo '</td>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('reporter');
		echo ': ';
		echo '</strong>';
		echo $this->string_format_field('name', $cursor['reporter']);
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td colspan="2">';
		echo '<strong>';
		echo $this->getLang('title');
		echo ': </strong>';
		echo $this->string_format_field('title', $cursor['title']);
		echo '</td>';
		echo '</tr>';
		echo '</table>';

		echo '<h2>';
		echo $this->getLang('2d');
		echo '</h2>';
		echo $this->wiki_parse($cursor['description']);

		if (is_array($cursor['root_causes'])) {
			echo '<h2>';
			echo $this->getLang('3d');
			echo '</h2>';

			foreach ($cursor['root_causes'] as $cause => $data) {
				echo '<h3>';
				echo $this->root_causes[$cause];
				echo '</h3>';
				echo '<ul>';
				foreach($data as $value) {
					echo '<li>';	
					echo $this->wiki_parse($value['content']);
					echo '</li>';	
				}
				echo '</ul>';
			}
		}

		if (count($cursor['tasks'][0]) > 0) {
			echo '<h2>';
			echo $this->getLang('4d');
			echo '</h2>';
		}

		$this->show_tasks_table($cursor, 0);

		if (count($cursor['tasks'][1]) > 0) {
			echo '<h2>';
			echo $this->getLang('5d');
			echo '</h2>';
		}

		$this->show_tasks_table($cursor, 1);


		if (count($cursor['tasks'][2]) > 0) {
			echo '<h2>';
			echo $this->getLang('6d');
			echo '</h2>';
		}

		$this->show_tasks_table($cursor, 2);

		if (strlen(trim($cursor['opinion'])) > 0) {
			echo '<h2>';
			echo $this->getLang('7d');
			echo '</h2>';
		}

		echo $this->wiki_parse($cursor['opinion']);

		echo '<h2>';
		echo $this->getLang('8d');
		echo '</h2>';
		echo '<table>';
		echo '<tr>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('true_date');
		echo ': </strong>';
		echo $this->string_format_field('true_date', $cursor['last_mod_date']);
		echo '</td>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('state');
		echo ': ';
		echo '</strong>';
		echo $this->issue_states[$cursor['state']];
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('cost_total');
		echo ': ';
		echo '</strong>';
		echo $this->string_format_field('cost', $cursor['cost_total']);
		echo '</td>';
		echo '<td>';
		echo '<strong>';
		echo $this->getLang('coordinator');
		echo ': ';
		echo '</strong>';
		echo $this->string_format_field('name', $cursor['coordinator']);
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	function generate_8d_pdf_report($cursor) {
		global $conf, $INFO;
		try {
			//TCP pdf code
			//we use dokuwiki images instead
			define('K_PATH_IMAGES', '');
			require_once('tcpdf/tcpdf.php');

			// create new PDF document
			$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

			$header = '#'.$cursor['_id'].' '.$this->string_format_field('type', $cursor['type']).' ('.$this->string_format_field('state', $cursor['state']).')';

			// set document information
			$pdf->SetCreator($this->getLang('bds'));
			$pdf->SetTitle($this->getLang('8d_report').' '.$this->getLang('8d_report_for').' '.$header);
				
			$pdf->SetCellPadding(0);
			// set default header daa

			$logoSize = array();
			$logo = tpl_getMediaFile(array(':wiki:logo.png', ':logo.png', 'images/logo.png'), false, $logoSize);
			$logo = "http" . (($_SERVER['SERVER_PORT']==443) ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $logo;

			$header_string = '';
			if ($conf['tagline']) {
				$header_string = $conf['tagline']; 
			}

			//$mm_width = $logoSize[0]/3.779528;

			//convent px to mm
			//count width basing on height
			$mm_width = 20*$logoSize[0]/$logoSize[1];
			$pdf->SetHeaderData($logo, $mm_width, $conf['title'], $header_string);

			// set header and footer fonts
			$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
			$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

			// set default monospaced font
			$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

			//set margins
			$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
			$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
			$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

			//set auto page breaks
			$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

			//set image scale factor
			$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

			// Set font
			// dejavusans is a UTF-8 Unicode font, if you only need to
			// print standard ASCII chars, you can use core fonts like
			// helvetica or times to reduce file size.
			$pdf->SetFont('dejavusans', '', 10, '', true);

			// Add a page
			// This method has several options, check the source code documentation for more information.
			$pdf->AddPage();

			$no_js = true;

			ob_start();
			$this->generate_8d_html_report($cursor);
			$html = ob_get_clean();

			$html = str_replace('<table>', '<table cellpadding="5" border="1">', $html);
			$html = str_replace('<th>', '<th bgcolor="#ccc" align="center">', $html);

			// Print text using writeHTMLCell()
			//$pdf->writeHTMLCell($w=0, $h=0, $x='', $y='', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
			$pdf->writeHTML($html, true, false, true, false, '');

			// ---------------------------------------------------------

			// Close and output PDF document
			// This method has several options, check the source code documentation for more information.
			$pdf->Output($cursor['_id'].'_'.$this->string_format_field('true_date', $cursor['date']).'.pdf', 'I');

			/*$buf = $p->get_buffer();
			$len = strlen($buf);

			header("Content-type: application/pdf");
			header("Content-Length: $len");
			header("Content-Disposition: inline; filename=hello.pdf");
			print $buf;*/
		}
		catch (Exception $e) {
			die($e);
		}
	}

	private function _handle_issue_show($id) {
		global $auth, $INFO;
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
			echo '[';
			echo $cursor['entity'];
			echo '] ';
			echo $cursor['title'];
			echo '</h1>';

			echo '<div class="time_box">';
			echo '<span>';
			echo $this->getLang('opened_for');
			echo ': ';
			echo $this->string_time_to_now($cursor['date']);
			echo '</span>';
			echo '<span>';
			if (isset($cursor['last_mod_date']) && $cursor['last_mod_date'] != $cursor['date']) {
				if (in_array($cursor['state'], $this->blocking_states)) {
					echo $this->getLang('closed');
				} else {
					echo $this->getLang('last_modified');
				}
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
			echo '<span>';
			echo ' ';
			echo  '('.$this->getLang('last_modified_by').' '.$this->string_get_full_name($desc_author).')';
			echo '</span>';
			echo '</h2>';

			echo $this->wiki_parse($cursor['description']);

			if (in_array($cursor['state'], $this->blocking_states)) {

				if (isset($cursor['last_opinion_author'])) {
					$opinion_author = $cursor['last_opinion_author'];
				} else {
					$opinion_author = $cursor['reporter'];
				}
				echo '<h2>';
				echo ' ';
				echo $this->getLang('opinion');
				echo ' ';
				echo  '('.$this->getLang('last_modified_by').' '.$this->string_get_full_name($opinion_author).')';
				echo '</h2>';

				echo $this->wiki_parse($cursor['opinion']);
			}

			$text = $this->rawLocale('bez_new_issue');
			$trep = array(
				'FULLNAME' => $this->get_name($cursor['coordinator']),
				'NR' => '#'.$cursor['_id'],
				'TYPE' => $this->string_format_field('type', $cursor['type']),
				'TITLE' => '['.$cursor['entity'].']'.$cursor['title'],
				'ISSUE' => $cursor['description'],
				'URL' => DOKU_URL.'doku.php'.$this->string_issue_href($cursor['_id'])
			);

			$to = $this->get_name($cursor['coordinator']).' <'.$this->get_email($cursor['coordinator']).'>';
			$subject = $this->getLang('new_issue').': #'.$cursor['_id'].' '.$this->string_format_field('type', $cursor['type']);

			// Apply replacements
			foreach($trep as $key => $substitution) {
				$text = str_replace('@'.strtoupper($key).'@', $substitution, $text);
			}
			echo '<a class="bds_inline_button bds_send_button" href="mailto:'.$to.'?subject='.rawurlencode($subject).'&body='.rawurlencode($text).'">✉ '.$this->getLang('send_mail').'</a>';

			echo '<a href="?do=bds_8d&bds_issue_id='.$id.'" class="bds_inline_button bds_report_button">';
			echo $this->getLang('8d_report');
			echo '</a>';


			echo '</div>';

			echo '<div class="bds_block" id="bds_history">';
			echo '<h1>'.$this->getLang('changes_history').' <span>('.count($cursor['events']).')</span></h1>';
			echo '<div class="bds_block_content">';
			if (isset($cursor['events'])) {
				foreach ($cursor['events'] as $event) {
					//create anchor
					$class = $event['type'];
					if (isset($event['root_cause']) && $event['root_cause'] != 0) {
						$class = 'root_cause_comment';
					}
					echo '<div id="'.$event['id'].'" class="'.$class.'">';

					echo '<h2>';
					switch ($event['type']) {
						case 'comment':
							echo $this->getLang('comment_added');
							break;
						case 'change':
							echo $this->getLang('change_made');
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
					if (isset($event['replay_to'])) {
						echo '<span class="replay_to">';
						echo $this->getLang('replay_to');
						echo ' ';
						echo $this->html_anchor_to_event($cursor['_id'], $event['replay_to']);
						echo '</span>';
					}
					if (isset($event['quoted_in'])) {
						echo '<span class="quoted_in">';
						echo $this->getLang('quoted_in');
						echo ': ';
						foreach ($event['quoted_in'] as $event_id) {
							echo $this->html_anchor_to_event($cursor['_id'], $event_id);
							echo ' ';
						}
						echo '</span>';
					}
					echo '<span>';
					switch ($event['type']) {
						case 'comment':
							echo $this->getLang('comment_noun');
							break;
						case 'change':
							echo $this->getLang('change');
							break;
						case 'task':
							echo $this->getLang('task');
							break;
					}
					echo ': ';
					echo $event['id'];
					echo '</span>';
					echo '</h2>';
					if ( ! in_array($cursor['state'], $this->blocking_states)) {
						if ($event['type'] == 'task') {
								$text = $this->rawLocale('bez_new_task');
								$nr = '#'.$cursor['_id'].':'.$event['id'];
								$trep = array(
									'FULLNAME' => $this->get_name($event['executor']),
									'NR' => $nr,
									'TASK' => $event['content'],
									'URL' => DOKU_URL.'doku.php'.$this->string_issue_href($cursor['_id'], $event['id'])
								);
								$to = $this->get_name($event['executor']).' <'.$this->get_email($event['executor']).'>';
								$subject = $this->getLang('new_task').': '.$nr.' '.$this->string_format_field('class', $event['class']);

								// Apply replacements
							    foreach($trep as $key => $substitution) {
									$text = str_replace('@'.strtoupper($key).'@', $substitution, $text);
								}
								echo '<a class="bds_inline_button" href="mailto:'.$to.'?subject='.rawurlencode($subject).'&body='.rawurlencode($text).'">✉ '.$this->getLang('send_mail').'</a>';
						}
						if ($event['type'] != 'task') {
							echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_to='.$event['id'].'#comment_form">↳ '.$this->getLang('replay').'</a>';
							echo ' ';
						}
						if ($this->user_is_moderator() && $event['type'] != 'task') {
							echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_by_task='.$event['id'].'#task_form">↳ '.$this->getLang('replay_by_task').'</a>';
							echo ' ';
						}
						if ($event['type'] != 'change') {
							if ($this->user_is_moderator() || $event['type'] == 'comment') {
								$value = '✎ '.$this->getLang('edit');
							} else {
								$value = '✎ '.$this->getLang('change_task_state');
							}
							if ($event['type'] == 'comment' || $event['executor'] == $INFO['client'] || $this->user_is_moderator()) {
								echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&bds_event_id='.$event['id'].'#'.$event['type'].'_form">'.$value.'</a>';
							}
						}
					}

					if (isset($_GET['rev_ev_id'])) {
						$rev_ev_id = (int)$_GET['rev_ev_id'];
					} else {
						$rev_ev_id = -1;
					}


					if ( ! isset($_GET['rev']) || $rev_ev_id != $event['id']) {
						//the newest
						$rev = -1;
					} else {
						$rev = (int)$_GET['rev'];
						if ( ! isset($event['rev'][$rev])) {
							$rev = -1;
						}
					}

					
					switch ($event['type']) {
						case 'change':
							echo $this->html_format_change($event['new'], $event['prev']);
						break;
						case 'task':
							echo '<table>';	
							echo '<tr>';
								if ($rev == -1) {
									echo '<th>';
									echo $this->getLang('task_state');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('task_state', $event['state']);
									echo '</td>';
									echo '<th>';
									echo $this->getLang('executor');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('name', $event['executor']);
									echo '</td>';
									echo '<th>';
									echo $this->getLang('class');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('class', $event['class']);
									echo '</td>';
									if (isset($event['cost'])) {
										echo '<th>';
										echo $this->getLang('cost');
										echo ':</th>';
										echo '<td>';
										echo $this->string_format_field('cost', $event['cost']);
										echo '</td>';
									}
								} else {
									echo '<th>';
									echo $this->getLang('task_state');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('task_state', $event['rev'][$rev]['state']);
									echo '</td>';
									echo '<th>';
									echo $this->getLang('executor');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('name', $event['rev'][$rev]['executor']);
									echo '</td>';
									echo '<th>';
									echo $this->getLang('class');
									echo ':</th>';
									echo '<td>';
									echo $this->string_format_field('class', $event['rev'][$rev]['class']);
									echo '</td>';
									if (isset($event['cost'])) {
										echo '<th>';
										echo $this->getLang('cost');
										echo ':</th>';
										echo '<td>';
										echo $this->string_format_field('cost', $event['rev'][$rev]['cost']);
										echo '</td>';
									}
								}
							echo '</tr>';
							echo '</table>';	
						break;
					}

					if ( $rev == -1) {
						$root_cause = $event['root_cause'];
					} else {
						$root_cause = $event['rev'][$rev]['root_cause'];
					}

					if (isset($root_cause) && $root_cause != 0) {
						echo '<div class="root_cause">';
						echo '<span>';
						echo lcfirst($this->getLang('root_cause'));
						echo ': ';
						echo '<strong>';
						echo $this->root_causes[$root_cause];
						echo '</strong>';
						echo '</span>';
						echo '</div>';
					}

					if ($event['type'] == 'task') {
						echo '<h3>';
						echo $this->getLang('description');
						echo '</h3>';
					}

					if (isset($event['content'])) {
						if ( $rev == -1) {
							echo $this->wiki_parse($event['content']);
						} else {
							
							echo $this->wiki_parse($event['rev'][$rev]['content']);
						}
					}

					$reason = '';
					if ($rev == -1) {
						$reason = $event['reason'];
					} else {
						$reason = $event['rev'][$rev]['reason'];
					}

					if ($event['type'] == 'task' && strlen($reason) > 0) {
						if ( $rev == -1) {
							echo '<h3>';
							switch($event['state']) {
								case 0:
									echo $this->getLang('reason_reopen');
								break;
								case 1:
									echo $this->getLang('reason_done');
								break;
								case 2:
									echo $this->getLang('reason_reject');
								break;
							}
							echo '</h3>';
							echo $this->wiki_parse($event['reason']);
						} else {
							echo '<h3>';
							switch($event['rev'][$rev]['state']) {
								case 0:
									echo $this->getLang('reason_reopen');
								break;
								case 1:
									echo $this->getLang('reason_done');
								break;
								case 2:
									echo $this->getLang('reason_reject');
								break;
							}
							echo '</h3>';
							echo $this->wiki_parse($event['rev'][$rev]['reason']);
						}
					}
					if (isset($event['rev'])) {
						echo '<span class="bds_last_edit">';
							if ($rev == -1) {
								echo $this->getLang('last_modified');
								echo ' ';
								echo $this->string_format_field('date', $event['last_mod_date']);
								echo ' ';
								echo $this->getLang('by');
								echo ' ';
								echo $this->string_format_field('name', $event['last_mod_author']);
							} else {
								echo $this->getLang('version');
								echo ': ';
								echo count($event['rev']) - $rev;
								echo ' ';
								echo lcfirst($this->getLang('last_modified'));
								echo ' ';
								echo $this->string_format_field('date', $event['rev'][$rev]['last_mod_date']);
								echo ' ';
								echo $this->getLang('by');
								echo ' ';
								echo $this->string_format_field('name', $event['rev'][$rev]['last_mod_author']);
							}
							
							if (isset($event['rev'][$rev+1])) {
							echo ' (';
								echo '<a href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&rev_ev_id='.$event['id'].'&rev='.($rev+1).'#'.$event['id'].'">';
							echo $this->getLang('preview');
							echo '</a>';
							echo ')';
							echo ' ';
							}

							if ($rev >= 0) {
							echo ' ';
							echo '(';
								echo '<a href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&rev_ev_id='.$event['id'].'&rev='.($rev-1).'#'.$event['id'].'">';
							echo $this->getLang('next');
							echo '</a>';
							echo ')';
							echo ' ';
							}
							echo ' (';
							echo $this->getLang('diff');
							echo ') ';
						echo '</span>';
					}
					echo '</div>';
				}
			}
			echo '</div>';
			echo '</div>';



			if (in_array($cursor['state'], $this->blocking_states)) {
				echo '<div class="bds_block" id="bds_closed">';
				echo '<div class="info">';
				$com = str_replace('%d', $this->string_format_field('date', $cursor['last_mod_date']), $this->getLang('issue_closed'));
				$com = str_replace('%u', $this->string_format_field('name', $cursor['last_mod_author']), $com);
				echo $com;
				echo '</div>';
				if ($this->user_is_moderator()) {
					echo '<h1>'.$this->getLang('reopen_issue').'</h1>';
					echo '<div class="bds_block_content">';
					$action = '?do=bds_issue_reopen&bds_issue_id='.$cursor['_id'].'';
					if (isset($this->vald)) {
						foreach ($this->vald as $error) {
							echo '<div class="error">'.$error.'</div>';
						}
					}
					echo '<form action="'.$action.'" method="post">';
					echo '<filedset class="bds_form">';
					echo '<div class="row">';
					echo '<label for="state">'.$this->getLang('state').':</label>';
					echo '<span>';
					echo '<select name="state" id="state">';

					if (isset($_POST['state'])) {
						$value['state'] = $_POST['state'];
					} elseif (isset($cursor['state'])) {
						$value['state'] = $cursor['state'];
					} else {
						$value['state'] = '';
					}

					foreach ($this->issue_states as $key => $state) {
						echo '<option';
						if ($value['state'] == $key) {
							echo ' selected';
						}
						echo ' value="'.$key.'">'.$state.'</opiton>';
					}
					echo '</select>';
					echo '</span>';
					echo '</div>';
					echo '</filedset>';
					echo '<input type="submit" value="'.$this->getLang('change_state_button').'">';
					echo '</form>';
					echo '</div>';
					echo '</div>';

				}
			} else {
				$ev_type = '';
				if (isset($_GET['bds_event_id'])) {
					$bds_event_id = (int)$_GET['bds_event_id'];

					$issues = $this->issues();
					if ($issues == false) {
						//we need error handling here;
						//$this->error = 'error_db_connection';
						//$event->data = 'bds_error';
						return;
					} else {
						$ev_type = '';
						foreach ($cursor['events'] as $ev) {
							if ($ev['id'] == $bds_event_id) {
								$ev_type = $ev['type'];
								$ev_cursor = $ev;
							}
						}
						if ($ev_type == '') {
							//we need error handling here(by header(Location))
							//$this->error = 'error_event_id_unknown';
							//$event->data = 'bds_error';
							return;
						}
					}
				}
				//we cannnot edit changes
				if ($ev_type == 'comment') {
					$action = '?do=bds_issue_change_event&bds_issue_id='.$cursor['_id'].'&bds_event_id='.$bds_event_id;
					$this->html_generate_event_form($action, $cursor, 
						array('submit' => $this->getLang('change_comment_button'), 'header' => array('name' => 'comment_form', 'value' => $this->getLang('change_comment')), 'event' => $_GET['do'], 'do' => 'bds_issue_change_event'), $ev_cursor);
				} else {
					$action = '?do=bds_issue_add_event&bds_issue_id='.$cursor['_id'].'';
					$this->html_generate_event_form($action, $cursor, 
						array('submit' => $this->getLang('comment'), 'header' => array('name' => 'comment_form', 'value' => $this->getLang('add_comment')), 'event' => $_GET['do'], 'do' => 'bds_issue_add_event', 'replay_to' => $_GET['replay_to']));
				}

				if ($ev_type == 'task') {
					$action = '?do=bds_issue_change_task&bds_issue_id='.$cursor['_id'].'&bds_event_id='.$bds_event_id;
					$this->html_generate_event_form($action, $cursor, 
							array('submit' => $this->getLang('change_task_button'), 'header' => array('name' => 'task_form', 'value' => $this->getLang('change_task')), 'event' => $_GET['do'], 'do' => 'bds_issue_change_task'), $ev_cursor);
				} else {
					if ($this->user_is_moderator() ) {
						$action = '?do=bds_issue_add_task&bds_issue_id='.$cursor['_id'].'';
						$this->html_generate_event_form($action, $cursor, 
								array('submit' => $this->getLang('add'), 'header' => array('name' => 'task_form', 'value' => $this->getLang('add_task')), 'event' => $_GET['do'], 'do' => 'bds_issue_add_task', 'replay_to' => $_GET['replay_by_task']));
					}
				}



				echo '<div class="bds_block" id="bds_change_issue">';
				echo '<h1>'.$this->getLang('change_issue').'</h1>';
				echo '<div class="bds_block_content">';
				
				$tasks = 0;
				if (count($cursor['events']) > 0) {
					foreach ($cursor['events'] as $event) {
						if ($event['type'] == 'task')
							$tasks++;
					}
				}
				if ($tasks == 0) {
					unset($this->issue_states[3]);
					unset($this->issue_states[4]);
				}
				$action = '?do=bds_issue_change&bds_issue_id='.$cursor['_id'].'#bds_change_issue';
				$this->html_generate_report_form($action, $cursor);
				echo '</div>';
				echo '</div>';
			}
			return true;
		}
	}
	private function html_table_view($doc, $fields, $table='issues') {
		echo '<table class="dattab">';
		echo '<thead>';
		echo '<tr>';	
		foreach ($fields as $field) {
			echo '<th';
			if ($field == 'title') {
				echo ' class="title_field"';
			} else if ($field == 'coordinator') {
				echo ' class="coordinator_field"';
			} else if ($field == 'type') {
				echo ' class="type_field"';
			}
			echo '>'.$this->getLang($field).'</th>';
		}
		echo '</tr>';	
		echo '</thead>';
		echo '<tbody>';
		foreach ($doc as $cursor) {
			echo '<tr>';	
			foreach ($fields as $field) {
				echo '<td';
				if ($field == '_id' || $field == 'date' || $field == 'last_mod_date')
					echo ' data-sort="'.$cursor[$field].'"';
				echo '>';
				//only if title
				if ($field == 'title') {
					echo '[';
					echo $cursor['entity'];
					echo '] ';
				} elseif ($field == 'id') {
					echo $this->html_anchor_to_event($cursor['_id'], $cursor[$field], true);
					continue;
				}
				echo $this->string_format_field($field, $cursor[$field], $table);
				echo '</td>';
			}
		}
		echo '</tbody>';
		echo '</table>';
	}
	private function get_issues_with_states_by_costs($report, $issues, $active, $id_indexed=false) {

						if ($report == 'newest_to_oldest') {
							$sort = array('_id' => -1);
						} else {
							$sort = array('last_mod_date' => -1);
						}

						//all

						//wybierz te dokumenty, które mają przypisane zadania
						$state0 = $issues->aggregate(
							array('$project' => array(
								'_id' => 1,
								'state' => 1,
								'type' => 1,
								'title' => 1,
								'coordinator' => 1,
								'entity' => 1,
								'reporter' => 1,
								'date' => 1,
								'last_mod_date' => 1,
								'events' => 1,
								'authors' => 1,
								'tasks' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.type' => 'task')))),
							array('$match' => array('$or' => $active)),
							array('$group' => array('_id' =>
													array('_id' => '$_id',
														'type' => '$type',
														'state' => '$state',
														'title' => '$title',
														'cost' => '$cost',
														'coordinator' => '$coordinator',
														'entity' => '$entity',
														'reporter' => '$reporter',
														'date' => '$date',
														'last_mod_date' => '$last_mod_date',
													),
													'cost_total' => array('$sum' => '$events.cost')
													)),
							array('$sort' => $sort)
						);

						//wybierz wszystkie dokumenty
						$doc2 = $issues->find(array('$or' => $active));

						if ($report == 'newest_to_oldest') {
							$doc2->sort(array('_id' => -1));
						} else {
							$doc2->sort(array('last_mod_date' => -1));
						}

						//połącz we wspólną tablicę
						$result = array();
						for ($i = 0; $i < count($state0['result']);$i++) {
							$v = $state0['result'][$i];
							$id = $v['_id']['_id'];	
							$result[$id] = $v['_id'];
							$result[$id]['cost_total'] = $v['cost_total'];
						}

						$res = array();
						$i = 0;
						foreach($doc2 as $k => $v) {
							if ($id_indexed == true) {
								$i = $v['_id'];
							}
							if (isset($result[$v['_id']])) {
								$res[$i] = $result[$v['_id']];
							} else {
								$v['cost_total'] = '0';
								$res[$i] = $v;
							}
							$i++;
						}

						return $res;
					}
	private function get_issues_with_states($report, $issues, $active, $id_indexed=false) {

						if ($report == 'newest_to_oldest') {
							$sort = array('_id' => -1);
						} else {
							$sort = array('last_mod_date' => -1);
						}

						//all

						//wybierz te dokumenty, które mają przypisane zadania
						$state0 = $issues->aggregate(
							array('$project' => array(
								'_id' => 1,
								'state' => 1,
								'type' => 1,
								'title' => 1,
								'coordinator' => 1,
								'entity' => 1,
								'reporter' => 1,
								'date' => 1,
								'last_mod_date' => 1,
								'events' => 1,
								'authors' => 1,
								'tasks' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.type' => 'task'), array('events.state' => 0)))),
							array('$match' => array('$or' => $active)),
							array('$group' => array('_id' =>
													array('_id' => '$_id',
														'type' => '$type',
														'state' => '$state',
														'title' => '$title',
														'cost' => '$cost',
														'coordinator' => '$coordinator',
														'entity' => '$entity',
														'reporter' => '$reporter',
														'date' => '$date',
														'last_mod_date' => '$last_mod_date',
													),
													'opened_tasks' => array('$sum' => 1)
													)),
							array('$sort' => $sort)
						);

						//wybierz wszystkie dokumenty
						$doc2 = $issues->find(array('$or' => $active));

						if ($report == 'newest_to_oldest') {
							$doc2->sort(array('_id' => -1));
						} else {
							$doc2->sort(array('last_mod_date' => -1));
						}

						//połącz we wspólną tablicę
						$result = array();
						for ($i = 0; $i < count($state0['result']);$i++) {
							$v = $state0['result'][$i];
							$id = $v['_id']['_id'];	
							$result[$id] = $v['_id'];
							$result[$id]['opened_tasks'] = $v['opened_tasks'];
						}

						$res = array();
						$i = 0;
						foreach($doc2 as $k => $v) {
							if ($id_indexed == true) {
								$i = $v['_id'];
							}
							if (isset($result[$v['_id']])) {
								$res[$i] = $result[$v['_id']];
							} else {
								$v['opened_tasks'] = '0';
								$res[$i] = $v;
							}
							$i++;
						}

						return $res;
					}
					
	private function _handle_table() {
		global $auth, $INFO;

		$table = $_GET['table'];
		$report = $_GET['report'];

		$issues = $this->issues();
		if ($issues == false) {
			$this->msg = 'error_db_connection';
			$this->_handle_error($this->getLang($this->error));
			return;
		}

		if ($table == 'issues') {
			switch ($report) {
				case 'newest_to_oldest':
				case 'by_last_activity':
					echo '<h1>';
					if ($report == 'newest_to_oldest') {
						echo $this->getLang('issues_newest_to_oldest');
					} else {
						echo $this->getLang('issues_by_last_activity');
					}
					echo '</h1>';
					$active = array();
					foreach ($this->issue_states as $k => $v) {
						if ( ! in_array($k, $this->blocking_states)) {
							$active[] = array('state' => $k);
						}
					}

					$res = $this->get_issues_with_states($report, $issues, $active, false);
					
					//, 'cost_total'
					$this->html_table_view($res, array('_id', 'state', 'type', 'title', 'coordinator', 'date', 'last_mod_date', 'opened_tasks'));
				break;
				case 'my_opened':
					echo '<h1>';
					echo $this->getLang('my_opened_issues');
					echo '</h1>';

					$active = array();
					foreach ($this->issue_states as $k => $v) {
						if ( ! in_array($k, $this->blocking_states)) {
							$active[] = array('state' => $k);
						}
					}
					$doc = $this->issues()->aggregate(
					array('$project' => array(
						'_id' => 1,
						'state' => 1,
						'type' => 1,
						'title' => 1,
						'coordinator' => 1,
						'entity' => 1,
						'reporter' => 1,
						'date' => 1,
						'last_mod_date' => 1,
						'events' => 1,
						'authors' => 1
					)),
					array('$unwind' => '$events'),
					array('$group' => array('_id' => '$_id', 
											'authors' => array('$addToSet' => '$events.author'),
											'executors' => array('$addToSet' => '$events.executor'),
											'last_mod_authors' => array('$addToSet' => '$events.last_mod_author'),
											'state' => array('$first' => '$state'),
											'reporter' => array('$first' => '$reporter'),
											'entity' => array('$first' => '$entity'),
											'type' => array('$first' => '$type'),
											'title' => array('$first' => '$title'),
											'coordinator' => array('$first' => '$coordinator'),
											'date' => array('$first' => '$date'),
											'last_mod_date' => array('$first' => '$last_mod_date'),
											'state' => array('$first' => '$state')
											)),
					array('$match' => array('$or' => array(
						array('authors' => array('$in' => array($INFO['client'])) ),
						array('last_mod_authors' => array('$in' => array($INFO['client'])) ),
						array('reporter' => $INFO['client']),
						array('executors' => array('$in' => array($INFO['client'])) )
					))),
					array('$sort' => array('_id' => -1)),
					array('$match' => array('$or' => $active))
						);
					
					$res = $this->get_issues_with_states($report, $issues, $active, true);
					$result = array();

					foreach ($doc['result'] as $v) {
						$rec = $v['_id'];
						$result[] = $res[$rec];
					}

					$this->html_table_view($result, array('_id', 'state', 'type', 'title', 'coordinator', 'date', 'last_mod_date', 'opened_tasks'));
				break;
				case 'newest_than':
					$this->vald = array();
					if (isset($_POST['days']) && ! is_numeric($_POST['days'])) {
						$this->vald['days'] = $this->getLang('vald_days_should_be_numeric');
						$this->_handle_issues();
					} else {
						$post['days'] = (int)$_POST['days'];
						echo '<h1>';
						echo str_replace('%d', $post['days'], $this->getLang('issues_newest_than_rep'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';
						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['days']*24*60*60;

						$doc = $issues->find(array('date' => array('$gt' => $date_limit)))->sort(array('_id' => -1));

						$active = array();
						foreach ($this->issue_states as $k => $v) {
							$active[] = array('state' => $k);
						}

						$res = $this->get_issues_with_states('newest_to_oldest', $issues, $active, true);
						$result = array();

						foreach ($doc as $v) {
							$rec = $v['_id'];
							$result[] = $res[$rec];
						}

						$this->html_table_view($result, array('_id', 'state', 'type', 'title', 'coordinator', 'date', 'last_mod_date', 'opened_tasks'));
					}
					break;
				case 'newest_than_cost':
					$this->vald = array();
					if (isset($_POST['days']) && ! is_numeric($_POST['days'])) {
						$this->vald['days'] = $this->getLang('vald_days_should_be_numeric');
						$this->_handle_issues();
					} else {
						$post['days'] = (int)$_POST['days'];
						echo '<h1>';
						echo str_replace('%d', $post['days'], $this->getLang('issues_cost_statement'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';
						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['days']*24*60*60;

						$doc = $issues->find(array('date' => array('$gt' => $date_limit)))->sort(array('_id' => -1));

						$active = array();
						foreach ($this->issue_states as $k => $v) {
							$active[] = array('state' => $k);
						}

						$res = $this->get_issues_with_states_by_costs('newest_to_oldest', $issues, $active, true);
						$result = array();

						foreach ($doc as $v) {
							$rec = $v['_id'];
							$result[] = $res[$rec];
						}

						$this->html_table_view($result, array('_id', 'state', 'type', 'title', 'coordinator', 'date', 'last_mod_date', 'cost_total'));
					}
					break;
				default:
					$this->msg = 'error_report_unknown';
					$this->_handle_error($this->getLang($this->msg));
				break;
			}
		} else if ($table == 'tasks') {
			switch ($report) {
				case 'newest_to_oldest':
					echo '<h1>';
					echo $this->getLang('tasks_newest_to_oldest');
					echo '</h1>';

					$active = array();
					foreach ($this->issue_states as $k => $v) {
						if ($k == 0) {
							$active[] = array('state' => $k);
						}
					}
					$doc = $this->issues()->aggregate(
						array('$project' => array('_id' => 1, 'events' => 1)),
						array('$unwind' => '$events'),
						array('$match' => array('events.type' => 'task')),
						array('$project' => array('_id' => 1, 'events' => 1,
						'state' => '$events.state',
						'id' => '$events.id',
						'class' => '$events.class',
						'cost' => '$events.cost',
						'date' => '$events.date',
						'executor' => '$events.executor',
						)),
						array('$match' => array('$or' => $active)),
						array('$sort' => array('events.date' => -1))
					);

					$this->html_table_view($doc['result'], array('id', 'state', 'class', 'executor', 'date', 'cost'), 'tasks');
				break;
				case 'my_opened':
					echo '<h1>';
					echo $this->getLang('task_me_executor');
					echo '</h1>';

					$active = array();
					foreach ($this->issue_states as $k => $v) {
						if ($k == 0) {
							$active[] = array('state' => $k);
						}
					}
					$doc = $this->issues()->aggregate(
						array('$project' => array('_id' => 1, 'events' => 1)),
						array('$unwind' => '$events'),
						array('$match' => array('events.type' => 'task')),
						array('$project' => array('_id' => 1, 'events' => 1,
						'state' => '$events.state',
						'id' => '$events.id',
						'class' => '$events.class',
						'cost' => '$events.cost',
						'date' => '$events.date',
						'executor' => '$events.executor',
						)),
						array('$match' => array('$or' => $active)),
						array('$match' => array('executor' => $INFO['client'])),
						array('$sort' => array('events.date' => -1))
					);

					$this->html_table_view($doc['result'], array('id', 'state', 'class', 'executor', 'date', 'cost'), 'tasks');
				break;
				case 'newest_than':
					$this->vald_comment = array();
					if (isset($_POST['tdays']) && ! is_numeric($_POST['tdays'])) {
						$this->vald_comment['tdays'] = $this->getLang('vald_days_should_be_numeric');
						$this->_handle_issues();
					} else {
						$post['tdays'] = (int)$_POST['tdays'];
						echo '<h1>';
						echo str_replace('%d', $post['tdays'], $this->getLang('tasks_newest_than_rep'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';
						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['tdays']*24*60*60;

						$doc = $this->issues()->aggregate(
							array('$project' => array('_id' => 1, 'events' => 1)),
							array('$unwind' => '$events'),
							array('$match' => array('events.type' => 'task')),
							array('$project' => array('_id' => 1, 'events' => 1,
							'state' => '$events.state',
							'id' => '$events.id',
							'class' => '$events.class',
							'cost' => '$events.cost',
							'date' => '$events.date',
							'executor' => '$events.executor',
							)),
							array('$match' => array('events.date' => array('$gt' => $date_limit))),
							array('$sort' => array('events.date' => -1))
						);
					$this->html_table_view($doc['result'], array('id', 'state', 'class', 'executor', 'date', 'cost'), 'tasks');
					}
				break;
				default:
					$this->msg = 'error_report_unknown';
					$this->_handle_error($this->getLang($this->msg));
				break;
			}
		} else if ($table == 'reports') {
			if (
				(isset($_POST['ridays']) && ! is_numeric($_POST['ridays'])) ||
				(isset($_POST['rtdays']) && ! is_numeric($_POST['rtdays'])) ||
				(isset($_POST['rcdays']) && ! is_numeric($_POST['rcdays']))
			) {
					$this->vald_report['days'] = $this->getLang('vald_days_should_be_numeric');
					$this->_handle_issues();
				} else {
					if (isset($_POST['ridays'])) {
						$post['days'] = (int)$_POST['ridays'];
						echo '<h1>';
						echo str_replace('%d', $post['days'], $this->getLang('report_issues_from'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';

						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['days']*24*60*60;
						
						$doc = $this->issues()->aggregate(
							array('$match' => array('date' => array('$gt' => $date_limit))),
							array('$project' => array(
								'type' => 1,
								'entity' => 1,
								'events' => 1
							)),
							array('$unwind' => '$events'),
							array('$group' => array('_id' => array('type' => '$type', 'entity' => '$entity'), 'cost_total' => array('$sum' => '$events.cost')))
						);

						$average_closed = $this->issues()->aggregate(
							array('$match' => array('date' => array('$gt' => $date_limit))),
							array('$project' => array(
								'type' => 1,
								'entity' => 1,
								'events' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.state' => 1), array('events.type' => 'task'))))
						);
						$average_opened = $this->issues()->aggregate(
							array('$match' => array('date' => array('$gt' => $date_limit))),
							array('$project' => array(
								'type' => 1,
								'entity' => 1,
								'events' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.state' => 1), array('events.type' => 'task'))))
						);

						$average = array();
						foreach ($average_closed['result'] as $v) {
							$k = $v['type'].$v['entity'];
							$ev = $v['events'];
							$average[$k]['sum'] += $ev['last_mod_date'] - $ev['date'];
							$average[$k]['qt']++;
						}

						foreach ($average_opened['result'] as $v) {
							$k = $v['type'].$v['entity'];
							$ev = $v['events'];
							$average[$k]['sum'] += time() - $ev['date'];
							$average[$k]['qt']++;
						}

						$doc2 = $this->issues()->aggregate(
							array('$match' => array('date' => array('$gt' => $date_limit))),
							array('$project' => array(
								'type' => 1,
								'entity' => 1,
							)),
							array('$group' => array('_id' => array('type' => '$type', 'entity' => '$entity'), 'number' => array('$sum' => 1)))
						);

						$result = array();
						foreach ($doc['result'] as $v) {
							foreach ($doc2['result'] as $v2) {
								if ($v['_id'] == $v2['_id']) {
									$v['_id']['number'] = $v2['number'];
								}
							}
							$v['_id']['cost_total'] = $v['cost_total'];
							$result[] = $v['_id'];
						}
						for($i = 0; $i < count($result); $i++) {
							$k = $result[$i]['type'].$result[$i]['entity'];
							foreach ($average as $ak => $av)
								if ($ak == $k)
									$result[$i]['average_days'] = round(($av['sum']/$av['qt'])/(60*60*24));
						}
					$this->html_table_view($result, array('type', 'entity', 'number', 'cost_total', 'average_days'), 'issues');
					} elseif (isset($_POST['rtdays'])) {
						$post['days'] = (int)$_POST['rtdays'];
						echo '<h1>';
						echo str_replace('%d', $post['days'], $this->getLang('report_tasks_from'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';

						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['days']*24*60*60;
						
						$doc = $this->issues()->aggregate(
							array('$project' => array(
								'events' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.date' => array('$gt' => $date_limit)), array('events.type' => 'task')))),
							array('$group' => array('_id' => array('class' => '$events.class'), 'cost_total' => array('$sum' => '$events.cost'), 'number' => array('$sum' => 1)))
						);

						$result = array();
						foreach ($doc['result'] as $v) {
							$v['_id']['cost_total'] = $v['cost_total'];
							$v['_id']['number'] = $v['number'];
							$result[] = $v['_id'];
						}
					$this->html_table_view($result, array('class', 'number', 'cost_total'), 'tasks');
					} elseif (isset($_POST['rcdays'])) {
						$post['days'] = (int)$_POST['rcdays'];
						echo '<h1>';
						echo str_replace('%d', $post['days'], $this->getLang('report_causes_from'));
						echo ' ';
						echo $this->getLang('days');
						echo '</h1>';

						$today = mktime(0, 0, 0);
						$date_limit = $today - $post['days']*24*60*60;
						
						$doc = $this->issues()->aggregate(
							array('$project' => array(
								'events' => 1
							)),
							array('$unwind' => '$events'),
							array('$match' => array('$and' => array(array('events.date' => array('$gt' => $date_limit)), array('events.type' => 'comment'), ))),
							array('$group' => array('_id' => array('root_cause' => '$events.root_cause'), 'number' => array('$sum' => 1)))
						);

						$result = array();
						foreach ($doc['result'] as $v) {
							$v['_id']['number'] = $v['number'];
							if ($v['_id']['root_cause'] != '0') {
								$result[] = $v['_id'];
							}
						}
					$this->html_table_view($result, array('root_cause', 'number'), 'tasks');
					}
				}
		} else {
			$this->msg = 'error_table_unknown';
			$this->_handle_error($this->getLang($this->msg));
		}
	}
	private function _handle_issues() {
		echo '<h1>';
		echo $this->getLang('issues');
		echo '</h1>';
		
		if (isset($this->vald)) {
			foreach ($this->vald as $error) {
				echo '<div class="error">'.$error.'</div>';
			}
		}

		if (isset($_POST['days'])) {
			$value['days'] = $_POST['days'];
		} else {
			$value['days'] = '';
		}

		echo '<ol>';
		echo '<li>';
		echo '<a href="?do=bds_table&table=issues&report=newest_to_oldest">';
		echo $this->getLang('newest_to_oldest');
		echo '</a>';
		echo '</li>';

		echo '<li>';
		echo '<a href="?do=bds_table&table=issues&report=by_last_activity">';
		echo $this->getLang('by_last_activity');
		echo '</a>';
		echo '</li>';

		echo '<li>';
		echo '<a href="?do=bds_table&table=issues&report=my_opened">';
		echo $this->getLang('my_opened');
		echo '</a>';
		echo '</li>';
		echo '<li>';
		echo $this->getLang('newest_than');
		echo ': ';
		echo '<form action="?do=bds_table&table=issues&report=newest_than" method="post">';
		echo '<input class="days" type="numeric" name="days" value="'.$value['days'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '<li>';
		echo $this->getLang('newest_than_cost');
		echo ': ';
		echo '<form action="?do=bds_table&table=issues&report=newest_than_cost" method="post">';
		echo '<input class="days" type="numeric" name="days" value="'.$value['days'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '</ol>';

		echo '<h1>';
		echo $this->getLang('tasks');
		echo '</h1>';

		if (isset($this->vald_comment)) {
			foreach ($this->vald_comment as $error) {
				echo '<div class="error">'.$error.'</div>';
			}
		}

		if (isset($_POST['tdays'])) {
			$value['tdays'] = $_POST['tdays'];
		} else {
			$value['tdays'] = '';
		}

		echo '<ol>';
		echo '<li>';
		echo '<a href="?do=bds_table&table=tasks&report=newest_to_oldest">';
		echo $this->getLang('newest_to_oldest');
		echo '</a>';
		echo '</li>';

		echo '<li>';
		echo '<a href="?do=bds_table&table=tasks&report=my_opened">';
		echo $this->getLang('me_executor');
		echo '</a>';
		echo '</li>';

		echo '<li>';
		echo $this->getLang('newest_than');
		echo ': ';
		echo '<form action="?do=bds_table&table=tasks&report=newest_than" method="post">';
		echo '<input class="days" type="numeric" name="tdays" value="'.$value['tdays'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '</ol>';

		echo '<h1>';
		echo $this->getLang('reports');
		echo '</h1>';

		if (isset($this->vald_report)) {
			foreach ($this->vald_report as $error) {
				echo '<div class="error">'.$error.'</div>';
			}
		}

		if (isset($_POST['ridays'])) {
			$value['ridays'] = $_POST['ridays'];
		} else {
			$value['ridays'] = '';
		}

		if (isset($_POST['rtdays'])) {
			$value['rtdays'] = $_POST['rtdays'];
		} else {
			$value['rtdays'] = '';
		}

		if (isset($_POST['rcdays'])) {
			$value['rcdays'] = $_POST['rcdays'];
		} else {
			$value['rcdays'] = '';
		}
		
		echo '<ol>';

		echo '<li>';
		echo $this->getLang('report_issues');
		echo ': ';
		echo '<form action="?do=bds_table&table=reports&report=issues" method="post">';
		echo '<input class="days" type="numeric" name="ridays" value="'.$value['ridays'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '<li>';
		echo $this->getLang('report_tasks');
		echo ': ';
		echo '<form action="?do=bds_table&table=reports&report=tasks" method="post">';
		echo '<input class="days" type="numeric" name="rtdays" value="'.$value['rtdays'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '<li>';
		echo $this->getLang('report_causes');
		echo ': ';
		echo '<form action="?do=bds_table&table=reports&report=causes" method="post">';
		echo '<input class="days" type="numeric" name="rcdays" value="'.$value['rcdays'].'">';
		echo ' ';
		echo $this->getLang('days');
		echo ': ';
		echo '<input type="submit" value="'.$this->getLang('show').'">';
		echo '</form>';
		echo '</li>';

		echo '</ol>';
	}

	private function _handle_error($msg) {
		echo '<div class="error">'.$msg.'</div>';
	}

	public function handle_act_preprocess(&$event, $param) {
		global $INFO;
		global $auth;

		if (plugin_isdisabled('indexmenu')) {
			//disable indexmenu
			plugin_enable('indexmenu');
		}

		if ( ! $this->user_can_view()) {
			return false;
		}
		switch($event->data) {
			case 'bds_timeline':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issue_add':
			case 'bds_issue_change':
			case 'bds_issue_reopen':
			case 'bds_issue_add_event':
			case 'bds_issue_add_task':
			case 'bds_issue_change_event':
			case 'bds_issue_change_task':
			case 'bds_issues':
			case 'bds_table':
			case 'bds_8d':
			case 'bds_switch_lang':
				$event->stopPropagation();
				$event->preventDefault();
				//disable indexmenu
				plugin_disable('indexmenu');
				break;
		}
		switch($event->data) {
			case 'bds_switch_lang':

				$probe = $this->getLang('save');
				//$newlc = $_SESSION[DOKU_COOKIE]['translationlc'];
				$newlc = $_COOKIE['newlc'];
				if (!isset($newlc) || $newlc == '') {
					if ($probe == 'Save') {
						$newlc = 'pl';
					} else {
						$newlc = 'en';
					}
				} elseif ($newlc == 'en') {
						$newlc = 'pl';
				} else {
						$newlc = 'en';
				}
				//$_SESSION[DOKU_COOKIE]['translationlc'] = $newlc;
				setcookie('newlc', $newlc);
				header('Location: ?do=bds_issues');
				break;
			case 'bds_8d':
				$id = (int) $_GET['bds_issue_id'];
				$map = new MongoCode('function() {
					report = {};
					for (var i = 0; i < this.events.length; i++) {
						event = this.events[i];
						if (event.type === "comment") {
							if (event.root_cause !== 0) {
								if ( ! report.root_causes) {
									report.root_causes = {};
								}
								if ( ! report.root_causes[event.root_cause]) {
									report.root_causes[event.root_cause] = [];
								}
								(report.root_causes[event.root_cause]).push({content: event.content});
							}
						} else if (event.type === "task" && event.state != 2) {
							if ( ! report.tasks) {
								report.tasks = {};
							}
							if ( ! report.tasks[event.class]) {
								report.tasks[event.class] = [];
							}
							(report.tasks[event.class]).push({executor: event.executor, content: event.content, date: event.date, state: event.state, cost: event.cost, last_mod_date: event.last_mod_date, reason: event.reason});
						}
					}
					emit(this._id, report);
				}');
				$reduce = new MongoCode('function(key, value) {
					return value;
				}');
				$report = $this->bds()->command(array(
						'mapreduce' => 'issues',
						'map' => $map,
						'reduce' => $reduce,
						'query' => array('_id' => $id),
						'out' => array('inline' => 1)));
				$events = $report['results'][0]['value'];


				$cursor = $this->issues()->findOne(array('_id' => $id), array('_id' => true, 'description' => true, 'date' => true, 'reporter' => true, 'title' => true, 'entity' => true, 'opinion' => true, 'last_mod_date' => true, 'type' => true, 'state' => true, 'coordinator' => true));

				$cost_total = $this->issues()->aggregate(
				array('$match' => array('_id' => $id)),
				array('$unwind' => '$events'),
				array('$group' => array('_id' => '$_id', 'cost_total' => array('$sum' => '$events.cost')))
				);

				$cursor['root_causes'] = $events['root_causes'];
				$cursor['tasks'] = $events['tasks'];
				$cursor['cost_total'] = $cost_total['result'][0]['cost_total'];
				

				if (count($cursor) == 0) { 
					$this->error = 'error_issue_id_unknown';
					$this->_handle_error($this->getLang($this->error));
				} else {
					//$this->generate_8d_pdf_report($cursor);
					$this->report_cursor = $cursor;
					//exit(0);
				}
			break;
			case 'bds_issue_add':
			case 'bds_issue_change':
				$this->vald = array();

				if ( ! array_key_exists((int)$_POST['type'], $this->issue_types)) {
					$this->vald['type'] = $this->getLang('vald_type_required');
				} else {
					$post['type'] = (int)$_POST['type'];
				}

				if ( ! in_array($_POST['entity'], $this->entity)) {
					$this->vald['entity'] = $this->getLang('vald_entity_required');
				} else {
					$post['entity'] = $_POST['entity'];
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
				//FALLTHROU
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
								$post['last_mod_date'] = $post['date'];

								//it should be empty by default
								$post['opinion'] = '';

								try {
									$issues->insert($post);
									$_GET['bds_issue_id'] = $min_nr;

									//Wyślij powiadomienie
									$text = $this->rawLocale('bez_new_issue');
									$trep = array(
										'FULLNAME' => $this->get_name($post['coordinator']),
										'NR' => '#'.$post['_id'],
										'TYPE' => $this->string_format_field('type', $post['type']),
										'TITLE' => '['.$post['entity'].']'.$post['title'],
										'ISSUE' => $post['description'],
										'URL' => DOKU_URL.'doku.php'.$this->string_issue_href($post['_id'])
									);
									$mail = new Mailer();
									$mail->to($post['coordinator'].' <'.$this->get_email($post['coordinator']).'>');
									$mail->subject($this->getLang('new_issue').': #'.$post['_id'].' '.$this->string_format_field('type', $post['type']));
									$mail->setBody($text, $trep);
									$mail->send();
								
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
						//if coordinator not exiist do not chaange him
						if (isset($_POST['coordinator']) && $this->user_is_moderator($INFO['client'])) {
							if ($this->user_is_moderator($_POST['coordinator'])) {
								$post['coordinator'] = $_POST['coordinator'];
							} else {
								$this->vald['type'] = $this->getLang('vald_coordinator_required');
							}
						}

					//check only state when user is reopening issue
					//FALLTHROUGH
					case 'bds_issue_reopen':
						try {
							$issues = $this->issues();
							$id = (int)$_GET['bds_issue_id'];
							$cursor = $this->issues()->findOne(array('_id' => $id));

							
							if (isset($_POST['state']) && $this->user_is_moderator()) {
								$_POST['state'] = (int)$_POST['state'];
								if ( ! array_key_exists($_POST['state'], $this->issue_states)) {
									$this->vald['state'] = $this->getLang('vald_task_state_required');
								} else {
									//state is good laready
									$post['state'] = $_POST['state'];

									$opened_tasks = array();
									if ($cursor['state'] != $post['state'] && in_array($_POST['state'], $this->blocking_states)) {
										//check for opened tasks
										foreach ($cursor['events'] as $ev) {
											if ($ev['type'] == 'task' 
												&& $ev['state'] == array_search($this->getLang('task_opened'), $this->task_states)) {
												$opened_tasks[] = $this->html_anchor_to_event($cursor['_id'], $ev['id'], false, true);
											}
										}
										if (count($opened_tasks) > 0) {
											$opened = implode(', ', $opened_tasks);
											$this->vald['state'] = str_replace('%t', $opened, $this->getLang('vald_task_state_tasks_not_closed'));
										} 
									}

								}
							}

							if (isset($_POST['opinion'])) {
								$_POST['opinion'] = trim($_POST['opinion']);
								if (strlen($_POST['opinion']) > $this->getConf('desc_max_len')) {
									$this->vald['opinion'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_opinion_too_long'));
								} else if (strlen($_POST['opinion']) > 0 && ! in_array($post['state'], $this->blocking_states)) {
									$this->vald['opinion'] = $this->getLang('vald_cannot_give_opinion');
								} else {
									$post['opinion'] = $_POST['opinion'];
								}
							}


							if (count($this->vald) == 0) {

								if ($event->data == 'bds_issue_reopen') {
									//reopening user become corodinatro
									$post['coordinator'] = $INFO['client'];
									//wyzerowanie przyczyn
									$post['opinion'] = '';
								}
								//fields that cannot be changed by form
								$diff_cursor = $cursor;
								$unchangable = array('event', '_id', 'last_mod_author', 'last_mod_date', 'date', 'author', 'reporter');
								foreach ($unchangable as $field) {
									unset($diff_cursor[$field]);
								}

								//determine changes
								$new = array_diff_assoc($post, $diff_cursor);
								$prev = array_diff_assoc($diff_cursor, $post);
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

									if (isset($post['opinion']) && $post['opinion'] != $cursor['opinion']) {
										$post['last_opinion_author'] = $INFO['client'];
									}

									$issues->update(array('_id' => $id), array('$set' => $post)); 
									
									$issues->update(array('_id' => $id), array('$push' => 
											array('events' => $events)
										));
									//relocate
									$this->anchor = 'top';
								}
								$event->data = 'bds_issue_show';
							} else {
								$event->data = 'bds_issue_show';
							}
						} catch(MongoException $e) {
							$this->error = 'error_issue_update';
							$event->data = 'bds_error';
						}
						break;
				}
				break;
			case 'bds_issue_add_task':
			case 'bds_issue_change_task':

				if ($this->user_is_moderator()) {
					if ($this->user_exists($_POST['executor'])) {
						$post['executor'] = $_POST['executor'];
					} else {
						$this->vald_comment['executor'] = $this->getLang('vald_executor_not_exists');
					}


					//cost is not required
					if (isset($_POST['cost']) && $_POST['cost'] != '') {
						//remove not nessesery chars
						$locale = localeconv();
						$separators = array(' ', $locale['thousands_sep']);	
						$fract_sep = $locale['decimal_point'];

						$cost = str_replace($separators, '', $_POST['cost']);
						$cost_ex = explode($fract_sep, $cost);

						if (count($cost_ex) > 2 || ! ctype_digit($cost_ex[0])) {
							$this->vald_comment['cost'] = $this->getLang('vald_cost_wrong_format');
						} elseif (isset($cost_ex[1]) && (strlen($cost_ex[1]) > 2 || ! ctype_digit($cost_ex[1]))) {
							$this->vald_comment['cost'] = $this->getLang('vald_cost_wrong_format');
						} elseif ( (double)$_POST['cost'] > (double)$this->getConf('cost_max')) {
							$this->vald_comment['cost'] = str_replace('%d', $this->getConf('cost_max'), $this->getLang('vald_cost_too_big'));
						} else {
							$post['cost'] = (double) $_POST['cost'];
						}
					}

					if ( ! (isset($_POST['class']) && array_key_exists((int)$_POST['class'], $this->task_classes))) {
						$this->vald_comment['class'] = $this->getLang('vald_class_required');
					} else {
						$post['class'] = (int)$_POST['class'];
					}

				} 
				$post['type'] = 'task';
				$post['state'] = array_search($this->getLang('task_opened'), $this->task_states);
				//FALL THROUGH
			case 'bds_issue_add_event':
			case 'bds_issue_change_event':
				if ( ! isset($post['type'])) {
					$post['type'] = 'comment';
				}
				//_id -> $_GET['bds_issue_id'];
				$id = (int)$_GET['bds_issue_id'];
				$cursor = $this->issues()->findOne(array('_id' => $id));
				if ($cursor == NULL) {
						$this->error = 'error_issue_id_unknown';
						$event->data = 'bds_error';
						break;
				}

				if (isset($_POST['content'])) {
					if ($event->data != 'bds_issue_change_task' || $this->user_is_moderator()) {
						$_POST['content'] = trim($_POST['content']);
						if (strlen($_POST['content']) == 0) {
							$this->vald_comment['content'] = $this->getLang('vald_content_required');
						} else if (strlen($_POST['content']) > $this->getConf('desc_max_len')) {
							$this->vald_comment['content'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_content_too_long'));
						} else {
							$post['content'] = $_POST['content'];
						}
					}
				}

				if ($event->data == 'bds_issue_add_event' || $event->data == 'bds_issue_change_event') {
					if ( ! array_key_exists((int)$_POST['root_cause'], $this->root_causes)) {
						$post['root_cause'] = 0;
					} else {
						$post['root_cause'] = (int)$_POST['root_cause'];
					}
				}


				
				try {

					//editing
					if ($event->data == 'bds_issue_change_event' || $event->data == 'bds_issue_change_task') {
						$bds_event_id = (int)$_GET['bds_event_id'];
						$bds_edited_event = array();

						$issues = $this->issues();
						if ($issues == false) {
							$this->error = 'error_db_connection';
							$event->data = 'bds_error';
							break;
						} else {
							$id_exists = false;
							foreach ($cursor['events'] as $ev) {
								if ($ev['id'] == $bds_event_id) {
									$id_exists = true;
									$bds_edited_event = $ev;
								}
							}
							if ($id_exists == false) {
								$this->error = 'error_event_id_unknown';
								$event->data = 'bds_error';
								break;
							}
							if ($bds_edited_event['type'] == 'change') {
								$this->error = 'error_event_cannot_edit_changes';
								$event->data = 'bds_error';
								break;
							}
						}

						//change state fo the tasks.
						if ($event->data == 'bds_issue_change_task') {
							//normla users can only change status of their tasks
							if (isset($_POST['state']) && array_key_exists((int)$_POST['state'], $this->issue_states)) {
								$post['state'] = (int)$_POST['state'];
							}

							if (isset($_POST['reason'])) {
								$_POST['reason'] = trim($_POST['reason']);
								if (strlen($_POST['reason']) > $this->getConf('desc_max_len')) {
									$this->vald_comment['reason'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_reason_too_long'));
								} else if ($_POST['reason'] != $bds_edited_event['reason'] && strlen($_POST['reason']) > 0 && $post['state'] == $bds_edited_event['state']) {
									$this->vald_comment['reason'] = $this->getLang('vald_cannot_give_reason');
								} else {
									$post['reason'] = $_POST['reason'];
								}
							}
						}


						if (count($this->vald_comment) == 0) {
							$issues = $this->issues();
							if ($issues == false) {
								throw new MongoException('Cannto load issues.');
							}
							//check if anything was changed
							$any_changes = false;
							foreach ($post as $k => $v) {
								if ($bds_edited_event[$k] != $v) {
									$any_changes = true;
									break;
								}
							}

							if ($any_changes == true) {
								//$bds_edited_event;
								if ( ! isset($bds_edited_event['rev'])) {
									$bds_edited_event['rev'] = array();
								}
								$old_event = $bds_edited_event;
								unset($old_event['rev']);

								$post['last_mod_author'] = $INFO['client'];
								$post['last_mod_date'] = time();

								if ($old_event['quoted_in'] != NULL) {
									$post['quoted_in'] = $old_event['quoted_in']; 
								}
								if ($old_event['replay_to'] != NULL) {
									$post['replay_to'] = $old_event['replay_to']; 
								}

								//in case of change
								if (isset($old_event['new'])) {
									$post['new'] = $old_event['new'];
									unset($old_event['new']);
								}
								if (isset($old_event['prev'])) {
									$post['prev'] = $old_event['prev'];
									unset($old_event['prev']);
								}

								unset($old_event['quoted_in']);
								unset($old_event['replay_to']);
								unset($old_event['author']);
								unset($old_event['date']);
								unset($old_event['type']);
								unset($old_event['id']);

								if ( ! isset($bds_edited_event['rev'])) {
									$bds_edited_event['rev'] = array();
								}

								array_unshift($bds_edited_event['rev'], $old_event);
								$post['rev'] = $bds_edited_event['rev'];

								$events = array();
								foreach ($post as $k => $v) {
									$events['events.$.'.$k] = $v;
								}

								if (strpos($event->data, 'event') !== false
									|| $bds_edited_event['executor'] == $INFO['client']
									|| $this->user_is_moderator()) {
										$issues->update(array('_id' => $id, 'events.id' => $bds_event_id), array('$set' => $events)); 
									}

								$issue['last_mod_author'] = $INFO['client'];
								$issue['last_mod_date'] = time();
								$issues->update(array('_id' => $id), array('$set' => $issue)); 
							}
							//redirecting 
							$event->data = 'bds_issue_show';
							$this->anchor = $bds_event_id;
						} else {
							$event->data ='bds_issue_show';
						}
					//adding 
					//only moderator can add tasks
					} else if ($event->data == 'bds_issue_add_event' || $this->user_is_moderator()) {
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
								throw new MongoException('Cannto load issues.');
							} else {
								//start from 1;
								$post['id'] = count($cursor['events']) + 1;
								$post['author'] = $INFO['client'];
								$post['date'] = time();

								$post['last_mod_author'] = $post['author'];
								$post['last_mod_date'] = $post['date'];

								$issue['last_mod_author'] = $INFO['client'];
								$issue['last_mod_date'] = time();

								$issues->update(array('_id' => $id), array('$set' => $issue)); 

								if (isset($_GET['replay_to'])) {
									$quoted['events.$.quoted_in'] = $post['id'];

									/*$xxx = $this->issues()->findOne(array('_id' => $id));
									foreach ($xxx['events'] as $event) {
										if ($event['id'] == $replay_to) {
											var_dump($event);
											exit();
										}
									}*/
									$issues->update(array('_id' => $id, 'events.id' => $replay_to), 
														array('$push' => $quoted)); 
								}
								
								$issues->update(array('_id' => $id), array('$push' => 
											array('events' => $post)
											));
								//Wyślij powiadomienie

								$cursor = $issues->findOne(array('_id' => $id));
								$text = $this->rawLocale('bez_new_task');
								$nr = '#'.$cursor['_id'].':'.$post['id'];
								$trep = array(
									'FULLNAME' => $this->get_name($post['executor']),
									'NR' => $nr,
									'TASK' => $post['content'],
									'URL' => DOKU_URL.'doku.php'.$this->string_issue_href($cursor['_id'], $post['id'])
								);
								$mail = new Mailer();
								$mail->to($post['executor'].' <'.$this->get_email($post['executor']).'>');
								$mail->subject($this->getLang('new_task').': '.$nr.' '.$this->string_format_field('class', $post['class']));
								$mail->setBody($text, $trep);
								$mail->send();

								$event->data = 'bds_issue_show';
								//scroll down to new one
								$this->anchor = $post['id'];
							}
						} else {
							$event->data = 'bds_issue_show';
						}
					} else {
						$this->error = 'error_task_add';
						$event->data = 'bds_error';
					}
					} catch(MongoException $e) {
						$this->error = 'error_event_add';
						$event->data = 'bds_error';
					}
		}
		//need relocating
		if ($this->anchor != '') {
			header('Location: '. $this->create_url($event->data));
		}
	}

	function create_url($do) {
			$get = array();
			foreach ($_GET as $k => $v) {
				$get[$k] = $v;
			}
			//remember about event->data
			$get['do'] = $do;

			//some special changes
			if (count($this->vald_comment) == 0) {
				unset($get['replay_to']);
				unset($get['bds_event_id']);
			}
			$url = '?';
			foreach ($get as $k => $v) {
				$url .= urlencode($k).'='.urlencode($v).'&';
			}
			//remove last &
			$url = substr($url, 0, -1);
			$url .= '#'.urlencode($this->anchor);
			return $url;
	}

	public function handle_act_unknown(& $event, $param) {
		if ( ! $this->user_can_view()) {
			return false;
		}
		switch ($event->data) {
			case 'bds_timeline':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issues':
			case 'bds_table':
			case 'bds_error':
			case 'bds_8d':
			case 'bds_switch_lang':
				$event->stopPropagation(); 
				$event->preventDefault();  
				break;
		}
		switch ($event->data) {
			case 'bds_timeline':
				$this->_handle_timeline();
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
			case 'bds_table':
				$this->_handle_table();
				break;
			case 'bds_error':
				$this->_handle_error($this->getLang($this->error));
				break;
			case 'bds_8d':
					$this->generate_8d_html_report($this->report_cursor);
					break;
			case 'bds_switch_lang':
				break;
		}
	}
 
	public function add_menu_item(&$event, $param) {
		global $lang;

		if ( ! $this->user_can_view()) {
			return false;
		}

		$lang['btn_bds_timeline'] = $this->getLang('bds_timeline');
		$lang['btn_bds_issues'] = $this->getLang('bds_issues');

		if ($this->user_can_edit()) {
			$lang['btn_bds_issue_report'] = $this->getLang('bds_issue_report');
		}
		$lang['btn_bds_switch_lang'] = $this->getLang('bds_switch_lang');

		$event->data['items']['separator'] = '<li style="display:block;float:left;">&nbsp;</li>';

		$event->data['items']['bds_timeline'] = tpl_action('bds_timeline', 1, 'li', 1);
		$event->data['items']['bds_issues'] = tpl_action('bds_issues', 1, 'li', 1);
		if ($this->user_can_edit()) {
			$event->data['items']['bds_issue_report'] = tpl_action('bds_issue_report', 1, 'li', 1);
		}
		$event->data['items']['bds_switch_lang'] = tpl_action('bds_switch_lang', 1, 'li', 1);
	}
	public function add_action(&$event, $param) {
		if ( ! $this->user_can_view()) {
			return false;
		}
		$data = &$event->data;

		switch($data['type']) {
			case 'bds_timeline':
			case 'bds_issues':
			case 'bds_issue_report':
			case 'bds_switch_lang':
				$event->preventDefault();
		}

	}
}

