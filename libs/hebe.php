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
		self::$config = new HebeConfig();
		self::$config_data = self::$config->data;

		self::$projects = new HebeProjects(self::$config);
		self::$projects_data = self::$projects->data;
	}

	public static function load($config_path = null){
			if (!$config_path) $path = '';
	}

	public static function requirements(){
		$errors = array();
		if (!function_exists("exec")) $errors[] = "exec() function appears to be disabled but required.";

		if (!count($errors)) return true;
		else Hebe::error(implode("\n", $errors));
	}

}
