<?php

class curl_response
{
	public $data;
	public $info;
	public $status_code;

	public function __construct()
	{
		$this->data = "";
		$this->info = "";
		$this->status_code = 0;
	}
}

class curl
{
	public function __construct()
	{
		$this->c = curl_init();

		$this->set_default();
	}

	public function set_url($url)
	{
		curl_setopt($this->c, CURLOPT_URL, $url);

		if(strpos($url, "https") === 0)
			curl_setopt($this->c, CURLOPT_SSL_VERIFYPEER, false);
	}

	public function set_default()
	{
		curl_setopt($this->c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->c, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($this->c, CURLOPT_HEADER, false);
		curl_setopt($this->c, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.9.1.7) Gecko/20091221 Firefox/3.5.7");
		curl_setopt($this->c, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($this->c, CURLOPT_TIMEOUT, 60);
		curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($this->c, CURLOPT_MAXREDIRS, 4);
	}

	public function run()
	{
		$r = new curl_response();

		ob_start();
		$r->data = curl_exec($this->c);
		ob_end_clean();

		$r->status_code = curl_getinfo($this->c, CURLINFO_HTTP_CODE);

		return $r;
	}

	public function set_cookies($file_path)
	{
		// clear the cookies
		fclose(fopen($file_path, 'w'));
	
		curl_setopt($this->c, CURLOPT_COOKIEJAR, $file_path);
		curl_setopt($this->c, CURLOPT_COOKIEFILE, $file_path);
	}

	public function set_proxy($proxy_ip, $proxy_port, $proxy_username, $proxy_password)
	{
		curl_setopt($this->c, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
		curl_setopt($this->c, CURLOPT_PROXY, $proxy_ip . ":" . $proxy_port);
	
		if(isset($proxy_username) && isset($proxy_password))
			curl_setopt($this->c, CURLOPT_PROXYUSERPWD, $proxy_username . ":" . $proxy_password);
	}

	public function set_post($data)
	{
		curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $data);
	}

	public function set_get()
	{
		curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, "GET");
		curl_setopt($this->c, CURLOPT_POST, false);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, '');
	}

	public function set_authentication($username, $password)
	{
		curl_setopt($this->c, CURLOPT_USERPWD, $username . ':' . $password);
		curl_setopt($this->c, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	}
	
	public function get()
	{
		return $this->c;
	}

	protected $c;
}

class multicurl
{
	public function __construct()
	{
		$this->max_connections = 10;
		$this->connections = array();
		$this->mc = curl_multi_init();
		$this->active = false;
		$this->timeout = 15;
		$this->active = true;
	}

	public function add($c, $callback)
	{
		$this->connections[$c->get()] = array();

		$this->connections[$c->get()]["handle"] = $c;
		$this->connections[$c->get()]["callback"] = $callback;

		curl_multi_add_handle($this->mc, $c->get());

		$this->active = true;
	}

	public function run()
	{
		if(!$this->active)
			return;

		if(curl_multi_select($this->mc, 0) != -1)
		{
			while(($status = curl_multi_exec($this->mc, $running)) == CURLM_CALL_MULTI_PERFORM) usleep(20000);
		
			if($status != CURLM_OK) break; 

			while($item = curl_multi_info_read($this->mc))
			{
				$c = $item["handle"];

				$connection = $this->connections[$c];
		
				$i = curl_getinfo($c);

				$d = curl_multi_getcontent($c);

				curl_multi_remove_handle($this->mc, $c);

				unset($this->connections[$c]);

				$r= new curl_response();
				$r->data = $d;
				$r->info = $i;

				if($connection["callback"] != NULL)
					call_user_func_array($connection["callback"], array($r));

				usleep(20000);
			}
		}
		
		if(count($this->connections) == 0)
			$this->active = false;
	}

	public function get()
	{
		return $this->mc;
	}

	protected $max_connections;
	protected $mc;
	protected $active;
	protected $connections;
	protected $timeout;
}


?>