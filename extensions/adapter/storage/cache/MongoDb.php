<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_mongodb_cache_adapter\extensions\adapter\storage\cache;

use lithium\core\Libraries;
use lithium\data\Connections;
use MongoDate;

/**
 * A MongoDb-based cache.
 *
 * This MongoDb adapter provides support for `write`, `read`, `delete`, `increment`
 * 'decrement', and `clear` cache functionality, as well as allowing the first four
 * methods to be filtered as per the Lithium filtering system.
 *
 * @see lithium\storage\cache\adapter
 */
class MongoDb extends \lithium\core\Object {

	/**
	 * `Mongo` object instance used by this adapter.
	 *
	 * @var object
	 */
	private $_server = null;

	/**
	 * `MongoDb` object instance used by this adapter.
	 *
	 * @var object
	 */
	private $_connection = null;

	/**
	 * `MongoCollection` object instance used by this adapter.
	 *
	 * @var object
	 */
	private $_collection = null;

	/**
	 * Class constructor.
	 *
	 * @see lithium\storage\Cache::config()
	 * @param array $config Configuration parameters for this cache adapter. These settings are
	 *        indexed by name and queryable through `Cache::config('name')`.
	 *        The defaults are:
	 *        - 'connection' : MongoDb connection name from connections.php .
	 *        - 'database' : Database name; defaults to the name of the app
	 *        - 'collection' : Collection name; defaults to cache
	 *        - 'capped': If the generated collection is capped; defaults to true
	 *        - 'size': If capped, the maximum size in bytes; defaults to 100000
	 *        - 'max': If capped, the maximum number of entries; defaults to null (unlimited)
	 *        - 'background': build the index; defaults to true
	 *        - 'expiry' : Default expiry time used if none is explicitly set when calling `Cache::write()`.
	 *        = 'useTtlCollection': Use MongoDB 2.2+ TTL collections for expiration (instead of adding an expiration attribute to cache entries and simply excluding them from find and update); defaults to false
	 */
	public function __construct(array $config = array()) {
		$defaults = array(
			'connection' => 'default',
			'database' => basename(LITHIUM_APP_PATH),
			'collection' => 'cache',
			'capped' => true,
			'size' => 100000,
			'max' => null,
			'background' => true,
			'expiry' => '+1 hour',
			'useTtlCollection' => false
		);
		parent::__construct($config + $defaults);
	}

	/**
	 * Handles the actual `MongoDb` connection and server connection adding for the adapter
	 * constructor.
	 *
	 * @return void
	 */
	protected function _init() {
		$this->_server = $this->_server ?: Connections::get($this->_config['connection']);
		$this->_connection = $this->_server->selectDb($this->_config['database']);
		$this->_connection->command(array('create' => $this->_config['collection'], 'capped' => $this->_config['capped'], 'size' => $this->_config['capped'], 'max' => $this->_config['capped']));
		$this->_collection = $this->_connection->selectCollection('cache');
		$this->_collection->ensureIndex(array('key' => 1, 'expires'), array('unique' => true, 'dropDups' => true, 'background' => $this->_config['capped']));
	}

	/**
	 * Write value(s) to the cache.
	 *
	 * @param string $key The key to uniquely identify the cached item.
	 * @param mixed $data The value to be cached.
	 * @param null|string $expiry A strtotime() compatible cache time. If no expiry time is set,
	 *        then the default cache expiration time set with the cache configuration will be used.
	 * @return closure Function returning boolean `true` on successful write, `false` otherwise.
	 */
	public function write($key, $data, $expiry = null) {
		$collection =& $this->_collection;
		$expiry = ($expiry) ?: $this->_config['expiry'];
		return function($self, $params) use (&$collection, $expiry) {
			return $collection->insert(array('key' => $params['key'], 'value' => $params['data'], 'expires' => new MongoDate(strtotime($expiry))));
		};
	}

	/**
	 * Read value(s) from the cache.
	 *
	 * @param string $key The key to uniquely identify the cached item.
	 * @return closure Function returning cached value if successful, `false` otherwise.
	 */
	public function read($key) {
		$collection =& $this->_collection;
		return function($self, $params) use (&$collection) {
			$entry = $collection->findOne(array('key' => $params['key'], 'expires' => array('$gte' => new MongoDb())), array('value' => true));
			return $entry['value'];
		};
	}

	/**
	 * Delete an entry from the cache.
	 *
	 * @param string $key The key to uniquely identify the cached item.
	 * @return closure Function returning boolean `true` on successful delete, `false` otherwise.
	 */
	public function delete($key) {
		$collection =& $this->_collection;
		return function($self, $params) use (&$collection) {
			$entry = $collection->remove(array('key' => $params['key']));
			return $entry;
		};
	}

	/**
		 * Performs an atomic increment operation on specified numeric cache item.
		 *
		 * Note that if the value of the specified key is *not* an integer, the increment
		 * operation will have no effect whatsoever. Redis chooses to not typecast values
		 * to integers when performing an atomic increment operation.
		 *
		 * @param string $key Key of numeric cache item to increment
		 * @param integer $offset Offset to increment - defaults to 1.
		 * @return closure Function returning item's new value on successful increment, else `false`
		 */
	public function increment($key, $offset = 1) {
		$collection =& $this->_collection;
		return function($self, $params) use (&$collection) {
			$entry = $collection->update(array('key' => $params['key'], 'expires' => array('$gte' => new MongoDb())), array('$inc' => array("value" => $params['offset'])), array("upsert" => true));
			return $entry;
		};
	}

	/**
	 * Performs an atomic decrement operation on specified numeric cache item.
	 *
	 * Note that if the value of the specified key is *not* an integer, the decrement
	 * operation will have no effect whatsoever. Redis chooses to not typecast values
	 * to integers when performing an atomic decrement operation.
	 *
	 * @param string $key Key of numeric cache item to decrement
	 * @param integer $offset Offset to decrement - defaults to 1.
	 * @return closure Function returning item's new value on successful decrement, else `false`
	 */
	public function decrement($key, $offset = 1) {
		$collection =& $this->_collection;
		return function($self, $params) use (&$collection, $offset) {
			$entry = $collection->update(array('key' => $params['key'], 'expires' => array('$gte' => new MongoDb())), array('$inc' => array("value" => $params['offset'] * -1)), array("upsert" => true));
			return $entry;
		};
	}

	/**
	 * Clears user-space cache.
	 *
	 * @return mixed True on successful clear, false otherwise.
	 */
	public function clear() {
		$this->_collection->drop();
		return true;
	}

	/**
	 * Determines if the Mongo extension has been installed
	 *
	 * @return boolean Returns `true` if the Redis extension is enabled, `false` otherwise.
	 */
	public static function enabled() {
		return extension_loaded('mongo');
	}
}

?>
