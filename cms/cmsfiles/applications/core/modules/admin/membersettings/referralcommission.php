<?php
/**
 * @brief		referralcommission
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community

 * @since		30 Sep 2019
 */

namespace IPS\core\modules\admin\membersettings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * referralcommission
 */
class _referralcommission extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\nexus\CommissionRule';

	/**
	 * Show the "add" button in the page root rather than the table root
	 */
	protected $_addButtonInRoot = FALSE;

	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'commission_rules_manage' );
		parent::execute();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function manage()
	{
		\IPS\Output::i()->sidebar['actions'][] = array(
			'icon'	=> 'cog',
			'title'	=> 'commission_settings',
			'link'	=> \IPS\Http\Url::internal( "app=nexus&module=customers&controller=referrals&do=settings" )
		);

		parent::manage();
	}

	/**
	 * Manage
	 *
	 * @return	void
	 */
	public function settings()
	{
		$form = new \IPS\Helpers\Form;
		$form->add( new \IPS\Helpers\Form\YesNo( 'nexus_com_rules', \IPS\Settings::i()->nexus_com_rules, FALSE, array( 'togglesOff' => array( 'nexus_com_rules_alt' ) ), NULL, NULL, NULL, 'nexus_com_rules' ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'nexus_com_rules_alt', NULL, FALSE, array( 'app' => 'nexus', 'key' => 'nexus_com_rules_val', 'editor' => array( 'app' => 'core', 'key' => 'Admin', 'autoSaveKey' => 'nexus_com_rules_alt', 'attachIds' => array( NULL, NULL, 'nexus_com_rules_alt' ) ) ), NULL, NULL, NULL, 'nexus_com_rules_alt' ) );

		if ( $values = $form->values() )
		{
			\IPS\Lang::saveCustom( 'nexus', 'nexus_com_rules_val', $values['nexus_com_rules_alt'] );
			unset( $values['nexus_com_rules_alt'] );

			$form->saveAsSettings( $values );

			/* Clear guest page caches */
			\IPS\Data\Cache::i()->clearAll();
			
			\IPS\Session::i()->log( 'acplog__referral_commission_settings_edited' );

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=nexus&module=customers&controller=referrals'), 'saved' );
		}

		\IPS\Output::i()->output = $form;

	}
}