<?php
/**
 * @brief		Twitter share link
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		11 Sept 2013
 * @see			<a href='https://dev.twitter.com/docs/tweet-button'>Tweet button documentation</a>
 */

namespace IPS\Content\ShareServices;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Twitter share link
 */
class _Twitter extends \IPS\Content\ShareServices
{
	/**
	 * Determine whether the logged in user has the ability to autoshare
	 *
	 * @return	boolean
	 */
	public static function canAutoshare()
	{
		if ( $method = \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\OAuth1\Twitter' ) and $method->canProcess( \IPS\Member::loggedIn() ) )
		{
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * Publish text or a URL to this service
	 *
	 * @param	string	$content	Text to publish
	 * @param	string	$url		[URL to publish]
	 * @return	void
	 */
	public static function publish( $content, $url=null )
	{
		if ( static::canAutoshare() and $method = \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\OAuth1\Twitter' ) )
		{	
			try
			{
				if ( !$method->postToTwitter( \IPS\Member::loggedIn(), $content, $url ) )
				{
					throw new \Exception;
				}
			}
			catch( \Exception $e )
			{
				\IPS\Log::log( \IPS\Member::loggedIn()->member_id . ': '. $e->getMessage(), 'twitter' );
				throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_exception') );
			}			
		}
		else
		{
			throw new \InvalidArgumentException( \IPS\Member::loggedIn()->language()->addToStack('twitter_publish_no_user') );
		}
	}

	/**
	 * Add any additional form elements to the configuration form. These must be setting keys that the service configuration form can save as a setting.
	 *
	 * @param	\IPS\Helpers\Form				$form		Configuration form for this service
	 * @param	\IPS\core\ShareLinks\Service	$service	The service
	 * @return	void
	 */
	public static function modifyForm( \IPS\Helpers\Form &$form, $service )
	{
		$form->add( new \IPS\Helpers\Form\Text( 'twitter_hashtag', \IPS\Settings::i()->twitter_hashtag, FALSE ) );
		
		if ( \IPS\Login\Handler::findMethod( 'IPS\Login\Handler\OAuth2\Twitter' ) )
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'share_autoshare_Twitter', $service->autoshare, false ) );
		}
		else
		{
			$form->add( new \IPS\Helpers\Form\YesNo( 'share_autoshare_Twitter', FALSE, false, array( 'disabled' => TRUE ) ) );
			\IPS\Member::loggedIn()->language()->words['share_autoshare_Twitter_desc'] = \IPS\Member::loggedIn()->language()->addToStack('share_autoshare_Twitter_disabled');
		}
	}

	/**
	 * Return the HTML code to show the share link
	 *
	 * @return	string
	 */
	public function __toString()
	{
		try
		{
			$url = preg_replace_callback( "{[^0-9a-z_.!~*'();,/?:@&=+$#-]}i",
				function ( $m )
				{
					return sprintf( '%%%02X', \ord( $m[0] ) );
				},
				$this->url) ;

			$title = $this->title ?: NULL;
			if ( \IPS\Settings::i()->twitter_hashtag !== '')
			{
				$title .= ' ' . \IPS\Settings::i()->twitter_hashtag;
			}
			return \IPS\Theme::i()->getTemplate( 'sharelinks', 'core' )->twitter( urlencode( $url ), rawurlencode( $title ) );
		}
		catch ( \Exception $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
		catch ( \Throwable $e )
		{
			\IPS\IPS::exceptionHandler( $e );
		}
	}
}