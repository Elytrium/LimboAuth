<?php
/**
 * @brief		Captcha class for Form Builder
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		18 Apr 2013
 */

namespace IPS\Helpers\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Captcha class for Form Builder
 */
class _Captcha extends FormAbstract
{
	/**
	 * CAPTCHA Class
	 */
	protected $captcha = NULL;
	
	/**
	 * Does the configured CAPTCHA service support being added in a modal?
	 * 
	 * @return	bool
	 */
	public static function supportsModal()
	{
		if ( \IPS\Settings::i()->bot_antispam_type === 'none' )
		{
			return TRUE;
		}
		
		$class = '\IPS\Helpers\Form\Captcha\\' . mb_ucfirst( \IPS\Settings::i()->bot_antispam_type );
		return ( !isset( $class::$supportsModal ) or $class::$supportsModal ); // isset() check is for backwards compatibility
	}
	
	/**
	 * Constructor
	 *
	 * @see		\IPS\Helpers\Form\FormAbstract::__construct
	 * @return	void
	 */
	public function __construct()
	{
		$params = \func_get_args();
		if ( !isset( $params[0] ) )
		{
			$params[0] = 'captcha_field';
		}
		
		if ( \IPS\Settings::i()->bot_antispam_type != 'none' )
		{
			$class = '\IPS\Helpers\Form\Captcha\\' . mb_ucfirst( \IPS\Settings::i()->bot_antispam_type );
			if ( !class_exists( $class ) )
			{
				\IPS\Output::i()->error( 'unexpected_captcha', '4S262/1', 500, 'unexpected_captcha_admin' );
			}
			$this->captcha = new $class;
		}
		
		parent::__construct( ...$params );

		$this->required = TRUE;
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function __toString()
	{
		if ( $this->captcha === NULL )
		{
			return '';
		}
		return parent::__toString();
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		if ( $this->captcha === NULL )
		{
			return '';
		}
		return $this->captcha->getHtml();
	}
	
	/**
	 * Get HTML
	 *
	 * @param	\IPS\Helpers\Form|null	$form	Form helper object
	 * @return	string
	 */
	public function rowHtml( $form=NULL )
	{
		if ( $this->captcha === NULL )
		{
			return '';
		}
		return ( method_exists( $this->captcha, 'rowHtml' ) and !$this->error ) ? $this->captcha->rowHtml() : parent::rowHtml( $form );
	}
		
	/**
	 * Get Value
	 *
	 * @return	bool|null	TRUE/FALSE indicate if the test passed or not. NULL indicates the test failed, but the captcha system will display an error so we don't have to.
	 */
	public function getValue()
	{
		if ( $this->captcha === NULL )
		{
			return TRUE;
		}
		else
		{
			/* If we previously did an AJAX validate which is still valid, return true */
			$cached = NULL;
			$cacheKey =  'captcha-val-' . $this->name . '-' . \IPS\Member::loggedIn()->ip_address;
			try
			{
				$cached = \IPS\Data\Cache::i()->getWithExpire( $cacheKey, TRUE );
			}
			catch( \Exception $ex ) { }
			
			if ( $cached )
			{
				unset( \IPS\Data\Cache::i()->$cacheKey );
				return TRUE;
			}
			/* Otherwise, check with service */
			else
			{
				/* Check */
				$return = $this->captcha->verify();
				
				/* If it's valid and we're doing an AJAX validate, save that in the session so the next request doesn't check again */
				if ( $return and \IPS\Request::i()->isAjax() )
				{
					\IPS\Data\Cache::i()->storeWithExpire( $cacheKey, time(), \IPS\DateTime::create()->add( new \DateInterval( 'PT1M' ) ), TRUE );
				}
				
				/* Return */
				return $return;
			}
		}
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @return	TRUE
	 */
	public function validate()
	{
		if ( $this->value !== TRUE )
		{
			throw new \InvalidArgumentException( 'form_bad_captcha' );
		}
		
		return TRUE;
	}
}