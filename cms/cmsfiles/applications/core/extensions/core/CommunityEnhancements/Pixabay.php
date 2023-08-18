<?php
/**
 * @brief		Community Enhancements
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		13 Jan 2020
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
class _Pixabay
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
	public $icon	= "pixabay.png";

	/**
	 * Constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->enabled = ( \IPS\Settings::i()->pixabay_enabled );
	}

	/**
	 * Edit
	 *
	 * @return	void
	 */
	public function edit()
	{
		$form = new \IPS\Helpers\Form;

		$form->add( new \IPS\Helpers\Form\Text( 'pixabay_apikey', \IPS\Settings::i()->pixabay_apikey ? \IPS\Settings::i()->pixabay_apikey : '', FALSE, array(), function( $val ) {
			if ( $val )
			{
				/* Check API */
				try
				{
					$response = \IPS\Http\Url::external( "https://pixabay.com/api/" )->setQueryString( array(
						'key'		=> $val,
						'q'			=> "winning"
					) )->request()->get();

					if ( $response->httpResponseCode == 400 )
					{
						throw new \DomainException('pixabay_api_key_invalid');
					}
				}
				catch ( \Exception $e )
				{
					throw new \DomainException('pixabay_api_key_invalid');
				}
			}
		}, NULL, NULL, 'pixabay_apikey' ) );

		$groups = array();
		foreach ( \IPS\Member\Group::groups() as $group )
		{
			$groups[ $group->g_id ] = $group->name;
		}
		
		$form->add( new \IPS\Helpers\Form\YesNo( 'pixabay_safesearch', \IPS\Settings::i()->pixabay_safesearch, FALSE, array(), NULL, NULL, NULL, 'pixabay_safesearch' ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'pixabay_editor_permissions', \IPS\Settings::i()->pixabay_editor_permissions == '*' ? '*' : explode( ',', \IPS\Settings::i()->pixabay_editor_permissions ), NULL, array( 'multiple' => TRUE, 'options' => $groups, 'unlimited' => '*', 'unlimitedLang' => 'everyone', 'impliedUnlimited' => TRUE ), NULL, NULL, NULL, 'pixabay_editor_permissions_access' ) );

		if ( $values = $form->values() )
		{
			try
			{
				/* Enable giphy automatically on the first submit of the form and add it automatically to all toolbars */
				if ( ! \IPS\Settings::i()->pixabay_apikey )
				{
					$values['pixabay_enabled'] = 1;
				}

				$values['pixabay_editor_permissions'] = $values['pixabay_editor_permissions'] == '*' ? '*' : implode( ',', $values['pixabay_editor_permissions'] );

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
				'link'		=> \IPS\Http\Url::ips( 'docs/pixabay' ),
				'target'	=> '_blank'
			),
		);

		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( 'enhancements__core_Pixabay', $form );
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
			if ( \IPS\Settings::i()->pixabay_apikey )
			{
				\IPS\Settings::i()->changeValues( array( 'pixabay_enabled' => 1 ) );
			}
			else
			{
				throw new \DomainException;
			}
		}
		else
		{
			\IPS\Settings::i()->changeValues( array( 'pixabay_enabled' => 0 ) );
		}
	}
}