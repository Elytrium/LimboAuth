<?php
/**
 * @brief		Upgrader: Confirm
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		20 May 2014
 */
 
namespace IPS\core\modules\setup\upgrade;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Upgrader: Confirm
 */
class _confirm extends \IPS\Dispatcher\Controller
{
	/**
	 * Show Form
	 *
	 * @return	void
	 */
	public function manage()
	{
		/* reset a few things */
		$_SESSION['lastJsonIndex'] = 0;
		$_SESSION['lastSqlError']  = NULL;
		$_SESSION['sqlFinished']   = array();

		/* Create our temporary upgrade data storage table */
		if( !\IPS\Db::i()->checkForTable( 'upgrade_temp' ) )
		{
			\IPS\Db::i()->createTable( array(
				'name'		=> 'upgrade_temp',
				'columns'	=> array(
					'id' => array(
						'name'			=> 'id',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'auto_increment'	=> true
					),
					'upgrade_data' => array(
						'name'			=> 'upgrade_data',
						'type'			=> 'mediumtext',
						'length'		=> null,
						'allow_null'	=> true,
						'default'		=> null
					),
					'lastaccess' => array(
						'name'			=> 'lastaccess',
						'type'			=> 'int',
						'length'		=> 10,
						'allow_null'	=> false,
						'default'		=> 0
					),
				),
				'indexes'	=> array(
					'PRIMARY' => array(
						'type'		=> 'primary',
						'name'		=> 'PRIMARY',
						'columns'	=> array( 'id' ),
						'length'	=> array( NULL )
					),
				)
			)	);
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('confirmpage');
		\IPS\Output::i()->output 	= \IPS\Theme::i()->getTemplate( 'global' )->confirm();
	}
}