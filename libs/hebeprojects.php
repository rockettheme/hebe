<?php

Class HebeProjects {

	private static $config;
	private static $projects_path = '';
	private static $projects_file = '';
	public $data = array();

	public function __construct($config){
		$this->projects_path = exec('echo $HOME').'/.hebe';
		$this->projects_file = $this->projects_path.'/projects';

		$this->config = $config;

		$this->create_projects_file();
		$this->load_projects_file();
	}

	public function load_manifest($manifest, $platform = false){
		$manifest = $this->_clean_path($manifest, 'right');
		if (is_dir($manifest)) $manifest .= DS . $this->config->get('manifest');

		if (!preg_match("/".$this->config->get('manifest')."$/", $manifest))
			throw new Exception("Error: The passed in manifest ($manifest) appears to be invalid. Expected manifest is `".$this->config->get('manifest')."`.");

		$data = file_get_contents($manifest);
		$data = (strlen($data)) ? json_decode($data, true) : array();

		if ($data === null) throw new Exception("Failed to decode the manifest file `".$manifest."`" . json_error());

		if ($platform != false){
			$key = array_key_exists_nc($platform, $data['platforms']);
			if ($key) $data = $data['platforms'][$key];
		}

		return $data;
	}

	private function create_projects_file(){
		if (!is_dir($this->projects_path) && !@mkdir($this->projects_path)){
			Hebe::error("Failed to create folder `".$this->projects_path."`");
		}

		if (!file_exists($this->projects_file) && !@fopen($this->projects_file, "a+")){
			Hebe::error("Failed to create the projects file " . $this->projects_file);
		}

		@fclose($this->projects_file);
	}

	public function load_projects_file(){
		$data = file_get_contents($this->projects_file);
		$data = (strlen($data)) ? json_decode($data, true) : array();

		if ($data === null) Hebe::error("Failed to decode the projects file `".$this->projects_file."`" . json_error());
		else $this->data = $data;

		if (!count($this->data)){
			$this->data = array();
			$this->save_projects_file();
		}
	}

	public function save_projects_file(){
		$data = stripslashes(json_beautify(json_encode($this->data)));

		if (!@file_put_contents($this->projects_file, $data)){
			Hebe::error("Unable to save the projects changes into `".$this->projects_file."`");
		}
	}

	public function register($options = array("arguments" => array(), "force" => false)){
		$locations = $options['arguments'];
		$force = $options['force'];

		foreach($locations as $location){
			$location = $this->_fix_path($location);
			$manifest = $this->load_manifest($location);
			if ($manifest != null){
				$project = $manifest['project'];
				$requires = $manifest['requires'];

				$platforms = array_keys($manifest['platforms']);

				$added = array();
				$skipped = array();
				$newproject = false;

				if (!isset($this->data[$project])){
					$this->data[$project] = array();
					$newproject = true;
				}

				foreach($platforms as $platform){
					if (!isset($this->data[$project][$platform]) || $force){
						$this->data[$project][$platform]= rtrim(rtrim($location, $this->config->get('manifest')), '/');
						$added[] = ucfirst($platform);
					} else {
						$skipped[] = ucfirst($platform);
					}
				}

				$message = "";
				if (count($added)) $message .= "\n   └ added: " . implode($added, ", ");
				if (count($skipped)) $message .= "\n   └ skipped: " . implode($skipped, ", ");
				if (!count($added) && !count($skipped)) $message .= "\n   └ no platforms";

				$status = ($newproject ? "created." : (count($added) ? "updated." : "skipped."));

				Hebe::message("\nProject `" . $project . "` " . $status . $message ."\n");

				if (isset($requires)){
					$requirements = array();
					foreach($requires as $require_project => $require_data){
						if (!isset($this->data[$require_project])){
							array_push($requirements, $require_project);
						}
					}

					if (count($requirements)){
						Hebe::message("Warning: Project `".$project."` requires the projects `".implode(", ", $requirements)."` to be registered.");
						Hebe::message("         Please run ./hebe register <projects working path>\n");
					}
				}
			}
		}

		$this->save_projects_file();
	}

	public function unregister($options = array("arguments" => array())){
		$projects = $options['arguments'];

		foreach($projects as $project){

			$key = array_key_exists_nc($project, $this->data);

			if (is_string($key)) unset($this->data[$key]);

			$status = (!$key) ? "not found." : "removed.";

			Hebe::message("\nProject `" . (!$key ? $project : $key) . "` " . $status ."\n");
		}

		$this->save_projects_file();
	}

	public function list_project($options = array("arguments" => array(), "filter" => false)){
		$filters = $options['filter'];
		$arguments = $options['arguments'];

		$projects = "";
		$message = "\n";

		if (!count($filters) && !count($arguments)) $projects = $this->data;
		if (count($arguments)){
			$projects = array();
			foreach($arguments as $argument){
				if (isset($this->data[$argument])) $projects[$argument] = $this->data[$argument];
			}
		}
		if (count($filters)){
			$projects = array();
			foreach($filters as $filter){
				foreach($this->data as $project_name => $project_data){
					if (preg_match("/".$filter."/i", $project_name)) $projects[$project_name] = $project_data;
				}
			}
		}

		$count = count($projects);

		$message .= "Found " . $count . " projects.";
		if (count($filters)) $message .= "\n[filter: " . implode(' ', $filters) . ']';

		if ($count > 0){
			foreach ($projects as $name => $project){
				$message .= "\n\n". $name . ":";
				foreach($project as $platform => $working_path){
					$message .= "\n   └ " . ucfirst($platform) . ':  ' . str_replace(exec('echo $HOME'), "~", $working_path);
				}
			}
		}

		Hebe::message($message . "\n");

	}

	public function link_project($options = array("projects" => array(), "destinations" => array(), "platform" => array(), "force" => false)){
		$time = time();

		$destinations = $options['destinations'];
		$projects = $options['projects'];
		$platform_option = (strlen($options['platform'])) ? $options['platform']: false;
		$force = $options['force'];

		if (!count($projects)) Hebe::error("There is no project set to be linked. Please read the documentation (./command help link).");
		if (!count($destinations)) Hebe::error("No destination path has been found. Please read the documentation (./command help link).");

		$errors = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));
		$success = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));

		foreach($destinations as $dest){
			$dest = $this->_clean_path($this->_fix_path($dest), 'right');

			if (!file_exists($dest)){
				$errors['destinations'][] = $dest;
			} else {
				$success['destinations'][] = $dest;

				Hebe::message("\nLinking projects into `".$dest."`");
				foreach($projects as $project){
					$name = $project;
					$project = array_key_exists_nc($name, $this->data);

					if (!$project) {
						$errors['projects']['name'][$name] = $name;
						Hebe::message("\n   └ " . $name . ": Project not found");
					}
					else {
						$name = $project;
						$project = $this->data[$name];
						$success['projects']['name'][$name] = $name;

						$expected_platform = HebePlatform::getInfo($this->_clean_path($dest, 'right'));
						$platform = (!$platform_option) ? $expected_platform : $platform_option;

						$platform = array_key_exists_nc($platform, $project);
						if (!$platform) $platform = array_key_exists_nc("custom", $project);

						if (!$platform){
							$platform = (!$platform_option) ? $expected_platform : $platform_option;
							Hebe::message("   └ " . $name . ": No platform `".$platform."` found in the manifest.");
						} else {
							$working_path = $project[$platform];
							$manifest = $this->load_manifest($working_path);
							$requires = $manifest['requires'];

							$manifest = $this->load_manifest($working_path, $platform);
							$nodes = $manifest['nodes'];

							Hebe::message("   └ " . $name . " [".$platform."]");

							if (!$nodes) {
								Hebe::message("      └ No nodes found.\n");
								continue;
							}


							$requirements = array();
							if (isset($requires)){
								foreach($requires as $require_project => $require_data){
									if (!isset($this->data[$require_project])){
										array_push($requirements, $require_project);
									}
								}
							}

							if (count($requirements) && !$force){
								Hebe::message("      └ skipped: unregistered requirements: " . implode(", ", $requirements));
							} else {
								foreach($nodes as $node => $paths){
									$failed_nodes = array("count" => 0, "msg" => array());
									foreach($paths as $path){
										$source = $this->_clean_path($working_path, 'right') . DS . $this->_clean_path($path['source']);
										$destination = $this->_clean_path($dest, 'right') . DS . $this->_clean_path($path['destination']);

										if ($this->config->get('backup_existing_when_linking')){
											if (file_exists($destination)){
												exec('rm -rf '.$destination.'.backup');
												exec('mv '.$destination.' '.$destination.'.backup');
											}
										}

										if (!file_exists($destination)) exec('mkdir -p '.$destination);
										exec('rm -rf '.$destination);

										if (!file_exists($source)) {
											$failed_nodes["msg"][] = "            └ node failed: source not found -> " . $path['source'];
											$failed_nodes["count"]++;
										} else {
											if (symlink($source, $destination)){
												$success['projects']['paths'][$destination] = $destination;
											} else {
												$errors['projects']['paths'][$destination] = $destination;
												$failed_nodes["msg"][] = "            └ node failed: link error -> " . $path['source'];
												$failed_nodes["count"]++;
											}
										}
									}

									Hebe::message("      └ " . $node . ": " . ($failed_nodes["count"] == count($paths) ? "failed" : "ok"));
									if (count($failed_nodes["msg"])) Hebe::message(implode("\n", $failed_nodes["msg"]));
								}
							}

							Hebe::message("");
						}
					}
				}
			}
		}

		Hebe::message("");
	}

	public function edit_project($options = array("arguments" => array(), "platforms" => array(), "force" => false)){
		$projects = $options['arguments'];
		$platforms = $options['platforms'];

		$list = array();

		foreach ($projects as $project) {
			$project_name = array_key_exists_nc($project, $this->data);
			if (!$project_name) Hebe::message("Project `".$project."` not found.");
			else {
				if (!count($platforms)) foreach($this->data[$project_name] as $working_path) $list[] = $working_path;
				else {
					foreach($platforms as $platform){
						$platform_name = array_key_exists_nc($platform, $this->data[$project_name]);

						if (!$platform_name) Hebe::message("No platform `".$platform."` found in the manifest of project `".$project."`.");
						else {
							$list[] = $this->data[$project_name][$platform];
						}
					}
				}
			}
		}

		if (count($list)) exec($this->config->get('editor') . ' ' . implode(" ", $list));
	}

	private function _clean_path($manifest, $position = 'both'){
		switch($position){
			case 'left':
				$manifest = ltrim($manifest, "/");
				break;
			case 'right':
				$manifest = rtrim($manifest, "/");
				break;
			case 'both': default:
				$manifest = rtrim(ltrim($manifest, "/"), "/");
				break;
		}

		return $manifest;
	}

	private function _fix_path($path){
		$first_chr = substr($path, 0, 1);
		if ($path == '.') return exec('echo `pwd`');
		if ($first_chr != '~' && $first_chr == '/') return $path;
		if ($first_chr != '/' && $first_chr != '~') return $this->_clean_path(exec('echo `pwd`'), 'right') . DS . $path;
		if ($first_chr == '~') return  preg_replace("/^~/", exec('echo $HOME'), $path);
	}

}
