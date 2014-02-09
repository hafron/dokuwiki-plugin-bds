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
	private $blocking_states = array();

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
		$this->issue_types[0] = $this->getLang('type_noneconformity');
		$this->issue_types[1] = $this->getLang('type_client_complaint');
		$this->issue_types[2] = $this->getLang('type_supplier_complaint');

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

		if (isset($_POST['opinion'])) {
			$value['opinion'] = $_POST['opinion'];
		} elseif (isset($default['opinion'])) {
			$value['opinion'] = $default['opinion'];
		} else {
			$value['opinion'] = '';
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
				if ($value['class'] == $name) {
					echo ' selected';
				}
				echo ' value="'.$name.'">'.$name.'</opiton>';
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

		echo '</filedset>';
		echo '<input type="submit" value="'.$data['submit'].'">';
		echo '</form>';
		echo '</div>';
		echo '</div>';
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
	private function html_anchor_to_event($issue, $event, $show_issue=false) {
		$value = '';
		if ($show_issue == true) {
			$value .= '#'.$issue.':';
		}
		$value .= $event;
		return '<a href="?do=bds_issue_show&bds_issue_id='.$issue.'#'.$event.'">'.$value.'</a>';
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

	private function _handle_timeline() {
		echo '<h1>'.$this->getLang('timeline').'</h1>';
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
			echo $this->string_time_to_now($cursor['date']);
			echo '</span>';
			echo '<span>';
			if (isset($cursor['last_mod_date'])) {
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
			echo ' ';
			echo  '('.$this->getLang('last_modified_by').' '.$this->string_get_full_name($desc_author).')';
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


			echo '</div>';

			echo '<div class="bds_block" id="bds_history">';
			echo '<h1>'.$this->getLang('changes_history').' <span>('.count($cursor['events']).')</span></h1>';
			echo '<div class="bds_block_content">';
			if (isset($cursor['events'])) {
				foreach ($cursor['events'] as $event) {
					//create anchor
					echo '<div id="'.$event['id'].'"';
					if ($event['type'] == 'task') {
						echo ' class="task"';
					}
					echo '>';
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
						echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_to='.$event['id'].'#comment_form">↳ '.$this->getLang('replay').'</a>';
						echo ' ';
						if ($this->user_is_moderator()) {
							echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&replay_by_task='.$event['id'].'#task_form">↳ '.$this->getLang('replay_by_task').'</a>';
							echo ' ';
						}
						if ($event['type'] != 'change') {
							echo '<a class="bds_inline_button" href="?do=bds_issue_show&bds_issue_id='.$cursor['_id'].'&bds_event_id='.$event['id'].'#'.$event['type'].'_form">✎ '.$this->getLang('edit').'</a>';
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
						case 'task':
							echo '<table>';	
							echo '<tr>';
								if ($rev == -1) {
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

					if (isset($event['content'])) {
						if ( $rev == -1) {
							echo $this->wiki_parse($event['content']);
						} else {
							echo $this->wiki_parse($event['rev'][$rev]['content']);
						}
					}
					if (isset($event['rev'])) {
						echo '<span class="bds_last_edit">';
							if ($rev == -1) {
								echo $this->getLang('last_modified');
								echo ' ';
								echo $this->string_format_field('date', $event['date']);
								echo ' ';
								echo $this->getLang('by');
								echo ' ';
								echo $this->string_format_field('name', $event['author']);
							} else {
								echo $this->getLang('version');
								echo ': ';
								echo count($event['rev']) - $rev;
								echo ' ';
								echo lcfirst($this->getLang('last_modified'));
								echo ' ';
								echo $this->string_format_field('date', $event['rev'][$rev]['date']);
								echo ' ';
								echo $this->getLang('by');
								echo ' ';
								echo $this->string_format_field('name', $event['rev'][$rev]['author']);
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

				if ($this->user_is_moderator() ) {
					if ($ev_type == 'task') {
						$action = '?do=bds_issue_change_task&bds_issue_id='.$cursor['_id'].'&bds_event_id='.$bds_event_id;
						$this->html_generate_event_form($action, $cursor, 
							array('submit' => $this->getLang('change_task_button'), 'header' => array('name' => 'task_form', 'value' => $this->getLang('change_task')), 'event' => $_GET['do'], 'do' => 'bds_issue_change_task'), $ev_cursor);
					} else {
						$action = '?do=bds_issue_add_task&bds_issue_id='.$cursor['_id'].'';
						$this->html_generate_event_form($action, $cursor, 
							array('submit' => $this->getLang('add'), 'header' => array('name' => 'task_form', 'value' => $this->getLang('add_task')), 'event' => $_GET['do'], 'do' => 'bds_issue_add_task', 'replay_to' => $_GET['replay_by_task']));
					}
				}



				echo '<div class="bds_block" id="bds_change_issue">';
				echo '<h1>'.$this->getLang('change_issue').'</h1>';
				echo '<div class="bds_block_content">';
				$action = '?do=bds_issue_change&bds_issue_id='.$cursor['_id'].'#bds_change_issue';
				$this->html_generate_report_form($action, $cursor);
				echo '</div>';
				echo '</div>';
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
					//FALLTHROUGH
					case 'bds_issue_reopen':
						if ( ! array_key_exists((int)$_POST['state'], $this->issue_states)) {
							$this->vald['state'] = $this->getLang('vald_state_required');
						} else {
							$post['state'] = (int)$_POST['state'];
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
			case 'bds_issue_change_task':

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
						} elseif (isset($cost_ex[1]) && (strlen($cost_ex[1]) > 2 || ! ctype_digit($cost_ex[1]))) {
							$this->vald_comment['cost'] = $this->getLang('vald_cost_wrong_format');
						} elseif ( (double)implode('.', $cost_ex) > (double)$this->getConf('cost_max')) {
							$this->vald_comment['cost'] = str_replace('%d', $this->getConf('cost_max'), $this->getLang('vald_cost_too_big'));
						} else {
							if ( ! isset($cost_ex[1])) {
								$cost .= ',00';
							} else if (strlen($cost_ex[1]) == 1) {
								$cost = $cost_ex[0].','.$cost_ex[1].'0';
							}
							$post['cost'] = $cost;
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
					break;
				}
				$post['type'] = 'task';
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

				$_POST['content'] = trim($_POST['content']);
				if (strlen($_POST['content']) == 0) {
					$this->vald_comment['content'] = $this->getLang('vald_content_required');
				} else if (strlen($_POST['content']) > $this->getConf('desc_max_len')) {
					$this->vald_comment['content'] = str_replace('%d', $this->getConf('desc_max_len'), $this->getLang('vald_content_too_long'));
				} else {
					$post['content'] = $_POST['content'];
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

								$post['author'] = $INFO['client'];
								$post['date'] = time();

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

								$issues->update(array('_id' => $id, 'events.id' => $bds_event_id), array('$set' => $events)); 

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
					} else {
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

								$event->data = 'bds_issue_show';
								//scroll down to new one
								$this->anchor = $post['id'];
							}
						} else {
							$event->data = 'bds_issue_show';
						}
					}
					} catch(MongoException $e) {
						$this->error = 'error_event_add';
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
				unset($get['bds_event_id']);
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
			case 'bds_timeline':
			case 'bds_issue_report':
			case 'bds_issue_show':
			case 'bds_issues':
			case 'bds_error':
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

		$lang['btn_bds_timeline'] = $this->getLang('bds_timeline');
		$lang['btn_bds_issues'] = $this->getLang('bds_issues');

		if ($this->user_can_edit()) {
			$lang['btn_bds_issue_report'] = $this->getLang('bds_issue_report');
		}

		$event->data['items']['separator'] = '<li style="display:block;float:left;">&nbsp;</li>';
		$event->data['items']['bds'] = '<li><strong>'.$this->getLang('bds_short').':</strong></li>';

		$event->data['items']['bds_timeline'] = tpl_action('bds_timeline', 1, 'li', 1);
		$event->data['items']['bds_issues'] = tpl_action('bds_issues', 1, 'li', 1);
		if ($this->user_can_edit()) {
			$event->data['items']['bds_issue_report'] = tpl_action('bds_issue_report', 1, 'li', 1);
		}
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
				$event->preventDefault();
		}

	}
}

