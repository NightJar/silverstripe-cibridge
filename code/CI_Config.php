<?php
class CI_Config extends Object {
	public function load() {
		if(Director::is_dev())
			user_error('Use YAML to define your configuration settings now.', E_USER_NOTICE);
	}
	public function item($item, $index=null) {
		$conf = Config::inst()->get('CIBridge', 'config');
		$value = isset($conf[$item]) ? $conf[$item] : null;
		if($index !== null) {
			if($value !== null) {
				$value = is_array($value) && isset($value[$index]) ? $value[$index] : false;
			}
			else {//sigh, the arguments are reversible (of sorts)
				$value = $conf->get('CIBridge', $index);
				$value = is_array($value) && isset($value[$item]) ? $value[$item] : false;
			}
		}
		return $value !== null ? $value : false;
	}
	public function slash_item($item) {
		$item = $this->item($item);
		return $item ? rtrim($item, '/').'/' : false;
	}
	public function site_url($path='') {//this is a fat load of obsolete/nothing.
		return $this->base_url($path);
	}
	public function base_url($path='') {
		return Controller::join_links(Director::absoluteBaseURL(), $path);
	}
	public function system_url() {
		return Director::baseFolder();
	}
	public function set_item($item, $value) {
		Config::inst()->update('CIBridge', 'config', array($item=>$value));
	}
}
