<?php
/**
 * @brief		Dashboard extension: Admin notes
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Jul 2013
 */

namespace IPS\core\extensions\core\Dashboard;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	Dashboard extension: Admin notes
 */
class _AdminNotes
{
	/**
	 * Can the current user view this dashboard item?
	 *
	 * @return	bool
	 */
	public function canView()
	{
		return TRUE;
	}

	/**
	 * Return the block to show on the dashboard
	 *
	 * @return	string
	 */
	public function getBlock()
	{
		$form	= new \IPS\Helpers\Form( 'form', 'save', \IPS\Http\Url::internal( "app=core&module=overview&controller=dashboard&do=getBlock&appKey=core&blockKey=core_AdminNotes" )->csrf() );
		$form->add( new \IPS\Helpers\Form\TextArea( 'admin_notes', ( isset( \IPS\Settings::i()->acp_notes ) ) ? htmlspecialchars( \IPS\Settings::i()->acp_notes, ENT_DISALLOWED, 'UTF-8', FALSE ) : '' ) );

		if( $values = $form->values() )
		{
			\IPS\Settings::i()->changeValues( array( 'acp_notes' => $values['admin_notes'], 'acp_notes_updated' => time() ) );

			if( \IPS\Request::i()->isAjax() )
			{
				return (string) \IPS\DateTime::ts( \intval( \IPS\Settings::i()->acp_notes_updated ) );
			}
		}

		return $form->customTemplate( array( \IPS\Theme::i()->getTemplate( 'dashboard' ), 'adminnotes' ), ( isset( \IPS\Settings::i()->acp_notes_updated ) and \IPS\Settings::i()->acp_notes_updated ) ? (string) \IPS\DateTime::ts( \intval( \IPS\Settings::i()->acp_notes_updated ) ) : '' );
	}
}