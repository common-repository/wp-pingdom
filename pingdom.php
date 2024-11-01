<?
/*
 Plugin Name: Pingdom Wordpress Widget
 Description: Adds a sidebar widget to display Website uptime using Pingdom, with which you must have an account and api key.
 Author: Elliott C. Back
 Version: 1.0
 Author URI: http://wordpress-plugins.feifei.us/
 Plugin URI: http://wordpress-plugins.feifei.us/pingdom/

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 */
class Pingdom {
	// constants
	var $url 		= 'https://ws.pingdom.com/soap/PingdomAPI.wsdl';
	var $api_ok 	= 0;

	// runtime variables
	var $user, $pass, $api_key, $check, $error;
	var $client, $session, $loggedin;

	function __construct(){
		$options = get_option('widget_pingdom');
		$this->user = $options['username'];
		$this->pass = $options['password'];
		$this->api_key = $options['apikey'];
		$this->check = $options['check'];
		$this->interval = $options['interval'];
	}
	
	function doLogin(){
		// only do this once
		if($this->loggedin)
		return;

		// connect
		$this->client = new SoapClient($this->url, array('trace' => 1, 'exceptions' => 0));

		// login
		$login_data->username = $this->user;
		$login_data->password = $this->pass;
		$login_response = $this->client->Auth_login($this->api_key, $login_data);
		
		if ($this->api_ok != $login_response->status) {
			$this->error = 'Unable to login.';
			return;
		}
			
		// set the session
		$this->session = $login_response->sessionId;

		// note login
		$this->loggedin = true;
	}
	
	function getChecks(){
		if(ENABLE_CACHE) {
			$checks = wp_cache_get('pingdom-checks');
		}
		
		if(!$checks){
			$this->doLogin();
			$get_list_response = $this->client->Check_getList($this->api_key, $this->session);

			if ($this->api_ok != $get_list_response->status){
				$this->error = 'Error occurred while trying to get list of checks';
				return array();
			}
				
			if(ENABLE_CACHE) {
				wp_cache_set('pingdom-checks', $get_list_response->checkNames, '', 3600);
			}
				
			return $get_list_response->checkNames;
		} else {
			return $checks;
		}
	}
	
	function getLastStates(){
		if(ENABLE_CACHE) {
			$lastStates = wp_cache_get('pingdom-last-states');
		}
		
		if(!$lastStates){
			$this->doLogin();
			$current_states_response = $this->client->Report_getCurrentStates($this->api_key, $this->session);

			if ($this->api_ok != $current_states_response->status){
				$this->error = 'Error occurred while trying to get list of statuses for your checks';
				return array();
			}
				
			if(ENABLE_CACHE) {
				wp_cache_set('pingdom-last-states', $current_states_response->status, '', 3600);
			}
			
			return $current_states_response->currentStates;
		} else {
			return $lastStates;
		}
	}
	
	function getLastDowntimes(){
		if(ENABLE_CACHE) {
			$lastDowntimes = wp_cache_get('pingdom-last-downtimes');
		}
		
		if(!$lastDowntimes){
			$this->doLogin();
			$last_down_response = $this->client->Report_getLastDowns($this->api_key, $this->session);

			if ($this->api_ok != $last_down_response->status){
				$this->error = 'Error occurred while trying to get list of yours checks last downs';
				return array();
			}
		
			if(ENABLE_CACHE){
				wp_cache_set('pingdom-last-downtimes', $last_down_response->lastDowns, '', 3600);
			}
			
			return $last_down_response->lastDowns;
		} else {
			return $lastDowntimes;
		}
	}
	
	function getDowntimes(){
		if(ENABLE_CACHE) {
			$downtimes = wp_cache_get('pingdom-downtimes');
		}
		
		if(!$downtimes){
		
			$this->doLogin();
			$get_downtimes_request->checkName = $this->check;
			$get_downtimes_request->from = time() - $this->interval;
			$get_downtimes_request->to = time();
			$get_downtimes_request->resolution = 'DAILY';
			$get_downtimes_response = $this->client->Report_getDowntimes($this->api_key, $this->session, $get_downtimes_request);
	
			if ($this->api_ok != $last_down_response->status) {
				$this->error = 'Error occurred while trying to get list of downtimes';
				return array();
			}
			
			if(ENABLE_CACHE){
				wp_cache_set('pingdom-downtimes', $get_downtimes_response->downtimesArray, '', 3600);
			}
			
			return $get_downtimes_response->downtimesArray;
		} else {
			return $downtimes;
		}
	}
	
	function getDowntimeGraph($filename, $width, $height, $relative){
		$data = array();
		$min = 0;
		$max = 0;

		foreach($this->getDowntimes() as $day){
			$data [] = $day->duration;
		
			if($day->duration > $max)
			$max = $day->duration;

			if($day->duration < $min)
			$min = $day->duration;
		}
		
		if($relative){
			for($i = 0; $i < count($data); $i++){
				$data[$i] = $max - $data[$i];
			}
		}
		
		// required libs
		require_once('lib/Sparkline_Bar.php');

		$sparkline = new Sparkline_Bar();
		$sparkline->SetYMax($max);
		$sparkline->SetYMin($min);
		$sparkline->SetBarSpacing(1);
		$sparkline->SetBarWidth($width / count($data) - 1);
		$sparkline->SetColor('BG', 240, 240, 200);
		$sparkline->SetColor('UP', 90, 190, 0);
		$sparkline->SetColor('DN', 180, 40, 0);
		$sparkline->SetColorBackground('BG');

		for($i = 0; $i < sizeof($data); $i++) {
			if($relative)
			$sparkline->SetData($i, $data[$i], $data[$i] != $max ? 'DN' : 'UP');
			else
			$sparkline->SetData($i, 86400 - $data[$i], $data[$i] > 0 ? 'DN' : 'UP');
		}

		$sparkline->Render($height);
		$sparkline->Output($filename);
	}
	
	function getDowntimeText($formatString){
		$days = 0;
		$downtime = 0;

		foreach($this->getDowntimes() as $day){
			$downtime += $day->duration;
			$days++;
		}
		
		$amount_post = 'of';
		
		if($downtime <= 0){
			$text = 'no';
			$amount_post = '';
		} else if($downtime < 60){
			$text = $downtime . ' seconds';
		} else if($downtime < 3600){
			$text = round($downtime / 60, 2) . ' minutes';
		} else if($downtime < 86400){
			$text = round($downtime / 3600, 2) . ' hours';
		} else if($downtime < 31556736){
			$text = round($downtime / 86400, 2) . ' days';
		} else {
			$text = 'for-ev-er';
		}
		
		$uptime = round((1 - $downtime / ($days * 86400)) * 100, 4);
		$days_unit = 'day' . (($downtime > 0)?'s':'');

		return str_replace(array('{UPTIME}', '{DAYS}', '{DAYS UNIT}', '{AMOUNT}', '{AMOUNT POSTFIX}'), array($uptime, $days, $days_unit, $text, $amount_post), $formatString);
	}
	
	function getError(){
		return $this->error;
	}
	
	function __destruct() {
		if(!$this->loggedin)
		return;

		$logout_response = $this->client->Auth_logout($this->api_key, $this->session);

		//Check if everything is OK
		if ($this->api_ok != $logout_response->status) {
			$this->error = 'Error occurred while closing connection';
			return;
		}
	}
}

function widget_pingdom_init() {
	if ( !function_exists('register_sidebar_widget') )
	return;

	function widget_pingdom($args) {
		extract($args);
		$options 		= get_option('widget_pingdom');
		$title 			= htmlspecialchars($options['title'], ENT_QUOTES);
		$formatstring 	= htmlspecialchars($options['formatstring'], ENT_QUOTES);
		$graph_on 		= htmlspecialchars($options['graph_on'], ENT_QUOTES);
		$graph_relative = htmlspecialchars($options['graph_relative'], ENT_QUOTES);
		$graph_width 	= htmlspecialchars($options['graph_width'], ENT_QUOTES);
		$graph_height 	= htmlspecialchars($options['graph_height'], ENT_QUOTES);

		echo $before_widget . $before_title . $title . $after_title;
		echo '<small>By <a href="http://wordpress-plugins.feifei.us/pingdom/">WP Pingdom</a> and <a href="http://pingdom.com">Pingdom</a></small>';
		
		// get the text
		if(ENABLE_CACHE) {
			$text = wp_cache_get('pingdom-text');

			if($text == false){
				$pingdom = new Pingdom();
				$text = @$pingdom->getDowntimeText($formatstring);
				wp_cache_set('pingdom-text', $text, '', 3600);
			}
		} else {
			$pingdom = new Pingdom();
			$text = @$pingdom->getDowntimeText($formatstring);
		}
		
		// get the graph
		if($graph_on){
			if(ENABLE_CACHE) {
				$graph = wp_cache_get('pingdom-graph');
		
				if($graph == false){
					$pingdom = new Pingdom();
					$pingdom->getDowntimeGraph(ABSPATH . '/wp-content/cache/pingdom-graph.png', $graph_width, $graph_height, $graph_relative);
					wp_cache_set('pingdom-graph', true, '', 3600);
				}
			} else {
				$pingdom = new Pingdom();
				$pingdom->getDowntimeGraph(ABSPATH . '/wp-content/cache/pingdom-graph.png', $graph_width, $graph_height, $graph_relative);
			}
			
			echo '<p>'.$text.'</p>';
			echo '<p><img src="' . get_bloginfo('url') . '/wp-content/cache/pingdom-graph.png" alt="Pingdom Uptime Graph" /></p>';
		}
		
		echo $after_widget;
	}

	function widget_pingdom_control() {
		$options = get_option('widget_pingdom');

		/*
		 * DEFAULTS
		 */
		
		if(!is_array($options)){
			$options = array(
				'title'=>'Pingdom Uptime',
				'apikey'=>'',
				'username'=>'',
				'password'=>'',
				'check' => '',
				'formatstring' => 'Your uptime is <strong>{UPTIME}%</strong>.  In the last {DAYS} {DAYS UNIT} there has been {AMOUNT} {AMOUNT POSTFIX} downtime.',
				'interval' => 2591999,
				'graph_on' => 1,
				'graph_width' => 200,
				'graph_height' => 20,
				'graph_relative' => 0
			);
		}

		/*
		 * POST HANDLER
		 */
		
		if($_POST['pingdom-submit']){
			$options['title'] = strip_tags(stripslashes($_POST['pingdom-title']));
			$options['apikey'] = strip_tags(stripslashes($_POST['pingdom-apikey']));
			$options['username'] = strip_tags(stripslashes($_POST['pingdom-username']));
			$options['password'] = strip_tags(stripslashes($_POST['pingdom-password']));
			$options['check'] = strip_tags(stripslashes($_POST['pingdom-check']));
			$options['formatstring'] = strip_tags(stripslashes($_POST['pingdom-format-string']));
			$options['interval'] = strip_tags(stripslashes($_POST['pingdom-interval']));
			$options['graph_on'] = strip_tags(stripslashes($_POST['pingdom-graph-on']));
			$options['graph_width'] = strip_tags(stripslashes($_POST['pingdom-graph-width']));
			$options['graph_height'] = strip_tags(stripslashes($_POST['pingdom-graph-height']));
			$options['graph_relative'] = strip_tags(stripslashes($_POST['pingdom-graph-relative']));
			update_option('widget_pingdom', $options);
			
			if(ENABLE_CACHE) {
				wp_cache_delete('pingdom-text');
				wp_cache_delete('pingdom-graph');
				wp_cache_delete('pingdom-checks');
				wp_cache_delete('pingdom-downtimes');
				wp_cache_delete('pingdom-last-states');
				wp_cache_delete('pingdom-last-downtimes');
			}
		}

		/*
		 * FIELDS
		 */
		
		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		$apikey = htmlspecialchars($options['apikey'], ENT_QUOTES);
		$username = htmlspecialchars($options['username'], ENT_QUOTES);
		$password = htmlspecialchars($options['password'], ENT_QUOTES);
		$check = htmlspecialchars($options['check'], ENT_QUOTES);
		$formatstring = htmlspecialchars($options['formatstring'], ENT_QUOTES);
		$interval = htmlspecialchars($options['interval'], ENT_QUOTES);
		$graph_on = htmlspecialchars($options['graph_on'], ENT_QUOTES);
		$graph_width = htmlspecialchars($options['graph_width'], ENT_QUOTES);
		$graph_height = htmlspecialchars($options['graph_height'], ENT_QUOTES);
		$graph_relative = htmlspecialchars($options['graph_relative'], ENT_QUOTES);
		
		echo '<p style="text-align:right;"><label for="pingdom-title">' . __('Title:') . ' <input style="width: 200px;" id="pingdom-title" name="pingdom-title" type="text" value="'.$title.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="pingdom-apikey">' . __('API-Key:') . ' <input style="width: 200px;" id="pingdom-apikey" name="pingdom-apikey" type="text" value="'.$apikey.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="pingdom-username">' . __('Username:') . ' <input style="width: 200px;" id="pingdom-username" name="pingdom-username" type="text" value="'.$username.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="pingdom-password">' . __('Password:') . ' <input style="width: 200px;" id="pingdom-password" name="pingdom-password" type="text" value="'.$password.'" /></label></p>';
		echo '<hr/>';
		
		/*
		 * CHECKS + FORMATS
		 */
		
		echo '<p style="text-align:right;"><label for="pingdom-check">' . __('Check:') . ' ';
		echo '<select style="width: 200px;" id="pingdom-check" name="pingdom-check">';

		$pingdom = new Pingdom();
		
		foreach(@$pingdom->getChecks() as $ch){
			echo '<option value="'.$ch.'"'.($ch == $check?' selected':'').'>'.$ch.'</option>';
		}
	
		echo '</select>';
		echo '</label></p>';
		
		echo '<p style="text-align:right;"><label for="pingdom-interval">' . __('Interval:') . ' ';
		echo '<select style="width: 200px;" id="pingdom-interval" name="pingdom-interval">';
		echo '<option value="86399"'.($interval == 86399 ?' selected':'').'>1 Day</option>';
		echo '<option value="604799"'.($interval == 604799 ?' selected':'').'>1 Week</option>';
		echo '<option value="2591999"'.($interval == 2591999 ?' selected':'').'>30 Days (Default)</option>';
		echo '<option value="3887999"'.($interval == 3887999 ?' selected':'').'>45 Days</option>';
		echo '</select>';
		echo '</label></p>';
		
		echo '<p style="text-align:right;"><label for="pingdom-format-string">' . __('Format String:') . ' <input style="width: 200px;" id="pingdom-format-string" name="pingdom-format-string" type="text" value="'.$formatstring.'" /></label></p>';
		echo '<p><small>You can use the meta-symbols <strong>{UPTIME}</strong>, <strong>{DAYS}</strong>, <strong>{DAYS UNIT}</strong>, <strong>{AMOUNT POSTFIX}</strong> and <strong>{AMOUNT}</strong> in your format string to display
				the uptime percentage, uptime unit, coverage length, downtime preposition, and downtime amount.</small></p>';
		
		echo '<hr/>';
		
		/*
		 * GRAPHING
		 */
		
		echo '<p style="text-align:right;"><label for="pingdom-graph-on">' . __('Graph:') . ' ';
		echo '<select style="width: 200px;" id="pingdom-graph-on" name="pingdom-graph-on">';
		echo '<option value="1"'.($graph_on == 1?' selected':'').'>Enabled</option>';
		echo '<option value="0"'.($graph_on == 0?' selected':'').'>Disabled</option>';
		echo '</select>';
		echo '</label></p>';
		
		echo '<p style="text-align:right;"><label for="pingdom-graph-width">' . __('Width (px):') . ' <input style="width: 200px;" id="pingdom-graph-width" name="pingdom-graph-width" type="text" value="'.$graph_width.'" /></label></p>';
		echo '<p style="text-align:right;"><label for="pingdom-graph-height">' . __('Height (px):') . ' <input style="width: 200px;" id="pingdom-graph-height" name="pingdom-graph-height" type="text" value="'.$graph_height.'" /></label></p>';
		
		echo '<p style="text-align:right;"><label for="pingdom-graph-relative">' . __('Relative:') . ' ';
		echo '<select style="width: 200px;" id="pingdom-graph-relative" name="pingdom-graph-relative">';
		echo '<option value="1"'.($graph_relative == 1 ?' selected':'').'>Enabled</option>';
		echo '<option value="0"'.($graph_relative == 0 ?' selected':'').'>Disabled</option>';
		echo '</select>';
		echo '</label></p>';
		
		echo '<p><small>You can decide if you would like an <strong>uptime graph</strong> shown on your site, at whatever size you prefer.  The <strong>relative</strong>
		setting will resize the y-axix window to show the downtime, not the day\'s uptime.</small></p>';
		
		/*
		 * ERRORS
		 */
		
		if(isset($pingdom)){
			echo '<p style="color:#FF0000;">' . $pingdom->getError() . '</p>';
		}
		
		/*
		 * END FORM
		 */
		
		echo '<input type="hidden" id="pingdom-submit" name="pingdom-submit" value="1" />';
		echo '<input style="float:right;" type="submit" id="pingdom-submit-override" name="pingdom-submit-override" value="Save Pingdom Settings"/>';
	}
	
	register_sidebar_widget(array('Pingdom', 'widgets'), 'widget_pingdom');
	register_widget_control(array('Pingdom', 'widgets'), 'widget_pingdom_control', 300, 650);
}

add_action('widgets_init', 'widget_pingdom_init');
?>