<?php
abstract class CI_Controller extends Controller {

/*	//most of this stuff is irrelevant now we've a better system.
	private $base_classes = array(
		'Benchmark',
		'Hooks',		//should be Extension stuff if ever implemented I guess
		'Config',		#done (& autoloaded)
		'Utf8',
		'URI',			//should hook SS_HTTPRequest if ever implemented
		'Router',		//Director::rules I guess.
		'Output',		//SS_HTTPResponse ?
		'Security',
		'Input',		#done
		'Lang'
	);*/
	
	public static function get_instance() {
		$inj = Injector::inst();
		if($inj->hasService('CI_Controller')) {
			$ret = $inj->get('CI_Controller');
		}
		else {
			//ie. what if we're using a CI model from SS based code/controller?
			$ret = new stdClass;
			$ret->load = $inj->get('CI_Loader');
			Injector::inst()->registerService($ret, 'CI_Controller');
		}
		return $ret;	
	}
	
	/*
		Called from CIBridge module's _config.php
		eg. website/foldername/subfolder/controller
		In SS's very flat default, this will match as website/$Controller
		if 'foldername' doesn't match a controller (class) name there will 
		be no page. So this sets so controllers in subdirs work as they used to.
	*/
	public static function update_routes() {
		$rdirs = array();
		$rules = array();
		$controllerdirs = Config::inst()->get('CIBridge', 'controllers');
		if($controllerdirs && is_array($controllerdirs)) {
			foreach($controllerdirs as $controllerdir) {
				$controllerdir = trim($controllerdir, "/ \t\n\r\0\x0B").'/';
				foreach(self::controller_dirs($controllerdir) as $subdir) {
					$rules[str_replace($controllerdir, '', $subdir).'/$Controller//$Action'] = '*';
				}
			}
			if(count($rules)) {
				Config::inst()->update('Director', 'rules', $rules);
			}
		}
	}
	//takes in a dir name with no leading or trailing separator & returns array of all subdirs.
	private static function controller_dirs($dir) {
		$dir = trim($dir, "/ \t\n\r\0\x0B");
		$base = Director::baseFolder().'/';
		$subdirs = array();
		foreach(array_diff(glob("$base$dir/*", GLOB_ONLYDIR), array('.', '..')) as $subdir) {
			$subdir = preg_replace("#^$base#", '', $subdir);
			$subdirs = array($subdir) + self::controller_dirs($subdir);
		}
		return $subdirs;
	}
	
	/*
		Since we're not in CI any more, we could load stuff from anywhere 
		and then exchange out the global instance (see __construct below). 
		So we still need to provide an interface for acting like a global 
		instance.
	*/
	public function __get($field) {
		$property = parent::__get($field);
		if(!$property && $inst = $this->load->is_loaded($field)) {
			$property = Injector::inst()->get("CIB_$inst");
			//if we've made it here it's obviously exists and just isn't set
			$this->$field = $property;
		}
		return $property;
	}
	
	public function __construct() {
		parent::__construct();
		
		//set allowed actions
		$ref = new ReflectionClass($this);
		$config = Config::inst();
		//most CI controllers extend directly from CI_Controller, but just in case... (eg. require_once(parentclass); class child extends parent {...})
		$ancestry = ClassInfo::ancestry($this->class);
		$lineage = array();
		$methods = array();
		//an allowed action must be defined on the class that declares the action method
		while(($ancestor = array_pop($ancestry)) && $ancestor != 'CI_Controller') {
			$lineage[] = $ancestor;
			$methods[$ancestor] = $config->get($ancestor, 'allowed_actions', Config::UNINHERITED);
		}
		//so get all the public methods (CI thinks that _ as the first char in a function name makes it private - it doesn't).
		foreach($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if($method->name[0] != '_' && in_array($method->class, $lineage))
				$methods[$method->class][] = $method->name;
		}
		//and set them on their respective controllers so we can execute them from a request
		foreach($methods as $class => $allowed) {
			if($allowed)
				$config->update($class, 'allowed_actions', array_unique($allowed));//array_unique probably unnecessary but whatever.
		}
		
		//store $this instance as singleton
		$inj = Injector::inst();
		$inj->registerService($this, 'CI_Controller'); //replaces any previous inst, such as stdClass masquerading as CI_Controller
		
		$this->load = $inj->get('CI_Loader');
		
		//do 'auto loading' of stuffs
		$autoload = $config->get('CIBridge', 'autoload');
		if($autoload) {
			if(is_array($autoload)) {
				//database is a special case
				if(isset($autoload['libraries']['database'])) {
					$this->load->database();
					unset($autoload['libraries']['database']);
				}
				//do all the other autoloads
				foreach($autoload as $type => $items) {
					$type = $type == 'libraries' ? 'library' : $type;
					if($this->load->hasMethod($type)) {
						foreach($items as $name => $item) {
							if(is_int($name)) $this->load->$type($item);
							else $type == 'model' ? $this->load->$type($item, $name) : $this->load->$type($item, null, $name);
						}
					}
				}
			}
			else { //new functionality over CI: assume it's a classname string
				$this->load->library($autoload);
			}
		}
	}
	
	public function init() {
		$this->input = CI_Input::create($this->request);
		return parent::init() ?: $this;
	}
	
	public function _remap() {} //Not sure what this does, but it seems like it might be a primitive version of $url_handlers
	
	public function handleAction($request, $action) {
		//will need this later
		$args = preg_split('#/+#',$this->request->getURL());
	
		//Below is copy and paste of the 2 parent classes calls for the most part :<
		
		//Controller::handleAction :
		foreach($request->latestParams() as $k => $v) {
			if($v || !isset($this->urlParams[$k])) $this->urlParams[$k] = $v;
		}

		$this->action = $action;
		$this->requestParams = $request->requestVars();

		if($this->hasMethod($action)) {
			$result = null; //was $result = parent::handleAction($request, $action);
			
			//RequestHandler::handleAction adjusted to run in local scope:
			$res = $this->extend('beforeCallActionHandler', $request, $action);
			if ($res) {
				$result = reset($res);
			}
			else {
				//this is the changed bit. Was $result = $this->$action($request);
				$result = call_user_func_array(array($this, $action), $args);

				$res = $this->extend('afterCallActionHandler', $request, $action);
				if ($res) $result = reset($res);
			}
			//End RequestHandler::handleAction
			
			//also changed: We used all the dir parts in one go, don't give me that 'not done' business.
			while($notdone) {
				$notdone = $request->shift();
			}
			
			// If the action returns an array, customise with it before rendering the template.
			if(is_array($result)) {
				return $this->getViewer($action)->process($this->customise($result));
			} else {
				return $result ?: $this->response;
			}
		} else {
			return $this->getViewer($action)->process($this);
		}
		//End Controller::handleAction
	}
	
	public function store($name, $thing, $override=false) {
		if(!$override && isset($this->$name)) {
			user_error("CI global object property conflict: $name is already set");
		}
		$this->$name = $thing;
		return $this;
	}

}
