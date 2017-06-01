<?php

use GuzzleHttp\Client as GClient;
use Sabre\DAV\Client as SClient;
use Sabre\DAV\Xml\Property\ResourceType;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Psr7\Request;

require __DIR__ . '/../../../../lib/composer/autoload.php';


trait WebDav {
	use Sharing;

	/** @var string*/
	private $davPath = "remote.php/webdav";
	/** @var boolean*/
	private $usingOldDavPath = true;
	/** @var ResponseInterface */
	private $response;
	/** @var map with user as key and another map as value, which has path as key and etag as value */
	private $storedETAG = NULL;
	/** @var integer */
	private $storedFileID = NULL;

	/**
	 * @Given /^using dav path "([^"]*)"$/
	 */
	public function usingDavPath($davPath) {
		$this->davPath = $davPath;
	}

	/**
	 * @Given /^using old dav path$/
	 */
	public function usingOldDavPath() {
		$this->davPath = "remote.php/webdav";
		$this->usingOldDavPath = true;
	}

	/**
	 * @Given /^using new dav path$/
	 */
	public function usingNewDavPath() {
		$this->davPath = "remote.php/dav";
		$this->usingOldDavPath = false;
	}

	public function getDavFilesPath($user){
		if ($this->usingOldDavPath === true){
			return $this->davPath;
		} else {
			return $this->davPath . '/files/' . $user;
		}
	}

	public function makeDavRequest($user,
								   $method,
								   $path,
								   $headers,
								   $body = null,
								   $type = "files",
								   $requestBody = null){
		if ( $type === "files" ){
			$fullUrl = substr($this->baseUrl, 0, -4) . $this->getDavFilesPath($user) . "$path";
		} else if ( $type === "uploads" ){
			$fullUrl = substr($this->baseUrl, 0, -4) . $this->davPath . "$path";
		} 
		$client = new GClient();

		$options = [];
		if ($user === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$user, $this->regularUser];
		}

		$request = new Request($method, $fullUrl, $headers, $requestBody);
		return $client->send($request, $options);
	}

	/**
	 * @Given /^User "([^"]*)" moved (file|folder|entry) "([^"]*)" to "([^"]*)"$/
	 * @param string $user
	 * @param string $fileSource
	 * @param string $fileDestination
	 */
	public function userMovedFile($user, $entry, $fileSource, $fileDestination){
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->getDavFilesPath($user);
		$headers['Destination'] = $fullUrl . $fileDestination;
		$this->response = $this->makeDavRequest($user, "MOVE", $fileSource, $headers);
		PHPUnit_Framework_Assert::assertEquals(201, $this->response->getStatusCode());
	}

	/**
	 * @When /^User "([^"]*)" moves (file|folder|entry) "([^"]*)" to "([^"]*)"$/
	 * @param string $user
	 * @param string $fileSource
	 * @param string $fileDestination
	 */
	public function userMovesFile($user, $entry, $fileSource, $fileDestination){
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->getDavFilesPath($user);
		$headers['Destination'] = $fullUrl . $fileDestination;
		try {
			$this->response = $this->makeDavRequest($user, "MOVE", $fileSource, $headers);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When /^User "([^"]*)" copies file "([^"]*)" to "([^"]*)"$/
	 * @param string $user
	 * @param string $fileSource
	 * @param string $fileDestination
	 */
	public function userCopiesFile($user, $fileSource, $fileDestination){
		$fullUrl = substr($this->baseUrl, 0, -4) . $this->getDavFilesPath($user);
		$headers['Destination'] = $fullUrl . $fileDestination;
		try {
			$this->response = $this->makeDavRequest($user, "COPY", $fileSource, $headers);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When /^Downloading file "([^"]*)" with range "([^"]*)"$/
	 * @param string $fileSource
	 * @param string $range
	 */
	public function downloadFileWithRange($fileSource, $range){
		$headers['Range'] = $range;
		$this->response = $this->makeDavRequest($this->currentUser, "GET", $fileSource, $headers);
	}

	/**
	 * @When /^Downloading last public shared file with range "([^"]*)"$/
	 * @param string $range
	 */
	public function downloadPublicFileWithRange($range){
		$token = $this->lastShareData->data->token;
		$fullUrl = substr($this->baseUrl, 0, -4) . "public.php/webdav";

		$client = new GClient();
		$options = [];
		$options['auth'] = [$token, ""];

		$request = new Request("GET", $fullUrl, ['Range' => $range]);

		$this->response = $client->send($request, $options);
	}

	/**
	 * @When /^Downloading last public shared file inside a folder "([^"]*)" with range "([^"]*)"$/
	 * @param string $range
	 */
	public function downloadPublicFileInsideAFolderWithRange($path, $range){
		$token = $this->lastShareData->data->token;
		$fullUrl = substr($this->baseUrl, 0, -4) . "public.php/webdav" . "$path";

		$client = new GClient();
		$options = [];
		$options['auth'] = [$token, ""];

		$request = new Request("GET", $fullUrl, ['Range' => $range]);

		$this->response = $client->send($request, $options);
	}

	/**
	 * @Then /^Downloaded content should be "([^"]*)"$/
	 * @param string $content
	 */
	public function downloadedContentShouldBe($content){
		PHPUnit_Framework_Assert::assertEquals($content, (string)$this->response->getBody());
	}

	/**
	 * @Then /^Downloaded content when downloading file "([^"]*)" with range "([^"]*)" should be "([^"]*)"$/
	 * @param string $fileSource
	 * @param string $range
	 * @param string $content
	 */
	public function downloadedContentWhenDownloadindShouldBe($fileSource, $range, $content){
		$this->downloadFileWithRange($fileSource, $range);
		$this->downloadedContentShouldBe($content);
	}

	/**
	 * @When Downloading file :fileName
	 * @param string $fileName
	 */
	public function downloadingFile($fileName) {
		try {
			$this->response = $this->makeDavRequest($this->currentUser, 'GET', $fileName, []);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When user :user downloads the file :fileName
	 * @param string $user
	 * @param string $fileName
	 */
	public function userDownloadsTheFile($user, $fileName) {
		try {
			$this->response = $this->makeDavRequest($user, 'GET', $fileName, []);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Then The following headers should be set
	 * @param \Behat\Gherkin\Node\TableNode $table
	 * @throws \Exception
	 */
	public function theFollowingHeadersShouldBeSet(\Behat\Gherkin\Node\TableNode $table) {
		foreach($table->getTable() as $header) {
			$headerName = $header[0];
			$expectedHeaderValue = $header[1];
			$returnedHeader = $this->response->getHeader($headerName);
			if($returnedHeader !== $expectedHeaderValue) {
				throw new \Exception(
					sprintf(
						"Expected value '%s' for header '%s', got '%s'",
						$expectedHeaderValue,
						$headerName,
						$returnedHeader
					)
				);
			}
		}
	}

	/**
	 * @Then Downloaded content should start with :start
	 * @param int $start
	 * @throws \Exception
	 */
	public function downloadedContentShouldStartWith($start) {
		if(strpos($this->response->getBody()->getContents(), $start) !== 0) {
			throw new \Exception(
				sprintf(
					"Expected '%s', got '%s'",
					$start,
					$this->response->getBody()->getContents()
				)
			);
		}
	}

	/**
	 * @Then /^as "([^"]*)" gets properties of (file|folder|entry) "([^"]*)" with$/
	 * @param string $user
	 * @param string $path
	 * @param \Behat\Gherkin\Node\TableNode|null $propertiesTable
	 */
	public function asGetsPropertiesOfFolderWith($user, $elementType, $path, $propertiesTable) {
		$properties = null;
		if ($propertiesTable instanceof \Behat\Gherkin\Node\TableNode) {
			foreach ($propertiesTable->getRows() as $row) {
				$properties[] = $row[0];
			}
		}
		$this->response = $this->listFolder($user, $path, 0, $properties);
	}
	
	/**
	 * @Given as :arg1 gets a custom property :arg2 of file :arg3
	 * @param string $user
	 * @param string $propertyName
	 * @param string $path
	 */
	 public function asGetsPropertiesOfFile($user, $propertyName, $path){
		$client = $this->getSabreClient($user);
		 $properties = [
				$propertyName
		 ];
		$response = $client->propfind($this->makeSabrePath($user, $path), $properties);
		$this->response = $response;
	 }
	
	/**
	 * @Given /^"([^"]*)" sets property "([^"]*)" of (file|folder|entry) "([^"]*)" to "([^"]*)"$/
	 * @param string $user
	 * @param string $propertyName
	 * @param string $elementType
	 * @param string $path
	 * @param string $propertyValue
	 */
	public function asSetsPropertiesOfFolderWith($user, $propertyName, $elementType, $path, $propertyValue) {
		$client = $this->getSabreClient($user);
		$properties = [
				$propertyName => $propertyValue
		];
		$client->proppatch($this->makeSabrePath($user, $path), $properties);
	}
	
	/**
	 * @Then /^the response should contain a custom "([^"]*)" property with "([^"]*)"$/
	 * @param string $propertyName
	 * @param string $propertyValue
	 */
	public function theResponseShouldContainACustomPropertyWithValue($propertyName, $propertyValue, $table=null)
	{
		$keys = $this->response;
		if (!array_key_exists($propertyName, $keys)) {
			throw new \Exception("Cannot find property \"$propertyName\"");
		}
		if ($keys[$propertyName] !== $propertyValue) {
			throw new \Exception("\"$propertyName\" has a value \"${keys[$propertyName]}\" but \"$propertyValue\" expected");
		}
	}
	
	/**
	 * @Then /^as "([^"]*)" the (file|folder|entry) "([^"]*)" does not exist$/
	 * @param string $user
	 * @param string $path
	 * @param \Behat\Gherkin\Node\TableNode|null $propertiesTable
	 */
	public function asTheFileOrFolderDoesNotExist($user, $entry, $path) {
		$client = $this->getSabreClient($user);
		$response = $client->request('HEAD', $this->makeSabrePath($user, $path));
		if ($response['statusCode'] !== 404) {
			throw new \Exception($entry . ' "' . $path . '" expected to not exist (status code ' . $response['statusCode'] . ', expected 404)');
		}

		return $response;
	}

	/**
	 * @Then /^as "([^"]*)" the (file|folder|entry) "([^"]*)" exists$/
	 * @param string $user
	 * @param string $path
	 * @param \Behat\Gherkin\Node\TableNode|null $propertiesTable
	 */
	public function asTheFileOrFolderExists($user, $entry, $path) {
		$this->response = $this->listFolder($user, $path, 0);
	}

	/**
	 * @Then the single response should contain a property :key with value :value
	 * @param string $key
	 * @param string $expectedValue
	 * @throws \Exception
	 */
	public function theSingleResponseShouldContainAPropertyWithValue($key, $expectedValue) {
		$keys = $this->response;
		if (!array_key_exists($key, $keys)) {
			throw new \Exception("Cannot find property \"$key\" with \"$expectedValue\"");
		}

		$value = $keys[$key];
		if ($value instanceof ResourceType) {
			$value = $value->getValue();
			if (empty($value)) {
				$value = '';
			} else {
				$value = $value[0];
			}
		}

		if ($expectedValue === "a_comment_url"){
			if (preg_match("#^/remote.php/dav/comments/files/([0-9]+)$#", $value)) {
				return 0;
			} else {
				throw new \Exception("Property \"$key\" found with value \"$value\", expected \"$expectedValue\"");
			}
		}

		if ($value != $expectedValue) {
			throw new \Exception("Property \"$key\" found with value \"$value\", expected \"$expectedValue\"");
		}
	}

	/**
	 * @Then the response should contain a share-types property with
	 */
	public function theResponseShouldContainAShareTypesPropertyWith($table)
	{
		$keys = $this->response;
		if (!array_key_exists('{http://owncloud.org/ns}share-types', $keys)) {
			throw new \Exception("Cannot find property \"{http://owncloud.org/ns}share-types\"");
		}

		$foundTypes = [];
		$data = $keys['{http://owncloud.org/ns}share-types'];
		foreach ($data as $item) {
			if ($item['name'] !== '{http://owncloud.org/ns}share-type') {
				throw new \Exception('Invalid property found: "' . $item['name'] . '"');
			}

			$foundTypes[] = $item['value'];
		}

		foreach ($table->getRows() as $row) {
			$key = array_search($row[0], $foundTypes);
			if ($key === false) {
				throw new \Exception('Expected type ' . $row[0] . ' not found');
			}

			unset($foundTypes[$key]);
		}

		if ($foundTypes !== []) {
			throw new \Exception('Found more share types then specified: ' . $foundTypes);
		}
	}

	/**
	 * @Then the response should contain an empty property :property
	 * @param string $property
	 * @throws \Exception
	 */
	public function theResponseShouldContainAnEmptyProperty($property) {
		$properties = $this->response;
		if (!array_key_exists($property, $properties)) {
			throw new \Exception("Cannot find property \"$property\"");
		}

		if ($properties[$property] !== null) {
			throw new \Exception("Property \"$property\" is not empty");
		}
	}

	/*Returns the elements of a propfind, $folderDepth requires 1 to see elements without children*/
	public function listFolder($user, $path, $folderDepth, $properties = null){
		$client = $this->getSabreClient($user);
		if (!$properties) {
			$properties = [
				'{DAV:}getetag'
			];
		}

		$response = $client->propfind($this->makeSabrePath($user, $path), $properties, $folderDepth);

		return $response;
	}

	/* Returns the elements of a report command
	 * @param string $user
	 * @param string $path
	 * @param string $properties properties which needs to be included in the report
	 * @param string $filterRules filter-rules to choose what needs to appear in the report
	 */
	public function reportFolder($user, $path, $properties, $filterRules, $offset = null, $limit = null){
		$client = $this->getSabreClient($user);

		$body = '<?xml version="1.0" encoding="utf-8" ?>
					<oc:filter-files xmlns:a="DAV:" xmlns:oc="http://owncloud.org/ns" >
						<a:prop>
							' . $properties . '
						</a:prop>
						<oc:filter-rules>
							' . $filterRules . '
						</oc:filter-rules>';
		if (is_int($offset) || is_int($limit)) {
			$body .=	'
						<oc:search>';
			if (is_int($offset)) {
				$body .= "
							<oc:offset>${offset}</oc:offset>";
			}
			if (is_int($limit)) {
				$body .= "
							<oc:limit>${limit}</oc:limit>";
			}
			$body .=	'
						</oc:search>';
		}
		$body .= '
					</oc:filter-files>';

		$response = $client->request('REPORT', $this->makeSabrePath($user, $path), [], $body);
		$parsedResponse = $client->parseMultistatus($response['body']);
		return $parsedResponse;
	}

	/* Returns the elements of a report command special for comments
	 * @param string $user
	 * @param string $path
	 * @param string $properties properties which needs to be included in the report
	 * @param string $filterRules filter-rules to choose what needs to appear in the report
	 */
	public function reportElementComments($user, $path, $properties){
		$client = $this->getSabreClient($user);

		$body = '<?xml version="1.0" encoding="utf-8" ?>
							 <oc:filter-comments xmlns:a="DAV:" xmlns:oc="http://owncloud.org/ns" >
									' . $properties . '
							 </oc:filter-comments>';


		$response = $client->request('REPORT', $this->makeSabrePathNotForFiles($path), [], $body);

		$parsedResponse = $client->parseMultistatus($response['body']);
		return $parsedResponse;
	}

	public function makeSabrePath($user, $path) {
		return $this->encodePath($this->getDavFilesPath($user) . $path);
	}

	public function makeSabrePathNotForFiles($path) {
		return $this->encodePath($this->davPath . $path);
	}

	public function getSabreClient($user) {
		$fullUrl = substr($this->baseUrl, 0, -4);

		$settings = [
			'baseUri' => $fullUrl,
			'userName' => $user,
		];

		if ($user === 'admin') {
			$settings['password'] = $this->adminUser[1];
		} else {
			$settings['password'] = $this->regularUser;
		}
		$settings['authType'] = SClient::AUTH_BASIC;

		return new SClient($settings);
	}

	/**
	 * @Then /^user "([^"]*)" should see following elements$/
	 * @param string $user
	 * @param \Behat\Gherkin\Node\TableNode|null $expectedElements
	 */
	public function checkElementList($user, $expectedElements){
		$elementList = $this->listFolder($user, '/', 3);
		if ($expectedElements instanceof \Behat\Gherkin\Node\TableNode) {
			$elementRows = $expectedElements->getRows();
			$elementsSimplified = $this->simplifyArray($elementRows);
			foreach($elementsSimplified as $expectedElement) {
				$webdavPath = "/" . $this->getDavFilesPath($user) . $expectedElement;
				if (!array_key_exists($webdavPath,$elementList)){
					PHPUnit_Framework_Assert::fail("$webdavPath" . " is not in propfind answer");
				}
			}
		}
	}

	/**
	 * @When User :user uploads file :source to :destination
	 * @param string $user
	 * @param string $source
	 * @param string $destination
	 */
	public function userUploadsAFileTo($user, $source, $destination)
	{
		$file = \GuzzleHttp\Stream\Stream::factory(fopen($source, 'r'));
		try {
			$this->response = $this->makeDavRequest($user, "PUT", $destination, [], $file);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When User :user adds a file of :bytes bytes to :destination
	 * @param string $user
	 * @param string $bytes
	 * @param string $destination
	 */
	public function userAddsAFileTo($user, $bytes, $destination){
		$filename = "filespecificSize.txt";
		$this->createFileSpecificSize($filename, $bytes);
		PHPUnit_Framework_Assert::assertEquals(1, file_exists("work/$filename"));
		$this->userUploadsAFileTo($user, "work/$filename", $destination);
		$this->removeFile("work/", $filename);
		$expectedElements = new \Behat\Gherkin\Node\TableNode([["$destination"]]);
		$this->checkElementList($user, $expectedElements);
	}

	/**
	 * @When User :user uploads file with content :content to :destination
	 */
	public function userUploadsAFileWithContentTo($user, $content, $destination)
	{
		$file = \GuzzleHttp\Stream\Stream::factory($content);
		try {
			$this->response = $this->makeDavRequest($user, "PUT", $destination, [], $file);
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}


	/**
	 * @When user :user uploads file with checksum :checksum and content :content to :destination
	 * @param $user
	 * @param $checksum
	 * @param $content
	 * @param $destination
	 */
	public function userUploadsAFileWithChecksumAndContentTo($user, $checksum, $content, $destination)
	{
		$file = \GuzzleHttp\Stream\Stream::factory($content);
		try {
			$this->response = $this->makeDavRequest(
				$user,
				"PUT",
				$destination,
				['OC-Checksum' => $checksum],
				$file
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}


	/**
	 * @Given file :file  does not exist for user :user
	 * @param string $file
	 * @param $user
	 */
	public function fileDoesNotExist($file, $user)  {
		try {
			$this->response = $this->makeDavRequest($user, 'DELETE', $file, []);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When /^User "([^"]*)" deletes (file|folder) "([^"]*)"$/
	 * @param string $user
	 * @param string $type
	 * @param string $file
	 */
	public function userDeletesFile($user, $type, $file)  {
		try {
			$this->response = $this->makeDavRequest($user, 'DELETE', $file, []);
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Given User :user created a folder :destination
	 * @param string $user
	 * @param string $destination
	 */
	public function userCreatedAFolder($user, $destination){
		try {
			$destination = '/' . ltrim($destination, '/');
			$this->response = $this->makeDavRequest($user, "MKCOL", $destination, []);
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Given user :user uploads chunk file :num of :total with :data to :destination
	 * @param string $user
	 * @param int $num
	 * @param int $total
	 * @param string $data
	 * @param string $destination
	 */
	public function userUploadsChunkedFile($user, $num, $total, $data, $destination)
	{
		$num -= 1;
		$data = \GuzzleHttp\Stream\Stream::factory($data);
		$file = $destination . '-chunking-42-' . $total . '-' . $num;
		$this->makeDavRequest($user, 'PUT', $file, ['OC-Chunked' => '1'], $data,  "uploads");
	}

	/**
	 * @Given user :user creates a new chunking upload with id :id
	 */
	public function userCreatesANewChunkingUploadWithId($user, $id)
	{
		$destination = '/uploads/'.$user.'/'.$id;
		$this->makeDavRequest($user, 'MKCOL', $destination, [], null, "uploads");
	}

	/**
	 * @Given user :user uploads new chunk file :num with :data to id :id
	 */
	public function userUploadsNewChunkFileOfWithToId($user, $num, $data, $id)
	{
		$data = \GuzzleHttp\Stream\Stream::factory($data);
		$destination = '/uploads/'. $user .'/'. $id .'/' . $num;
		$this->makeDavRequest($user, 'PUT', $destination, [], $data, "uploads");
	}

	/**
	 * @Given user :user uploads new chunk file :num with :data to id :id with checksum :checksum
	 */
	public function userUploadsNewChunkFileOfWithToIdWithChecksum($user, $num, $data, $id, $checksum)
	{
		try {
			$data = \GuzzleHttp\Stream\Stream::factory($data);
			$destination = '/uploads/' . $user . '/' . $id . '/' . $num;
			$this->makeDavRequest(
				$user,
				'PUT',
				$destination,
				['OC-Checksum' => $checksum],
				$data,
				"uploads"
			);
		} catch (\GuzzleHttp\Exception\BadResponseException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @Given user :user moves new chunk file with id :id to :dest
	 */
	public function userMovesNewChunkFileWithIdToMychunkedfile($user, $id, $dest)
	{
		$source = '/uploads/' . $user . '/' . $id . '/.file';
		$destination = substr($this->baseUrl, 0, -4) . $this->getDavFilesPath($user) . $dest;
		$this->makeDavRequest($user, 'MOVE', $source, [
			'Destination' => $destination
		], null, "uploads");
	}


	/**
	 * @Given /^Downloading file "([^"]*)" as "([^"]*)"$/
	 */
	public function downloadingFileAs($fileName, $user) {
		try {
			$this->response = $this->makeDavRequest($user, 'GET', $fileName, []);
		} catch (\GuzzleHttp\Exception\ServerException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * URL encodes the given path but keeps the slashes
	 *
	 * @param string $path to encode
	 * @return string encoded path
	 */
	private function encodePath($path) {
		// slashes need to stay
		return str_replace('%2F', '/', rawurlencode($path));
	}

	/**
	 * @When user :user favorites element :path
	 */
	public function userFavoritesElement($user, $path){
		$this->response = $this->changeFavStateOfAnElement($user, $path, 1, 0, null);
	}

	/**
	 * @When user :user unfavorites element :path
	 */
	public function userUnfavoritesElement($user, $path){
		$this->response = $this->changeFavStateOfAnElement($user, $path, 0, 0, null);
	}

	/*Set the elements of a proppatch, $folderDepth requires 1 to see elements without children*/
	public function changeFavStateOfAnElement($user, $path, $favOrUnfav, $folderDepth, $properties = null){
		$fullUrl = substr($this->baseUrl, 0, -4);
		$settings = [
			'baseUri' => $fullUrl,
			'userName' => $user,
		];
		if ($user === 'admin') {
			$settings['password'] = $this->adminUser[1];
		} else {
			$settings['password'] = $this->regularUser;
		}
		$settings['authType'] = SClient::AUTH_BASIC;

		$client = new SClient($settings);
		if (!$properties) {
			$properties = [
				'{http://owncloud.org/ns}favorite' => $favOrUnfav
			];
		}

		$response = $client->proppatch($this->getDavFilesPath($user) . $path, $properties, $folderDepth);
		return $response;
	}

	/**
	 * @Given user :user stores etag of element :path
	 */
	public function userStoresEtagOfElement($user, $path){
		$propertiesTable = new \Behat\Gherkin\Node\TableNode([['{DAV:}getetag']]);
		$this->asGetsPropertiesOfFolderWith($user, NULL, $path, $propertiesTable);
		$pathETAG[$path] = $this->response['{DAV:}getetag'];
		$this->storedETAG[$user]= $pathETAG;
	}

	/**
	 * @Then etag of element :path of user :user has not changed
	 */
	public function checkIfETAGHasNotChanged($path, $user){
		$propertiesTable = new \Behat\Gherkin\Node\TableNode([['{DAV:}getetag']]);
		$this->asGetsPropertiesOfFolderWith($user, NULL, $path, $propertiesTable);
		PHPUnit_Framework_Assert::assertEquals($this->response['{DAV:}getetag'], $this->storedETAG[$user][$path]);
	}

	/**
	 * @Then etag of element :path of user :user has changed
	 */
	public function checkIfETAGHasChanged($path, $user){
		$propertiesTable = new \Behat\Gherkin\Node\TableNode([['{DAV:}getetag']]);
		$this->asGetsPropertiesOfFolderWith($user, NULL, $path, $propertiesTable);
		PHPUnit_Framework_Assert::assertNotEquals($this->response['{DAV:}getetag'], $this->storedETAG[$user][$path]);
	}

	/**
	 * @When Connecting to dav endpoint
	 */
	public function connectingToDavEndpoint() {
		try {
			$this->response = $this->makeDavRequest(null, 'PROPFIND', '', []);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Then there are no duplicate headers
	 */
	public function thereAreNoDuplicateHeaders() {
		$headers = $this->response->getHeaders();
		foreach ($headers as $headerName => $headerValues) {
			// if a header has multiple values, they must be different
			if (count($headerValues) > 1 && count(array_unique($headerValues)) < count($headerValues)) {
				throw new \Exception('Duplicate header found: ' . $headerName);
			}
		}
    }

    /**
	 * @Then /^user "([^"]*)" in folder "([^"]*)" should have favorited the following elements$/
	 * @param string $user
	 * @param string $folder
	 * @param \Behat\Gherkin\Node\TableNode|null $expectedElements
	 */
	public function checkFavoritedElements($user, $folder, $expectedElements){
		$this->checkFavoritedElementsPaginated($user, $folder, $expectedElements, null, null);
	}

    /**
	 * @Then /^user "([^"]*)" in folder "([^"]*)" should have favorited the following elements from offset ([\d*]) and limit ([\d*])$/
	 * @param string $user
	 * @param string $folder
	 * @param \Behat\Gherkin\Node\TableNode|null $expectedElements
	 * @param int $offset
	 * @param int $limit
	 */
	public function checkFavoritedElementsPaginated($user, $folder, $expectedElements, $offset, $limit){
		$elementList = $this->reportFolder($user,
											$folder,
											'<oc:favorite/>',
											'<oc:favorite>1</oc:favorite>');
		if ($expectedElements instanceof \Behat\Gherkin\Node\TableNode) {
			$elementRows = $expectedElements->getRows();
			$elementsSimplified = $this->simplifyArray($elementRows);
			foreach($elementsSimplified as $expectedElement) {
				$webdavPath = "/" . $this->getDavFilesPath($user) . $expectedElement;
				if (!array_key_exists($webdavPath,$elementList)){
					PHPUnit_Framework_Assert::fail("$webdavPath" . " is not in report answer");
				}
			}
		}
	}

	/**
	 * @When /^User "([^"]*)" deletes everything from folder "([^"]*)"$/
	 * @param string $user
	 * @param string $folder
	 */
	public function userDeletesEverythingInFolder($user, $folder)  {
		$elementList = $this->listFolder($user, $folder, 1);
		$elementListKeys = array_keys($elementList);
		array_shift($elementListKeys);
		$davPrefix =  "/" . $this->getDavFilesPath($user);
		foreach($elementListKeys as $element) {
			if (substr($element, 0, strlen($davPrefix)) == $davPrefix) {
				$element = substr($element, strlen($davPrefix));
			}
			$this->userDeletesFile($user, "element", $element);
		}
	}

	/**
	 * @param string $user
	 * @param string $path
	 * @return int
	 */
	private function getFileIdForPath($user, $path) {
		$propertiesTable = new \Behat\Gherkin\Node\TableNode([["{http://owncloud.org/ns}fileid"]]);
		$this->asGetsPropertiesOfFolderWith($user, 'file', $path, $propertiesTable);
		return (int) $this->response['{http://owncloud.org/ns}fileid'];
	}

	/**
	 * @Given /^User "([^"]*)" stores id of file "([^"]*)"$/
	 * @param string $user
	 * @param string $path
	 * @param string $fileid
	 * @return int
	 */
	public function userStoresFileIdForPath($user, $path) {
		$this->storedFileID = $this->getFileIdForPath($user, $path);
	}

	/**
	 * @Given /^User "([^"]*)" checks id of file "([^"]*)"$/
	 * @param string $user
	 * @param string $path
	 * @param string $fileid
	 * @return int
	 */
	public function userChecksFileIdForPath($user, $path) {
		$currentFileID = $this->getFileIdForPath($user, $path);
		PHPUnit_Framework_Assert::assertEquals($currentFileID, $this->storedFileID);
	}
}
