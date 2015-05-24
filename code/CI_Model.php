<?php
abstract class CI_Model extends ViewableData {
	function __construct() {
		parent::__construct();
		$this->failover = CI_Controller::get_instance();
	}
}
