<?php

namespace li3_mongodb_cache\tests\cases\extensions\adapter\storage\cache;

use lithium\storage\Cache;

class MongoDbCacheTest extends \lithium\test\Unit {

	public $cachedName = 'mongodb_cache_test_foo';

	public $defaultOptions = array(
		'adapter' => 'MongoDb',
		'database' => 'database_test',
		'collection' => 'collection_test',
		'expiry' => '+10 days',
		'capped' => false
	);

	public function setUp() {
		Cache::config(array(
			$this->cachedName => $this->defaultOptions,
		));
		Cache::clear($this->cachedName);
	}

	public function tearDown() {
		Cache::clear($this->cachedName);
	}

	public function testBasicReadWriteSuccess() {
		$value = 'foobar';
		Cache::write($this->cachedName, 'foo', $value);
		$result = Cache::read($this->cachedName, 'foo');
		$this->assertEqual($value, $result);
	}

	public function testOverwriteValue() {
		$value = 'foobar';
		Cache::write($this->cachedName, 'not_foobar', $value);
		Cache::write($this->cachedName, 'foo', $value);
		$result = Cache::read($this->cachedName, 'foo');
		$this->assertEqual($value, $result);
	}

	public function testReadNull() {
		$result = Cache::read($this->cachedName, 'foobar');
		$this->assertNull($result);
	}

	public function testDelete() {
		Cache::write($this->cachedName, 'foo', 'bar');
		Cache::write($this->cachedName, 'foobar', 'baz');

		$result = Cache::read($this->cachedName, 'foo');
		$this->assertEqual('bar', $result);

		$result = Cache::read($this->cachedName, 'foobar');
		$this->assertEqual('baz', $result);

		Cache::delete($this->cachedName, 'foobar');

		$result = Cache::read($this->cachedName, 'foo');
		$this->assertEqual('bar', $result);

		$result = Cache::read($this->cachedName, 'foobar');
		$this->assertEqual(null, $result);
	}

	public function testClear() {
		Cache::write($this->cachedName, 'foo', 'bar');
		Cache::write($this->cachedName, 'foobar', 'baz');
		Cache::clear($this->cachedName);

		$result = Cache::read($this->cachedName, 'foo');
		$this->assertNull($result);

		$result = Cache::read($this->cachedName, 'foobar');
		$this->assertNull($result);
	}

	public function testExpiresQuick() {
		Cache::write($this->cachedName, 'foo', 'bar', '+5 second');
		$this->assertEqual('bar', Cache::read($this->cachedName, 'foo'));
		sleep(10);
		$this->assertNull(Cache::read($this->cachedName, 'foo'));
	}

}