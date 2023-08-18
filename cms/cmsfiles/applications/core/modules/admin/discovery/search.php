<?php
/**
 * @brief		Search settings
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		14 Apr 2014
 */

namespace IPS\core\modules\admin\discovery;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Search settings
 */
class _search extends \IPS\Dispatcher\Controller
{
	/**
	 * @brief	Has been CSRF-protected
	 */
	public static $csrfProtected = TRUE;
	
	/**
	 * Execute
	 *
	 * @return	void
	 */
	public function execute()
	{
		\IPS\Dispatcher::i()->checkAcpPermission( 'search_manage' );
		parent::execute();
	}

	/**
	 * Manage Settings
	 *
	 * @return	void
	 */
	protected function manage()
	{		
		/* Rebuild button */
		\IPS\Output::i()->sidebar['actions'] = array(
			'rebuildIndex'	=> array(
				'title'		=> 'search_rebuild_index',
				'icon'		=> 'undo',
				'link'		=> \IPS\Http\Url::internal( 'app=core&module=discovery&controller=search&do=queueIndexRebuild' )->csrf(),
				'data'		=> array( 'confirm' => '', 'confirmSubMessage' => \IPS\Member::loggedIn()->language()->get('search_rebuild_index_confirm') ),
			),
		);
		
		$form = new \IPS\Helpers\Form;
		$form->addHeader('search_method');
		$form->add( new \IPS\Helpers\Form\Radio( 'search_method', \IPS\Settings::i()->search_method, FALSE, array(
			'options' => array(
				'mysql'		=> 'search_method_mysql',
				'elastic'	=> 'search_method_elastic'
			),
			'toggles' => array(
				'mysql'		=> array( 'search_index_timeframe' ),
				'elastic'	=> array( 'search_elastic_server', 'search_elastic_index', 'search_elastic_analyzer', 'search_decay', 'search_elastic_self_boost', 'search_index_maxresults' )
			)
		) ) );
		$form->add( new \IPS\Helpers\Form\Url( 'search_elastic_server', \IPS\Settings::i()->search_elastic_server, NULL, array( 'placeholder' => 'http://localhost:9200' ), function( $val )
		{
			if( \IPS\Request::i()->search_method != 'elastic' )
			{
				return;
			}

			if( !( $val instanceof \IPS\Http\Url ) )
			{
				throw new \DomainException('form_url_error');
			}

			try
			{
				$response = \IPS\Content\Search\Elastic\Index::request( $val )->get()->decodeJson();
			}
			catch ( \Exception $e )
			{
				throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('search_elastic_server_error', FALSE, array( 'sprintf' => array( $e->getMessage() ) ) ) );
			}
			if ( !isset( $response['version']['number'] ) )
			{
				throw new \DomainException('search_elastic_server_no_version');
			}

			/* Open search is compatible up to at least version 2 */
			if( isset( $response['version']['distribution'] ) and $response['version']['distribution'] == 'opensearch' )
			{
				if ( version_compare( $response['version']['number'], '2.2', '>=' ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('search_opensearch_server_unsupported_version', FALSE, array( 'sprintf' => array( $response['version']['number'] ) ) ) );
				}
			}
			else
			{
				if ( version_compare( $response['version']['number'], \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION, '<' ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('search_elastic_server_unsupported_version', FALSE, array( 'sprintf' => array( \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION, $response['version']['number'] ) ) ) );
				}
				if ( version_compare( $response['version']['number'], \IPS\Content\Search\Elastic\Index::UNSUPPORTED_VERSION, '>=' ) )
				{
					throw new \DomainException( \IPS\Member::loggedIn()->language()->addToStack('search_elastic_server_unsupported_version_high', FALSE, array( 'sprintf' => array( \IPS\Content\Search\Elastic\Index::UNSUPPORTED_VERSION, $response['version']['number'] ) ) ) );
				}
			}
		}, NULL, NULL, 'search_elastic_server' ) );
		\IPS\Member::loggedIn()->language()->words['search_elastic_server_desc'] = sprintf( \IPS\Member::loggedIn()->language()->get('search_elastic_server_desc'), \IPS\Content\Search\Elastic\Index::MINIMUM_VERSION, \IPS\Content\Search\Elastic\Index::UNSUPPORTED_VERSION );
		$form->add( new \IPS\Helpers\Form\Text( 'search_elastic_index', \IPS\Settings::i()->search_elastic_index, NULL, array( 'regex' => '/^[A-Z0-9_]*$/i' ), NULL, NULL, NULL, 'search_elastic_index' ) );
		$form->add( new \IPS\Helpers\Form\Select( 'search_elastic_analyzer', \IPS\Settings::i()->search_elastic_analyzer, FALSE, array(
			'options' => array(
				'language'		=> array(
					'arabic'		=> 'elastic_analyzer_arabic',
					'armenian'		=> 'elastic_analyzer_armenian',
					'basque'		=> 'elastic_analyzer_basque',
					'brazilian'		=> 'elastic_analyzer_brazilian',
					'bulgarian' 	=> 'elastic_analyzer_bulgarian',
					'catalan'		=> 'elastic_analyzer_catalan',
					'cjk'			=> 'elastic_analyzer_cjk',
					'czech' 		=> 'elastic_analyzer_czech',
					'danish' 		=> 'elastic_analyzer_danish',
					'dutch' 		=> 'elastic_analyzer_dutch',
					'english' 		=> 'elastic_analyzer_english',
					'finnish' 		=> 'elastic_analyzer_finnish',
					'french' 		=> 'elastic_analyzer_french',
					'galician' 		=> 'elastic_analyzer_galician',
					'german' 		=> 'elastic_analyzer_german',
					'greek' 		=> 'elastic_analyzer_greek',
					'hindi' 		=> 'elastic_analyzer_hindi',
					'hungarian' 	=> 'elastic_analyzer_hungarian',
					'indonesian' 	=> 'elastic_analyzer_indonesian',
					'irish' 		=> 'elastic_analyzer_irish',
					'italian' 		=> 'elastic_analyzer_italian',
					'latvian' 		=> 'elastic_analyzer_latvian',
					'lithuanian' 	=> 'elastic_analyzer_lithuanian',
					'norwegian' 	=> 'elastic_analyzer_norwegian',
					'persian' 		=> 'elastic_analyzer_persian',
					'portuguese' 	=> 'elastic_analyzer_portuguese',
					'romanian' 		=> 'elastic_analyzer_romanian',
					'russian' 		=> 'elastic_analyzer_russian',
					'sorani' 		=> 'elastic_analyzer_sorani',
					'spanish' 		=> 'elastic_analyzer_spanish',
					'swedish' 		=> 'elastic_analyzer_swedish',
					'turkish' 		=> 'elastic_analyzer_turkish',
					'thai' 			=> 'elastic_analyzer_thai',
				),
				'other'			=> array(
					'standard'		=> 'elastic_analyzer_standard',
					'custom'		=> 'elastic_analyzer_custom',
				)
			),
			'toggles'	=> array(
				'custom'	=> array( 'search_elastic_custom_analyzer_row' )
			)
		), NULL, NULL, NULL, 'search_elastic_analyzer' ) );
		$form->add( new \IPS\Helpers\Form\Codemirror(
			'search_elastic_custom_analyzer',
			\IPS\Settings::i()->search_elastic_custom_analyzer ?: "\t\"analyzer\": {\n\t\t\"my_custom_analyzer\": {\n\t\t\t\"type\": \"custom\",\n\t\t\t\"char_filter\": [\n\t\t\t\t\"emoticons\" \n\t\t\t],\n\t\t\t\"tokenizer\": \"punctuation\", \n\t\t\t\"filter\": [\n\t\t\t\t\"lowercase\",\n\t\t\t\t\"english_stop\" \n\t\t\t]\n\t\t}\n\t},\n\t\"tokenizer\": {\n\t\t\"punctuation\": { \n\t\t\t\"type\": \"pattern\",\n\t\t\t\"pattern\": \"[ .,!?]\"\n\t\t}\n\t},\n\t\"char_filter\": {\n\t\t\"emoticons\": { \n\t\t\t\"type\": \"mapping\",\n\t\t\t\"mappings\": [\n\t\t\t\t\":) => _happy_\",\n\t\t\t\t\":( => _sad_\"\n\t\t\t]\n\t\t}\n\t},\n\t\"filter\": {\n\t\t\"english_stop\": { \n\t\t\t\"type\": \"stop\",\n\t\t\t\"stopwords\": \"_english_\"\n\t\t}\n\t}",
			NULL,
			array( 'mode' => 'javascript' ),
			function( $val ) {
				$json = json_decode( '{' . $val . '}', TRUE );
				if ( $json === NULL or !isset( $json['analyzer'] ) or \count( $json['analyzer'] ) !== 1 )
				{
					throw new \DomainException( 'search_elastic_custom_analyzer_error' );
				}
			},
			'<code>"analysis": {</code>',
			'<code>}</code>',
			'search_elastic_custom_analyzer_row'
		) );
		$form->addHeader('search_options');
		$form->add( new \IPS\Helpers\Form\Radio( 'search_default_operator', \IPS\Settings::i()->search_default_operator, FALSE, array( 'options' => array(
			'or'	=> 'search_default_operator_or',
			'and'	=> 'search_default_operator_and',
		) ), NULL, \IPS\Member::loggedIn()->language()->addToStack('search_default_operator_prefix') ) );
		$form->add( new \IPS\Helpers\Form\Number( 'search_title_boost', \IPS\Settings::i()->search_title_boost, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'search_title_boost_unlimited' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('search_title_boost_prefix'), \IPS\Member::loggedIn()->language()->addToStack('search_title_boost_suffix'), 'search_title_boost' ) );
		$form->add( new \IPS\Helpers\Form\Custom( 'search_decay', array( \IPS\Settings::i()->search_decay_days, \IPS\Settings::i()->search_decay_factor ), FALSE, array(
			'getHtml' => function( $field ) {
				return \IPS\Theme::i()->getTemplate( 'settings' )->searchDecay( isset( $field->value[0] ) ? $field->value[0] : 0, isset( $field->value[1] ) ? $field->value[1] : 0 );
			}
		), NULL, NULL, NULL, 'search_decay' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'search_elastic_self_boost', \IPS\Settings::i()->search_elastic_self_boost, FALSE, array( 'unlimited' => \floatval( 0 ), 'unlimitedLang' => 'do_not_boost', 'decimals' => 1 ), NULL, \IPS\Member::loggedIn()->language()->addToStack('search_elastic_self_boost_prefix'), \IPS\Member::loggedIn()->language()->addToStack('search_elastic_self_boost_suffix'), 'search_elastic_self_boost' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'search_index_timeframe', \IPS\Settings::i()->search_index_timeframe, FALSE, array( 'unlimited' => 0, 'unlimitedLang' => 'search_index_timeframe_unlimited' ), NULL, \IPS\Member::loggedIn()->language()->addToStack('search_index_timeframe_prefix'), \IPS\Member::loggedIn()->language()->addToStack('search_index_timeframe_suffix'), 'search_index_timeframe' ) );
		$form->add( new \IPS\Helpers\Form\Number( 'search_index_maxresults', \IPS\Settings::i()->search_index_maxresults, FALSE, array(), NULL, NULL, NULL, 'search_index_maxresults' ) );


		$form->addHeader('search_logs');
		$groups	= array_combine( array_keys( \IPS\Member\Group::groups( TRUE, TRUE ) ), array_map( function( $_group ) { return (string) $_group; }, \IPS\Member\Group::groups( TRUE, TRUE ) ) );
		$form->add( new \IPS\Helpers\Form\CheckboxSet( 'searchlog_exclude_groups', json_decode( \IPS\Settings::i()->searchlog_exclude_groups, TRUE ), FALSE, array( 'options' => $groups, 'multiple' => true ), NULL, NULL, NULL, 'searchlog_exclude_groups' ) );

		if ( $values = $form->values() )
		{
			$engine = \IPS\Settings::i()->search_method;
			$indexPrune = \IPS\Settings::i()->search_index_timeframe;
			$analyzer = \IPS\Settings::i()->search_elastic_analyzer;
			$customAnalyzer = \IPS\Settings::i()->search_elastic_custom_analyzer;
			$maxResults = \IPS\Settings::i()->search_index_maxresults;
			
			if ( isset( $values['search_decay'][2] ) )
			{
				$values['search_decay_days'] = 0;
				$values['search_decay_factor'] = 0;
			}
			else
			{
				$values['search_decay_days'] = $values['search_decay'][0];
				$values['search_decay_factor'] = $values['search_decay'][1];
			}
			unset( $values['search_decay'] );
			
			if( $engine != $values['search_method'] )
			{
				try
				{
					\IPS\Content\Search\Index::i()->prune();
				}
				catch( \Exception $e )
				{
					\IPS\Log::log( $e, 'search_index_prune' );
				}
			}

			$values['searchlog_exclude_groups'] = json_encode( $values['searchlog_exclude_groups'] );

			/* Go ahead and save... */
			$form->saveAsSettings( $values );
			\IPS\Session::i()->log( 'acplogs__search_settings' );
			
			/* And re-index if setting updated */
			if( $engine != $values['search_method'] or ( $values['search_method'] == 'elastic' and ( $values['search_elastic_analyzer'] != $analyzer or ( $values['search_elastic_analyzer'] == 'custom' and $values['search_elastic_custom_analyzer'] != $customAnalyzer ) or $values['search_index_maxresults'] != $maxResults ) ) )
			{
				/* We pass TRUE to the i() method to ensure we get a new instance, otherwise the old instance cached from the previous prune call will be used */
				\IPS\Content\Search\Index::i( TRUE )->init();
				\IPS\Content\Search\Index::i()->rebuild();
			}
			elseif( $indexPrune != $values['search_index_timeframe'] )
			{				
				\IPS\Content\Search\Index::i()->rebuild();
			}
		}
		
		\IPS\Output::i()->title		= \IPS\Member::loggedIn()->language()->addToStack('menu__core_discovery_search');
		\IPS\Output::i()->output	.= \IPS\Theme::i()->getTemplate( 'global' )->block( 'menu__core_discovery_search', $form );
	}
	
	/**
	 * Queue an index rebuild
	 *
	 * @return	void
	 */
	protected function queueIndexRebuild()
	{
		\IPS\Session::i()->csrfCheck();
		
		/* Clear MySQL minimum word length cached value */
		unset( \IPS\Data\Store::i()->mysqlMinWord );
		unset( \IPS\Data\Store::i()->mysqlMaxWord );

		\IPS\Content\Search\Index::i()->rebuild();
	
		\IPS\Session::i()->log( 'acplogs__queued_search_index' );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal( 'app=core&module=discovery&controller=search' ), 'search_index_rebuilding' );
	}
}