<?php
/**
 * @brief		Application builder custom filter iterator
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		8 Aug 2013
 */

namespace IPS\Application;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Custom filter iterator for application building
 */
class _BuilderIterator extends \RecursiveIteratorIterator
{
	/**
	 * @brief	The application
	 */
	protected $application;

	/**
	 * Constructor
	 *
	 * @param \IPS\Application $application
	 */
	public function __construct( \IPS\Application $application )
	{
		$this->application = $application;
		parent::__construct( new BuilderFilter( new \RecursiveDirectoryIterator( \IPS\ROOT_PATH . "/applications/" . $application->directory, \RecursiveDirectoryIterator::SKIP_DOTS ) ) );
	}
	
	/**
	 * Current key
	 *
	 * @return	void
	 */
	public function key()
	{
		return mb_substr( parent::current(), mb_strlen( \IPS\ROOT_PATH . "/applications/" . $this->application->directory ) + 1 );
	}
	
	/**
	 * Current value
	 *
	 * @return	void
	 */
	public function current()
	{
		$file = (string) parent::current();
		if ( mb_substr( str_replace( '\\', '/', $file ), mb_strlen( \IPS\ROOT_PATH . "/applications/" . $this->application->directory ) + 1, 6 ) === 'hooks/' )
		{
			$temporary = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			\file_put_contents( $temporary, \IPS\Plugin::addExceptionHandlingToHookFile( $file ) );

			register_shutdown_function( function( $temporary ) {
				unlink( $temporary );
			}, $temporary );
			
			return $temporary;
		}
		else
		{
			return $file;
		}
	}
}