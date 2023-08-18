<?php
/**
 * @brief		fields
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		01 May 2014
 */

namespace IPS\nexus\modules\admin\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * fields
 */
class _fields extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Package\CustomField';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'package_fields_manage' );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'forms', 'core' )->blurb( 'custom_package_fields_blurb' );
		return parent::manage();
	}
	
	/**
	 * Warning about unconsecutive rules
	 *
	 * @return	void
	 */
	protected function warning()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'store' )->productOptionsChanged(
			new \IPS\Patterns\ActiveRecordIterator(
				\IPS\Db::i()->select( '*', 'nexus_packages', \IPS\Db::i()->in( 'p_id', explode( ',', \IPS\Request::i()->ids ) ) ),
				'IPS\nexus\Package'
			)
		);
	}	
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		if ( $old AND $old->extra != $new->extra )
		{
			$products = \IPS\Db::i()->select( 'DISTINCT(opt_package)', 'nexus_product_options', "opt_values LIKE '%\"{$new->_id}\":%'" );
			if ( \count( $products ) )
			{
				\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'do' => 'warning', 'ids' => implode( ',', iterator_to_array( $products ) ) ) ) );
			}
		}
		
		parent::_afterSave( $old, $new, $lastUsedTab );
	}
}