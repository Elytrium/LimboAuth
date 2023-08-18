<?php
/**
 * @brief		Payment Gateways
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		10 Feb 2014
 */

namespace IPS\nexus\modules\admin\payments;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Payment Gateways
 */
class _gateways extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\Gateway';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'gateways_manage' );
		parent::execute();
	}
	
	/**
	 * Get Root Buttons
	 *
	 * @return	array
	 */
	public function _getRootButtons()
	{
		$buttons = parent::_getRootButtons();
		
		if ( isset( $buttons['add'] ) )
		{
			$buttons['add']['link'] = $buttons['add']['link']->setQueryString( '_new', TRUE );
		}
		
		return $buttons;
	}
	
	/**
	 * Add/Edit Form
	 *
	 * @return void
	 */
	protected function form()
	{
		if ( \IPS\Request::i()->id )
		{
			return parent::form();
		}
		else
		{
			if ( \IPS\IN_DEV )
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/moxie.js', 'core', 'interface' ) );
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.dev.js', 'core', 'interface' ) );
			}
			else
			{
				\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'plupload/plupload.full.min.js', 'core', 'interface' ) );
			}
			\IPS\Output::i()->output = (string) new \IPS\Helpers\Wizard( array(
				'gateways_gateway'	=> function( $data )
				{
					$options = array();
					foreach ( \IPS\nexus\Gateway::gateways() as $key => $class )
					{
						/* Deprecate Authorize.net, Braintree and 2Checkout don't allow them to be used for new gateways */
						if( \in_array( $key, array( 'AuthorizeNet', 'TwoCheckout', 'Braintree' ) ) )
						{
							continue;
						}
						$options[ $key ] = 'gateway__' . $key;
					}

					$form = new \IPS\Helpers\Form;
					$form->add( new \IPS\Helpers\Form\Radio( 'gateways_gateway', TRUE, NULL, array( 'options' => $options ) ) );
					if ( $values = $form->values() )
					{
						return array( 'gateway' => $values['gateways_gateway'] );
					}
					return $form;
				},
				'gateways_details'	=> function( $data )
				{
					$form = new \IPS\Helpers\Form('gw');
					$class = \IPS\nexus\Gateway::gateways()[ $data['gateway'] ];
					$obj = new $class;
					$obj->gateway = $data['gateway'];
					$obj->active = TRUE;
					$obj->form( $form );
					if ( $values = $form->values() )
					{

						$settings = array();
						foreach ( $values as $k => $v )
						{
							if ( $k !== 'paymethod_name' AND $k !== 'paymethod_countries' )
							{
								$settings[ mb_substr( $k, mb_strlen( $data['gateway'] ) + 1 ) ] = $v;
							}
						}
						try
						{
							$settings = $obj->testSettings( $settings );
						}
						catch ( \InvalidArgumentException $e )
						{
							$form->error = $e->getMessage();
							return $form;
						}
						
						$name = $values['paymethod_name'];
						$values = $obj->formatFormValues( $values );
						$obj->settings = json_encode( $settings );
						$obj->countries = $values['countries'];
						if( isset(  $values['validationfile'] ) )
						{
							$obj->validationfile = $values['validationfile'];
						}

						$obj->save();
						\IPS\Lang::saveCustom( 'nexus', "nexus_paymethod_{$obj->id}", $name );
						\IPS\Session::i()->log( 'acplogs__nexus_added_gateway', array( "nexus_paymethod_{$obj->id}" => TRUE ) );

						\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=payments&controller=paymentsettings&tab=gateways') );
					}
					return $form;
				}
			), \IPS\Http\Url::internal('app=nexus&module=payments&controller=paymentsettings&tab=gateways&do=form') );
		}
	}

	/**
	 * Delete
	 *
	 * @return	void
	 */
	protected function delete()
	{
		/* Get node */
		$nodeClass = $this->nodeClass;
		if ( \IPS\Request::i()->subnode )
		{
			$nodeClass = $nodeClass::$subnodeClass;
		}
		
		try
		{
			$node = $nodeClass::load( \IPS\Request::i()->id );
		}
		catch ( \OutOfRangeException $e )
		{
			\IPS\Output::i()->error( 'node_error', '2X411/1', 404, '' );
		}
		 
		/* Permission check */
		if( !$node->canDelete() )
		{
			\IPS\Output::i()->error( 'node_noperm_delete', '2X411/2', 403, '' );
		}

		if( $node->hasActiveBillingAgreements() )
		{
			/* Make sure the user confirmed the deletion */
			\IPS\Request::i()->confirmedDelete();
			
			\IPS\Task::queue( 'nexus', 'DeletePaymentMethod', array( 'id' => $node->id ), 3, array( 'id' ) );
			\IPS\Session::i()->log( 'acplog__node_deleted_c', array( $this->title => TRUE, $node->titleForLog() => FALSE ) );
			\IPS\Output::i()->redirect( $this->url->setQueryString( array( 'root' => ( $node->parent() ? $node->parent()->_id : '' ) ) ), 'deleted' );
		}
		else
		{
			return parent::delete();
		}
	}
}