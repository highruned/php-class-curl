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
			$this->connection_list[$c] = array();

			$this->connection_list[$c]['request'] = $request;
			$this->connection_list[$c]['handle'] = $c;
			$this->connection_list[$c]['callback'] = $callback;

			curl_multi_add_handle($this->mc, $c);

			$this->active = true;

			return false;
		}
		// nope, no asio for us today
		else
		{
			$r = new curl_response();

			ob_start();
			$r->data = curl_exec($c);
			ob_end_clean();

			$r->request = $request;
			$r->info = curl_getinfo($c);
			$r->status_code = curl_getinfo($c, CURLINFO_HTTP_CODE);

			curl_close($c);

			return $r;
		}

		$this->last_request = $request;
	}

	public function update()
	{
		if(!$this->active)
			return;

		while(($status = curl_multi_exec($this->mc, $running)) == CURLM_CALL_MULTI_PERFORM) usleep(20000);
		
		if($status != CURLM_OK) break; 

		while($item = curl_multi_info_read($this->mc))
		{
			$c = $item['handle'];

			$connection = $this->connection_list[$c];
		
			$i = curl_getinfo($c);

			$d = curl_multi_getcontent($c);

			curl_multi_remove_handle($this->mc, $c);

			unset($this->connection_list[$c]);

			$response = new curl_response();
			$response->request = $connection['request'];
			$response->data = $d;
			$response->info = $i;
			$response->status_code = curl_getinfo($c, CURLINFO_HTTP_CODE);

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