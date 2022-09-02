<?php

namespace Opencart\Catalog\Controller\Extension\Ip2location\Startup;

class IpCountryRedirect extends \Opencart\System\Engine\Controller
{
	public function index(): void
	{
		if ($this->config->get('module_ip_country_redirect_status') != '1') {
			return;
		}

		if (($rule = $this->getRule($this->request->server['REQUEST_URI'])) !== null) {
			if ($rule['code'] == '404') {
				$this->request->get['route'] = 'error/not_found';
			} else {
				$this->response->redirect($rule['to'], $rule['code']);
			}
		}
	}

	private function getRule($from)
	{
		$from = ltrim($from, '/');

		$query = $this->db->query('SELECT * FROM `' . DB_PREFIX . "ip_country_redirect` WHERE `status` = '1'");

		foreach ($query->rows as $row) {
			$triggered = false;

			if ($from == $row['to'] && $row['code'] != 404) {
				continue;
			}

			$row['from'] = ltrim($row['from'], '/');

			switch ($row['mode']) {
				case 0:
					if ($row['from'] == $from) {
						$triggered = true;
					}

					break;

				case 1:
					if (substr($from, 0, strlen($row['from'])) === $row['from']) {
						$triggered = true;
					}

					break;

				case 2:
					if (preg_match('/' . $row['from'] . '/', $from)) {
						$triggered = true;
					}

					break;
			}

			if ($triggered) {
				$this->load->model('setting/setting');
				$settings = $this->model_setting_setting->getSetting('module_ip_country_redirect');

				if (!$settings) {
					return;
				}

				if (($records = $this->getLocation($this->getClientIp())) !== false) {
					$origins = json_decode($row['origins']);

					foreach ($origins as $origin) {
						if ($origin->code == '*') {
							return [
								'to'   => $row['to'],
								'code' => $row['code'],
							];
						}

						if ($origin->code == $records['countryCode']) {
							if ($origin->region == '*') {
								return [
									'to'   => $row['to'],
									'code' => $row['code'],
								];
							}

							if ($origin->region == $records['regionName']) {
								return [
									'to'   => $row['to'],
									'code' => $row['code'],
								];
							}
						}
					}
				}
			}
		}
	}

	private function getLocation($ip)
	{
		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('module_ip_country_redirect');

		if ($settings['module_ip_country_redirect_lookup_method'] == '0') {
			require_once DIR_EXTENSION . 'ip2location/system/library/IP2Location.php';
			$db = new \Opencart\System\Library\IP2Location($settings['module_ip_country_redirect_bin_path']);
			$records = $db->lookup($ip, \Opencart\System\Library\IP2Location::ALL);

			if ($records) {
				return [
					'countryCode' => $records['countryCode'],
					'countryName' => $records['countryName'],
					'regionName'  => $records['regionName'],
				];
			}
		} elseif ($settings['module_ip_country_redirect_api_key']) {
			$ch = curl_init();
			curl_setopt_array($ch, [
				\CURLOPT_HEADER => 0,
				\CURLOPT_URL    => 'https://api.ip2location.com/v2/?' . http_build_query([
					'key'     => $settings['ip2location_api_key'],
					'ip'      => $ip,
					'format'  => 'json',
					'package' => 'WS3',
				]),
				\CURLOPT_RETURNTRANSFER => 1,
				\CURLOPT_TIMEOUT        => 10,
				\CURLOPT_SSL_VERIFYPEER => 0,
			]);

			$result = curl_exec($ch);
			curl_close($ch);

			if (($json = json_decode($result)) !== null) {
				return [
					'countryCode' => $json->country_code,
					'countryName' => $json->country_name,
					'regionName'  => $json->region_name,
				];
			}
		}

		return false;
	}

	private function getClientIp()
	{
		// Get client IP
		$ip = $_SERVER['REMOTE_ADDR'];

		// Get forwarded IP
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$xip = trim(current(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));

			if (filter_var($xip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				$ip = $xip;
			}
		}

		if (isset($_SERVER['DEV_MODE'])) {
			$ip = '8.8.8.8';
		}

		return $ip;
	}
}
