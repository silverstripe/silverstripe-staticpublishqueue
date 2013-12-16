<?php
//replace this with 'apache' if you are running RedHat, or '_www' if you are running Mac OS X
$webserver_user = 'www-data';

$cwd = getcwd();
$crontext = "#Cronjob for processing the static publishing queue for site: $cwd \n".
		"* * * * * $webserver_user $cwd/framework/sake dev/tasks/BuildStaticCacheFromQueue daemon=1 verbose=0 >> /tmp/buildstaticcache.log\n\n".
		"#Rebuild the entire static cache at 1am every night".
		"0 1 * * * $webserver_user $cwd/framework/sake dev/tasks/SiteTreeFullBuildEngine flush=all";

$cronPath = '/etc/cron.d/staticpublishqueue';
file_put_contents($cronPath,$crontext);

echo "Success: created new cron job to run static publishing in '$cronPath'\n";
