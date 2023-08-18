<?php
/**
 * @brief		Donation Goal Node
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Nexus
 * @since		17 Jun 2014
 */

namespace IPS\nexus\Donation;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Donation Goal Node
 */
class _Goal extends \IPS\Node\Model
{
	/**
	 * @brief	[ActiveRecord] Multiton Store
	 */
	protected static $multitons;
	
	/**
	 * @brief	[ActiveRecord] Database Table
	 */
	public static $databaseTable = 'nexus_donate_goals';
	
	/**
	 * @brief	[ActiveRecord] Database Prefix
	 */
	public static $databasePrefix = 'd_';
		
	/**
	 * @brief	[Node] Order Database Column
	 */
	public static $databaseColumnOrder = 'position';
		
	/**
	 * @brief	[Node] Node Title
	 */
	public static $nodeTitle = 'donation_goals';
	
	/**
	 * @brief	[Node] Title prefix.  If specified, will look for a language key with "{$key}_title" as the key
	 */
	public static $titleLangPrefix = 'nexus_donategoal_';

	/**
	 * @brief	[Node] Description suffix.  If specified, will look for a language key with "{$titleLangPrefix}_{$id}_{$descriptionLangSuffix}" as the key
	 */
	public static $descriptionLangSuffix = '_desc';

	/**
	 * @brief	[Node] ACP Restrictions
	 * @code
	 	array(
	 		'app'		=> 'core',				// The application key which holds the restrictrions
	 		'module'	=> 'foo',				// The module key which holds the restrictions
	 		'map'		=> array(				// [Optional] The key for each restriction - can alternatively use "prefix"
	 			'add'			=> 'foo_add',
	 			'edit'			=> 'foo_edit',
	 			'permissions'	=> 'foo_perms',
	 			'delete'		=> 'foo_delete'
	 		),
	 		'all'		=> 'foo_manage',		// [Optional] The key to use for any restriction not provided in the map (only needed if not providing all 4)
	 		'prefix'	=> 'foo_',				// [Optional] Rather than specifying each  key in the map, you can specify a prefix, and it will automatically look for restrictions with the key "[prefix]_add/edit/permissions/delete"
	 * @endcode
	 */
	protected static $restrictions = array(
		'app'		=> 'nexus',
		'module'	=> 'payments',
		'all'		=> 'donationgoals_manage'
	);
	
	/**
	 * [Node] Add/Edit Form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form
	 * @return	void
	 */
	public function form( &$form )
	{
		$form->add( new \IPS\Helpers\Form\Translatable( 'd_name', NULL, TRUE, array( 'app' => 'nexus', 'key' => $this->id ? "nexus_donategoal_{$this->id}" : NULL ) ) );
		$form->add( new \IPS\Helpers\Form\Translatable( 'd_desc', NULL, FALSE, array(
			'app' => 'nexus',
			'key' => $this->id ? "nexus_donategoal_{$this->id}_desc" : NULL,
			'editor'	=> array(
				'app'			=> 'nexus',
				'key'			=> 'Admin',
				'autoSaveKey'	=> ( $this->id ? "nexus-donategoal-{$this->id}" : "nexus-new-donategoal" ),
				'attachIds'		=> $this->id ? array( $this->id, NULL, 'donategoal' ) : NULL, 'minimize' => 'd_desc_placeholder'
			)
		), NULL, NULL, NULL, 'd_desc_editor' ) );
				
		if ( \count( \IPS\nexus\Money::currencies() ) > 1 and !$this->current )
		{
			$form->add( new \IPS\Helpers\Form\Radio( 'd_currency', $this->currency ?: \IPS\nexus\Customer::loggedIn()->defaultCurrency(), TRUE, array(
				'options' => array_combine( \IPS\nexus\Money::currencies(), \IPS\nexus\Money::currencies() ),
			) ) );
		}
				
		$form->add( new \IPS\Helpers\Form\Number( 'd_goal', $this->goal, FALSE, array( 'unlimited' => (float) 0, 'unlimitedLang' => 'd_goal_none', 'decimals' => TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\Number( 'd_current', $this->current, FALSE, array( 'decimals' => TRUE ) ) );
		
	}
	
	/**
	 * [Node] Format form values from add/edit form for save
	 *
	 * @param	array	$values	Values from the form
	 * @return	array
	 */
	public function formatFormValues( $values )
	{
		if ( !$this->id )
		{
			$this->currency = \IPS\nexus\Customer::loggedIn()->defaultCurrency();
			$this->save();
			\IPS\File::claimAttachments( 'nexus-new-donategoal', $this->id, NULL, 'donategoal', TRUE );
		}

		if( isset( $values['d_name'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_donategoal_{$this->id}", $values['d_name'] );

			/* Save the SEO name */
			$this->name_seo = \IPS\Http\Url\Friendly::seoTitle( $values[ 'd_name' ][ \IPS\Lang::defaultLanguage() ] );
			$this->save();

			unset( $values['d_name'] );
		}

		if( isset( $values['d_desc'] ) )
		{
			\IPS\Lang::saveCustom( 'nexus', "nexus_donategoal_{$this->id}_desc", $values['d_desc'] );
			unset( $values['d_desc'] );
		}
		
		if ( !isset( $values['d_currency'] ) )
		{
			$values['d_currency'] = \IPS\nexus\Customer::loggedIn()->defaultCurrency();
		}
		
		return $values;
	}
		
	/**
	 * [ActiveRecord] Save Changed Columns
	 *
	 * @return	void
	 */
	public function save()
	{
		parent::save();
		\IPS\Widget::deleteCaches( 'donations', 'nexus' );
		static::recountDonationGoals();
	}
	
	/**
	 * [ActiveRecord] Delete
	 *
	 * @return	void
	 */
	public function delete()
	{
		parent::delete();
		static::recountDonationGoals();
	}
	
	/**
	 * Recount card storage gateays
	 *
	 * @return	void
	 */
	protected static function recountDonationGoals()
	{
		$count = \count( static::roots() );
		\IPS\Settings::i()->changeValues( array( 'donation_goals' => $count ) );
	}

	/**
	 * @brief	Generated URL storage
	 */
	protected $_url;
	
	/**
	 * Get URL
	 *
	 * @param	string|null	$action	Action
	 * @return	\IPS\Http\Url
	 */
	public function url( $action=NULL )
	{
		/* self-heal missing seo titles */
		if( $this->name_seo === null )
		{
			$language = \IPS\Lang::load( \IPS\Lang::defaultLanguage() );
			$this->name_seo = \IPS\Http\Url\Friendly::seoTitle( $language->get( 'nexus_donategoal_' . $this->_id ) );
			$this->save();
		}

		if( $this->_url === null )
		{
			$this->_url = \IPS\Http\Url::internal( 'app=nexus&module=clients&controller=donations&id=' . $this->_id, 'front', 'clientsdonate', array( $this->name_seo ) );
		}

		return $this->_url;
	}
}