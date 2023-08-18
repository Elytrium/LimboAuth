<?php
/**
 * @brief		icons
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		25 Jul 2018
 */

namespace IPS\core\modules\admin\customization;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * icons
 */
class _icons extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'theme_sets_manage' );
		parent::execute();
	}

	/**
	 * Show the form
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$form = new \IPS\Helpers\Form;

		/* Generic favicon - easy enough */
		$form->add( new \IPS\Helpers\Form\Upload( 'icons_favicon', \IPS\Settings::i()->icons_favicon ? \IPS\File::get( 'core_Icons', \IPS\Settings::i()->icons_favicon ) : NULL, FALSE, array( 'obscure' => false, 'allowedFileTypes' => array( 'ico', 'png', 'gif', 'jpeg', 'jpg', 'jpe' ), 'storageExtension' => 'core_Icons' ) ) );

		/* Sharer logos, allows multiple images to be uploaded */
		$shareLogos = \IPS\Settings::i()->icons_sharer_logo ? json_decode( \IPS\Settings::i()->icons_sharer_logo, true ) : array();
		$form->add( new \IPS\Helpers\Form\Upload( 'icons_sharer_logo', \count( $shareLogos ) ? array_map( function( $val ) { return \IPS\File::get( 'core_Icons', $val ); }, $shareLogos ) : array(), FALSE, array( 'image' => true, 'storageExtension' => 'core_Icons', 'multiple' => true ) ) );

		/* We've submitted, check our values! */
		if ( $values = $form->values() )
		{
			/* Favicon is easy, we just store the string value of the file object */
			$values['icons_favicon']		= (string) $values['icons_favicon'];

			/* Sharer logos are easy too, except it's an array of images instead of a single image */
			if( \count( $values['icons_sharer_logo'] ) )
			{
				$values['icons_sharer_logo']	= json_encode( array_map( function( $val ){ return (string) $val; }, $values['icons_sharer_logo'] ) );
			}

			/* Save the settings */
			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();

			/* And log */
			\IPS\Session::i()->log( 'acplogs__icons_and_logos' );

			/* And Redirect */
			\IPS\Output::i()->redirect( $this->url, 'saved' );
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_customization_icons');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_customization_icons', $form );
	}
}