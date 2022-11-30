<?php
/*
 * @copyright	Copyright (C) 2014 Markus Neubauer. All rights reserved.
 * @license	http://www.gnu.org/licenses/agpl-3.0.html GNU/AGPL
 * @github	https://github.com/marneu/joomlatic/tree/master/syslogauthlog
 * @homepage	http://www.std-soft.com/index.php/hm-service/81-c-std-service-code/9-joomla-plugin-syslogauthlog
 * @version	1.6
 */

defined('_JEXEC') or die();

jimport('joomla.plugin');

class PlgSystemSyslogAuthLog extends JPlugin {

	/**
	* Load the language file on instantiation.
	*
	* @var    boolean
	* @since  3.1
	*/
    protected $autoloadLanguage = true;
	
	private $format = '{PRIORITY} {EVENT} {USERNAME} {ADMIN} {MESSAGE} from {CLIENTIP}';

	private $syslog_options = array(
		'sys_ident' 	 => 'jauthlog',
		'sys_add_pid' 	 => true,
		'sys_use_stderr' => false,
		'sys_facility' 	 => LOG_AUTH
		);
	private $syslog;

	// ranges know as private or virtual IP's (unused in public internet, see ieee.org)
	private $ignore_ip = array (
		array('0.0.0.0','2.255.255.255'),
		array('10.0.0.0','10.255.255.255'),
		array('127.0.0.0','127.255.255.255'),
		array('169.254.0.0','169.254.255.255'),
		array('172.16.0.0','172.31.255.255'),
		array('192.0.0.0','192.0.0.255'),
		array('192.0.2.0','192.0.2.255'),
		array('192.88.99.0','192.88.99.255'),
		array('192.168.0.0','192.168.255.255'),
		array('198.18.0.0','198.19.255.255'),
		array('198.51.100.0','198.51.100.255'),
		array('203.0.113.0','203.0.113.255'),
		array('224.0.0.0','255.255.255.255')
		);
		
	public $priorities = array(
                JLog::EMERGENCY => 'EMERG',
                JLog::ALERT => 'ALERT',
                JLog::CRITICAL => 'CRIT',
                JLog::ERROR => 'ERR',
                JLog::WARNING => 'WARNING',
                JLog::NOTICE => 'NOTICE',
                JLog::INFO => 'INFO',
                JLog::DEBUG => 'DEBUG');

	private $priority = JLog::INFO;

	private $field = array( 'MESSAGE' => '' );
	
	private $version = JVERSION;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		
		$input = JFactory::getApplication()->input;
		$hash  = JApplicationHelper::getHash('PlgSystemSyslogAuthLog');
	}

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
		if (isset($_SERVER["HTTP_CLIENT_IP"]) && $this->check_ip($_SERVER["HTTP_CLIENT_IP"])) {
			return $_SERVER["HTTP_CLIENT_IP"];
		}
		if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ) {
			foreach (explode(",",$_SERVER["HTTP_X_FORWARDED_FOR"]) as $ip) {
				if ($this->check_ip(trim($ip))) {
					return $ip;
					}
			}
		}
		if ( isset($_SERVER["HTTP_X_FORWARDED"]) && $this->check_ip($_SERVER["HTTP_X_FORWARDED"])) {
			return $_SERVER["HTTP_X_FORWARDED"];
		} elseif ( isset($_SERVER["HTTP_FORWARDED_FOR"]) && $this->check_ip($_SERVER["HTTP_FORWARDED_FOR"])) {
			return $_SERVER["HTTP_FORWARDED_FOR"];
		} elseif ( isset($_SERVER["HTTP_FORWARDED"]) && $this->check_ip($_SERVER["HTTP_FORWARDED"])) {
			return $_SERVER["HTTP_FORWARDED"];
		} else {
			return $_SERVER["REMOTE_ADDR"];
		}
	}


	private function log() {

		if (JFactory::getApplication()->isClient('administrator')) {  // Works from Joomla 3.7 to 4.x
			$this->field['ADMIN'] = 'ADMIN';
		}
		else {
			if ( $this->params->def('type', 1) == 1 ) return;
			$this->field['ADMIN'] = '';
		}

		$this->field['CLIENTIP'] = $this->getAddr();


		$this->field['PRIORITY'] = $this->priorities[$this->priority];
		
		// Fill in field data for the line.
		$message = $this->format;

		foreach ($this->field as $tmp => $val)
		{
			$message = str_replace('{' . $tmp . '}', $val, $message);
		}

		// setup the new entry for syslog.
		$jLogEntry = new JLogEntry($message, $this->priority, getenv('USER'));

		// connect to syslog
		if ( substr( $this->version, 0, 1) == '3' || substr( $this->version, 0, 1) == '4' ) {
			$this->syslog = new JLogLoggerSyslog($this->syslog_options);
		} else {
			die ("Unsupported Joomla version for plg syslogauth.");
		}
		// Write the new entry to syslog.
		$this->syslog->addEntry($jLogEntry);

	}

	public function onUserAfterLogin($options)
	{ // JAuthenticationResponse array

		if ( $this->params->def('mode', 1) != 0 ) return;
		if ( $this->params->def('event', 1) == 2 ) return;
		$this->field['EVENT'] = 'login';
		$this->field['USERNAME'] = $options['user']->username;
		$this->log();
	}

	public function onUserLoginFailure($response) 
	{

		// do not log canceled
		if ( $response['status'] == JAuthentication::STATUS_SUCCESS ) return;
		// log only if configured to do so
		if ( $this->params->def('event', 1) == 2 ) return;
		
		$this->field['MESSAGE'] = $response['error_message'];
		$this->priority = JLog::WARNING;
		$this->field['EVENT'] = 'login';
		if ($this->params->get('log_username', 0)) {
			$this->field['USERNAME'] = $response['username'];
		} else {
			$this->field['USERNAME'] = 'UNKNOWN';
		}
		$this->log();
	}

	public function onUserAfterLogout($options)
	{

		if ( $this->params->def('mode', 1) != 0 ) return;
		if ( $this->params->def('event', 1) == 1 ) return;
		$this->field['EVENT'] = 'logout';
		$this->field['USERNAME'] = $options['username'];
		$this->log();
	}

	public function onUserLogoutFailure($parameters)
	{

		if ( $this->params->def('event', 1) == 1 ) return;
		$this->priority = JLog::WARNING;
		$this->field['EVENT'] = 'logout';
		$this->field['USERNAME'] = $parameters['username'];
		$this->field['MESSAGE'] = 'failure';
		$this->log();
	}

}
?>
