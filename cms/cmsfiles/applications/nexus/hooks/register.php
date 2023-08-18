//<?php

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	exit;
}

class nexus_hook_register extends _HOOK_CLASS_
{
	/**
	 * Register
	 *
	 * @return	void
	 */
	protected function manage()
	{
		if ( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'nexus', 'store' ) ) and ( \IPS\Settings::i()->nexus_reg_force or !isset( \IPS\Request::i()->noPurchase ) ) and \IPS\nexus\Package::haveRegistrationProducts() )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=store&controller=store&do=register', 'front', 'store' ) );
		}
		else if ( \IPS\Member::loggedIn()->canAccessModule( \IPS\Application\Module::get( 'nexus', 'subscriptions' ) ) and ( \IPS\Settings::i()->nexus_subs_register ) )
		{
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=nexus&module=subscriptions&controller=subscriptions&register=1', 'front', 'nexus_subscriptions' ) );
		}
		
		return parent::manage();
	}
}