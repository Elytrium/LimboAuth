<?php
/**
 * @brief		Key/Value input class for social profile links
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		21 Feb 2017
 */

namespace IPS\core\Form;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Key/Value input class for social profile links
 */
class _SocialProfiles extends \IPS\Helpers\Form\KeyValue
{
	/**
	 * @brief	Default Options
	 * @see		\IPS\Helpers\Form\Date::$defaultOptions
	 * @code
	 	$defaultOptions = array(
	 		'start'			=> array( ... ),
	 		'end'			=> array( ... ),
	 	);
	 * @endcode
	 */
	protected $defaultOptions = array(
		'key'		=> array(
		),
		'value'		=> array(
		),
	);

	/**
	 * @brief	Key Object
	 */
	public $keyField = NULL;
	
	/**
	 * @brief	Value Object
	 */
	public $valueField = NULL;
	
	/**
	 * Constructor
	 * Creates the two date objects
	 *
	 * @param	string		$name			Form helper name
	 * @param	mixed		$defaultValue	Default value for the helper
	 * @param	bool		$required		Helper is required (TRUE) or not (FALSE)
	 * @param	array		$options		Options for the helper instance
	 * @see		\IPS\Helpers\Form\Abstract::__construct
	 * @return	void
	 */
	public function __construct( $name, $defaultValue=NULL, $required=FALSE, $options=array() )
	{
		$options = array_merge( $this->defaultOptions, $options );

		$options = $this->addSocialNetworks( $options );

		parent::__construct( $name, $defaultValue, $required, $options );
		
		$this->keyField = new \IPS\Helpers\Form\Url( "{$name}[key]", isset( $defaultValue['key'] ) ? $defaultValue['key'] : NULL, FALSE, isset( $options['key'] ) ? $options['key'] : array() );
		$this->valueField = new \IPS\Helpers\Form\Select( "{$name}[value]", isset( $defaultValue['value'] ) ? $defaultValue['value'] : NULL, FALSE, isset( $options['value'] ) ? $options['value'] : array() );
	}

	/**
	 * Add social networks to the options array
	 *
	 * @note	Abstracted so third parties can extend as needed
	 * @param	array 	$options	Options array
	 * @return	array
	 */
	protected function addSocialNetworks( $options )
	{
		$options['value']['options'] = array(
			'facebook'		=> "siteprofilelink_facebook",
			'twitter'		=> "siteprofilelink_twitter",
			'youtube'		=> "siteprofilelink_youtube",
			'tumblr'		=> "siteprofilelink_tumblr",
			'deviantart'	=> "siteprofilelink_deviantart",
			'etsy'			=> "siteprofilelink_etsy",
			'flickr'		=> "siteprofilelink_flickr",
			'foursquare'	=> "siteprofilelink_foursquare",
			'github'		=> "siteprofilelink_github",
			'instagram'		=> "siteprofilelink_instagram",
			'pinterest'		=> "siteprofilelink_pinterest",
			'linkedin'		=> "siteprofilelink_linkedin",
			'slack'			=> "siteprofilelink_slack",
			'xing'			=> "siteprofilelink_xing",
			'weibo'			=> "siteprofilelink_weibo",
			'vk'			=> "siteprofilelink_vk",
			'discord'		=> "siteprofilelink_discord",
			'twitch'		=> "siteprofilelink_twitch",
		);

		return $options;
	}
	
	/**
	 * Format Value
	 *
	 * @return	array
	 */
	public function formatValue()
	{
		return array(
			'key'	=> $this->keyField->formatValue(),
			'value'	=> $this->valueField->formatValue()
		);
	}
	
	/**
	 * Get HTML
	 *
	 * @return	string
	 */
	public function html()
	{
		return \IPS\Theme::i()->getTemplate( 'forms', 'core', 'admin' )->socialProfiles( $this->keyField->html(), $this->valueField->html() );
	}
	
	/**
	 * Validate
	 *
	 * @throws	\InvalidArgumentException
	 * @throws	\LengthException
	 * @return	TRUE
	 */
	public function validate()
	{
		$this->keyField->validate();
		$this->valueField->validate();
		
		if( $this->customValidationCode !== NULL )
		{
			$validationCode = $this->customValidationCode;
			$validationCode( $this->value );
		}
	}
}