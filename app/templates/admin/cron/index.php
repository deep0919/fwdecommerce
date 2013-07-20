<?php
/**
 * Admin cron index. Invokes all admin cron tasks.
 * This should be invoked by a cron-like daemon
 * once every few minutes depending on timing desired.
 *
 * Example crontab:
 *
 *	*	*	*	*	*	php /path/to/public/start.php admin:/cron/index.php
 *
 * Each task/script must itself limit runtime,
 * if the default interval is too frequent.
 */

// Cron scripts path (self dir).
$cron_path = dirname(__FILE__).'/';

// Run each script.
foreach((array)scandir($cron_path) as $entry)
{
	if ($entry[0] == '.')
	{
		continue;
	}

	// Ignore self.
	if ($cron_path.$entry == __FILE__ || $entry == 'index.php')
	{
		continue;
	}
	
	// Log cron entry.
	$request->log[] = "[".time()."] Task: {$entry}";
	
	// Run script (via render).
	$result = render(array(
		'view' => "cron/{$entry}"
	));
}

// No layout.
$request->layout = null;

// Output log on command line.
if (php_sapi_name() == "cli")
{
	foreach((array)$request->log as $key => $message)
	{
		echo "{$message}\n";
	}
}