<?php
/**
 * @brief		Community Enhancements: Spam Monitoring Service
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		17 Apr 2013
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancements: Spam Monitoring Service
 */
class _SpamMonitoring
{
	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= TRUE;

	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "ips.png";
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$licenseData = \IPS\IPS::licenseKey();
		if( !$licenseData or !isset( $licenseData['products']['spam'] ) or !$licenseData['products']['spam'] or ( !$licenseData['cloud'] AND strtotime( $licenseData['expires'] ) < time() ) )
		{
			$this->enabled	= false;
		}
		else
		{
			$this->enabled = \IPS\Settings::i()->spam_service_enabled;
		}
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		try
		{
			$this->testSettings();
		}
		catch ( \RuntimeException $e )
		{
			\IPS\Output::i()->error( 'spam_service_error', '3C116/3', 500, '' );
		}
		catch ( \LogicException $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2C116/2', 403, '' );
		}
		
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=moderation&controller=spam&tab=service' ) );
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 * @throws	\Exception
	 */
	public function toggle( $enabled )
	{
		if ( $enabled )
		{
			$this->testSettings();
		}
		
		\IPS\Settings::i()->changeValues( array( 'spam_service_enabled' => $enabled ) );
	}
	
	/**
	 * Test Settings
	 *
	 * @return	void
	 * @throws	\Exception
	 */
	protected function testSettings()
	{
		$licenseData = \IPS\IPS::licenseKey();
			
		if ( !$licenseData )
		{
			throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('spam_service_nokey', FALSE, array( 'sprintf' => array( \IPS\Http\Url::internal( 'app=core&module=settings&controller=licensekey', null ) ) ) ) );
		}
		if ( !$licenseData['cloud'] AND strtotime( $licenseData['expires'] ) < time() )
		{
			throw new \DomainException('licensekey_expired');
		}
		if ( !$licenseData['products']['spam'] )
		{
			throw new \DomainException('spam_service_noservice');
		}		
	}
}