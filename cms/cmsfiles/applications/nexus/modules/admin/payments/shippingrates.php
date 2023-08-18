<?php
/**
 * @brief		Shipping Rates
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		13 Feb 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Shipping
 */
class _shippingrates extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Shipping\FlatRate';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'shipmethods_manage' );
		parent::execute();
	}
	
	/**
	 * Warning about unconsecutive rules
	 *
	 * @return	void
	 */
	protected function warning()
	{
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->decision( 'ship_rates_' . \IPS\Request::i()->type, array(
			'ship_rates_go_back'		=> \IPS\Http\Url::internal( 'app=nexus&module=payments&controller=shippingrates&do=form&id=' . \IPS\Request::i()->id ),
			'ship_rates_save_anyway'	=> $this->url,
		) );
	}
	
	/**
	 * Redirect after save
	 *
	 * @param	\IPS\Node\Model|NULL	$old			A clone of the node as it was before or NULL if this is a creation
	 * @param	\IPS\Node\Model	$new			The node now
	 * @param	string			$lastUsedTab	The tab last used in the form
	 * @return	void
	 */
	protected function _afterSave( ?\IPS\Node\Model $old, \IPS\Node\Model $new, $lastUsedTab = FALSE )
	{
		$haveNoLowerLimit = FALSE;
		$haveNoUpperLimit = FALSE;
		$lastUpper = NULL;
		$consecutive = TRUE;

		foreach ( json_decode( $new->rates, TRUE ) as $rate )
		{
			if ( !$haveNoLowerLimit )
			{
				if ( $rate['min'] === '*' )
				{
					$haveNoLowerLimit = TRUE;
				}
				elseif ( \is_array( $rate['min'] ) )
				{
					$allAreZero = TRUE;
					foreach ( $rate['min'] as $val )
					{
						if ( (int) $val !== 0 )
						{
							$allAreZero = FALSE;
						}
					}
					
					if ( $allAreZero )
					{
						$haveNoLowerLimit = TRUE;
					}
				}
				elseif ( (int) $rate['min'] === 0 )
				{
					$haveNoLowerLimit = TRUE;
				}
			}
						
			if ( !$haveNoUpperLimit and $rate['max'] === '*' )
			{
				$haveNoUpperLimit = TRUE;
			}
			
			if ( $lastUpper !== NULL )
			{
				if ( \is_array( $lastUpper ) )
				{
					foreach ( $lastUpper as $k => $v )
					{
						if ( \strval( $rate['min'][ $k ] - $v ) >= 0.02 ) # @note: We use strval() here to prevent PHP from applying a precision to the float, inadvertently causing this to return TRUE
						{
							$consecutive = FALSE;
						}
					}
				}
				elseif ( \strval( $rate['min'] - $lastUpper ) >= 0.02 ) # @note: We use strval() here to prevent PHP from applying a precision to the float, inadvertently causing this to return TRUE
				{
					$consecutive = FALSE;
				}
			}
			$lastUpper = $rate['max'];
		}
				
		if( !$haveNoLowerLimit )
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'do' => 'warning', 'type' => 'missing_lower', 'id' => $new->_id ) ) );
		}
		elseif( !$haveNoUpperLimit )
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'do' => 'warning', 'type' => 'missing_upper', 'id' => $new->_id ) ) );
		}
		elseif ( !$consecutive )
		{
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'do' => 'warning', 'type' => 'unconsecutive', 'id' => $new->_id ) ) );
		}
		else
		{
			parent::_afterSave( $old, $new, $lastUsedTab );
		}
	}
}