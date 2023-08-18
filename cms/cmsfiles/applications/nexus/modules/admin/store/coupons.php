<?php
/**
 * @brief		Coupons
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		05 May 2014
 */

namespace IPS\nexus\modules\admin\store;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * coupons
 */
class _coupons extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Coupon';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'coupons_manage' );
		parent::execute();
	}
	
	/**
	 * View Uses
	 *
	 * @return	void
	 */
	public function viewUses()
	{
		try
		{
			$coupon = \IPS\nexus\Coupon::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X234/1', 404, '' );
		}
		
		$data = array();
		$usedBy = json_decode( $coupon->used_by, TRUE );
		if ( $usedBy )
		{
			foreach ( $usedBy as $member => $uses )
			{
				$data[] = array( 'coupon_customer' => $member, 'coupon_uses' => $uses );
			}
		}

				
		$table = new \IPS\Helpers\Table\Custom( $data, \IPS\Http\Url::internal("app=nexus&module=store&controller=coupons&do=viewUses&id={$coupon->id}") );
		$table->parsers = array(
			'coupon_customer'	=> function ( $val )
			{
				return \is_numeric( $val ) ? \IPS\Theme::i()->getTemplate('global')->userLink( \IPS\Member::load( $val ) ) : $val;
			}
		);
		$table->sortBy = 'coupon_uses';
		$table->noSort = array( 'coupon_customer' );
		
		\IPS\Output::i()->title = $coupon->code;
		\IPS\Output::i()->output = (string) $table;
	}
}