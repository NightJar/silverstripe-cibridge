<?php
if(!defined('BASEPATH')) define('BASEPATH', 'code igniter conventions are stupid');
function get_instance(){return Injector::inst()->get('CI_Controller');}
CI_Controller::update_routes();
