<?php
class GearmanAdminTest extends PHPUnit_Framework_TestCase
{
	public function testSettersAndGetters()
	{
		$gearmanAdmin = new GearmanAdmin();
		$result = $gearmanAdmin->setHostname('dummyhost');
		$this->assertInstanceOf('GearmanAdmin', $result);
		$this->assertEquals('dummyhost', $gearmanAdmin->getHostname());
		$result = $gearmanAdmin->setPort(12345);
		$this->assertInstanceOf('GearmanAdmin', $result);
		$this->assertEquals(12345, $gearmanAdmin->getPort());
		$result = $gearmanAdmin->setTimeout(60.0);
		$this->assertInstanceOf('GearmanAdmin', $result);
		$this->assertEquals(60.0, $gearmanAdmin->getTimeout());

		$gearmanAdmin2 = new GearmanAdmin('dummy2', 54321);
		$this->assertEquals('dummy2', $gearmanAdmin2->getHostname());
		$this->assertEquals(54321, $gearmanAdmin2->getPort());
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testSetInvalidHostnameThrowsException()
	{
		$gearmanAdmin = new GearmanAdmin();
		$gearmanAdmin->setHostname(array('myhost.com'));
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testSetInvalidPortThrowsException()
	{
		$gearmanAdmin = new GearmanAdmin();
		$gearmanAdmin->setPort('twelwe');
	}

	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testSetInvalidTimeoutThrowsException()
	{
		$gearmanAdmin = new GearmanAdmin();
		$gearmanAdmin->setTimeout('forever');
	}

	public function testCommands()
	{
		$this->markTestSkipped('Requires a running Gearman server, unless a better approach is available');
		$gearmanAdmin = new GearmanAdmin();
		$version = $gearmanAdmin->version();
		$this->assertInternalType('string', $version);
		$this->assertTrue(false !== $gearmanAdmin->status());
		$this->assertTrue(false !== $gearmanAdmin->workers());
		$this->assertTrue($gearmanAdmin->maxQueue('test'));
		$this->assertEmpty($gearmanAdmin->getLastError());
		$this->assertTrue($gearmanAdmin->shutdown());
		$this->assertFalse($gearmanAdmin->version());
	}
}
