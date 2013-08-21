<?php
/**
 * PHP Client for the Gearman Administrative Protocol
 * @package GearmanAdmin
 * @version 1.0
 * @author Jon Skarpeteig <jon.skarpeteig@gmail.com>
 *
 */
class GearmanAdmin
{

	/**
	 * Hostname of Gearman server
	 * @var string
	 */
	private $hostname;

	/**
	 * Port of Gearman server
	 * @var numeric
	 */
	private $port;

	/**
	 * Timeout against Gearman server (in seconds)
	 * @var numeric
	 */
	private $timeout;

	/**
	 * 
	 * @param string $hostname Hostname of Gearman server (optional)
	 * @param numeric $port Port of Gearman server (optional)
	 * @param numeric $timeout (optional)
	 */
	public function __construct($hostname = 'localhost', $port = 4730, $timeout = 1)
	{
		$this->setHostname($hostname)->setPort($port)->setTimeout($timeout);
	}

	private $lastError;

	/**
	 * Request a list of all registered functions on the server.  Next to
	 * each function is the number of jobs in the queue, the number of
	 * running jobs, and the number of capable workers.
	 * @return array|false List of registered functions. False on failure
	 */
	public function status()
	{
		$result = $this->sendCommand('status');
		if (false === $result) {
			return false;
		}
		$status = array();
		foreach ($result as $t) {
			list($function, $inQueue, $jobsRunning, $capable) = explode("\t", $t);
			$status[$function] = array('function' => $function, 'in_queue' => $inQueue, 'jobs_running' => $jobsRunning,
					'capable_workers' => $capable
			);
		}
		return $status;
	}

	/**
	 * Request the version of the server.
	 * @return string|false String value of version. False on failure
	 */
	public function version()
	{
		$result = $this->sendCommand('version');
		if (false === $result) {
			return false;
		}
		$firstLine = array_pop($result);
		$version = substr($firstLine, 3);
		return $version;
	}

	/**
	 * Request a list of all workers, their file descriptors,
	 * their IPs, their IDs, and a list of registered functions they can
	 * perform. 
	 * @return array|false Array of workers. False on failure
	 */
	public function workers()
	{
		$result = $this->sendCommand('workers');
		if (false === $result) {
			return false;
		}
		$workers = array();
		foreach ($result as $t) {
			// FD IP-ADDRESS CLIENT-ID : FUNCTION
			if (preg_match("~^(\d+)[ \t](.*?)[ \t](.*?) : ?(.*)~", $t, $matches)) {
				$fd = $matches[1];
				$workers[] = array('file_descriptor' => $fd, 'host' => $matches[2], 'job_handle' => $matches[3],
						'commands' => $matches[4],
				);
			}
		}
		return $workers;
	}

	/**
	 * This sets the maximum queue size for a function. If no size is
	 * given, the default is used. If the size is negative, then the queue
	 * is set to be unlimited.
	 * @param string $function
	 * @param numeric $maxQueueSize
	 * @return boolean True on success, false on failure
	 */
	public function maxQueue($function, $maxQueueSize = null)
	{
		$command = "maxqueue $function $maxQueueSize";
		$result = $this->sendCommand($command);
		if (false === $result) {
			return false;
		}
		$firstLine = array_pop($result);
		return ('OK' == $firstLine);
	}

	/**
	 * Shutdown the server. If the optional "graceful" argument is used,
	 * close the listening socket and let all existing connections
	 * complete.
	 * @param boolean $graceful
	 * @return boolean True on success, false on failure
	 */
	public function shutdown($graceful = false)
	{
		$command = 'shutdown';
		if ($graceful) {
			$command .= ' graceful';
		}
		$result = $this->sendCommand($command);
		if (false === $result) {
			return false;
		}
		$firstLine = array_pop($result);
		return ('OK' == $firstLine);
	}

	/**
	 * Connect to port, and return socket resource
	 * @return resource|false Returns a file pointer which may be used
	 * together with the other file functions (such as
	 * fgets, fgetss,
	 * fwrite, fclose, and
	 * feof). If the call fails, it will return false
	 */
	protected function connect()
	{
		$hostname = $this->getHostname();
		$port = $this->getPort();
		$timeout = $this->getTimeout();

		// Connect to server
		$errno = null;
		$errstr = null;
		$socket = @fsockopen('tcp://' . $hostname, $port, $errno, $errstr, $timeout);
		$this->lastError = $errstr;
		return $socket;
	}

	/**
	 * Open a socket to the Gearman server, and send request as string
	 * @param string $command
	 * @throws \RuntimeException
	 * @return string|false String response from server. False on failure
	 */
	protected function sendCommand($command)
	{
		// Connect
		$socket = $this->connect();
		if (!is_resource($socket) || feof($socket)) {
			return false;
		}

		// Send request
		if (false === fwrite($socket, $command . "\n")) {
			throw new \RuntimeException(__METHOD__ . ' Writing request to socket failed');
		}

		// Read response
		$timeout = $this->getTimeout();
		stream_set_timeout($socket, $timeout);
		$firstLine = fgets($socket);
		if ($firstLine === false) {
			throw new \RuntimeException(__METHOD__ . ' Reading of socket failed');
		}

		// Validate
		$firstLine = trim($firstLine);
		if (preg_match('/^ERR/', $firstLine)) {
			list(, $errcode, $errstr) = explode(' ', $firstLine);
			throw new \RuntimeException(
					__METHOD__ . ' Server returned error: ' . $errcode . ': ' . urldecode($errstr), $errcode);
		}

		// Read the rest and return
		$data[] = $firstLine;
		do {
			$info = stream_get_meta_data($socket);
			$line = trim(fgets($socket, 1024));
			if ($line == '.' || empty($line)) {
				break;
			}
			$data[] = $line;
		} while ($line && !$info['timed_out']);
		fclose($socket);
		return $data;
	}

	/**
	 * Return the last error message
	 * @return string|null
	 */
	public function getLastError()
	{
		return $this->lastError;
	}

	/**
	 * Get hostname or IP for Gearman server
	 * @return string|null
	 */
	public function getHostname()
	{
		return $this->hostname;
	}

	/**
	 * Set hostname or IP for Gearman server
	 * @param string $hostname
	 * @return GearmanAdmin Provides fluent interface
	 */
	public function setHostname($hostname)
	{
		if (!is_string($hostname)) {
			throw new \InvalidArgumentException(__METHOD__ . ' Expected hostname to be of type string');
		}
		$this->hostname = $hostname;
		return $this;
	}

	/**
	 * Get port for Gearman server
	 * @return numeric|null
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * Set port for Gearman server
	 * @param numeric $port
	 * @return GearmanAdmin Provides fluent interface
	 */
	public function setPort($port)
	{
		if (!is_numeric($port)) {
			throw new \InvalidArgumentException(__METHOD__ . ' Expected port value to be numeric');
		}
		$this->port = $port;
		return $this;
	}

	/**
	 * Get timeout (in seconds)
	 * @return float|null
	 */
	public function getTimeout()
	{
		return $this->timeout;
	}

	/**
	 * Set timeout (in seconds)
	 * @param float $timeout
	 * @return GearmanAdmin Provides fluent interface
	 */
	public function setTimeout($timeout)
	{
		if (!is_numeric($timeout)) {
			throw new \InvalidArgumentException(__METHOD__ . ' Expected timeout to be a numeric value');
		}
		$this->timeout = $timeout;
		return $this;
	}
}
