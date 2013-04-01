<?php
if (!file_exists('cache')) {
	mkdir('cache'); //create a new cache dir
}
chmod('cache',0777);    //make sure it is readable by the web-server user
echo "Success: created 'cache' folder that contains the statically published files\n";