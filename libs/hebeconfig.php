<?php

Class HebeConfig {

	private static $config_path = '';
	private static $config_file = '';
	public $data;

	public function __construct(){
		$this->config_path = exec('echo $HOME').'/.hebe';
		$this->config_file = $this->config_path.'/config';

		$this->data = new stdClass();

		$this->create_config();
		$this->load_config();
	}

	private function create_config(){
		if (!is_dir($this->config_path) && !@mkdir($this->config_path)){
			Hebe::error("Failed to create folder `".$this->config_path."`");
		}

		if (!file_exists($this->config_file) && !@copy(PATH . '/resources/config', $this->config_file)){
			Hebe::error("Failed to copy default config file from `" .
				PATH . "/resources/config` " . "to " . $this->config_file);
		}
	}

	public function load_config(){
		$data = json_decode(file_get_contents($this->config_file));

		if (!$data) Hebe::error("Failed to decode the config file `".$this->config_file."`" . json_error());
		else $this->data = $data;
	}

	public function save_config(){
		$data = json_beautify(json_encode($this->data));

		if (!@file_put_contents($this->config_file, $data)){
			Hebe::error("Unable to save the configuration changes into `".$this->config_file."`");
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
