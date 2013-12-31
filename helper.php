<?php
/**
 * Plugin Now: Inserts a timestamp.
 * 
 * @license    GPL 3 (http://www.gnu.org/licenses/gpl.html)
 * @author     Szymon Olewniczak <szymon.olewniczak@rid.pl>
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class helper_plugin_bds extends dokuwiki_plugin {
	public function ObjectId($id) {
		$sanitized = '';
		//sanitize
		$allowed = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f');
		for ($i = 0; $i < strlen($id); $i++) {
			if (in_array($id[$i], $allowed)) {
				$sanitized .= $id[$i];
			}
		}
		return $sanitized;
	}
}
