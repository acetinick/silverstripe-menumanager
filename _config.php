<?php

Object::add_extension('LeftAndMain', 'MenuManagerExtension');

if(($MODULE_DIR = basename(dirname(__FILE__))) != 'menumanager') {
	throw new Exception("The menu manager module must be in a directory named 'menumanager', not $MODULE_DIR");
}

?>
