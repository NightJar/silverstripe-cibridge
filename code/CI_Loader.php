<?php
class CI_Loader extends Object {
	public function is_loaded($class) {
		$name = $class; //not always but close enough
		return Injector::inst()->hasService("CIB_$name") ? $name : false;
	}
	public function library($lib, $params=null, $name=null) {
		$lib = explode('/', trim($lib,"/ \t\n\r\0\x0B"));
		$class = array_pop($lib);
		if(!$name) $name = $class;
		if(!class_exists($class)) $class = "CI_$class";
		$instName = "CIB_$name";
		$inj = Injector::inst();
		if(!$inj->hasService($instName)) {
			Config::inst()->update('Injector', $instName, array(
				'id' => $instName,
				'class' => $class,
				'type' => 'singleton'
			));
		}
		$instance = $inj->get($instName, true, $params);
		$ci = CI_Controller::get_instance();
		if($ci instanceof CI_Controller) $ci->store($name, $instance); //yeah this is ugly coupling, but whatever. CI _IS_ ugly.
		else $ci->$name = $instance; //doesn't error on overwrite of existing loaded thing
		return $instance;
	}
	public function model($model, $name=null, $autoconn=false) {
		if($autoconn) $this->database($autoconn, false, true);
		return $this->library($model, null, $name);
	}
	public function database($params=null, $return=false, $querywrapper=null) {
		$name = 'CIBridge_';
		//dbdriver://username:password@hostname/database
		if(is_string($params) && preg_match('#^([a-z8]+)://([a-zA-Z0-9_-]+):([^@]+)@([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)#', $params, $details)) {
			$params = array(
				'dbdriver' => $details[1],
				'username' => $details[2],
				'password' => $details[3],
				'hostname' => $details[4],
				'database' => $details[5]
			);
		}
		if(is_array($params)) {
			switch($params['dbdriver']) {
				case 'mssql':
					$params['type'] = 'Microsoft SQL Server Database';
					break;
				case 'postgre':
					$params['type'] = 'Postgre SQL Database';
					break;
				case 'mysql':
				case 'mysqli':
				default:
					$params['type'] = 'MySQLDatabase';
			}
			$name .= $params['hostname'].':'.$params['type'].':'.$params['database'];
		}
		else {
			$name .= $identifier = $params ?:  'default';
			$params = Config::inst()->get('CIBridge', 'databases');
			if(is_array($params) && isset($params[$identifier])) {
				$params = $params[$identifier];
			}
			else {
				user_error("Can't find database config $name in the config");
			}
		}
		$db = DB::getConn($name) ?: DB::connect($params, $name);
		if(!$return) {
			//yeah this is ugly coupling, but whatever. CI _IS_ ugly.
			$ci = CI_Controller::get_instance();
			$db = Injector::inst()->get('CI_DB', true, array($name));//it makes no sense to not have this, so just do it.
			if($ci instanceof Controller) $ci->store('db', $db, true);
			else $ci->db = $db;
		}
		return $return ? $db : $name;
	}
	#public function dbutil(){}
	#public function dbforge(){}
	public function view($_ci_path, $_ci_vars=array(), $_ci_return=false) {
		$_ci_path = $this->locateProceduralFile($_ci_path, 'view');
		extract($_ci_vars);
		ob_start();
		include($_ci_path);
		$view = ob_get_contents();
		ob_end_clean();
		if(!$_ci_return) {
			$controller = CI_Controller::get_instance();
			if(!$controller instanceof CI_Controller)
				$controller = Controller::curr();
			$response = $controller->getResponse();
			$response->setBody($response->getBody().$view);
		}
		return $view;
	}
	public function driver($lib, $params=null, $name=null) {
		//a library with child classes as objects within eg $parent->childclass->method(); //polymorphism derp.
		return $this->library($lib, $params, $name);
	}
	public function helper($_ci_helpers=array()) {
		//do nothing and hope the autoloader has already pulled them in.
		/*foreach((array)$_ci_helpers as $_ci_helper) {
			try {
				$_ci_path = $this->locateProceduralFile($_ci_helper, 'helper');
			}
			catch(Exception $nofile) {
				$_ci_path = $this->locateProceduralFile($_ci_helper.'_helper', 'helper');
			}
			include_once($_ci_path);
			$_ci_path = null; //clean up for next loop
		}*/
	}
	public function helpers($helpers=array()){$this->helper($helpers);}
	private function locateProceduralFile($path, $type) {
		$found = array();
		foreach(Config::inst()->get('CIBridge', $type.'s') as $viewDir) {
			if(!preg_match('#/$#', $viewDir)) $viewDir .= '/';
			$testPath = Director::baseFolder().'/'.$viewDir.$path.'.php';
			if(file_exists($testPath)) {
				$found[] = $testPath;
			}
		}
		if(count($found) > 1) {
			user_error("Found the $type $path in multiple places!");
		}
		elseif(count($found) == 0) {
			user_error("Could not find the $type $path");
			$found[] = null;
		}
		return $found[0];
	}
}
