<?php
//They're kinda like decorators, only not quite.
class CI_Driver_Library {
	//$this->blah->thing = blah_driver::thing
	#referencing a driver belonging to this lib instantiates and accesses
}
class CI_Driver {
	//$this->parentthing = parent::parentthing
	#all 'parent' methods and properties accessible directly here (as if 'parent' driver lib is extension on this driver)
}
//TODO: implement __get to injector fetch a child class of same name. if class_exists && subclass_of $this, injector::inst->get
#currently not fussed as our app doesn't use drivers.