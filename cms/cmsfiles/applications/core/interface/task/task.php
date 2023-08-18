<?php
/**
 * @brief		Runs tasks
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		24 Jun 2013
 */

// To execute a specific task pass the ID (core_tasks.id - NB: This is different to the task key)
// as the second parameter to the command
// e.g: /usr/bin/php /path/to/site/applications/core/interface/task/task.php abc1234 6
// would execute task with ID 6

@header( "Cache-Control: no-cache, no-store, must-revalidate, max-age=0, s-maxage=0" );
@header( "Expires: 0" );

/* Check this is running at the command line */
if ( !isset( $_SERVER['argv'] ) )
{
	echo "Not at command line\n";
	exit;
}

/* Init Invision Community */
\define('READ_WRITE_SEPARATION', FALSE);
\define('REPORT_EXCEPTIONS', TRUE);

$path = str_replace( 'interface/task/task.php', 'data/requirements.json', str_replace( '\\', '/', __FILE__ ) );

/* Check the used PHP version and compare it with the requirements */
$phpVersion = PHP_VERSION;
$requirements = json_decode( file_get_contents( $path ), TRUE );

if( version_compare($phpVersion, $requirements['php']['required'] ) < 0 )
{
	@ini_set('display_errors', 1);
	trigger_error('The task was called with PHP version ' . $phpVersion . " while PHP " . $requirements['php']['required'] . " is required.", E_USER_ERROR );
	exit;
}

require_once str_replace( 'applications/core/interface/task/task.php', 'init.php', str_replace( '\\', '/', __FILE__ ) );

if ( !\IPS\CIC and $_SERVER['argv'][1] !== \IPS\Settings::i()->task_cron_key )
{
	echo "Incorrect key\n";
	exit;
}

/* Execute */
try
{
	/* Ensure applications set up correctly before task is executed. Pages, for example, needs to set up spl autoloaders first */
	\IPS\Application::applications();
	
	if( isset( $_SERVER['argv'][2] ) )
	{
		$task = \IPS\Task::load( $_SERVER['argv'][2], ( \is_numeric( $_SERVER['argv'][2] ) ) ? NULL : 'key' );

		if ( !$task )
		{
			throw new \OutOfRangeException( 'NO_TASK' );
		}
		
		$task->runAndLog();
	}
	else
	{
		while( $task = \IPS\Task::queued() )
		{
			$task->runAndLog();
		}
	}
}
catch ( \Exception $e )
{
	\IPS\Log::log( $e, 'uncaught_exception' );
	
	echo "Exception:\n";
	print_r( $e );
	exit;
}

/* Exit */
exit;