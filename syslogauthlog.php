<?php
/*
 * @copyright  Copyright (C) 2014 Markus Neubauer. All rights reserved.
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU/AGPL
 */

defined('_JEXEC') or die();


class PlgSystemSyslogAuthLog extends JPlugin {
	
	private $format = '{EVENT} {USERNAME} {ADMIN} {MESSAGE} from {CLIENTIP}';
	private $syslog_options = array(
				'sys_ident' => 'jauthlog',
				'sys_add_pid' => true,
				'sys_use_stderr' => false,
				'sys_facility' => LOG_AUTH,
				);
	private $ignore_ip = array (
				array('0.0.0.0','2.255.255.255'),
				array('10.0.0.0','10.255.255.255'),
				array('127.0.0.0','127.255.255.255'),
				array('169.254.0.0','169.254.255.255'),
				array('172.16.0.0','172.31.255.255'),
				array('192.0.2.0','192.0.2.255'),
				array('192.168.0.0','192.168.255.255'),
				array('255.255.255.0','255.255.255.255')
			      );
	private $field = array();
	private $message_option = array();


	private function check_ip($ip)
	{
 		if (!empty($ip) && ip2long($ip) != -1) {
			foreach ($this->ignore_ip as $r) {
				$min = ip2long($r[0]);
				$max = ip2long($r[1]);
				if ((ip2long($ip) >= $min) && (ip2long($ip) <= $max)) return false;
			}
			return true;
		} else {
			return false;
		}
	}


	private function getAddr()
	{
		if ($this->check_ip($_SERVER["HTTP_CLIENT_IP"])) {
			return $_SERVER["HTTP_CLIENT_IP"];
		}
		foreach (explode(",",$_SERVER["HTTP_X_FORWARDED_FOR"]) as $ip) {
			if ($this->check_ip(trim($ip))) {
				return $ip;
			}
		}
		if ($this->check_ip($_SERVER["HTTP_X_FORWARDED"])) {
			return $_SERVER["HTTP_X_FORWARDED"];
		} elseif ($this->check_ip($_SERVER["HTTP_FORWARDED_FOR"])) {
			return $_SERVER["HTTP_FORWARDED_FOR"];
		} elseif ($this->check_ip($_SERVER["HTTP_FORWARDED"])) {
			return $_SERVER["HTTP_FORWARDED"];
		} else {
			return $_SERVER["REMOTE_ADDR"];
		}
	}


	private function log() {

		if (JFactory::getApplication()->isAdmin()) { 
			$this->field['ADMIN'] = 'ADMIN';
		}
		else {
			if ( $this->params->def('type', 1) == 1 ) return;
			$this->field['ADMIN'] = '';
		}

		$this->message_option['category'] = getenv('USER');

		$this->field['CLIENTIP'] = $this->getAddr();		

		// Fill in field data for the line.
		$message = $this->format;
		if (!isset($this->field['MESSAGE'])) $this->field['MESSAGE'] = '';

		foreach ($this->field as $tmp => $val)
		{
			$message = str_replace('{' . $tmp . '}', $val, $message);
		}

		$jLogEntry = new JLogEntry($message, $this->message_option['priority'], $this->message_option['category']);

		// Write the new entry to syslog.
		$syslog = new JLogLoggerSyslog($this->syslog_options);
		$syslog::addEntry($jLogEntry);

	}

	public function onUserAfterLogin($options)
	{ // JAuthenticationResponse array

		if ( $this->params->def('mode', 1) != 0 ) return;
		if ( $this->params->def('event', 1) == 2 ) return;
		$this->message_option['priority'] = JLog::INFO;
		$this->field['EVENT'] = 'login';
		$this->field['USERNAME'] = $options['user']->username;
		$this->log();
	}

	public function onUserLoginFailure($response) 
	{

		if ( $this->params->def('event', 1) == 2 ) return;
		$this->message_option['priority'] = JLog::WARNING;
		$this->field['EVENT'] = 'login';
		$this->field['USERNAME'] = $response['username'];
		$this->field['MESSAGE'] = $response['error_message'];
		$this->log();
	}

	public function onUserAfterLogout($options)
	{

		if ( $this->params->def('mode', 1) != 0 ) return;
		if ( $this->params->def('event', 1) == 1 ) return;
		$this->message_option['priority'] = JLog::INFO;
		$this->field['EVENT'] = 'logout';
		$this->field['USERNAME'] = $options['username'];
		$this->log();
	}

	public function onUserLogoutFailure($parameters)
	{

		if ( $this->params->def('event', 1) == 1 ) return;
		$this->message_option['priority'] = JLog::WARNING;
		$this->field['EVENT'] = 'logout';
		$this->field['USERNAME'] = $parameters['username'];
		$this->field['MESSAGE'] = 'failure';
		$this->log();
	}

}
