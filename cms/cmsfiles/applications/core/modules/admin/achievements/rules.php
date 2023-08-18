<?php
/**
 * @brief		Achievement rules
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		23 Feb 2021
 */

namespace IPS\core\modules\admin\achievements;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Achievement rules
 */
class _rules extends \IPS\Node\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Node Class
	 */
	protected $nodeClass = 'IPS\core\Achievements\Rule';
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rules_manage' );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'achievements/achievements.css', 'core', 'admin' ) );
		\IPS\Output::i()->jsFiles  = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_members.js', 'core' ) );
		parent::execute();
	}
	
	/**
	 * Manage
	 *
	 * @return	void
	 */
	protected function manage()
	{
		$baseUrl = \IPS\Http\Url::internal('app=core&module=achievements&controller=rules');
		$perPage = 25;
		$totalCount = \IPS\Db::i()->select( 'COUNT(*)', 'core_achievements_rules' )->first();
		$totalPages = ceil( $totalCount / $perPage );
		$page = \IPS\Request::i()->page ?? 1;

		$query = \IPS\Db::i()->select( '*', 'core_achievements_rules', NULL, 'action, milestone ASC', [ ( $perPage * ( $page - 1 ) ), $perPage ] );
		$rules = new \IPS\Patterns\ActiveRecordIterator( $query, 'IPS\core\Achievements\Rule' );
		$pagination = $totalPages > 1 ? \IPS\Theme::i()->getTemplate( 'global', 'core', 'global' )->pagination( $baseUrl, $totalPages, $page, $perPage ) : '';
		
		if( \IPS\Request::i()->isAjax() )
		{
			\IPS\Output::i()->json([
				'rows'			=> \IPS\Theme::i()->getTemplate( 'achievements' )->rulesListRows( $rules ),
				'pagination'	=> $pagination
			]);
		}
		else
		{
			\IPS\Output::i()->sidebar['actions']['export'] = array(
				'primary' => false,
				'icon' => 'cloud-download',
				'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=rules&do=export'),
				'data'	=> [],
				'title' => 'acp_achievements_export',
			);

			if ( \IPS\Member::loggedIn()->hasAcpRestriction( 'core', 'achievements', 'rules_add' ) )
			{
				\IPS\Output::i()->sidebar['actions']['import'] = array(
					'primary' => false,
					'icon' => 'cloud-upload',
					'link' => \IPS\Http\Url::internal('app=core&module=achievements&controller=rules&do=importForm'),
					'data'	=> array( 'ipsDialog' => '', 'ipsDialog-title' => \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import') ),
					'title' => 'acp_achievements_import',
				);
			}


			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'achievements' )->rulesList( $rules, $pagination, $this->_getRootButtons() );
		}
	}
	
	/**
	 * Get form
	 *
	 * @param	\IPS\Helpers\Form	$form	The form as returned by _addEditForm()
	 * @return	string
	 */
	protected function _showForm( \IPS\Helpers\Form $form )
	{
		\IPS\Output::i()->hiddenElements = array('acpHeader');

		if ( \IPS\Request::i()->id )
		{

			\IPS\Dispatcher::i()->checkAcpPermission( 'rules_edit' );
			try
			{
				$form->hiddenValues['rule_enabled'] = \IPS\core\Achievements\Rule::load( \IPS\Request::i()->id )->enabled;
			}
			catch( \Exception $e ) { }
		}
		else
		{
			\IPS\Dispatcher::i()->checkAcpPermission( 'rules_add' );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('menu__core_achievements_rules');
		
		return $form->customTemplate( [ \IPS\Theme::i()->getTemplate( 'achievements' ), 'rulesForm' ] );
	}

	/**
	 * Toggle enabled status
	 *
	 * @return void
	 */
	protected function toggleEnabled()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rules_edit' );

		\IPS\Session::i()->csrfCheck();

		try
		{
			$rule = \IPS\core\Achievements\Rule::load( \IPS\Request::i()->id );
			$rule->enabled = (int) \IPS\Request::i()->enable;
			$rule->save();
			
			if ( \IPS\Request::i()->enable )
			{
				\IPS\Session::i()->log( 'acplogs__rule_enabled', array( $rule->id => FALSE ) );
			}
			else
			{
				\IPS\Session::i()->log( 'acplogs__rule_disabled', array( $rule->id => FALSE ) );
			}

			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=rules' ), 'updated' );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( 'node_error', '2T353/3', 404, '' ); /* @todo error code */
		}
	}

	/**
	 * Export rules with badges as an XML file (XML is better at potentially large values from raw image data)
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function export()
	{
		$xml = \IPS\Xml\SimpleXML::create('rules');
		$badgeMap = [];

		$langs = [];
		foreach( \IPS\Db::i()->select( 'word_key, word_default', 'core_sys_lang_words', [ 'lang_id=? AND (word_key LIKE \'core_award_subject_badge_%\' OR word_key LIKE \'core_award_other_badge_%\')', \IPS\Member::loggedIn()->language()->id ] ) as $row )
		{
			$langs[ $row['word_key'] ] = $row['word_default'];
		}

		/* Rules */
		foreach ( \IPS\Db::i()->select( '*', 'core_achievements_rules') as $row )
		{
			$forXml = [];
			foreach( $row as $k => $v )
			{
				if ( $k !== 'id' )
				{
					if ( $k == 'badge_subject' and $v )
					{
						if ( !isset( $badgeMap[$row['badge_subject']] ) )
						{
							$badgeMap[$row['badge_subject']] = uniqid();
						}

						$v = $badgeMap[$row['badge_subject']];
					}
					if ( $k == 'badge_other' and $v )
					{
						if ( !isset( $badgeMap[$row['badge_other']] ) )
						{
							$badgeMap[$row['badge_other']] = uniqid();
						}

						$v = $badgeMap[$row['badge_other']];
					}

					$forXml[$k] = $v;
				}
			}

			$forXml['award_subject_lang'] = $langs['core_award_subject_badge_' . $row['id']] ?? NULL;
			$forXml['award_other_lang'] = $langs['core_award_other_badge_' . $row['id']] ?? NULL;

			$xml->addChild( 'rule', $forXml );
		}

		/* Badges */
		if ( \count( $badgeMap ) )
		{
			$langs = [];
			foreach( \IPS\Db::i()->select('word_key, word_default', 'core_sys_lang_words', [ \IPS\Db::i()->like( 'word_key', 'core_badges_') ] ) as $row )
			{
				$langs[ \mb_substr( $row['word_key'], 12 ) ] = $row['word_default'];
			}

			foreach( \IPS\Db::i()->select('*', 'core_badges', [ \IPS\Db::i()->in( '`id`', array_keys( $badgeMap ) ) ] ) as $badge )
			{
				try
				{
					$icon = \IPS\File::get( 'core_Badges', $badge['image'] );

					$xml->addChild( 'badge', [
						'manually_awarded' => $badge['manually_awarded'],
						'id' => $badgeMap[ $badge['id'] ],
						'title' => $langs[ $badge['id'] ],
						'icon_name' => $icon->originalFilename,
						'icon_data' => base64_encode( $icon->contents() )
					] );
				}
				catch( \Exception $e ) { }
			}
		}
		
		\IPS\Session::i()->log( 'acplogs__rules_exported' );

		\IPS\Output::i()->sendOutput( $xml->asXML(), 200, 'application/xml', array( 'Content-Disposition' => \IPS\Output::getContentDisposition( 'attachment', "Achievement_Rules.xml" ) ) );
	}

	/**
	 * Import dialog
	 *
	 * @return void
	 */
	public function importForm()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rules_add' );
		$form = new \IPS\Helpers\Form( 'form', 'acp_achievements_import' );

		$form->addMessage( \IPS\Member::loggedIn()->language()->addToStack( 'acp_achievements_import_rules_message'), 'ipsMessage ipsMessage_info');
		$form->add( new \IPS\Helpers\Form\YesNo( 'acp_achievements_import_option_rule_wipe', 0, FALSE, [], NULL, NULL, NULL, 'acp_achievements_import_option_rule_wipe' ) );
		$form->add( new \IPS\Helpers\Form\Upload( 'acp_achievements_import_xml', NULL, FALSE, [ 'allowedFileTypes' => array( 'xml' ), 'temporary' => TRUE ], NULL, NULL, NULL, 'acp_achievements_import_xml' ) );

		if ( $values = $form->values() )
		{
			/* Move it to a temporary location */
			$tempFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPS' );
			move_uploaded_file( $values['acp_achievements_import_xml'], $tempFile );

			/* Initate a redirector */
			\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=rules&do=import' )->setQueryString( array('wipe' => $values['acp_achievements_import_option_rule_wipe'], 'file' => $tempFile, 'key' => md5_file( $tempFile )) )->csrf() );
		}

		/* Display */
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'global' )->block( \IPS\Member::loggedIn()->language()->addToStack('acp_achievements_import'), $form, FALSE );
	}

	/**
	 * Import from upload
	 *
	 * @return	void
	 */
	public function import()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'rules_add' );
		\IPS\Session::i()->csrfCheck();

		if ( !file_exists( \IPS\Request::i()->file ) or md5_file( \IPS\Request::i()->file ) !== \IPS\Request::i()->key )
		{
			\IPS\Output::i()->error( 'generic_error', '3C130/1', 500, '' );
		}

		try
		{
			\IPS\core\Achievements\Rule::importXml( \IPS\Request::i()->file, ( ! empty( \IPS\Request::i()->wipe ) ) );
		}
		catch( \Exception $e )
		{
			\IPS\Output::i()->error( $e->getMessage(), '2C424/1', 403, '' );
		}
		
		\IPS\Session::i()->log( 'acplogs__rules_imported' );

		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=achievements&controller=rules' ), 'completed' );
	}
}