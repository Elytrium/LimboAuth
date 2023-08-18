<?php
/**
 * @brief		Community Enhancements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		07 Sep 2018
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
class _Giphy
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
	public $icon	= "giphy.png";

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->giphy_enabled );
	}

	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\Text( 'giphy_apikey', \IPS\Settings::i()->giphy_apikey ? \IPS\Settings::i()->giphy_apikey : '', FALSE, array(), NULL, NULL, NULL, 'giphy_apikey' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'giphy_rating', \IPS\Settings::i()->giphy_rating ? \IPS\Settings::i()->giphy_rating : 'x', FALSE, array(
			'options' => array(
				'x' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_x'),
				'r' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_r'),
				'pg-13' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_pg-13'),
				'pg' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_pg'),
				'g' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_g'),
				'y' => \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_y'),
			)
		), NULL, NULL, \IPS\Member::loggedIn()->language()->addToStack('giphy_rating_suffix') ) );

		if ( $values = $form->values() )
		{
			try
			{
				/* Enable giphy automatically on the first submit of the form and add it automatically to all toolbars */
				if ( ! \IPS\Settings::i()->giphy_enabled AND \IPS\Settings::i()->giphy_apikey != '' )
				{
					$values['giphy_enabled'] = 1;

					$toolbars = json_decode( \IPS\Settings::i()->ckeditor_toolbars, TRUE );
					foreach ( array( 'desktop', 'tablet', 'phone' ) as $device )
					{
						if ( !\in_array( 'ipsgiphy', $toolbars[$device][0]['items'] ) )
						{
							$toolbars[$device][0]['items'][] = 'ipsgiphy';
						}
					}
					$values['ckeditor_toolbars'] = json_encode( $toolbars );
				}
				
				unset( $values['giphy_custom_apikey'] );
				
				$form->saveAsSettings( $values );

				\IPS\Output::i()->inlineMessage	= \IPS\Member::loggedIn()->language()->addToStack('saved');
			}
			catch ( \LogicException $e )
			{
				$form->error = $e->getMessage();
			}
		}

		\IPS\Output::i()->sidebar['actions'] = array(
			'help'	=> array(
				'title'		=> 'help',
				'icon'		=> 'question-circle',
				'link'		=> \IPS\Http\Url::ips( 'docs/giphy' ),
				'target'	=> '_blank'
			),
		);

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_Giphy', $form );
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
			if ( \IPS\Settings::i()->giphy_apikey )
			{
				\IPS\Settings::i()->changeValues( array( 'giphy_enabled' => 1 ) );
			}
			else
			{
				throw new \DomainException;
			}
		}
		else
		{
			\IPS\Settings::i()->changeValues( array( 'giphy_enabled' => 0 ) );
		}
	}
}