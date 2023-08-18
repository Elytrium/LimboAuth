<?php
/**
 * @brief		Upgrader: License
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Sep 2014
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: License
 */
class _license extends \IPS\Dispatcher\Controller
{

	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "controller=applications" )->setQueryString( 'key', $_SESSION['uniqueKey'] ) );
	}

	/**
	 * Retrieve license data from license server
	 *
	 * @return mixed
	 */
	protected function getLicenseData()
	{
		/* Call the main server */
		try
		{
			$response = \IPS\Http\Url::ips( 'license/' . \IPS\Settings::i()->ipb_reg_number )->request()->get();
			if ( $response->httpResponseCode == 404 )
			{
				$licenseData	= NULL;
			}
			else
			{
				$licenseData	= $response->decodeJson();
			}
		}
		catch ( \Exception $e )
		{
			$licenseData	= NULL;
		}

		return $licenseData;
	}
}