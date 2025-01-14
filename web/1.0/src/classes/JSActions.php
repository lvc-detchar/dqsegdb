//# Copyright (C) 2014-2020 Syracuse University, European Gravitational Observatory, and Christopher Newport University.  Written by Ryan Fisher and Gary Hemming. See the NOTICE file distributed with this work for additional information regarding copyright ownership.

//# This program is free software: you can redistribute it and/or modify

//# it under the terms of the GNU Affero General Public License as

//# published by the Free Software Foundation, either version 3 of the

//# License, or (at your option) any later version.

//#

//# This program is distributed in the hope that it will be useful,

//# but WITHOUT ANY WARRANTY; without even the implied warranty of

//# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the

//# GNU Affero General Public License for more details.

//#

//# You should have received a copy of the GNU Affero General Public License

//# along with this program.  If not, see <http://www.gnu.org/licenses/>.
<?php

//////////////////////////////
// Jquery-related actions. //
////////////////////////////

// Get libraries.
require_once('DAO.php');
require_once('Files.php');
require_once('GetServerData.php');
require_once('GetStructure.php');
require_once('InitVar.php');

// JavaScript/AJAX/Jquery action class.
class JSAction {
	
	private $document;
	private $getReqResponse;
 
	public function __construct() {
		// Build JS action response.
		$this->document = $this->getReqResponse();
	}

	// Decide which response to build.
	private function getReqResponse() {
		// Instantiate.
		$dao = new DAO();
		$file = new Files();
		$serverdata = new GetServerData();
		$structure = new GetStructure();
		$variable = new Variables();
		// Get admin type.
		$variable->getReq();
		// Get app variables.
		$variable->get_app_variables();
		// Get file-related variables.
		$variable->get_file_related_variables();
		// Get query server form.
		if($variable->req == 'update_div_query_server') {
			// If IFO passed.
			if(isset($_GET['ifo'])) {
				$_SESSION['ifo'] = $_GET['ifo'];
				// Reset arrays.
				unset($_SESSION['uri_deselected']);
				$_SESSION['uri_deselected'] = array();
				unset($_SESSION['dq_flag']);
//				$_SESSION['dq_flag'] = array();
			}
			$serverdata->get_query_form_div(3);
			$this->document = $serverdata->query_form;
		}
		// Build an individual JSON payload.
		elseif($variable->req == 'build_individual_json_payload') {
			// Reset arrays.
			unset($_SESSION['uri_deselected']);
			$_SESSION['uri_deselected'] = array();
			// Add to deselected array.
			array_push($_SESSION['uri_deselected'], $_GET['uri']);
			// Get segment JSON.
			$data = $serverdata->retrieve_segments(NULL, NULL);
			// If JSON passed.
			if(!empty($data)) {
				// Set format.
				$format = 'json';
				// Set filename.
				$in_file = time().'.'.$format;
				// Make JSON file.
				if($file->make_json_file($in_file, $data)) {
					// Insert file metadata to database.
	                $dao->insert_file_metadata($in_file, $format);
					// Set return.
					$this->document = $variable->download_dir.$in_file;
				}
			}
		}
		// Filter JSON payload list.
		elseif($variable->req == 'filter_json_payloads') {
			// Set user filter.
			if(isset($_GET['u'])) {
				$_SESSION['filter_user'] = $_GET['u'];
			}
			// Set data filter.
			if(isset($_GET['d'])) {
				$_SESSION['filter_data'] = $_GET['d'];
			}
			// Reset filter start page.
			$_SESSION['filter_start_page'] = $variable->default_filter_start_page;
			// Set return.
			$this->document = $dao->get_recent_query_results($variable->payloads_to_display, FALSE, 4);
		}
		// Update version div.
		elseif($variable->req == 'update_version_div') {
			// If flag passed.
			if(isset($_GET['dq_flag'])) {
				$_SESSION['dq_flag'] = $_GET['dq_flag'];
			}
			$serverdata->get_version_div_contents(3);
			$this->document = $serverdata->version_div;
		}
		// Update version div from textarea.
		elseif($variable->req == 'update_version_div_from_ta') {
			// If flags passed.
			if(isset($_GET['dq_flag'])) {
				// Set flag session.
				$_SESSION['dq_flag'] = $serverdata->set_ta_flags($_GET['dq_flag']);
			}
			$serverdata->get_version_div_contents(3);
			$this->document = $serverdata->version_div;
		}
		// Get version div.
		elseif($variable->req == 'update_version_select_session') {
			// If URI passed.
			if(isset($_GET['uri'])) {
				// If URI not in update array.
				if(!in_array($_GET['uri'], $_SESSION['dq_flag_version_update'])) {
					// Add to array.
					array_push($_SESSION['dq_flag_version_update'], $_GET['uri']);
				}
				// Otherwise.
				else {
					// Get value key and then delete.
					$k = array_search($_GET['uri'], $_SESSION['dq_flag_version_update']);
					unset($_SESSION['dq_flag_version_update'][$k]);
				}
			}
			$serverdata->get_flag_version_span_contents($_GET['uri']);
			$this->document = $serverdata->version_span;
		}
		// If selecting/de-selecting a version.
		elseif($variable->req == 'deselect_version_uri') {
			// Set selected class to re-send.
			$this->document = '';
			// If URI passed.
			if(isset($_GET['uri'])) {
				// If URI not in deselected array.
				if(!in_array($_GET['uri'], $_SESSION['uri_deselected'])) {
					// Add to deselected array.
					array_push($_SESSION['uri_deselected'], $_GET['uri']);
					// Set deselected class to re-send.
					$this->document = '_checked';
				}
				// Otherwise, if in deselected array.
				else {
					// Remove from de-selected array.
					if(($k = array_search($_GET['uri'], $_SESSION['uri_deselected'])) !== false) {
						unset($_SESSION['uri_deselected'][$k]);
					}
				}
			}
		}
		// If retrieving segments.
		elseif($variable->req == 'retrieve_segments') {
			// Get segment JSON.
			$data = $serverdata->retrieve_segments($_GET['s'], $_GET['e'], $_GET['history']);
			// If JSON passed.
			if(!empty($data)) {
				// Get UNIX timestamp.
				$unix_ts = time();
				// Set in-file filename.
				$in_file = $unix_ts.'.json';
				// Make JSON file.
				if($file->make_json_file($in_file, $data)) {
					// Set out-file filename.
					$out_file = $unix_ts.'.'.$_GET['format'];
					// Make non-JSON file.
					$file->make_non_json_file($in_file, $out_file, $data, $_GET['format']);
					// Set file to open automatically, replacing underscre with point, so as to enable JSON data to to be formatted in browser.
					$this->document = $variable->download_dir.$unix_ts.'.'.str_replace('_', '.', $_GET['format']);
				}
			}
		}
		// If re-populating recent query results div.
		elseif($variable->req == 'get_recent_query_results') {
			// Get application variables.
			$variable->get_app_variables();
			// Set default limit.
			$limit = $variable->payloads_to_display;
			// If on homepage.
			if($_GET["home"]) {
				// Set limit.
				$limit = $variable->payloads_to_display_on_homepage;
			}
			// Get recent results.
			$this->document = $dao->get_recent_query_results($limit, $_GET['home'], 3);
		}
		// If providing option to change host.
		elseif($variable->req == 'get_current_host_box') {
			// Update session to take request into account.
			if(!$_SESSION['changing_current_host']) {
				$_SESSION['changing_current_host'] = TRUE;
			}
			else {
				$_SESSION['changing_current_host'] = FALSE;
			}
			// Get current-host div contents.
			$this->document = $structure->get_current_host_div_contents(3);
		}
		// Set the currently-used host.
		elseif($variable->req == 'set_current_host') {
			$_SESSION['default_host'] = $_GET['h'];
			// Unset selected flags.
			unset($_SESSION['dq_flag']);
			// Unset selected URI.
			unset($_SESSION['uri_deselected']);
			// Stop changing host.
			$_SESSION['changing_current_host'] = FALSE;
		}
		// Alternate the flag choice option.
		elseif($variable->req == 'alternate_flag_choice_option') {
			if($_SESSION['flag_choice_option'] == 0) {
				$_SESSION['flag_choice_option'] = 1;
			}
			else {
				$_SESSION['flag_choice_option'] = 0;
			}
			// Get currently selected option.
			$this->document = $serverdata->get_choose_flag_option(3);
		}
		// Set filter start page number.
		elseif($variable->req == 'set_filter_start_page_no') {
			$_SESSION['filter_start_page'] = $_GET['p'];
		}
                // Setting session memory for format choice
                elseif($variable->req == 'set_format') {
        			$_SESSION['default_output_format'] = $_GET['f'];
                }
                elseif($variable->req == 'set_history') {
        			$_SESSION['default_output_history'] = $_GET['h'];
                }
        // Output response.
		echo $this->document;
	}

}

?>
