<?php

class curl_request
{
	public $options;

	public function __construct($default = true)
	{
		if($default)
			$this->set_default();
	}

	public function set_default()
	{
		$this->options[CURLOPT_RETURNTRANSFER] = true;
		$this->options[CURLOPT_FOLLOWLOCATION] = false;
		$this->options[CURLOPT_HEADER] = false;
		$this->options[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7";
		$this->options[CURLOPT_CONNECTTIMEOUT] = 60;
		$this->options[CURLOPT_TIMEOUT] = 60;
		$this->options[CURLOPT_CUSTOMREQUEST] = "GET";
		$this->options[CURLOPT_MAXREDIRS] = 4;
	}

	public function set_timeout($timeout)
	{
		$this->options[CURLOPT_CONNECTTIMEOUT] = $timeout;
		$this->options[CURLOPT_TIMEOUT] = $timeout;
	}

	public function set_options($options)
	{
		foreach($options as $key => $value)
			$this->options[$key] = $value;
	}

	public function set_option($key, $value)
	{
		$this->options[$key] = $value;
	}

	public function get_options()
	{
		return $this->options;
	}

	public function set_url($url)
	{
		$this->options[CURLOPT_URL] = $url;

		if(strpos($url, "https") === 0)
			$this->options[CURLOPT_SSL_VERIFYPEER] = false;
	}

	public function set_referer($url)
	{
		$this->options[CURLOPT_REFERER] = $url;
	}

	public function enable_redirects()
	{
		$this->options[CURLOPT_FOLLOWLOCATION] = true;
	}

	public function set_authentication($username, $password)
	{
		$this->options[CURLOPT_USERPWD] = $username . ':' . $password;
		$this->options[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
	}

	public function set_cookies($file_path)
	{
		// clear the cookies
		//fclose(fopen($file_path, 'w'));
	
		$this->options[CURLOPT_COOKIEJAR] = $file_path;
		$this->options[CURLOPT_COOKIEFILE] = $file_path;
	}

	public function set_proxy($type, $host, $port, $username = NULL, $password = NULL)
	{
		if($type == "http")
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
		else if($type == "socks4")
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
		else if($type == "socks5")
			$this->options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;

		$this->options[CURLOPT_PROXY] = $host . ":" . $port;
	
		if($username && $password)
			$this->options[CURLOPT_PROXYUSERPWD] = $username . ":" . $password;
	}

	public function set_post($data)
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "POST";
		$this->options[CURLOPT_POST] = true;
		$this->options[CURLOPT_POSTFIELDS] = $data;
	}

	public function set_get()
	{
		$this->options[CURLOPT_CUSTOMREQUEST] = "GET";
		$this->options[CURLOPT_POST] = false;
		$this->options[CURLOPT_POSTFIELDS] = '';
	}

	public function set_header($data)
	{
		$this->options[CURLOPT_HEADER] = true;
		$this->options[CURLOPT_HTTPHEADER] = $data;
	}
};

class curl_response
{
	public $data;
	public $request;
	public $info;
	public $status_code;
	public $header_list;

	public function __construct()
	{
		$this->data = '';
		$this->info = '';
		$this->status_code = 0;
		$this->request = NULL;
	}
}

class curl
{
	public function __construct()
	{
		$this->settings = array("max_connections" => 10);
		$this->connections = array();
		$this->active = false;
		$this->timeout = 15;
		$this->active = true;
		$this->job_list = array();

		$this->config_connection_max = 20;

		$this->mc = curl_multi_init();
	}

	public function run(&$request, $callback = NULL)
	{
		$c = curl_init();

		foreach($request->get_options() as $key => $value)
			curl_setopt($c, $key, $value);

		// we've got a callback so let's go asynchronous
		if($callback)
		{
			$this->job_list[] = array("request" => $request, "handle" => $c, "callback" => $callback);

			return false;
		}
		// nope, no asio for us today
		else
		{
			$r = new curl_response();

			$header_list = array();

			curl_setopt($c, CURLOPT_HEADERFUNCTION, function($c, $header) use(&$header_list)
			{
				if(strstr($header, ":"))
				{
					$h = explode(":", $header);

					$key = $h[0];

					array_shift($h);

					$header_list[$key] = implode(":", $h);
				}

				return strlen($header);
			});

			ob_start();
			$r->data = curl_exec($c);
			ob_end_clean();

			$r->request = $request;
			$r->info = curl_getinfo($c);
			$r->status_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
			$r->header_list = $header_list;

			curl_close($c);

			return $r;
		}

		$this->last_request = $request;
	}

	public function update()
	{
		while(count($this->connection_list) < $this->config_connection_max && count($this->job_list) > 0)
		{
			$job = array_shift($this->job_list);

			$this->connection_list[$job['handle']] = array("request" => $job['request'], "handle" => $job['handle'], "callback" => $job['callback']);

			curl_multi_add_handle($this->mc, $job['handle']);

			$this->active = true;
		}

		if(!$this->active)
			return;

		while(($status = curl_multi_exec($this->mc, $running)) == CURLM_CALL_MULTI_PERFORM) usleep(20000);
		
		if($status != CURLM_OK) break; 

		while($item = curl_multi_info_read($this->mc))
		{
			$handle = $item['handle'];

			$connection = $this->connection_list[$handle];
		
			$info = curl_getinfo($handle);

			$data = curl_multi_getcontent($handle);

			curl_multi_remove_handle($this->mc, $handle);

			unset($this->connection_list[$handle]);

			$response = new curl_response();
			$response->request = $connection['request'];
			$response->data = $data;
			$response->info = $info;
			$response->status_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

			$this->last_response = $response;

			if($connection['callback'] != NULL)
				if(phpversion() >= 5.3)
					$connection['callback']($response);
				else
					call_user_func_array($connection['callback'], array($response));

			usleep(20000);
		}
		
		if(count($this->connection_list) == 0)
			$this->active = false;
	}

	public function get_last_request()
	{
		return $this->last_request;
	}

	public function get_last_response()
	{
		return $this->last_response;
	}

	public function get()
	{
		return $this->mc;
	}

	protected $settings;
	protected $mc;
	protected $active;
	protected $connection_list;
	protected $timeout;
	protected $last_request;
	protected $last_response;
}

?>