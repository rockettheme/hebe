<?php

Class HebeConfig {

	private static $config_path = '';
	private static $config_file = '';
	public $data;

	public function __construct(){
		if (!Hebe::requirements()) return false;

		self::$config_path = exec('echo $HOME').'/.hebe';
		self::$config_file = self::$config_path.'/config';

		$this->data = new stdClass();

		$this->create_config();
		$this->load_config();
	}

	private function create_config(){
		if (!is_dir(self::$config_path) && !@mkdir(self::$config_path)){
			Hebe::error("Failed to create folder `".self::$config_path."`");
		}

		if (!file_exists(self::$config_file) && !@copy(PATH . '/resources/config', self::$config_file)){
			Hebe::error("Failed to copy default config file from `" .
				PATH . "/resources/config` " . "to " . self::$config_file);
		}
	}

	public function load_config(){
		$data = json_decode(file_get_contents(self::$config_file));

		if (!$data) Hebe::error("Failed to decode the config file `".self::$config_file."`" . json_error());
		else $this->data = $data;
	}

	public function save_config(){
		$data = json_beautify(json_encode($this->data));

		if (!@file_put_contents(self::$config_file, $data)){
			Hebe::error("Unable to save the configuration changes into `".self::$config_file."`");
		}
	}

	public function get($option){
		if (!isset($this->data->$option)) {
			Hebe::error("Unable to find the option `".$option."`");
			return null;
		} else {
			return $this->data->$option;
		}
	}

	public function set($option, $value){
		if (!isset($value)) return Hebe::error("A value is required for the option `".$option."`");

		if (!isset($this->data->$option)){
			return Hebe::error("Unable to find the option `".$option."`");
		}

		$this->data->$option = $value;
		$this->save_config();
	}

}
