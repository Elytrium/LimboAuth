<?php
/**
 * @brief		ACP Member Profile: Tabbed Block
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		22 Nov 2017
 */

namespace IPS\core\MemberACPProfile;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * @brief	ACP Member Profile: Tabbed Block
 */
abstract class _TabbedBlock extends Block
{
	/**
	 * Get Output
	 *
	 * @return	string
	 */
	public function output()
	{
		$tabs = $this->tabs();
		if ( !\count( $tabs ) )
		{
			return '';
		} 
		$tabKeys = array_keys( $tabs );
		
		$exploded = explode( '\\', \get_called_class() );
		$tabParam = $exploded[1] . '_' . $exploded[5];
		$activeTabKey = ( isset( \IPS\Request::i()->block[$tabParam] ) and array_key_exists( \IPS\Request::i()->block[$tabParam], $tabs ) ) ? \IPS\Request::i()->block[$tabParam] : array_shift( $tabKeys );
		
		return \IPS\Theme::i()->getTemplate('memberprofile')->tabbedBlock( $this->member, $tabParam, $this->blockTitle(), $tabs, $activeTabKey, $this->tabOutput( $activeTabKey ), $this->showEditLink() ? $this->editLink() : NULL );
	}
	
	/**
	 * Show Edit Link?
	 *
	 * @return	bool
	 */
	protected function showEditLink()
	{
		return false;
	}
	
	/**
	 * Edit Link
	 *
	 * @return	bool
	 */
	protected function editLink()
	{
		return \IPS\Http\Url::internal("app=core&module=members&controller=members&do=editBlock")->setQueryString( array(
			'block'	=> \get_called_class(),
			'id'	=> $this->member->member_id
		) );
	}
	
	/**
	 * Get Block Title
	 *
	 * @return	string
	 */
	public function blockTitle()
	{
		return NULL;
	}
	
	/**
	 * Get Tab Names
	 *
	 * @return	string
	 */
	abstract public function tabs();
	
	/**
	 * Get output
	 *
	 * @return	string
	 */
	abstract public function tabOutput( $tab );
}