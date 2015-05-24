<?php

class CI_Input extends Object {
	private $req;
	function __construct(SS_HTTPRequest $request) {
		parent::__construct();
		if(!$request) $request = Controller::curr()->getRequest();
		$this->req = $request;
	}
	function post($var=null) {
		$val = $var ? $this->req->postVar($var) : $this->req->postVars();
		$this->extend(__FUNCTION__, $var, $val);
		return $val ?: false;
	}
	function get($var=null) {
		$val = $var ? $this->req->getVar($var) : $this->req->getVars();
		$this->extend(__FUNCTION__, $var, $val);
		return $val ?: false;
	}
	function get_post($var=null) {
		$val = $var ? $this->req->requestVar($var) : $this->req->requestVars();
		$this->extend(__FUNCTION__, $var, $val);
		return $val ?: false;
	}
	function cookie($var) {
		return Cookie::get($var) ?: false;
	}
	function set_cookie($name, $value, $expire=90, $domain=null, $path=null, $prefix='', $secure=FALSE) {
		return Cookie::set($prefix.$name, $value, $expiry, $path, $domain, $secure);
	}
	function request_headers() {
		return $this->req->getHeaders();
	}
	function get_request_header($header) {
		return $this->req->getHeader($header);
	}
	function is_ajax_request() {
		return $this->req->isAjax();
	}
	function is_cli_request() {
		return Director::is_cli();
	}
}
