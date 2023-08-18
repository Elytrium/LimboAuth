<?php
/**
 * @brief		Marketplace Controller
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @since		27 Sep 2019
 */

namespace IPS\core\modules\admin\marketplace;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !\defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Marketplace Controller
 */
class _marketplace extends \IPS\Dispatcher\Controller
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
		\IPS\Output::i()->jsFiles = array_merge( \IPS\Output::i()->jsFiles, \IPS\Output::i()->js( 'admin_marketplace.js', 'core', 'admin' ) );
		\IPS\Output::i()->cssFiles = array_merge( \IPS\Output::i()->cssFiles, \IPS\Theme::i()->css( 'marketplace/marketplace.css', 'core', 'admin' ) );
		\IPS\Dispatcher::i()->checkAcpPermission( 'marketplace_manage' );
		parent::execute();
	}

	/**
	 * Fetch category tree from API or Cache
	 *
	 * @return	array
	 */
	final public function hierarchicalCategoryTree(): array
	{
		try
		{
			return \IPS\Data\Cache::i()->getWithExpire( 'marketplace_categorytree', TRUE );
		}
		catch ( \OutOfRangeException $e ) { }

		try
		{
			$response = $this->_api( 'marketplace/categories', NULL, FALSE, NULL, 1 );
			$tree = $this->_buildTree( $response['results'] );

		}
		catch( \Throwable $e )
		{
			return [];
		}

		/* This is intentionally stored in cache since it is used on every page load of the AdminCP */
		\IPS\Data\Cache::i()->storeWithExpire( 'marketplace_categorytree', $tree, \IPS\DateTime::create()->add( new \DateInterval( 'P7D' ) ), TRUE );

		return $tree;
	}

	/**
	 * View Marketplace Index
	 *
	 * @return	void
	 */
	final protected function manage()
	{
		try
		{
			$dataSet = $this->_getDatastoreCache( 'dashboard' );
		}
		catch( \OutOfRangeException $e )
		{
			$resourceTypeCategories = $this->_resourceTypeCategories();
			$dataSet = array(
				'newest' => $this->_apiWrapper( 'marketplace/files', [ 'sortBy' => 'date', 'sortDir' => 'DESC', 'hidden' => 0, 'perPage' => 9, 'categories' => implode( ',', $resourceTypeCategories['all'] ) ] )['results'],
				'updated' => $this->_apiWrapper( 'marketplace/files/updated', [ 'sortBy' => 'updated', 'sortDir' => 'DESC', 'hidden' => 0, 'perPage' => 4, 'categories' => implode( ',', $resourceTypeCategories['all'] ) ] )['results'],
				'popularApps' => $this->_apiWrapper( 'marketplace/files', [ 'sortBy' => 'popular', 'sortDir' => 'DESC', 'hidden' => 0, 'perPage' => 9, 'categories' => implode( ',', $resourceTypeCategories['apps'] ) ] )['results'],
				'popularThemes' => $this->_apiWrapper( 'marketplace/files', [ 'sortBy' => 'popular', 'sortDir' => 'DESC', 'hidden' => 0, 'perPage' => 9, 'categories' => implode( ',', $resourceTypeCategories['themes'] ) ] )['results'],
				'popularLanguages' => $this->_apiWrapper( 'marketplace/files', [ 'sortBy' => 'popular', 'sortDir' => 'DESC', 'hidden' => 0, 'perPage' => 4, 'categories' => implode( ',', $resourceTypeCategories['languages'] ) ] )['results']
 			);

			$this->_setDatastoreCache( 'dashboard', $dataSet, 60 );
		}

		\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack( 'menutab__marketplace' );
		\IPS\Output::i()->customHeader = $this->_customHeader( \IPS\Output::i()->title );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->dashboard( $dataSet );
	}

	/**
	 * Renew Resource
	 *
	 * @return	void
	 */
	final protected function renewFile()
	{
		\IPS\Session::i()->csrfCheck();

		/* Check License Validity */
		$this->_licenseCheck();

		if ( $this->_token() )
		{
			$response = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id . "/renew/" . (int) \IPS\Request::i()->invoiceId, NULL, TRUE, []  );

			if ( isset( $response['errorCode'] ) )
			{
				\IPS\Output::i()->error( 'marketplace_cannot_purchase', '2C409/J', 403, '', array(), $response['errorMessage'] );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $response['checkoutUrl'] ) );
			}
		}
		else
		{
			\IPS\Output::i()->error( 'marketplace_sign_in_required', '2C409/K', 403, '' );
		}
	}

	/**
	 * Install resource
	 *
	 * @return	void
	 */
	final protected function install()
	{
		\IPS\Session::i()->csrfCheck();

		if ( \IPS\DEMO_MODE )
		{
			\IPS\Output::i()->error( 'demo_mode_function_blocked', '2C409/4', 403, '' );
		}

		if ( \IPS\NO_WRITES )
		{
			\IPS\Output::i()->error( 'no_writes', '2C409/5', 403, '' );
		}

		if ( \IPS\IN_DEV )
		{
			\IPS\Output::i()->error( 'theme_error_not_available_in_dev', '2C409/6', 403, '' );
		}

		if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/applications/" ) )
		{
			\IPS\Output::i()->error( 'app_dir_not_write', '4C409/7', 500, '' );
		}

		if( !\IPS\CIC2 AND !is_writable( \IPS\ROOT_PATH . "/plugins/" ) ) // necessary as we write the hooks.php file here
		{
			\IPS\Output::i()->error( 'plugin_dir_not_write', '4C409/8', 500, '' );
		}

		if ( !\IPS\CIC2 AND file_exists( \IPS\ROOT_PATH . "/plugins/hooks.php") AND !is_writable( \IPS\ROOT_PATH . "/plugins/hooks.php" ) )
		{
			\IPS\Output::i()->error( 'plugin_file_not_write', '4C409/9', 500, '' );
		}

		if ( !\extension_loaded('phar') )
		{
			\IPS\Output::i()->error( 'no_phar_extension', '2C409/A', 500, '' );
		}

		/* Check a token is being used instead of the guest token */
		if ( !$this->_token() )
		{
			\IPS\Output::i()->error( 'marketplace_sign_in_required', '2C409/H', 403, '' );
		}

		$file = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id, [ 'termFields' => 1 ], TRUE );

		/* Check purchase status */
		if( $file['isPaid'] AND empty( $file['isPurchased'] ) AND !$file['canDownload'] )
		{
			\IPS\Output::i()->error( 'marketplace_resource_not_purchased', '2C171/A', 403, '' );
		}
		/* If we can still download this paid file, don't check license */
		elseif( !( $file['isPaid'] AND $file['isPurchased'] AND $file['canDownload'] ) )
		{
			/* Check License Validity */
			$this->_licenseCheck();
		}

		if( empty( \IPS\Request::i()->confirm ) AND \in_array( $file['disclaimerLocation'], [ 'both', 'download'] ) AND !empty( $file['downloadDisclaimer'] ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('marketplace_terms_and_conditions');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->termsAndConditions( $file, 'download', $file['downloadDisclaimer'], $file['authorDisclaimer'] ?? '' );
			return;
		}

		/* Check Locale */
		if( \IPS\Request::i()->type == 'install' AND $file['category']['resourceType'] == 'languages' )
		{
			/* If it's a language, ask for locale */
			$form = new \IPS\Helpers\Form;
			$form->ajaxOutput = TRUE;
			$form->class = 'ipsForm_vertical';

			\IPS\Output::i()->httpHeaders['X-IPS-FormNoSubmit'] = "true";
			\IPS\Lang::localeField( $form );

			if( \IPS\Request::i()->ajaxValidate AND !$values = $form->values() )
			{
				\IPS\Output::i()->output = $form;
				return;
			}

			/* Work out locale */
			$locale = NULL;
			if( isset( $_POST['lang_short_custom'] ) )
			{
				if ( !isset( $_POST['lang_short'] ) OR $_POST['lang_short'] === 'x' )
				{
					$locale = $_POST['lang_short_custom'];
				}
				else
				{
					$locale = $_POST['lang_short'];
				}
			}

			if( !$locale )
			{
				\IPS\Output::i()->output = $form;
				return;
			}
			else
			{
				\IPS\Request::i()->locale = $locale;
			}
		}

		$fileDownload = $this->_api( "downloads/files/" . (int) \IPS\Request::i()->id . '/download', NULL, TRUE );
		$fileExtension = pathinfo( $fileDownload['files'][0]['name'], PATHINFO_EXTENSION );

		if( !\in_array( $fileExtension, [ 'tar', 'xml' ] ) )
		{
			\IPS\Log::log( [ 'file' => $file['id'], 'extension' => $fileExtension ], 'mp_install_error' );
			\IPS\Output::i()->error( 'marketplace_error', '2C409/B', 500, '' );
		}

		/* Resource file */
		$resourceData = \IPS\Http\Url::external( $fileDownload['files'][0]['url'] )->request()->get();
		\IPS\Request::i()->marketplace = $file['id'];

		if( $resourceData->httpResponseCode != 200 )
		{
			\IPS\Log::log( $resourceData, 'mp_install_error' );
			\IPS\Output::i()->error( 'marketplace_error', '2C409/C', 500, '' );
		}

		/* Temporary File */
		$temporaryFile = tempnam( \IPS\TEMP_DIRECTORY, 'IPSMP' );
		\file_put_contents( $temporaryFile, (string) $resourceData );

		try
		{
			switch( $file['category']['resourceType'] )
			{
				case 'apps':
					/* Plugin or App? */
					switch( $fileExtension )
					{
						/* Application */
						case 'tar':
							$this->_installApplication( $file, $temporaryFile );
							break;
						/* Plugin */
						case 'xml':
							$this->_installPlugin( $file, $temporaryFile );
							break;
					}
					break;
				case 'themes':
					$this->_installTheme( $file, $temporaryFile );
					break;
				case 'languages':
					$this->_installLanguage( $file, $temporaryFile );
					break;
				default:
					throw new \UnexpectedValueException( 'marketplace_resourcetype_unsupported_' . $file['category']['resourceType'] );
					break;
			}
		}
		catch( \Throwable $e )
		{
			if( $fileExtension == 'tar' )
			{
				/* Application install renames the temporary file */
				$temporaryFile .= '.tar';
			}

			@unlink( $temporaryFile );
			\IPS\Log::log( $e, 'mp_install_error' );
			\IPS\Output::i()->error( 'marketplace_error', '2C409/D', 403, '' );
		}
	}

	/**
	 * View Category
	 *
	 * @return	void
	 */
	final protected function viewCategory()
	{
		$page = \IPS\Request::i()->page ?? 1;
		try
		{
			$dataSet = $this->_getDatastoreCache( 'category_' . (int) \IPS\Request::i()->id . '_' . $page );
		}
		catch( \OutOfRangeException $e )
		{
			$ids = [ (int) \IPS\Request::i()->id ];

			$categories = $this->hierarchicalCategoryTree();
			$ourCatKey = array_search( $ids[0], array_column( $categories, 'id' ) );

			if( $ourCatKey !== FALSE )
			{
				foreach ( $categories[ $ourCatKey ]['children'] as $c )
				{
					$ids[] = $c['id'];
				}
			}

			$dataSet = array(
				'category' => $this->_apiWrapper( 'downloads/categories/' . (int) \IPS\Request::i()->id ),
				'files' => $this->_apiWrapper( 'marketplace/files', [ 'perPage' => 18, 'sortBy' => 'updated', 'sortDir' => 'DESC', 'hidden' => 0, 'categories' => implode( ',', $ids ), 'page' => (int) $page ] )
			);

			$this->_setDatastoreCache( 'category_' . (int) \IPS\Request::i()->id . '_' . $page, $dataSet, 60 );
		}

		\IPS\Output::i()->title = $dataSet['category']['name'];
		\IPS\Output::i()->customHeader = $this->_customHeader( \IPS\Output::i()->title, NULL, $ids );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->categoryIndex( (int) \IPS\Request::i()->id, $dataSet['files'] );
	}
	
	/**
	 * View File
	 *
	 * @return	void
	 */
	final protected function viewFile()
	{
		$file = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id, [ 'termFields' => 1 ], TRUE );

		/* Find out if it is installed */
		$updateAvailable = FALSE;
		try
		{
			switch( $file['category']['resourceType'] )
			{
				case 'apps':
					/* Plugin or App? */
					try
					{
						$app = \IPS\Application::load( $file['id'], 'app_marketplace_id' );
						if( $file['latestLongVersion'] > $app->long_version )
						{
							$updateAvailable = TRUE;
						}
					}
					catch( \OutOfRangeException $e )
					{
						$plugin = \IPS\Plugin::load( $file['id'], 'plugin_marketplace_id' );
						if( $file['latestLongVersion'] > $plugin->version_long )
						{
							$updateAvailable = TRUE;
						}
					}
					/* Legacy apps, or those with missing files */
					catch( \UnexpectedValueException $e )
					{
						if ( mb_stristr( $e->getMessage(), 'Missing:' ) )
						{
							try
							{
								$appVersion = \IPS\Db::i()->select('app_long_version', 'core_applications', array( 'app_marketplace_id=?', $file['id'] ) )->first();
								if( $file['latestLongVersion'] > $appVersion )
								{
									$updateAvailable = TRUE;
								}
							}
							catch( \UnderflowException $e ) {}
						}
					}
					break;
				case 'themes':
					$theme = \IPS\Theme::load( $file['id'], 'set_marketplace_id' );
					if( $file['latestLongVersion'] > $theme->long_version )
					{
						$updateAvailable = TRUE;
					}
					break;
				case 'languages':
					$lang = \IPS\Lang::load( $file['id'], 'lang_marketplace_id' );
					if( $file['latestLongVersion'] > $lang->version_long )
					{
						$updateAvailable = TRUE;
					}
					break;
			}
			$installed = TRUE;
		}
		catch( \OutOfRangeException $e )
		{
			$installed = FALSE;
		}

		if( $file['renewalTerm'] )
		{
			$file['renewalTermString'] = $this->_calculateRenewalTerm( $file['renewalTerm'] );
		}

		$reviewsSet = NULL;
		if( $file['reviews'] )
		{
			$page = \IPS\Request::i()->page ?? 1;
			$reviewsSet = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id . '/reviews', [ 'perPage' => 10, 'page' => (int) $page ] );
		}

		\IPS\Output::i()->title = $file['title'];
		\IPS\Output::i()->customHeader = $this->_customHeader( $file['title'], $file );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->fileView( $file, $reviewsSet, $this->_token(), $installed, $updateAvailable, $this->_licenseCheck( TRUE ) );
	}
	
	/**
	 * Buy File
	 *
	 * @return	void
	 */
	final protected function buyFile()
	{
		\IPS\Session::i()->csrfCheck();

		/* Check License Validity */
		$this->_licenseCheck();

		$file = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id, [ 'termFields' => 1 ], TRUE );

		if( empty( \IPS\Request::i()->confirm ) AND \in_array( $file['disclaimerLocation'], [ 'both', 'purchase'] ) AND !empty( $file['downloadDisclaimer'] ) )
		{
			\IPS\Output::i()->title = \IPS\Member::loggedIn()->language()->addToStack('marketplace_terms_and_conditions');
			\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->termsAndConditions( $file, 'purchase', $file['downloadDisclaimer'], $file['authorDisclaimer'] ?? '' );
			return;
		}

		if ( $this->_token() )
		{
			$response = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id . "/buy", NULL, TRUE, [] );
			
			if ( isset( $response['errorCode'] ) )
			{
				\IPS\Output::i()->error( 'marketplace_cannot_purchase', '2C409/3', 403, '', array(), $response['errorMessage'] );
			}
			else
			{
				\IPS\Output::i()->redirect( \IPS\Http\Url::external( $response['checkoutUrl'] ) );
			}
		}
		else
		{
			\IPS\Output::i()->error( 'marketplace_sign_in_required', '2C409/3', 403, '' );
		}
	}

	/**
	 * file change log
	 *
	 * @return	void
	 */
	final protected function fileChangelog()
	{
		$fileHistory = $this->_apiWrapper( "marketplace/files/" . (int) \IPS\Request::i()->id . '/history' );
		\IPS\Output::i()->output = \IPS\Theme::i()->getTemplate( 'marketplace' )->fileChangelogGrid( $fileHistory );
	}

	/**
	 * Search dialog for onboarding
	 *
	 * @return	void
	 */
	final public function search()
	{
		\IPS\Output::i()->output =\IPS\Theme::i()->getTemplate('marketplace')->onboardSearch( \IPS\Request::i()->category, (bool) \IPS\Request::i()->compatible );
	}
	
	/**
	 * Signout
	 *
	 * @return	void
	 */
	final protected function signout()
	{
		\IPS\Session::i()->csrfCheck();
		\IPS\Db::i()->delete( 'core_marketplace_tokens', array( 'id=?', \IPS\Member::loggedIn()->member_id ) );
		\IPS\Output::i()->redirect( \IPS\Http\Url::internal('app=core&module=marketplace&controller=marketplace') );
	}
	
	/**
	 * Search for a file by title
	 *
	 * @return	void
	 */
	final protected function apiSearch()
	{
		$queryString = [
			'q'				=> \IPS\Request::i()->title,
			'type'			=> 'downloads_file',
			'search_in'		=> 'titles',
			'doNotTrack'	=> TRUE,
			'search_and_or' => 'and'
		];

		$resourceTypeCategories = $this->_resourceTypeCategories();
		if ( \IPS\Request::i()->category )
		{
			if ( array_key_exists( \IPS\Request::i()->category, $resourceTypeCategories ) )
			{
				$queryString['nodes'] = implode( ',', $resourceTypeCategories[ \IPS\Request::i()->category ] );
			}
			else
			{
				$queryString['nodes'] = \IPS\Request::i()->category;
			}
		}

		/* Make sure we're only searching categories that should be in the AdminCP */
		if( empty( $queryString['nodes'] ) )
		{
			$queryString['nodes'] = implode( ',', $resourceTypeCategories['all'] );
		}
		
		$results = $this->_api( "core/search", $queryString );
		
		if ( \IPS\Request::i()->single )
		{
			if ( $results['totalResults'] == 1 )
			{
				\IPS\Output::i()->json( [
					'id'	=> \intval( $results['results'][0]['objectId'] ),
					'html'	=> \IPS\Theme::i()->getTemplate('marketplace')->fileCardMini( $this->_api( "downloads/files/" . (int) $results['results'][0]['objectId'] ) )
				] );
			}
			else
			{
                \IPS\Output::i()->json( [ 'error' => \IPS\Member::loggedIn()->language()->addToStack( 'no_results' ) ], 404 );
			}
		}
		else
		{
			$ids = [];
			foreach( $results['results'] as $result )
			{
				$ids[] = $result['objectId'];
			}
			
			if ( $ids )
			{
				$results = $this->_api( "marketplace/files", [ 'ids' => implode( ',', $ids ), 'compatible' => (bool) \IPS\Request::i()->compatible ?? FALSE ] );

				/* Nothing compatible */
				if( !$results['totalResults'] )
				{
					\IPS\Output::i()->json( [ 'error' => \IPS\Member::loggedIn()->language()->addToStack( 'no_results' ) ], 404 );
				}
				else
				{
					\IPS\Output::i()->json( [ 'html' => \IPS\Theme::i()->getTemplate('marketplace')->fileCardList( $results['results'] ) ] );
				}
			}
			else
			{
				\IPS\Output::i()->json( [ 'error' => \IPS\Member::loggedIn()->language()->addToStack( 'no_results' ) ], 404 );
			}
		}
	}
	
	/**
	 * Lookup a file by ID
	 *
	 * @return	void
	 */
	final protected function apiLookup()
	{
		try
		{
            \IPS\Output::i()->json( [
                'html' => \IPS\Theme::i()->getTemplate('marketplace')->fileCardMini( $this->_api( "downloads/files/" . (int) \IPS\Request::i()->id ) )
            ] );
		}
		catch ( \Exception $e )
		{
            \IPS\Output::i()->json( [ 'error' => \IPS\Member::loggedIn()->language()->addToStack( 'no_results' ) ], 403 );
		}
	}
	
	/**
	 * Wrapper to make a request to the Marketplace API. Wrapper will display an error message if something doesn't work
	 *
	 * @param	string		$endpoint		The endpoint
	 * @param 	array		$queryString	The query string to append
	 * @param 	bool		$authenticate	Send Authentication data
	 * @param 	array|null	$data			POST/PUT Data
	 * @param 	int			$timeout		Custom timeout value for request
	 * @return	array						JSON decoded response
	 */
	final protected function _apiWrapper( string $endpoint, array $queryString = NULL, bool $authenticate = TRUE, array $data = NULL, int $timeout = \IPS\DEFAULT_REQUEST_TIMEOUT )
	{
		try
		{
			return $this->_api( $endpoint, $queryString, $authenticate, $data, $timeout );
		}
		catch( \Exception $e )
		{
			if( $json = json_decode( $e->getMessage(), TRUE ) AND $json['errorMessage'] == 'IPS_VERSION_INCOMPATIBLE' )
			{
				\IPS\Output::i()->error( 'marketplace_incompatible_file', '3C409/G', 500 );
			}
			\IPS\Output::i()->error( 'marketplace_communication_error', '3C409/3', 500, NULL, array(), $e->getMessage() );
		}
	}

	/**
	 * Make a request to the Marketplace API
	 *
	 * @param	string		$endpoint		The endpoint
	 * @param 	array		$queryString	The query string to append
	 * @param 	bool		$authenticate	Send Authentication data
	 * @param 	array|null	$data			POST/PUT Data
	 * @param 	int			$timeout		Custom timeout value for request
	 * @param   bool        $retry          Retried request after token expire
	 * @param   int         $ipsVersion     Version of Invision to use for version check
	 * @return	array						JSON decoded response
	 *
	 * @throws	\RuntimeException
	 * @throws	\UnexpectedValueException
	 */
	final protected function _api( string $endpoint, array $queryString = NULL, bool $authenticate = FALSE, array $data = NULL, int $timeout = \IPS\DEFAULT_REQUEST_TIMEOUT, bool $retry = FALSE, ?int $ipsVersion = NULL ): array
	{
		$token = $authenticate ? ( $this->_token() ?? \IPS\MARKETPLACE_GUEST_TOKEN ) : \IPS\MARKETPLACE_GUEST_TOKEN;
		$url = \IPS\Http\Url::external( \IPS\MARKETPLACE_URL . '/api/' . $endpoint )->setQueryString( 'ips_version', $ipsVersion ?? \IPS\Application::load('core')->long_version );

		if ( $queryString )
		{
			$url = $url->setQueryString( $queryString );
		}

		$request = $url	->request( $timeout )
						->setHeaders( array( 'Authorization' => "Bearer {$token}" ) );

		try
		{
			if ( $data === NULL )
			{
				$response = $request->get();
			}
			else
			{
				$response = $request->post( $data );
			}
		}
		catch( \IPS\Http\Request\Exception $e )
		{
			throw new \UnexpectedValueException();
		}

		$json = $response->decodeJson( TRUE );

		if( !$retry AND $response->httpResponseCode == 401 AND !empty( $json['errorCode'] ) AND \in_array( $json['errorCode'], [ '1S290/F', '3S290/9' ] ) )
		{
			/* Delete token */
			$this->_token = NULL;
			\IPS\Db::i()->delete( 'core_marketplace_tokens', array( 'id=?', \IPS\Member::loggedIn()->member_id ) );

			/* Retry request */
			return $this->_api( $endpoint, $queryString, $authenticate, $data, $timeout, TRUE );
		}

		if( $response->httpResponseCode != 200 )
		{
			throw new \RuntimeException( (string) $response, $response->httpResponseCode );
		}

		if( $json === NULL )
		{
			throw new \UnexpectedValueException();
		}

		return $json;
	}

	/**
	 * Build Category Tree
	 *
	 * @param 	array		$categories		Category array
	 * @param 	int 		$parentId		Parent ID being processed
	 * @return 	array
	 */
	final protected function _buildTree( array &$categories, int $parentId =0 ): array
	{
		$hold = array();
		foreach( $categories as $key => $cat )
		{
			if( $cat['parentId'] == $parentId )
			{
				$cat['children'] = $this->_buildTree( $categories, $cat['id'] );

				unset( $categories[ $key ], $cat['class'], $cat['url'], $cat['parentId'] );

				$hold[] = $cat;
			}
		}

		return $hold;
	}

	/**
	 * Calculate renewal term string from API response
	 *
	 * @param   array   $renewalTerm
	 * @return  string
	 */
	final protected function _calculateRenewalTerm( array $renewalTerm ): string
	{
		$lang = \IPS\Member::loggedIn()->language();
		if( $renewalTerm['interval']['y'] )
		{
			$term = $lang->pluralize( $lang->get('marketplace_renew_years'), array( $renewalTerm['interval']['y'] ) );
		}
		elseif( $renewalTerm['interval']['m'] )
		{
			$term =  $lang->pluralize( $lang->get('marketplace_renew_months'), array( $renewalTerm['interval']['m'] ) );
		}
		else
		{
			$term = $lang->pluralize( $lang->get('marketplace_renew_days'), array( $renewalTerm['interval']['d'] ) );
		}

		return sprintf( \IPS\Member::loggedIn()->language()->get( 'marketplace_renew_option'), $renewalTerm['cost']['amount'], $term );
	}

	/**
	 * Custom AdminCP Header for Marketplace
	 *
	 * @param	string		$title			Page Title
	 * @param 	array|NULL 	$file			File data, if on viewFile
	 * @param 	array	 	$categoryIds		Category IDs
	 * @return 	string
	 */
	final protected function _customHeader( string $title, array $file=NULL, array $categoryIds=NULL ): string
	{
		$authenticationUrl = NULL;
		$authenticationError = NULL;
		$authenticatedMember = NULL;

		/* Get our token */
		$token = $this->_token( FALSE );

		/* If we are not logged in, build the stuff we need for sign in */
		if ( \substr( $token, 0, 8 ) === 'pending-' )
		{
			if ( $licenseKey = \IPS\IPS::licenseKey() )
			{
				/* Normalize our URL's. Specifically ignore the www. subdomain. */
				$validUrls	= array(
					'liveUrl' => rtrim( str_replace( array( 'http://', 'https://', 'www.' ), '', $licenseKey['url'] ), '/' ),
					'testUrl' => rtrim( str_replace( array( 'http://', 'https://', 'www.' ), '', $licenseKey['test_url'] ), '/' )
				);
				$ourUrl	= rtrim( str_replace( array( 'http://', 'https://', 'www.' ), '', \IPS\Settings::i()->base_url ), '/' );

				if ( \in_array( $ourUrl, $validUrls ) )
				{
					$authenticationUrl = \IPS\Http\Url::ips('marketplace/signin')->setQueryString( array(
						'lkey'		=> $licenseKey['key'] . ( $validUrls['liveUrl'] === $ourUrl ? '' : '-TESTINSTALL' ),
						'member'	=> \IPS\Member::loggedIn()->member_id,
						'hash'		=> \substr( $token, 8 )
					) );
				}
				else
				{
					$authenticationError = \IPS\Member::loggedIn()->language()->addToStack('marketplace_error_wrong_url');
				}
			}
			else
			{
				$authenticationError = \IPS\Member::loggedIn()->language()->addToStack('marketplace_error_no_lkey');
			}
		}
		else
		{
			try
			{
				$response = \IPS\Http\Url::external( \IPS\MARKETPLACE_URL . '/api/core/me' )->request()->setHeaders( array( 'Authorization' => "Bearer {$token}" ) )->get();
				$data = $response->decodeJson();

				/* Revoked Token, delete our local token and re-set the header */
				if( !empty( $data['errorCode'] ) AND \in_array( $data['errorCode'], [ '1S290/F', '3S290/9' ] ) )
				{
					/* Delete token */
					$this->_token = NULL;
					\IPS\Db::i()->delete( 'core_marketplace_tokens', array( 'id=?', \IPS\Member::loggedIn()->member_id ) );
					return $this->_customHeader( $title, $file, $categoryIds );
				}
				$authenticatedMember = $data['name'];
			}
			catch ( \Exception $e )
			{
				\IPS\Output::i()->error( 'marketplace_communication_error', '3C409/2', 500, NULL, array(), $response );
			}
		}

		return \IPS\Theme::i()->getTemplate( 'marketplace' )->customHeader( $title, $authenticatedMember, $authenticationUrl, $authenticationError, $file, $categoryIds );
	}

	/**
	 * Get data from datastore with expires
	 *
	 * @param 	string		$name		Data name
	 * @return 	array
	 * @throws	\OutOfRangeException
	 */
	final protected function _getDatastoreCache( string $name ): array
	{
		if( \IPS\IN_DEV )
		{
			throw new \OutOfRangeException;
		}

		$variableName = 'marketplace_' . $name . '_' . md5( $this->_token() ?? \IPS\MARKETPLACE_GUEST_TOKEN );
		if( !isset( \IPS\Data\Store::i()->$variableName ) )
		{
			throw new \OutOfRangeException;
		}

		$storedValue = \IPS\Data\Store::i()->$variableName;

		if( $storedValue['expires'] < time() )
		{
			throw new \OutOfRangeException;
		}
		else
		{
			return $storedValue['result'];
		}
	}

	/**
	 * Install application TAR from Marketplace
	 *
	 * @param	array	$file				REST API file array
	 * @param	string 	$temporaryFile		Path to temporary file for application
	 */
	final protected function _installApplication( array $file, string $temporaryFile )
	{
		/* Test the phar */
		rename( $temporaryFile, $temporaryFile . '.tar' );
		$temporaryFile = $temporaryFile . '.tar';
		$application = new \PharData( $temporaryFile, 0, NULL, \Phar::TAR );

		/* Get app directory */
		$appData = json_decode( file_get_contents( "phar://" . $temporaryFile . '/data/application.json' ), TRUE );

		/* Make sure that the app data is valid */
		if( !isset( $appData['app_directory'] ) )
		{
			throw new \UnexpectedValueException;
		}

		$appDirectory = $appData['app_directory'];

		/* Check existing plugins */
		try
		{
			$existingApp = \IPS\Application::load( $file['id'], 'app_marketplace_id' );
			$_type = 'upgrade';
		}
		catch( \OutOfRangeException $e )
		{
			try
			{
				\IPS\Application::load( $appDirectory );
				\IPS\Output::i()->error( 'marketplace_app_ns_conflict', '2C409/E', 403, '' );
			}
			catch( \OutOfRangeException $e )
			{
				/* New install */
				$_type = 'install';
			}
			catch( \UnexpectedValueException $e )
			{
				/* Catch exception if it is a legacy application that needs upgrading */
				if ( !mb_stristr( $e->getMessage(), 'Missing:' ) )
				{
					throw $e;
				}
			}
		}

		if ( \IPS\CIC2 )
		{
			unset( \IPS\Data\Store::i()->syncCompleted );

			\IPS\Cicloud\file( "IPScustomapp_{$appData['app_directory']}.tar", \file_get_contents( $temporaryFile ) );

			/* Check files are ready */
			$i = 0;
			do
			{
				if ( ( isset( \IPS\Data\Store::i()->syncCompleted ) AND \IPS\Data\Store::i()->syncCompleted ) OR $i >= 30 ) # 30 x 0.25 seconds
				{
					/* We need to wait for the backend to process the tar */
					sleep(3);
					break;
				}

				/* Pause slightly before checking the datastore again */
				usleep( 250000 );
				$i++;
			}
			while( TRUE );
		}
		else
		{
			/* Extract */
			$application->extractTo( \IPS\ROOT_PATH . "/applications/" . $appDirectory, NULL, TRUE );
			\IPS\core\modules\admin\applications\applications::_checkChmod( \IPS\ROOT_PATH . '/applications/' . $appDirectory );
			\IPS\IPS::resyncIPSCloud( 'Extracted marketplace application in ACP' );
		}

		@unlink( $temporaryFile );

		/* Set values our MR expects, and start it. */
		\IPS\Request::i()->appKey = $appDirectory;

		( new \IPS\core\modules\admin\applications\applications )->$_type();
	}

	/**
	 * Install language XML from Marketplace
	 *
	 * @param	array	$file				REST API file array
	 * @param	string 	$temporaryFile		Path to temporary language XML file
	 */
	final protected function _installLanguage( array $file, string $temporaryFile )
	{
		/* Already installed? */
		$xml = \IPS\Xml\XMLReader::safeOpen( $temporaryFile );
		if ( !@$xml->read() )
		{
			throw new \UnexpectedValueException;
		}

		/* Set Values our MultipleRedirector expects */
		\IPS\Request::i()->file = $temporaryFile;
		\IPS\Request::i()->key = md5_file( $temporaryFile );

		try
		{
			$existing = \IPS\Lang::load( $file['id'], 'lang_marketplace_id' );
			\IPS\Request::i()->into = $existing->id;
		}
		catch( \OutOfRangeException $e ) { }

		/* Start the MR */
		( new \IPS\core\modules\admin\languages\languages )->import();
	}

	/**
	 * Install plugin XML from Marketplace
	 *
	 * @param	array	$file				REST API file array
	 * @param	string 	$temporaryFile		Path to temporary plugin XML file
	 */
	final protected function _installPlugin( array $file, string $temporaryFile )
	{
		/* Already installed? */
		$xml = \IPS\Xml\XMLReader::safeOpen( $temporaryFile );
		if ( !@$xml->read() )
		{
			throw new \UnexpectedValueException;
		}

		/* Check existing plugins */
		try
		{
			$existingPlugin = \IPS\Plugin::load( $file['id'], 'plugin_marketplace_id' );
			\IPS\Request::i()->id = $existingPlugin->id;
		}
		catch( \OutOfRangeException $e )
		{
			try
			{
				$id = \IPS\Db::i()->select( 'plugin_id', 'core_plugins', array( 'plugin_name=? AND plugin_author=?', $xml->getAttribute('name'), $xml->getAttribute('author') ) )->first();
				\IPS\Output::i()->error( 'marketplace_plugin_conflict', '2C409/F', 403, '' );
			}
			catch( \UnderflowException $e ) { }
		}

		/* Set values our MR expects, and start it. */
		\IPS\Request::i()->file = $temporaryFile;
		\IPS\Request::i()->key = md5_file( $temporaryFile );

		( new \IPS\core\modules\admin\applications\plugins )->doInstall();
	}

	/**
	 * Install theme XML from Marketplace
	 *
	 * @param	array	$file				REST API file array
	 * @param	string 	$temporaryFile		Path to temporary theme XML file
	 */
	final protected function _installTheme( array $file, string $temporaryFile )
	{
		$xml = \IPS\Xml\XMLReader::safeOpen( $temporaryFile );
		if ( !@$xml->read() )
		{
			throw new \UnexpectedValueException;
		}

		/* Check existing theme */
		try
		{
			$theme = \IPS\Theme::load( $file['id'], 'set_marketplace_id' );
		}
		catch( \OutOfRangeException $e )
		{
			$max = \IPS\Db::i()->select( 'MAX(set_order)', 'core_themes' )->first();

			/* Create a default theme */
			$theme = new \IPS\Theme;
			$theme->parent_array = '[]';
			$theme->child_array  = '[]';
			$theme->parent_id    = 0;
			$theme->by_skin_gen  = 0;
			$theme->editor_skin	 = 'ips';
			$theme->order        = $max + 1;
			$theme->marketplace_id = $file['id'];
			$theme->save();

			$theme->copyResourcesFromSet();
		}

		/* Set values our MR expects, and start it. */
		\IPS\Request::i()->file = $temporaryFile;
		\IPS\Request::i()->key = md5_file( $temporaryFile );
		\IPS\Request::i()->id = $theme->id;

		( new \IPS\core\modules\admin\customization\themes )->import();
	}

	/**
	 * Get resource type category IDs
	 *
	 * @return	array	array( 'apps' => ..., 'themes' => ..., 'languages' => ... )
	 */
	final protected function _resourceTypeCategories(): array
	{
		try
		{
			$categories = $this->_getDatastoreCache( 'type_categories' );
		}
		catch( \OutOfRangeException $e )
		{
			$categories = [ 'apps' => [], 'themes' => [], 'languages' => [], 'all' => [] ];

			try
			{
				$response = $this->_api( 'marketplace/categories', NULL, FALSE, NULL, 2 );
			}
			catch( \RuntimeException | \UnexpectedValueException $e )
			{
				return $categories;
			}

			foreach( $response['results'] as $cat )
			{
				if( isset( $categories[ $cat['resourceType'] ] ) )
				{
					$categories['all'][] = $categories[ $cat['resourceType'] ][] = $cat['id'];
				}
			}

			$this->_setDatastoreCache( 'type_categories', $categories, 2880 );
		}

		return $categories;
	}

	/**
	 * Set data to datastore with a TTL
	 *
	 * @param	string		$name		Data name
	 * @param 	array 		$content	Data content
	 * @param 	int 		$ttl		Time to live in minutes
	 * @return	void
	 */
	final protected function _setDatastoreCache( string $name, array $content, int $ttl )
	{
		$ttlSeconds = $ttl * 60;
		$variableName = 'marketplace_' . $name . '_' . md5( $this->_token() ?? \IPS\MARKETPLACE_GUEST_TOKEN );
		\IPS\Data\Store::i()->$variableName = array( 'expires' => time() + $ttlSeconds, 'result' => $content );
	}

	/**
	 * @brief	Authentication Token
	 */
	protected $_token = NULL;

	/**
	 * Get Authentication Token
	 *
	 * @param	bool			$authenticatedOnly	If TRUE, will only return an actual authentication token (not including a pending flag)
	 * @return	string|null
	 */
	final protected function _token( bool $authenticatedOnly = TRUE ):? string
	{
		if ( $this->_token === NULL )
		{
			try
			{
				$this->_token = \IPS\Db::i()->select( 'token', 'core_marketplace_tokens', array( 'id=? AND ( expires_at>? OR expires_at IS NULL )', \IPS\Member::loggedIn()->member_id, time() ) )->first();
			}
			catch ( \UnderflowException $e )
			{
				$this->_token = 'pending-' . \IPS\Login::generateRandomString( 89 );
				\IPS\Db::i()->insert( 'core_marketplace_tokens', array(
					'id'			=> \IPS\Member::loggedIn()->member_id,
					'token'			=> $this->_token,
					'expires_at'	=> NULL
				), TRUE );
			}
		}

		if ( \substr( $this->_token, 0, 8 ) === 'pending-' )
		{
			return $authenticatedOnly ? NULL : $this->_token;
		}
		else
		{
			return $this->_token;
		}
	}

	/**
	 * Parse raw content
	 *
	 * @param 	string 		$text		HTML to parse
	 * @return 	string
	 */
	final public static function _parseRawContent( string $text ): string
	{
		if ( mb_stristr( $text, 'iframe' ) OR mb_stristr( $text, '<img' ) OR mb_stristr( $text, '<video' ) OR mb_stristr( $text, '<a' ) )
		{
			$dom = new \IPS\Xml\DOMDocument( '1.0', 'UTF-8' );
			$dom->loadHTML( \IPS\Xml\DOMDocument::wrapHtml( $text ) );
			$xPath = new \DOMXPath( $dom );

			/* Replace Iframes */
			if( mb_stristr( $text, '<iframe' ) )
			{
				foreach ( $xPath->query( '//iframe' ) as $iframe )
				{
					$paragraph = $dom->createElement( 'p' );
					$newLink = $dom->createElement( 'a' );
					$srcAttribute = preg_replace( '#(\?|&)do=embed#i', '', ( $iframe->hasAttribute( 'data-embed-src' ) ? $iframe->getAttribute( 'data-embed-src' ) : $iframe->getAttribute( 'src' ) ) );
					$newLink->setAttribute( 'href', $srcAttribute );
					$newLink->nodeValue = $srcAttribute;
					$paragraph->appendChild( $newLink );
					$iframe->parentNode->replaceChild( $paragraph, $iframe );
				}
			}

			/* ipsEmbeddedVideo */
			if( mb_stristr( $text, 'ipsEmbeddedVideo' ) )
			{
				foreach ( $xPath->query( "//div[contains(@class, 'ipsEmbeddedVideo')]" ) as $embed )
				{
					$embed->removeAttribute('class');
				}
			}

			/* Update links */
			if( mb_stristr( $text, '<a' ) )
			{
				foreach ( $xPath->query( '//a' ) as $link )
				{
					$link->setAttribute( 'rel', 'noopener noreferrer' );
					$link->setAttribute( 'target', '_blank' );

					if( $link->hasAttribute( 'data-ipshover' ) )
					{
						$link->removeAttribute( 'data-ipshover' );
					}
				}
			}

			/* Remove remote images & video */
			if( mb_stristr( $text, '<img' ) OR mb_stristr( $text, '<video' ) )
			{
				$allowed = '//dne4i5cb88590.cloudfront.net/invisionpower-com';
				foreach ( $xPath->query( '//img | //video' ) as $image )
				{
					$src = $image->hasAttribute( 'data-src' ) ? $image->getAttribute( 'data-src' ) : $image->getAttribute( 'src' );

					if ( mb_substr( str_replace( [ 'https://', 'http://' ], '//', $src ), 0, \mb_strlen( $allowed ) ) !== $allowed )
					{
						$image->parentNode->removeChild( $image );
					}
				}
			}

			$text = preg_replace( '/<meta http-equiv(?:[^>]+?)>/i', '', preg_replace( '/^<!DOCTYPE.+?>/', '', str_replace( array( '<html>', '</html>', '<body>', '</body>', '<head>', '</head>' ), '', $dom->saveHTML() ) ) );
		}

		return $text;
	}

	/**
	 * Handler for Core update check functionality
	 *
	 * @param	array	$files  Array of files, key is Marketplace ID, value is current version
	 * @return 	array
	 */
	final public function _updateCheck( array $files, ?int $version = NULL ):? array
	{
		return $this->_api("marketplace/files/versions", [ 'files' => $files ], FALSE, NULL, \IPS\DEFAULT_REQUEST_TIMEOUT, FALSE, $version );
	}

	/**
	 * Check license status
	 *
	 * @param 	bool 			$return		Whether to return status
	 * @return 	bool|void
	 */
	final protected function _licenseCheck( bool $return=FALSE )
	{
		$valid = TRUE;
		$licenseData = \IPS\IPS::licenseKey();
		if ( !$licenseData or ( $licenseData['expires'] !== NULL AND strtotime( $licenseData['expires'] ) < time() ) )
		{
			$valid = FALSE;
		}

		if( $return )
		{
			return $valid;
		}
		elseif( !$valid )
		{
			\IPS\Output::i()->error( 'marketplace_inactive_license', '3C409/I', 403, '' );
		}
	}
}