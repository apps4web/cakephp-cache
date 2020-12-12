<?php

namespace Cache\Test\TestCase\Controller\Component;

use App\Controller\CacheComponentTestController;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\Network\Response;
use Cake\TestSuite\TestCase;

class CacheComponentTest extends TestCase {

	/**
	 * @var \Cake\Controller\Controller
	 */
	protected $Controller;

	/**
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->Controller = new CacheComponentTestController();
		$this->Controller->startupProcess();

		$this->Controller->request->getSession()->delete('CacheMessage');

		$this->Controller->Cache->setConfig('debug', true);
		$this->Controller->Cache->setConfig('force', true);
	}

	/**
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();

		unset($this->Controller->Cache);
		unset($this->Controller);
	}

	/**
	 * @return void
	 */
	public function testAction() {
		$request = new ServerRequest([
			'environment' =>[
				'REQUEST_METHOD' => 'GET',
			]
		]);

		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . '_root.html';
		$result = file_get_contents($file);
		$expected = '<!--cachetime:0;ext:html-->Foo bar';
		$this->assertEquals($expected, $result);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithCacheTime() {
		$request = new ServerRequest([
			'environment' =>[
				'REQUEST_METHOD' => 'GET',
			],
		]);

		$this->Controller->setRequest($request);
		$this->Controller->Cache->setConfig('duration', DAY);
		$this->Controller->response = $this->getResponseMock(['getBody']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . '_root.html';
		$result = file_get_contents($file);
		$expectedTime = time() + DAY;
		$expected = '<!--cachetime:' . substr($expectedTime, 0, -1);
		$this->assertTextStartsWith($expected, $result);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithExt() {
		$request = new ServerRequest([
			'url' => '/foo/bar/baz.json?x=y',
			'environment' => [
				'REQUEST_METHOD' => 'GET',
			],
		]);
		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody', 'getType']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));
		$this->Controller->response->expects($this->once())
			->method('getType')
			->will($this->returnValue('application/json'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . 'foo-bar-baz-json-x-y.html';
		$result = file_get_contents($file);
		$expected = '<!--cachetime:0;ext:json-->Foo bar';
		$this->assertEquals($expected, $result);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithWhitelist() {
		$this->Controller->Cache->setConfig('actions', ['baz']);

		$request = new ServerRequest([
			'url' => '/foo/bar',
			'params' => [
				'action' => 'bar',
			],
			'environment' => [
				'REQUEST_METHOD' => 'GET',
			],
		]);

		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody']);
		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . 'foo-bar.html';
		$this->assertFalse(file_exists($file));

		$request = new ServerRequest([
			'url' => '/foo/baz',
			'params' => [
				'action' => 'baz',
			],
			'environment' => [
				'REQUEST_METHOD' => 'GET',
			],
		]);
		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody']);
		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . 'foo-baz.html';
		$this->assertFileExists($file);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithCompress() {
		$request = new ServerRequest([
			'environment' =>[
				'REQUEST_METHOD' => 'GET',
			],
		]);

		$this->Controller->setRequest($request);
		$this->Controller->Cache->setConfig('compress', true);

		$this->Controller->response = $this->getResponseMock(['getBody']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar <!-- Some comment --> and

			more text.'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . '_root.html';
		$result = file_get_contents($file);
		$expected = '<!--cachetime:0;ext:html-->Foo bar and more text.';
		$this->assertEquals($expected, $result);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithCompressCallback() {
		$request = new ServerRequest([
			'environment' =>[
				'REQUEST_METHOD' => 'GET',
			],
		]);

		$this->Controller->setRequest($request);
		$this->Controller->Cache->setConfig('compress', function ($content) {
			$content = str_replace('bar', 'b', $content);
			return $content;
		});

		$this->Controller->response = $this->getResponseMock(['getBody']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar.'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . '_root.html';
		$result = file_get_contents($file);
		$expected = '<!--cachetime:0;ext:html-->Foo b.';
		$this->assertEquals($expected, $result);

		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testFileWithBasePath() {
		$request = new ServerRequest([
			'url' => '/myapp/pages/view/1',
			'base' => '/myapp',
			'environment' =>[
				'REQUEST_METHOD' => 'GET',
			]
		]);

		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody', 'getType']);
		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));
		$this->Controller->response->expects($this->once())
			->method('getType')
			->will($this->returnValue('text/html'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);
		$file = CACHE . 'views' . DS . 'pages-view-1.html';
		$this->assertFileExists($file);
		unlink($file);
	}

	/**
	 * @return void
	 */
	public function testActionWithNonGet() {
		
		$request = new ServerRequest([
			'environment' => [
				'REQUEST_METHOD' => 'POST',
			],
		]);

		$this->assertTrue($request->is('POST'));

		$this->Controller->setRequest($request);
		$this->Controller->response = $this->getResponseMock(['getBody']);

		$this->Controller->response->expects($this->once())
			->method('getBody')
			->will($this->returnValue('Foo bar'));

		$event = new Event('Controller.shutdown', $this->Controller);
		$this->Controller->Cache->shutdown($event);

		$file = CACHE . 'views' . DS . '_root.html';

		$this->assertFileNotExists($file, 'POST should not cache request');

		@unlink($file);
	}

	/**
	 * @param array $methods
	 *
	 * @return \Cake\Http\Client\Response|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getResponseMock(array $methods) {
		return $this->getMockBuilder(Response::class)->setMethods($methods)->getMock();
	}

}
