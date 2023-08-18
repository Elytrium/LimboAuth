//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class nexus_hook_DispatcherAdmin extends _HOOK_CLASS_
{
	public function finish()
	{
        if( !\IPS\Request::i()->isAjax() )
		{
            $dismiss = [];
            $updated = false;
            if ( isset( \IPS\Request::i()->cookie['acpDeprecations'] ) )
			{
                $dismiss = json_decode( \IPS\Request::i()->cookie['acpDeprecations'], TRUE );

                if ( ! is_array( $dismiss ) )
				{
                    $dismiss = [];
                }
            }

            if ( isset( \IPS\Request::i()->deprecationDismiss ) )
            {
                $updated = true;
                $dismiss[ \IPS\Request::i()->deprecationDismiss ] = \IPS\DateTime::create()->add( new \DateInterval( 'P1M' ) )->getTimestamp();
            }


            foreach ( $dismiss as $key => $value )
            {
                if ( $value < time() )
                {
                    $updated = true;
                    unset( $dismiss[ $key ] );
                }
            }

            if ( $updated )
			{
                \IPS\Request::i()->setCookie( 'acpDeprecations', json_encode( $dismiss ), \IPS\DateTime::create()->add( new \DateInterval( 'P1Y' ) ) );
            }

			if ( isset( \IPS\Request::i()->deprecationDismiss ) )
			{
                \IPS\Output::i()->redirect( \IPS\Request::i()->url()->stripQueryString( 'deprecationDismiss' ) );
            }

			if( $this->module->application == 'nexus' AND $this->module->key == 'support' and ! in_array( 'support', array_keys( $dismiss ) ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('nexus_support_deprecated', NULL, [ 'sprintf' => [ \IPS\Request::i()->url()->setQueryString('deprecationDismiss', 'support') ] ] ), 'warning' ) . \IPS\Output::i()->output;
			}

			if( $this->module->application == 'nexus' AND ( ( $this->module->key == 'payments' AND $this->controller == 'shipping' ) OR ( $this->module->key == 'store' AND $this->controller == 'packages' ) ) and ! in_array( 'physical', array_keys( $dismiss ) ) )
			{
				\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( \IPS\Member::loggedIn()->language()->addToStack('nexus_physical_deprecated', NULL, [ 'sprintf' => [ \IPS\Request::i()->url()->setQueryString('deprecationDismiss', 'physical') ] ] ), 'warning' ) . \IPS\Output::i()->output;
			}

			if( $this->module->application == 'nexus' AND ( $this->module->key == 'payments' AND $this->controller == 'paymentsettings' ) )
			{
				foreach ( \IPS\nexus\Gateway::roots() as $gateway )
				{
					if( \in_array( $gateway->gateway, array( 'TwoCheckout', 'Braintree' ) ) AND $gateway->active )
					{
						\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global', 'core' )->message( 'nexus_gateways_deprecated', 'warning' ) . \IPS\Output::i()->output;
						break;
					}
				}
			}
        }
        
		return parent::finish();
	}
}
