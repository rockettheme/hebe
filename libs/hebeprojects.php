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
			$this->data = array('groups' => array(), 'projects' => array());
			$this->save_projects_file();
		}
	}

	public function save_projects_file(){
		$data = stripslashes(json_beautify(json_encode($this->data)));

		if (!@file_put_contents($this->projects_file, $data)){
			Hebe::error("Unable to save the configuration changes into `".$this->projects_file."`");
		}
	}

	public function add_group($group, $options = array("settings" => array(), "force" => false)){
		$clean_name = strtolower(preg_replace("/(\s|\-)/", '_', $group));
		if (!$options["force"] && array_has_r($this->data['groups'], $clean_name)) Hebe::error("A group entry named `".$group."` appears to be already existing.");
		else {
			$this->data['groups'][$group] = array();
		}

		$this->save_projects_file();
	}

	public function edit_project($project, $options = array("nodes" => array())){
		$clean_name = strtolower(preg_replace("/(\s|\-)/", '_', $project));

		if (!array_has($this->data['projects'], $clean_name)) Hebe::error("The project named `".$clean_name."` does not appear to exist.");

		if (isset($options['add_nodes']) && count($options['add_nodes'])){
			
			$success = array();
			$error = array();
			foreach($options['add_nodes'] as $node){
				// grab name, source, destination of the node and add to the project nodes list
				for($i = 0; $i < count($node); $i++){
					list($name, $source, $destination) = $node;
				}
				
				$source = $this->_fix_path($source);
				$notice = array("name" => $name, "source" => $source);
				if (!file_exists($source)) $error[] = $notice;
				else {
					$this->data['projects'][$clean_name]['nodes'][$name] = array(
						"name" => $name,
						"source" => $source,
						"destination" => $destination
					);

					$success[] = $notice;
				}
			}

			$colors = new Colors();

			$list_green = $list_red = "\n";
			foreach($success as $i => $msg){
				$sep = ($i == count($success) - 1) ? '└' : '├';
				$list_green .= $colors->getColoredString("   " . $sep . " " .  $msg['name'] . ' - ' . $msg['source'], "white") . "\n";
			}

			foreach($error as $i => $msg){
				$sep = ($i == count($success) - 1) ? '└' : '├';
				$list_red .= $colors->getColoredString("   " . $sep . " " . $msg['name'] . ' - ' . $msg['source'], "white") . "\n";
			}

			Hebe::message(
				$colors->getColoredString("\nProject: ".$clean_name, "white") . "\n\n" . 
				$colors->getColoredString(count($success) . " nodes successfully added/updated. ", "light_green") .
				$list_green .

				$colors->getColoredString(count($error) . " nodes could not be added because the source path was not found. ", "light_red") .
				$list_red
			);
		}

		if (isset($options['remove_nodes']) && count($options['remove_nodes'])){
			if (!array_has($this->data['projects'], $clean_name)) Hebe::error("No project named `".$clean_name."` has been found.");
			if (!isset($this->data['projects'][$clean_name]['nodes'])) Hebe::error("The project `".$clean_name."` does not have any node.");

			$success = array();
			$error = array();
			foreach($options['remove_nodes'] as $node_name){
				
				$node = $this->data['projects'][$clean_name]['nodes'][$node_name];
				if (isset($node)){
					unset($this->data['projects'][$clean_name]['nodes'][$node_name]);
					$success[] = $node_name;	
				} else {
					$error[] = $node_name;
				}
			}

			$colors = new Colors();
			Hebe::message(
				$colors->getColoredString("\nProject: ".$clean_name, "white") . "\n\n" . 
				$colors->getColoredString(count($success) . " nodes successfully removed. ", "light_green") .
				$colors->getColoredString(implode(", ", $success), "white") . "\n" .

				$colors->getColoredString(count($error) . " nodes could not be found. ", "light_red") .
				$colors->getColoredString(implode(", ", $error), "white")
			);
		}

		$this->save_projects_file();

	}

	public function rename_project($project, $new_name, $options = array('force' => false)){
		$project = strtolower(preg_replace("/(\s|\-)/", '_', $project));
		$new_name = strtolower(preg_replace("/(\s|\-)/", '_', $new_name));

		$force = $options['force'];

		if (!array_has($this->data['projects'], $project))
			Hebe::error("No project named `".$project."` has been found.");
		
		if (!$force && array_has($this->data['projects'], $new_name))
			Hebe::error("A project named `".$new_name."` already exists. Use the +force flag if you want to overwrite it.");

		$this->data['projects'][$new_name] = $this->data['projects'][$project];
		unset($this->data['projects'][$project]);

		$colors = new Colors();
		Hebe::message($colors->getColoredString("The project `". $project . "` has been successfully renamed to `". $new_name . "`.", "light_green"));

		$this->save_projects_file();

	}

	public function add_project($project, $options = array("add_nodes" => array(), "remove_nodes" => array())){
		$clean_name = strtolower(preg_replace("/(\s|\-)/", '_', $project));

		if (isset($options['add_nodes']) && count($options['add_nodes'])){
			// if the project doesn't exist, let's create it
			if (!array_has($this->data['projects'], $clean_name)) $this->data['projects'][$clean_name] = array();
			// let's add the nodes sub-array for the project
			if (!isset($this->data['projects'][$clean_name]['nodes'])) $this->data['projects'][$clean_name]['nodes'] = array();
		}

		$this->edit_project($project, $options);
	}

	public function remove_project($project){
		$project = strtolower(preg_replace("/(\s|\-)/", '_', $project));

		if (!array_has($this->data['projects'], $project))
			Hebe::error("No project named `".$project."` has been found.");

		unset($this->data['projects'][$project]);

		$colors = new Colors();
		Hebe::message($colors->getColoredString("The project `". $project . "` has been successfully removed.", "light_green"));

		$this->save_projects_file();

	}

	function list_project($projects){
		$filters = $projects['filter'];
		$arguments = $projects['arguments'];

		$no_details = $projects['no_details'];
		$no_nodes = $projects['no_nodes'];
		$no_sources = $projects['no_sources'];
		$no_destinations = $projects['no_destinations'];

		$projects = "";
		$message = "\n";
		$colors = new Colors();

		if (!count($filters) && !count($arguments)) $projects = $this->data['projects'];
		if (count($arguments)){
			$projects = array();
			foreach($arguments as $arg){
				if (isset($this->data['projects'][$arg])) $projects[$arg] = $this->data['projects'][$arg];
			}	
		}
		if (count($filters)){
			$projects = array();
			foreach($filters as $filter){
				foreach($this->data['projects'] as $project_name => $project_data){
					if (preg_match("/".$filter."/", $project_name)) $projects[$project_name] = $project_data;
				}
			}
		}

		$count = count($projects);

		$message .= "Found " . $colors->getColoredString($count, "white") . " projects.\n";

		if ($count > 0){
			$message .= "\n";
			foreach ($projects as $name => $project){
				$message .= "\n".$colors->getColoredString($name, "light_blue") . " (".$colors->getColoredString(count($project['nodes']), "white")." nodes):\n";

				if (!$no_nodes){
					if (!count($project['nodes'])){
						$message .= "   └ Empty project. There are no nodes. \n";
					} else {
						$i = 0;
						foreach($project['nodes'] as $node_name => $node){
							$sep = ($i == count($project['nodes']) - 1) ? '└' : '├';
							$sub_sep = ($i == count($project['nodes']) - 1) ? ' ' : '│';

							$message .= "   " . $sep . " " . $colors->getColoredString($node_name, "white") . "\n";

							if (!$no_details){
							
								if (!$no_sources)
									$message .= "   ".$sub_sep."   ├ source path: " . $colors->getColoredString($node['source'], file_exists($node['source']) ? 'light_green' : 'light_red') . "\n";
								if (!$no_destinations)
									$message .= "   ".$sub_sep."   └ destination: " . $colors->getColoredString($node['destination'], "white") . "\n";
							
							}
							
							$i++;
						}
					}
				}
			}
		}

		Hebe::message($message);

	}

	function link_project($options = array("arguments" => array(), "projects" => array())){
		$time = time();
		$destinations = $options['arguments'];
		$force = $options['force'];
		$projects = $options['projects'];

		if (!count($projects)) Hebe::error("There is no project set to be linked. Please read the documentation (./command help project.link).");
		if (!count($destinations)) Hebe::error("No destination path has been found. Please read the documentation (./command help project.link).");

		$errors = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));
		$success = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));

		foreach($destinations as $dest){
			$dest = rtrim($this->_fix_path($dest), "/");

			if (!file_exists($dest)){
				$errors['destinations'][] = $dest;
			} else {
				$success['destinations'][] = $dest;
				foreach($projects as $project){
					$name = $project;
					$project = $this->data['projects'][$project];
					if (!$project) $errors['projects']['name'][$name] = $name;
					else {
						$success['projects']['name'][$name] = $name;
						foreach($project['nodes'] as $node_name => $node){
							$source_path = $node['source'];
							$destination = $dest . '/'. rtrim(ltrim($node['destination'], "/"), "/");

							if (file_exists($destination) || ($force && !file_exists($destination))){
								if ($this->config->get('backup_existing_when_linking')){
									if (file_exists($destination)){
										exec('rm -rf '.$destination.'.backup');
										exec('mv '.$destination.' '.$destination.'.backup');
									}
								}
								exec('rm -rf '.$destination);
								if (symlink($source_path, $destination)){
									$success['projects']['paths'][$destination] = $destination;
								} else {
									$errors['projects']['paths'][$destination] = $destination;

								}
							}
						}
					}
				}
			}
		}

		$message = "\n";

		$colors = new Colors();
		$message .= $colors->getColoredString(count($success['projects']['name']). " projects linked with success", "light_green") ."\n";
		
		if (count($success['destinations']))
			$message .= " - Destinations: \n    - ". implode("\n    - ", $success['destinations']);
		if (count($success['projects']['name']))
			$message .= "\n - Projects: \n    - ". implode("\n    - ", $success['projects']['name']);
		if (count($success['projects']['paths']))
			$message .= "\n - Paths: \n    - ". implode("\n    - ", $success['projects']['paths']);

		$message .= "\n\n";

		$message .= $colors->getColoredString(count($errors['projects']['name']). " projects couldn't be linked", "light_red") ."\n";
		
		if (count($errors['destinations']))
			$message .= " - Destinations: \n    - ". implode("\n    - ", $errors['destinations']);
		if (count($errors['projects']['name']))
			$message .= "\n - Projects: \n    - ". implode("\n    - ", $errors['projects']['name']);
		if (count($errors['projects']['paths'])){
			$message .= "\n - Paths: \n    - ". implode("\n    - ", $errors['projects']['paths']);
			$message .= "\n\n";
		}

		$message .= "\n\n" .
					$colors->getColoredString("It took ", "light_blue") .
					$colors->getColoredString((time() - $time) . "ms", "white") .
					$colors->getColoredString(" to link ", "light_blue") .
					$colors->getColoredString(count($success['projects']['paths']), "white") .
					$colors->getColoredString(" nodes of ", "light_blue") .
					$colors->getColoredString(count($success['projects']['name']), "white") .
					$colors->getColoredString(" projects, with no effort. \nRemember when you where doing this manually? :)", "light_blue").
					"\n\n";

		Hebe::message($message);
	}

	function _fix_path($path){
		if ($path == '.') return exec('echo `pwd`');
		if (substr($path, 0, 1) != '~') return $path;

		$path = preg_replace("/^~/", exec('echo $HOME'), $path);
		return $path;
	}

}