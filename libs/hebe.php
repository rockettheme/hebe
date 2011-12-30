<?php

define('DS', DIRECTORY_SEPARATOR);
require dirname(__FILE__) . '/../libs/hebeconfig.php';
require dirname(__FILE__) . '/../libs/hebeplatform.php';
require dirname(__FILE__) . '/../libs/hebeprojects.php';
require dirname(__FILE__) . '/../helpers/json.php';
require dirname(__FILE__) . '/../helpers/array.php';
require dirname(__FILE__) . '/../helpers/colors.php';

Class Hebe {

	public static $config, $config_data, $projects, $projects_data;
	private static $path;

	public static function message($message, $type = 'php://stdout'){
		$std_err = fopen($type, 'w');
		fwrite($std_err, $message."\n");
		fclose($std_err);
	}

	public static function error($message){
		self::message("ERROR: " . $message, 'php://stderr');
		exit(1);
	}

	public function __construct(){
		$this->config = new HebeConfig();
		$this->config_data = $this->config->data;

		$this->projects = new HebeProjects($this->config);
		$this->projects_data = $this->projects->data;
	}

	public function load($config_path = null){
			if (!$config_path) $path = '';
	}

}
