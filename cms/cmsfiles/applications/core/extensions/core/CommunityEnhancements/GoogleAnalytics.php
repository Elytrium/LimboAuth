<?php
/**
 * @brief		Community Enhancements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		13 Sep 2021
 */

namespace IPS\core\extensions\core\CommunityEnhancements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Community Enhancement
 */
class _GoogleAnalytics
{
	/**
	 * @brief	Enhancement is enabled?
	 */
	public $enabled	= FALSE;

	/**
	 * @brief	IPS-provided enhancement?
	 */
	public $ips	= FALSE;

	/**
	 * @brief	Enhancement has configuration options?
	 */
	public $hasOptions	= TRUE;

	/**
	 * @brief	Icon data
	 */
	public $icon	= "google_analytics.png";
	
	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->ga_enabled and \IPS\Settings::i()->ga_code );
	}
	
	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		
		$validation = function( $val ) {
			if ( $val and !\IPS\Request::i()->ga_code )
			{
				throw new \DomainException('ga_code_required');
			}
		};
		
		$form = new \IPS\Helpers\Form;		
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'ga_enabled', \IPS\Settings::i()->ga_enabled, FALSE, array(), $validation ) );
		$form->add( new \IPS\Helpers\Form\Codemirror( 'ga_code', \IPS\Settings::i()->ga_code, FALSE, array( 'height' => 150, 'mode' => 'javascript' ), NULL, NULL, NULL, 'ga_code' ) );	
		
		if ( $form->values() )
		{
			try
			{
				$form->saveAsSettings();

				\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
			}
			catch ( \LogicException $e )
			{
				$form->error = $e->getMessage();
			}
		}
		
		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'learn_more',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/googleanalytics' ), //@todo
				'target'	=> '_blank'
			),
		);
		
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_GoogleAnalytics', $form );
	}
	
	/**
	 * Enable/Disable
	 *
	 * @param	$enabled	bool	Enable/Disable
	 * @return	void
	 * @throws	\LogicException
	 */
	public function toggle( $enabled )
	{
		if ( $enabled )
		{
			if ( \IPS\Settings::i()->ga_code )
			{
				\IPS\Settings::i()->changeValues( array( 'ga_enabled' => 1 ) );
			}
			else
			{
				throw new \DomainException;
			}
		}
		else
		{
			\IPS\Settings::i()->changeValues( array( 'ga_enabled' => 0 ) );
		}
	}
}