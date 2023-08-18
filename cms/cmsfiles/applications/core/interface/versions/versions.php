<?php
/**
 * @brief		Versions
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		15 Apr 2014
 */

\define('REPORT_EXCEPTIONS', TRUE);
require_once str_replace( 'applications/core/interface/versions/versions.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';

/**
 * Versions
 */
class versions
{
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		if ( isset( $_SERVER['REQUEST_METHOD'] ) or !isset( $_SERVER['argv'] ) or substr( str_replace( '\\', '/', $_SERVER['argv'][0] ), -31 ) !== 'interface/versions/versions.php' or strpos( $_SERVER['argv'][0], '?' ) !== FALSE )
		{
			echo "CLI Only\n";
			exit;
		}
	}

	/**
	 * Get versions
	 *
	 * @return	void
	 */
	public function get()
	{
		$apps = array();
		foreach (\IPS\Application::applications() as $key => $application )
		{
			$apps[$key]['human'] = $application->version;
			$apps[$key]['long'] = $application->long_version; 
		}

		\IPS\Output::i()->sendOutput( json_encode( $apps ), 200, 'application/json', array(), FALSE );
	}
}

$versions = new versions;
$versions->get();
