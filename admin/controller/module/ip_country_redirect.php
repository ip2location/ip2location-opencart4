<?php

namespace Opencart\Admin\Controller\Extension\IP2Location\Module;

class IPCountryRedirect extends \Opencart\System\Engine\Controller
{
	private $error = [];
	private $isRegionSupported = false;
	private $path = 'extension/ip2location/module/ip_country_redirect';

	public function index(): void
	{
		$this->load->language($this->path);

		$this->document->addScript('https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js');
		$this->document->addStyle('https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/6.6.6/css/flag-icons.min.css');
		$this->document->addStyle('https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css');
		$this->document->setTitle($this->language->get('heading_title'));

		if (isset($this->request->get['store_id'])) {
			$store_id = $this->request->get['store_id'];
		} else {
			$store_id = 0;
		}

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'),
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link($this->path, 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id),
		];

		$data['save'] = $this->url->link($this->path . '|save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$this->load->model('setting/setting');

		$settings = $this->model_setting_setting->getSetting('module_ip_country_redirect', $store_id);

		foreach ($settings as $key => $value) {
			$data[$key] = $value;
		}

		$data['sort'] = (isset($this->request->get['sort'])) ? $this->request->get['sort'] : '';
		$data['order'] = (isset($this->request->get['order'])) ? $this->request->get['order'] : '';

		$data['link_sort_from'] = $this->getSortLink('from');
		$data['link_sort_to'] = $this->getSortLink('to');
		$data['link_sort_code'] = $this->getSortLink('code');
		$data['link_sort_status'] = $this->getSortLink('status');

		$data['origins'] = $this->getOrigins();
		$data['codes'] = [
			'301' => ['code' => 301, 'text' => $this->language->get('text_code_301')],
			'302' => ['code' => 302, 'text' => $this->language->get('text_code_302')],
			'404' => ['code' => 404, 'text' => $this->language->get('text_code_404')],
		];

		$data['rules'] = [];

		// Add New Rule
		if (isset($this->request->post['newRule'])) {
			$data['new_origins'] = (isset($this->request->post['newOrigins'])) ? $this->request->post['newOrigins'] : [];
			$data['new_condition'] = (isset($this->request->post['newCondition'])) ? $this->request->post['newCondition'] : '1';
			$data['new_from'] = (isset($this->request->post['newFrom'])) ? $this->request->post['newFrom'] : '';
			$data['new_to'] = (isset($this->request->post['newTo'])) ? $this->request->post['newTo'] : '';
			$data['new_code'] = (isset($this->request->post['newCode'])) ? $this->request->post['newCode'] : '301';
			$data['new_status'] = (isset($this->request->post['newStatus'])) ? $this->request->post['newStatus'] : '1';

			$data['scripts'] = '
			$(\'html, body\').animate({
				scrollTop: $(\'#rules\').offset().top
			}, 0);';

			if (empty($data['new_origins'])) {
				$data['rules_error'] = $this->language->get('error_empty_origins');
			} elseif (empty($data['new_from'])) {
				$data['rules_error'] = $this->language->get('error_empty_from');
			} elseif ($data['new_condition'] == '2' && @preg_match('/' . $data['new_from'] . '/', sha1(microtime())) === false) {
				$data['rules_error'] = $this->language->get('error_invalid_regular_expression');
			} elseif (empty($data['new_to']) && $data['new_code'] != '404') {
				$data['rules_error'] = $this->language->get('error_empty_to');
			}

			if (!isset($data['rules_error'])) {
				$origins = [];
				$countries = [];

				$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'ip2location_country');

				foreach ($query->rows as $row) {
					$countries[$row['country_code']] = $row['country_name'];
				}

				foreach ($data['new_origins'] as $newOrigin) {
					list($countryCode, $regionName) = explode('-', $newOrigin, 2);

					$origins[] = [
						'code'   => $countryCode,
						'name'   => (isset($countries[$countryCode])) ? $countries[$countryCode] : $this->language->get('text_all_countries'),
						'region' => $regionName,
					];
				}

				$this->db->query('INSERT INTO `' . DB_PREFIX . 'ip_country_redirect` SET ' . implode(', ', [
					'`origins` = \'' . $this->db->escape(json_encode($origins)) . '\'',
					'`mode` = \'' . $this->db->escape($data['new_condition']) . '\'',
					'`from` = \'' . $this->db->escape($data['new_from']) . '\'',
					'`to` = \'' . $this->db->escape(($data['new_code'] == '404') ? '' : $data['new_to']) . '\'',
					'`code` = \'' . (int) $data['new_code'] . '\'',
					'`status` = \'' . (int) $data['new_status'] . '\'',
				]));

				$data['new_origins'] = [];
				$data['new_condition'] = '1';
				$data['new_from'] = '';
				$data['new_to'] = '';
				$data['new_code'] = '301';
				$data['new_status'] = '1';

				$data['rules_success'] = $this->language->get('text_success_add');
			} else {
				$data['scripts'] .= '
				$(\'#add-rule-modal\').modal(\'show\');';
			}
		}

		// Edit Rule
		if (isset($this->request->post['ruleId'])) {
			$origins = [];
			$countries = [];

			$query = $this->db->query('SELECT * FROM ' . DB_PREFIX . 'ip2location_country');

			foreach ($query->rows as $row) {
				$countries[$row['country_code']] = $row['country_name'];
			}

			if (isset($this->request->post['origins']) && $this->request->post['origins'] !== 'null') {
				$editOrigins = explode(',', $this->request->post['origins']);

				if (\count($editOrigins) > 0) {
					foreach ($editOrigins as $newOrigin) {
						list($countryCode, $regionName) = explode('-', $newOrigin, 2);

						$origins[] = [
							'code'   => $countryCode,
							'name'   => (isset($countries[$countryCode])) ? $countries[$countryCode] : $this->language->get('text_all_countries'),
							'region' => $regionName,
						];
					}

					if (empty($origins)) {
						$data['edit_rules_error'] = $this->language->get('error_empty_origins');
					} elseif (empty($this->request->post['from'])) {
						$data['edit_rules_error'] = $this->language->get('error_empty_from');
					} elseif ($this->request->post['mode'] == '2' && @preg_match('/' . $this->request->post['from'] . '/', sha1(microtime())) === false) {
						$data['edit_rules_error'] = $this->language->get('error_invalid_regular_expression');
					} elseif (empty($this->request->post['to']) && $this->request->post['code'] != '404') {
						$data['edit_rules_error'] = $this->language->get('error_empty_to');
					} else {
						$this->db->query('UPDATE `' . DB_PREFIX . 'ip_country_redirect` SET ' . implode(', ', [
							'`origins` = \'' . $this->db->escape(json_encode($origins)) . '\'',
							'`mode` = \'' . $this->db->escape($this->request->post['mode']) . '\'',
							'`from` = \'' . $this->db->escape($this->request->post['from']) . '\'',
							'`to` = \'' . $this->db->escape(($this->request->post['code'] == '404') ? '' : $this->request->post['to']) . '\'',
							'`code` = \'' . (int) $this->request->post['code'] . '\'',
							'`status` = \'' . (int) $this->request->post['status'] . '\'',
						]) . ' WHERE `rule_id` = \'' . $this->db->escape($this->request->post['ruleId']) . '\'');

						$data['rules_success'] = $this->language->get('text_success_save_rule');
					}
				}
			}
		}

		// Delete Rule
		if (isset($this->request->post['deleteId'])) {
			$data['scripts'] = '
			$(\'html, body\').animate({
				scrollTop: $(\'#rules\').offset().top
			}, 0);';

			$this->db->query('DELETE FROM `' . DB_PREFIX . "ip_country_redirect` WHERE `rule_id` = '" . (int) $this->request->post['deleteId'] . "' LIMIT 1");
			$data['rules_success'] = $this->language->get('text_success_delete_rule');
		}

		$rules = $this->getRules();

		if (\count($rules) > 0) {
			foreach ($rules as $rule) {
				$origins = json_decode($rule['origins']);

				$showOrigin = '';
				$originCodes = [];
				foreach ($origins as $origin) {
					$showOrigin .= '<span class="fi fi-' . strtolower($origin->code) . '"></span> ' . $origin->name . (($origin->region != '*') ? (', ' . $origin->region) : '') . '<br />';

					$originCodes[] = $origin->code . '-' . $origin->region;
				}

				$data['rules'][] = [
					'id'          => $rule['rule_id'],
					'origins'     => $originCodes,
					'show_origin' => $showOrigin,
					'condition'   => $rule['mode'],
					'from'        => $rule['from'],
					'show_from'   => $rule['from'],
					'to'          => $rule['to'],
					'show_to'     => $rule['to'],
					'code'        => $rule['code'],
					'status'      => (bool) $rule['status'],
				];
			}
		}

		// IP Lookup
		$data['ipAddress'] = $this->getClientIp();
		$data['ajax_lookup'] = $this->url->link($this->path . '|lookup', 'user_token=' . $this->session->data['user_token']);

		if (isset($settings['module_ip_country_redirect_lookup_method'])) {
			if ($settings['module_ip_country_redirect_lookup_method'] == '0' && file_exists($settings['module_ip_country_redirect_bin_path'])) {
				$data['lookup_enabled'] = true;
			}
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view($this->path, $data));
	}

	public function lookup(): void
	{
		$this->load->language($this->path);

		$json = [];

		if (isset($this->request->post['ipAddress'])) {
			if (filter_var($this->request->post['ipAddress'], \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6 | \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
				$result = $this->getLocation($this->request->post['ipAddress']);

				if ($result) {
					$json['success'] = sprintf(
						$this->language->get('text_success_ip_lookup'),
						$this->request->post['ipAddress'],
						(($result['regionName'] != '-' && !preg_match('/unavailable/', $result['regionName'])) ? $result['regionName'] . ', ' : '') . $result['countryName'] . ' (' . $result['countryCode'] . ')'
					);
				} else {
					$json['error'] = $this->language->get('error_ip_address_invalid');
				}
			} else {
				$json['error'] = $this->language->get('error_ip_address_invalid');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function save(): void
	{
		$this->load->language($this->path);

		$json = [];

		if (!$this->user->hasPermission('modify', $this->path)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (isset($this->request->post['module_ip_country_redirect_status'])) {
			$this->request->post['module_ip_country_redirect_status'] = 1;
		} else {
			$this->request->post['module_ip_country_redirect_status'] = 0;
		}

		if ($this->request->post['module_ip_country_redirect_lookup_method'] == 0) {
			if (!isset($this->request->post['module_ip_country_redirect_bin_path'])) {
				$json['error'] = $this->language->get('error_database_not_found');
			} elseif (empty($this->request->post['module_ip_country_redirect_bin_path'])) {
				$json['error'] = $this->language->get('error_database_not_found');
			} elseif (!file_exists($this->request->post['module_ip_country_redirect_bin_path'])) {
				$json['error'] = $this->language->get('error_database_not_found');
			} else {
				require_once DIR_EXTENSION . 'ip2location/system/library/IP2Location.php';

				$db = new \Opencart\System\Library\IP2Location($this->request->post['module_ip_country_redirect_bin_path']);

				if (!$db->getDatabaseVersion()) {
					$json['error'] = $this->language->get('error_database_invalid');
				}
			}
		}

		if (!$json) {
			$this->load->model('setting/setting');

			if (isset($this->request->get['store_id'])) {
				$store_id = $this->request->get['store_id'];
			} else {
				$store_id = 0;
			}

			$this->model_setting_setting->editSetting('module_ip_country_redirect', $this->request->post, $store_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void
	{
		$countries = ['AF' => 'Afghanistan', 'AX' => 'Aland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia, Plurinational State of', 'BQ' => 'Bonaire, Sint Eustatius and Saba', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, The Democratic Republic of The', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote D\'ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba', 'CW' => 'Curacao', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'VA' => 'Holy See', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic of', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, Democratic People\'s Republic of', 'KR' => 'Korea, Republic of', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia, The Former Yugoslav Republic of', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico', 'FM' => 'Micronesia, Federated States of', 'MD' => 'Moldova, Republic of', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine, State of', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena, Ascension and Tristan Da Cunha', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'MF' => 'Saint Martin (French Part)', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and The Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SX' => 'Sint Maarten (Dutch Part)', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and The South Sandwich Islands', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen', 'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania, United Republic of', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela, Bolivarian Republic of', 'VN' => 'Viet Nam', 'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'];

		$regions = [['AD', 'Andorra la Vella'], ['AD', 'Canillo'], ['AD', 'Encamp'], ['AD', 'Escaldes-Engordany'], ['AD', 'La Massana'], ['AD', 'Ordino'], ['AD', 'Sant Julia de Loria'], ['AE', '\'Ajman'], ['AE', 'Abu Zaby'], ['AE', 'Al Fujayrah'], ['AE', 'Ash Shariqah'], ['AE', 'Dubayy'], ['AE', 'Ra\'s al Khaymah'], ['AE', 'Umm al Qaywayn'], ['AF', 'Balkh'], ['AF', 'Bamyan'], ['AF', 'Daykundi'], ['AF', 'Ghor'], ['AF', 'Herat'], ['AF', 'Kabul'], ['AF', 'Kandahar'], ['AF', 'Khost'], ['AF', 'Kunar'], ['AF', 'Kunduz'], ['AF', 'Logar'], ['AF', 'Nangarhar'], ['AF', 'Nimroz'], ['AF', 'Paktiya'], ['AF', 'Parwan'], ['AF', 'Uruzgan'], ['AG', 'Saint John'], ['AG', 'Saint Mary'], ['AG', 'Saint Paul'], ['AI', 'Anguilla'], ['AL', 'Berat'], ['AL', 'Diber'], ['AL', 'Durres'], ['AL', 'Elbasan'], ['AL', 'Fier'], ['AL', 'Gjirokaster'], ['AL', 'Korce'], ['AL', 'Kukes'], ['AL', 'Lezhe'], ['AL', 'Shkoder'], ['AL', 'Tirane'], ['AL', 'Vlore'], ['AM', 'Aragacotn'], ['AM', 'Ararat'], ['AM', 'Armavir'], ['AM', 'Erevan'], ['AM', 'Gegark\'unik\''], ['AM', 'Kotayk\''], ['AM', 'Lori'], ['AM', 'Sirak'], ['AM', 'Syunik\''], ['AM', 'Tavus'], ['AM', 'Vayoc Jor'], ['AO', 'Bengo'], ['AO', 'Benguela'], ['AO', 'Bie'], ['AO', 'Cabinda'], ['AO', 'Cunene'], ['AO', 'Huambo'], ['AO', 'Huila'], ['AO', 'Kuando Kubango'], ['AO', 'Kwanza Norte'], ['AO', 'Kwanza Sul'], ['AO', 'Luanda'], ['AO', 'Lunda Norte'], ['AO', 'Lunda Sul'], ['AO', 'Malange'], ['AO', 'Moxico'], ['AO', 'Namibe'], ['AO', 'Uige'], ['AO', 'Zaire'], ['AR', 'Buenos Aires'], ['AR', 'Catamarca'], ['AR', 'Chaco'], ['AR', 'Chubut'], ['AR', 'Ciudad Autonoma de Buenos Aires'], ['AR', 'Cordoba'], ['AR', 'Corrientes'], ['AR', 'Entre Rios'], ['AR', 'Formosa'], ['AR', 'Jujuy'], ['AR', 'La Pampa'], ['AR', 'La Rioja'], ['AR', 'Mendoza'], ['AR', 'Misiones'], ['AR', 'Neuquen'], ['AR', 'Rio Negro'], ['AR', 'Salta'], ['AR', 'San Juan'], ['AR', 'San Luis'], ['AR', 'Santa Cruz'], ['AR', 'Santa Fe'], ['AR', 'Santiago del Estero'], ['AR', 'Tierra del Fuego'], ['AR', 'Tucuman'], ['AS', 'Eastern District'], ['AS', 'Western District'], ['AT', 'Burgenland'], ['AT', 'Karnten'], ['AT', 'Niederosterreich'], ['AT', 'Oberosterreich'], ['AT', 'Salzburg'], ['AT', 'Steiermark'], ['AT', 'Tirol'], ['AT', 'Vorarlberg'], ['AT', 'Wien'], ['AU', 'Australian Capital Territory'], ['AU', 'New South Wales'], ['AU', 'Northern Territory'], ['AU', 'Queensland'], ['AU', 'South Australia'], ['AU', 'Tasmania'], ['AU', 'Victoria'], ['AU', 'Western Australia'], ['AW', 'Aruba (general)'], ['AX', 'Eckeroe'], ['AX', 'Finstroem'], ['AX', 'Hammarland'], ['AX', 'Jomala'], ['AX', 'Mariehamn'], ['AX', 'Saltvik'], ['AZ', 'Abseron'], ['AZ', 'Agcabadi'], ['AZ', 'Agdas'], ['AZ', 'Agstafa'], ['AZ', 'Agsu'], ['AZ', 'Astara'], ['AZ', 'Baki'], ['AZ', 'Balakan'], ['AZ', 'Barda'], ['AZ', 'Beylaqan'], ['AZ', 'Bilasuvar'], ['AZ', 'Calilabad'], ['AZ', 'Daskasan'], ['AZ', 'Ganca'], ['AZ', 'Goycay'], ['AZ', 'Goygol'], ['AZ', 'Haciqabul'], ['AZ', 'Imisli'], ['AZ', 'Ismayilli'], ['AZ', 'Lankaran'], ['AZ', 'Lerik'], ['AZ', 'Masalli'], ['AZ', 'Mingacevir'], ['AZ', 'Naxcivan'], ['AZ', 'Oguz'], ['AZ', 'Qabala'], ['AZ', 'Qax'], ['AZ', 'Qazax'], ['AZ', 'Quba'], ['AZ', 'Qusar'], ['AZ', 'Saatli'], ['AZ', 'Sabirabad'], ['AZ', 'Saki'], ['AZ', 'Salyan'], ['AZ', 'Samaxi'], ['AZ', 'Samkir'], ['AZ', 'Samux'], ['AZ', 'Sirvan'], ['AZ', 'Sumqayit'], ['AZ', 'Tartar'], ['AZ', 'Tovuz'], ['AZ', 'Ucar'], ['AZ', 'Xacmaz'], ['AZ', 'Xizi'], ['AZ', 'Yardimli'], ['AZ', 'Yevlax'], ['AZ', 'Zaqatala'], ['AZ', 'Zardab'], ['BA', 'Federacija Bosna i Hercegovina'], ['BA', 'Republika Srpska'], ['BB', 'Christ Church'], ['BB', 'Saint James'], ['BB', 'Saint Joseph'], ['BB', 'Saint Michael'], ['BB', 'Saint Peter'], ['BD', 'Barisal'], ['BD', 'Chittagong'], ['BD', 'Dhaka'], ['BD', 'Khulna'], ['BD', 'Rajshahi'], ['BD', 'Rangpur'], ['BD', 'Sylhet'], ['BE', 'Antwerpen'], ['BE', 'Brabant Wallon'], ['BE', 'Brussels Hoofdstedelijk Gewest'], ['BE', 'Hainaut'], ['BE', 'Liege'], ['BE', 'Limburg'], ['BE', 'Luxembourg'], ['BE', 'Namur'], ['BE', 'Oost-Vlaanderen'], ['BE', 'Vlaams-Brabant'], ['BE', 'West-Vlaanderen'], ['BF', 'Boulkiemde'], ['BF', 'Comoe'], ['BF', 'Gourma'], ['BF', 'Houet'], ['BF', 'Kadiogo'], ['BF', 'Mouhoun'], ['BF', 'Passore'], ['BF', 'Tui'], ['BF', 'Yatenga'], ['BG', 'Blagoevgrad'], ['BG', 'Burgas'], ['BG', 'Dobrich'], ['BG', 'Gabrovo'], ['BG', 'Haskovo'], ['BG', 'Kardzhali'], ['BG', 'Kyustendil'], ['BG', 'Lovech'], ['BG', 'Montana'], ['BG', 'Pazardzhik'], ['BG', 'Pernik'], ['BG', 'Pleven'], ['BG', 'Plovdiv'], ['BG', 'Razgrad'], ['BG', 'Ruse'], ['BG', 'Shumen'], ['BG', 'Silistra'], ['BG', 'Sliven'], ['BG', 'Smolyan'], ['BG', 'Sofia'], ['BG', 'Sofia (stolitsa)'], ['BG', 'Stara Zagora'], ['BG', 'Targovishte'], ['BG', 'Varna'], ['BG', 'Veliko Tarnovo'], ['BG', 'Vidin'], ['BG', 'Vratsa'], ['BG', 'Yambol'], ['BH', 'Al Asimah'], ['BH', 'Al Muharraq'], ['BH', 'Ash Shamaliyah'], ['BI', 'Bujumbura Mairie'], ['BI', 'Bururi'], ['BI', 'Gitega'], ['BI', 'Kirundo'], ['BI', 'Mwaro'], ['BI', 'Ngozi'], ['BJ', 'Alibori'], ['BJ', 'Atacora'], ['BJ', 'Atlantique'], ['BJ', 'Borgou'], ['BJ', 'Collines'], ['BJ', 'Donga'], ['BJ', 'Littoral'], ['BJ', 'Mono'], ['BJ', 'Oueme'], ['BJ', 'Plateau'], ['BJ', 'Zou'], ['BL', 'Saint Barthelemy'], ['BM', 'Hamilton'], ['BM', 'Saint George'], ['BN', 'Belait'], ['BN', 'Brunei-Muara'], ['BN', 'Temburong'], ['BN', 'Tutong'], ['BO', 'Chuquisaca'], ['BO', 'Cochabamba'], ['BO', 'El Beni'], ['BO', 'La Paz'], ['BO', 'Oruro'], ['BO', 'Pando'], ['BO', 'Potosi'], ['BO', 'Santa Cruz'], ['BO', 'Tarija'], ['BQ', 'Bonaire'], ['BQ', 'Saba'], ['BQ', 'Sint Eustatius'], ['BR', 'Acre'], ['BR', 'Alagoas'], ['BR', 'Amapa'], ['BR', 'Amazonas'], ['BR', 'Bahia'], ['BR', 'Ceara'], ['BR', 'Distrito Federal'], ['BR', 'Espirito Santo'], ['BR', 'Goias'], ['BR', 'Maranhao'], ['BR', 'Mato Grosso'], ['BR', 'Mato Grosso do Sul'], ['BR', 'Minas Gerais'], ['BR', 'Para'], ['BR', 'Paraiba'], ['BR', 'Parana'], ['BR', 'Pernambuco'], ['BR', 'Piaui'], ['BR', 'Rio Grande do Norte'], ['BR', 'Rio Grande do Sul'], ['BR', 'Rio de Janeiro'], ['BR', 'Rondonia'], ['BR', 'Roraima'], ['BR', 'Santa Catarina'], ['BR', 'Sao Paulo'], ['BR', 'Sergipe'], ['BR', 'Tocantins'], ['BS', 'Freeport'], ['BS', 'Fresh Creek'], ['BS', 'Harbour Island'], ['BS', 'Long Island'], ['BS', 'Marsh Harbour'], ['BS', 'New Providence'], ['BS', 'Rock Sound'], ['BT', 'Chhukha'], ['BT', 'Dagana'], ['BT', 'Gasa'], ['BT', 'Monggar'], ['BT', 'Paro'], ['BT', 'Punakha'], ['BT', 'Thimphu'], ['BT', 'Trashi Yangtse'], ['BW', 'Central'], ['BW', 'Kgatleng'], ['BW', 'Kweneng'], ['BW', 'North-East'], ['BW', 'North-West'], ['BW', 'South-East'], ['BY', 'Brestskaya Voblasts\''], ['BY', 'Homyel\'skaya Voblasts\''], ['BY', 'Hrodzyenskaya Voblasts\''], ['BY', 'Mahilyowskaya Voblasts\''], ['BY', 'Minskaya Voblasts\''], ['BY', 'Vitsyebskaya Voblasts\''], ['BZ', 'Belize'], ['BZ', 'Cayo'], ['BZ', 'Corozal'], ['BZ', 'Orange Walk'], ['BZ', 'Stann Creek'], ['BZ', 'Toledo'], ['CA', 'Alberta'], ['CA', 'British Columbia'], ['CA', 'Manitoba'], ['CA', 'New Brunswick'], ['CA', 'Newfoundland and Labrador'], ['CA', 'Northwest Territories'], ['CA', 'Nova Scotia'], ['CA', 'Nunavut'], ['CA', 'Ontario'], ['CA', 'Prince Edward Island'], ['CA', 'Quebec'], ['CA', 'Saskatchewan'], ['CA', 'Yukon'], ['CD', 'Bandundu'], ['CD', 'Bas-Congo'], ['CD', 'Equateur'], ['CD', 'Kasai-Occidental'], ['CD', 'Kasai-Oriental'], ['CD', 'Katanga'], ['CD', 'Kinshasa'], ['CD', 'Maniema'], ['CD', 'Nord-Kivu'], ['CD', 'Orientale'], ['CD', 'Sud-Kivu'], ['CF', 'Bangui'], ['CF', 'Ouham'], ['CF', 'Ouham-Pende'], ['CG', 'Brazzaville'], ['CG', 'Cuvette'], ['CG', 'Cuvette-Ouest'], ['CG', 'Pointe-Noire'], ['CG', 'Sangha'], ['CH', 'Aargau'], ['CH', 'Appenzell Ausserrhoden'], ['CH', 'Appenzell Innerrhoden'], ['CH', 'Basel-Landschaft'], ['CH', 'Basel-Stadt'], ['CH', 'Bern'], ['CH', 'Fribourg'], ['CH', 'Geneve'], ['CH', 'Glarus'], ['CH', 'Graubunden'], ['CH', 'Jura'], ['CH', 'Luzern'], ['CH', 'Neuchatel'], ['CH', 'Nidwalden'], ['CH', 'Obwalden'], ['CH', 'Sankt Gallen'], ['CH', 'Schaffhausen'], ['CH', 'Schwyz'], ['CH', 'Solothurn'], ['CH', 'Thurgau'], ['CH', 'Ticino'], ['CH', 'Uri'], ['CH', 'Valais'], ['CH', 'Vaud'], ['CH', 'Zug'], ['CH', 'Zurich'], ['CI', 'Agneby'], ['CI', 'Bas-Sassandra'], ['CI', 'Dix-Huit Montagnes'], ['CI', 'Fromager'], ['CI', 'Haut-Sassandra'], ['CI', 'Lacs'], ['CI', 'Lagunes'], ['CI', 'Marahoue'], ['CI', 'Moyen-Cavally'], ['CI', 'Moyen-Comoe'], ['CI', 'N\'zi-Comoe'], ['CI', 'Savanes'], ['CI', 'Sud-Bandama'], ['CI', 'Sud-Comoe'], ['CI', 'Vallee du Bandama'], ['CI', 'Worodougou'], ['CI', 'Zanzan'], ['CK', 'Cook Islands'], ['CL', 'Antofagasta'], ['CL', 'Araucania'], ['CL', 'Arica y Parinacota'], ['CL', 'Atacama'], ['CL', 'Aysen'], ['CL', 'Biobio'], ['CL', 'Coquimbo'], ['CL', 'Libertador General Bernardo O\'Higgins'], ['CL', 'Los Lagos'], ['CL', 'Los Rios'], ['CL', 'Magallanes'], ['CL', 'Maule'], ['CL', 'Region Metropolitana de Santiago'], ['CL', 'Tarapaca'], ['CL', 'Valparaiso'], ['CM', 'Adamaoua'], ['CM', 'Centre'], ['CM', 'Est'], ['CM', 'Extreme-Nord'], ['CM', 'Littoral'], ['CM', 'Nord'], ['CM', 'Nord-Ouest'], ['CM', 'Ouest'], ['CM', 'Sud'], ['CM', 'Sud-Ouest'], ['CN', 'Anhui'], ['CN', 'Beijing'], ['CN', 'Chongqing'], ['CN', 'Fujian'], ['CN', 'Gansu'], ['CN', 'Guangdong'], ['CN', 'Guangxi'], ['CN', 'Guizhou'], ['CN', 'Hainan'], ['CN', 'Hebei'], ['CN', 'Heilongjiang'], ['CN', 'Henan'], ['CN', 'Hubei'], ['CN', 'Hunan'], ['CN', 'Jiangsu'], ['CN', 'Jiangxi'], ['CN', 'Jilin'], ['CN', 'Liaoning'], ['CN', 'Nei Mongol'], ['CN', 'Ningxia'], ['CN', 'Qinghai'], ['CN', 'Shaanxi'], ['CN', 'Shandong'], ['CN', 'Shanghai'], ['CN', 'Shanxi'], ['CN', 'Sichuan'], ['CN', 'Tianjin'], ['CN', 'Xinjiang'], ['CN', 'Xizang'], ['CN', 'Yunnan'], ['CN', 'Zhejiang'], ['CO', 'Amazonas'], ['CO', 'Antioquia'], ['CO', 'Arauca'], ['CO', 'Atlantico'], ['CO', 'Bolivar'], ['CO', 'Boyaca'], ['CO', 'Caldas'], ['CO', 'Caqueta'], ['CO', 'Casanare'], ['CO', 'Cauca'], ['CO', 'Cesar'], ['CO', 'Choco'], ['CO', 'Cordoba'], ['CO', 'Cundinamarca'], ['CO', 'Distrito Capital de Bogota'], ['CO', 'Guainia'], ['CO', 'Guaviare'], ['CO', 'Huila'], ['CO', 'La Guajira'], ['CO', 'Magdalena'], ['CO', 'Meta'], ['CO', 'Narino'], ['CO', 'Norte de Santander'], ['CO', 'Putumayo'], ['CO', 'Quindio'], ['CO', 'Risaralda'], ['CO', 'San Andres, Providencia y Santa Catalina'], ['CO', 'Santander'], ['CO', 'Sucre'], ['CO', 'Tolima'], ['CO', 'Valle del Cauca'], ['CO', 'Vaupes'], ['CO', 'Vichada'], ['CR', 'Alajuela'], ['CR', 'Cartago'], ['CR', 'Guanacaste'], ['CR', 'Heredia'], ['CR', 'Limon'], ['CR', 'Puntarenas'], ['CR', 'San Jose'], ['CU', 'Artemisa'], ['CU', 'Camaguey'], ['CU', 'Ciego de Avila'], ['CU', 'Cienfuegos'], ['CU', 'Granma'], ['CU', 'Guantanamo'], ['CU', 'Holguin'], ['CU', 'La Habana'], ['CU', 'Las Tunas'], ['CU', 'Matanzas'], ['CU', 'Mayabeque'], ['CU', 'Pinar del Rio'], ['CU', 'Sancti Spiritus'], ['CU', 'Santiago de Cuba'], ['CU', 'Villa Clara'], ['CV', 'Boa Vista'], ['CV', 'Brava'], ['CV', 'Porto Novo'], ['CV', 'Praia'], ['CV', 'Sal'], ['CV', 'Santa Catarina'], ['CV', 'Sao Filipe'], ['CV', 'Sao Miguel'], ['CV', 'Sao Vicente'], ['CW', 'Curacao'], ['CY', 'Ammochostos'], ['CY', 'Keryneia'], ['CY', 'Larnaka'], ['CY', 'Lefkosia'], ['CY', 'Lemesos'], ['CY', 'Pafos'], ['CZ', 'Jihocesky kraj'], ['CZ', 'Jihomoravsky kraj'], ['CZ', 'Karlovarsky kraj'], ['CZ', 'Kralovehradecky kraj'], ['CZ', 'Liberecky kraj'], ['CZ', 'Moravskoslezsky kraj'], ['CZ', 'Olomoucky kraj'], ['CZ', 'Pardubicky kraj'], ['CZ', 'Plzensky kraj'], ['CZ', 'Praha, hlavni mesto'], ['CZ', 'Stredocesky kraj'], ['CZ', 'Ustecky kraj'], ['CZ', 'Vysocina kraj'], ['CZ', 'Zlinsky kraj'], ['DE', 'Baden-Wurttemberg'], ['DE', 'Bayern'], ['DE', 'Berlin'], ['DE', 'Brandenburg'], ['DE', 'Bremen'], ['DE', 'Hamburg'], ['DE', 'Hessen'], ['DE', 'Mecklenburg-Vorpommern'], ['DE', 'Niedersachsen'], ['DE', 'Nordrhein-Westfalen'], ['DE', 'Rheinland-Pfalz'], ['DE', 'Saarland'], ['DE', 'Sachsen'], ['DE', 'Sachsen-Anhalt'], ['DE', 'Schleswig-Holstein'], ['DE', 'Thuringen'], ['DJ', 'Dikhil'], ['DJ', 'Djibouti'], ['DJ', 'Tadjourah'], ['DK', 'Hovedstaden'], ['DK', 'Midtjylland'], ['DK', 'Nordjylland'], ['DK', 'Sjelland'], ['DK', 'Syddanmark'], ['DM', 'Saint George'], ['DM', 'Saint John'], ['DM', 'Saint Joseph'], ['DM', 'Saint Paul'], ['DO', 'Azua'], ['DO', 'Baoruco'], ['DO', 'Barahona'], ['DO', 'Dajabon'], ['DO', 'Distrito Nacional'], ['DO', 'Duarte'], ['DO', 'El Seibo'], ['DO', 'Espaillat'], ['DO', 'Hato Mayor'], ['DO', 'Independencia'], ['DO', 'La Altagracia'], ['DO', 'La Romana'], ['DO', 'La Vega'], ['DO', 'Maria Trinidad Sanchez'], ['DO', 'Monsenor Nouel'], ['DO', 'Monte Cristi'], ['DO', 'Monte Plata'], ['DO', 'Pedernales'], ['DO', 'Peravia'], ['DO', 'Puerto Plata'], ['DO', 'Salcedo'], ['DO', 'Samana'], ['DO', 'San Cristobal'], ['DO', 'San Juan'], ['DO', 'San Pedro De Macoris'], ['DO', 'Sanchez Ramirez'], ['DO', 'Santiago'], ['DO', 'Santiago Rodriguez'], ['DO', 'Valverde'], ['DZ', 'Adrar'], ['DZ', 'Ain Defla'], ['DZ', 'Ain Temouchent'], ['DZ', 'Alger'], ['DZ', 'Annaba'], ['DZ', 'Batna'], ['DZ', 'Bechar'], ['DZ', 'Bejaia'], ['DZ', 'Biskra'], ['DZ', 'Blida'], ['DZ', 'Bordj Bou Arreridj'], ['DZ', 'Bouira'], ['DZ', 'Boumerdes'], ['DZ', 'Chlef'], ['DZ', 'Constantine'], ['DZ', 'Djelfa'], ['DZ', 'El Bayadh'], ['DZ', 'El Oued'], ['DZ', 'El Tarf'], ['DZ', 'Ghardaia'], ['DZ', 'Guelma'], ['DZ', 'Illizi'], ['DZ', 'Khenchela'], ['DZ', 'Laghouat'], ['DZ', 'Mascara'], ['DZ', 'Medea'], ['DZ', 'Mila'], ['DZ', 'Mostaganem'], ['DZ', 'Msila'], ['DZ', 'Naama'], ['DZ', 'Oran'], ['DZ', 'Ouargla'], ['DZ', 'Oum el Bouaghi'], ['DZ', 'Relizane'], ['DZ', 'Saida'], ['DZ', 'Setif'], ['DZ', 'Sidi Bel Abbes'], ['DZ', 'Skikda'], ['DZ', 'Souk Ahras'], ['DZ', 'Tamanrasset'], ['DZ', 'Tebessa'], ['DZ', 'Tiaret'], ['DZ', 'Tindouf'], ['DZ', 'Tipaza'], ['DZ', 'Tissemsilt'], ['DZ', 'Tizi Ouzou'], ['DZ', 'Tlemcen'], ['EC', 'Azuay'], ['EC', 'Bolivar'], ['EC', 'Canar'], ['EC', 'Carchi'], ['EC', 'Chimborazo'], ['EC', 'Cotopaxi'], ['EC', 'El Oro'], ['EC', 'Esmeraldas'], ['EC', 'Galapagos'], ['EC', 'Guayas'], ['EC', 'Imbabura'], ['EC', 'Loja'], ['EC', 'Los Rios'], ['EC', 'Manabi'], ['EC', 'Morona-Santiago'], ['EC', 'Napo'], ['EC', 'Orellana'], ['EC', 'Pastaza'], ['EC', 'Pichincha'], ['EC', 'Santa Elena'], ['EC', 'Sucumbios'], ['EC', 'Tungurahua'], ['EC', 'Zamora-Chinchipe'], ['EE', 'Harjumaa'], ['EE', 'Hiiumaa'], ['EE', 'Ida-Virumaa'], ['EE', 'Jarvamaa'], ['EE', 'Jogevamaa'], ['EE', 'Laane-Virumaa'], ['EE', 'Laanemaa'], ['EE', 'Parnumaa'], ['EE', 'Polvamaa'], ['EE', 'Raplamaa'], ['EE', 'Saaremaa'], ['EE', 'Tartumaa'], ['EE', 'Valgamaa'], ['EE', 'Viljandimaa'], ['EE', 'Vorumaa'], ['EG', 'Ad Daqahliyah'], ['EG', 'Al Bahr al Ahmar'], ['EG', 'Al Buhayrah'], ['EG', 'Al Fayyum'], ['EG', 'Al Gharbiyah'], ['EG', 'Al Iskandariyah'], ['EG', 'Al Ismailiyah'], ['EG', 'Al Jizah'], ['EG', 'Al Minufiyah'], ['EG', 'Al Minya'], ['EG', 'Al Qahirah'], ['EG', 'Al Qalyubiyah'], ['EG', 'Al Uqsur'], ['EG', 'Al Wadi al Jadid'], ['EG', 'As Suways'], ['EG', 'Ash Sharqiyah'], ['EG', 'Aswan'], ['EG', 'Asyut'], ['EG', 'Bani Suwayf'], ['EG', 'Bur Sa\'id'], ['EG', 'Dumyat'], ['EG', 'Janub Sina\''], ['EG', 'Kafr ash Shaykh'], ['EG', 'Matruh'], ['EG', 'Qina'], ['EG', 'Shamal Sina\''], ['EG', 'Suhaj'], ['ER', 'Al Awsat'], ['ES', 'Andalucia'], ['ES', 'Aragon'], ['ES', 'Asturias, Principado de'], ['ES', 'Canarias'], ['ES', 'Cantabria'], ['ES', 'Castilla y Leon'], ['ES', 'Castilla-La Mancha'], ['ES', 'Catalunya'], ['ES', 'Ceuta'], ['ES', 'Extremadura'], ['ES', 'Galicia'], ['ES', 'Illes Balears'], ['ES', 'La Rioja'], ['ES', 'Madrid, Comunidad de'], ['ES', 'Melilla'], ['ES', 'Murcia, Region de'], ['ES', 'Navarra, Comunidad Foral de'], ['ES', 'Pais Vasco'], ['ES', 'Valenciana, Comunidad'], ['ET', 'Adis Abeba'], ['ET', 'Afar'], ['ET', 'Amara'], ['ET', 'Dire Dawa'], ['ET', 'Oromiya'], ['ET', 'Sumale'], ['ET', 'Tigray'], ['ET', 'YeDebub Biheroch Bihereseboch na Hizboch'], ['FI', 'Etela-Karjala'], ['FI', 'Etela-Pohjanmaa'], ['FI', 'Etela-Savo'], ['FI', 'Kainuu'], ['FI', 'Kanta-Hame'], ['FI', 'Keski-Pohjanmaa'], ['FI', 'Keski-Suomi'], ['FI', 'Kymenlaakso'], ['FI', 'Lappi'], ['FI', 'Paijat-Hame'], ['FI', 'Pirkanmaa'], ['FI', 'Pohjanmaa'], ['FI', 'Pohjois-Karjala'], ['FI', 'Pohjois-Pohjanmaa'], ['FI', 'Pohjois-Savo'], ['FI', 'Satakunta'], ['FI', 'Uusimaa'], ['FI', 'Varsinais-Suomi'], ['FJ', 'Central'], ['FJ', 'Northern'], ['FJ', 'Western'], ['FK', 'Falkland Islands'], ['FM', 'Chuuk'], ['FM', 'Pohnpei'], ['FM', 'Yap'], ['FO', 'Eysturoy'], ['FO', 'Nordoyar'], ['FO', 'Sandoy'], ['FO', 'Streymoy'], ['FO', 'Suduroy'], ['FO', 'Vagar'], ['FR', 'Alsace'], ['FR', 'Aquitaine'], ['FR', 'Auvergne'], ['FR', 'Basse-Normandie'], ['FR', 'Bourgogne'], ['FR', 'Bretagne'], ['FR', 'Centre'], ['FR', 'Champagne-Ardenne'], ['FR', 'Corse'], ['FR', 'Franche-Comte'], ['FR', 'Haute-Normandie'], ['FR', 'Ile-de-France'], ['FR', 'Languedoc-Roussillon'], ['FR', 'Limousin'], ['FR', 'Lorraine'], ['FR', 'Midi-Pyrenees'], ['FR', 'Nord-Pas-de-Calais'], ['FR', 'Pays de la Loire'], ['FR', 'Picardie'], ['FR', 'Poitou-Charentes'], ['FR', 'Provence-Alpes-Cote d\'Azur'], ['FR', 'Rhone-Alpes'], ['GA', 'Estuaire'], ['GA', 'Haut-Ogooue'], ['GA', 'Moyen-Ogooue'], ['GA', 'Ngounie'], ['GA', 'Ogooue-Lolo'], ['GA', 'Ogooue-Maritime'], ['GA', 'Woleu-Ntem'], ['GB', 'England'], ['GB', 'Northern Ireland'], ['GB', 'Scotland'], ['GB', 'Wales'], ['GD', 'Saint Andrew'], ['GD', 'Saint David'], ['GD', 'Saint George'], ['GD', 'Saint John'], ['GD', 'Saint Mark'], ['GD', 'Saint Patrick'], ['GE', 'Abkhazia'], ['GE', 'Ajaria'], ['GE', 'Akhalk\'alak\'is Raioni'], ['GE', 'Baghdat\'is Raioni'], ['GE', 'Borjomis Raioni'], ['GE', 'Goris Raioni'], ['GE', 'Guria'], ['GE', 'Imereti'], ['GE', 'K\'arelis Raioni'], ['GE', 'Kakheti'], ['GE', 'Khashuris Raioni'], ['GE', 'Kvemo Kartli'], ['GE', 'Mtskheta-Mtianeti'], ['GE', 'Racha-Lechkhumi and Kvemo Svaneti'], ['GE', 'Samegrelo and Zemo Svaneti'], ['GE', 'Samtskhe-Javakheti'], ['GE', 'Shida Kartli'], ['GE', 'T\'bilisi'], ['GF', 'Guyane'], ['GG', 'Guernsey (general)'], ['GH', 'Ashanti'], ['GH', 'Brong-Ahafo'], ['GH', 'Central'], ['GH', 'Eastern'], ['GH', 'Greater Accra'], ['GH', 'Northern'], ['GH', 'Upper East'], ['GH', 'Volta'], ['GH', 'Western'], ['GI', 'Gibraltar'], ['GL', 'Kommune Kujalleq'], ['GL', 'Kommuneqarfik Sermersooq'], ['GL', 'Qaasuitsup Kommunia'], ['GL', 'Qeqqata Kommunia'], ['GM', 'Banjul'], ['GM', 'Central River'], ['GM', 'Lower River'], ['GM', 'North Bank'], ['GM', 'Western'], ['GN', 'Boke'], ['GN', 'Conakry'], ['GN', 'Coyah'], ['GN', 'Dabola'], ['GN', 'Dalaba'], ['GN', 'Dubreka'], ['GN', 'Fria'], ['GN', 'Gueckedou'], ['GN', 'Kankan'], ['GN', 'Kissidougou'], ['GN', 'Labe'], ['GN', 'Macenta'], ['GN', 'Nzerekore'], ['GN', 'Pita'], ['GN', 'Siguiri'], ['GP', 'Guadeloupe'], ['GQ', 'Bioko Norte'], ['GQ', 'Bioko Sur'], ['GQ', 'Litoral'], ['GQ', 'Wele-Nzas'], ['GR', 'Aitolia kai Akarnania'], ['GR', 'Akhaia'], ['GR', 'Argolis'], ['GR', 'Arkadhia'], ['GR', 'Arta'], ['GR', 'Attiki'], ['GR', 'Dhodhekanisos'], ['GR', 'Drama'], ['GR', 'Evritania'], ['GR', 'Evros'], ['GR', 'Evvoia'], ['GR', 'Florina'], ['GR', 'Fokis'], ['GR', 'Fthiotis'], ['GR', 'Grevena'], ['GR', 'Ilia'], ['GR', 'Imathia'], ['GR', 'Ioannina'], ['GR', 'Iraklion'], ['GR', 'Kardhitsa'], ['GR', 'Kastoria'], ['GR', 'Kavala'], ['GR', 'Kefallinia'], ['GR', 'Kerkira'], ['GR', 'Khalkidhiki'], ['GR', 'Khania'], ['GR', 'Khios'], ['GR', 'Kikladhes'], ['GR', 'Kilkis'], ['GR', 'Korinthia'], ['GR', 'Kozani'], ['GR', 'Lakonia'], ['GR', 'Larisa'], ['GR', 'Lasithi'], ['GR', 'Lesvos'], ['GR', 'Levkas'], ['GR', 'Magnisia'], ['GR', 'Messinia'], ['GR', 'Pella'], ['GR', 'Pieria'], ['GR', 'Preveza'], ['GR', 'Rethimni'], ['GR', 'Rodhopi'], ['GR', 'Samos'], ['GR', 'Serrai'], ['GR', 'Thesprotia'], ['GR', 'Thessaloniki'], ['GR', 'Trikala'], ['GR', 'Voiotia'], ['GR', 'Xanthi'], ['GR', 'Zakinthos'], ['GS', 'South Georgia and the South Sandwich Islands'], ['GT', 'Alta Verapaz'], ['GT', 'Baja Verapaz'], ['GT', 'Chimaltenango'], ['GT', 'Chiquimula'], ['GT', 'El Progreso'], ['GT', 'Escuintla'], ['GT', 'Guatemala'], ['GT', 'Huehuetenango'], ['GT', 'Izabal'], ['GT', 'Jalapa'], ['GT', 'Jutiapa'], ['GT', 'Peten'], ['GT', 'Quetzaltenango'], ['GT', 'Quiche'], ['GT', 'Retalhuleu'], ['GT', 'Sacatepequez'], ['GT', 'San Marcos'], ['GT', 'Santa Rosa'], ['GT', 'Solola'], ['GT', 'Suchitepequez'], ['GT', 'Totonicapan'], ['GT', 'Zacapa'], ['GU', 'Agana Heights Municipality'], ['GU', 'Agat Municipality'], ['GU', 'Asan-Maina Municipality'], ['GU', 'Barrigada Municipality'], ['GU', 'Chalan Pago-Ordot Municipality'], ['GU', 'Dededo Municipality'], ['GU', 'Hagatna Municipality'], ['GU', 'Inarajan Municipality'], ['GU', 'Mangilao Municipality'], ['GU', 'Merizo Municipality'], ['GU', 'Mongmong-Toto-Maite Municipality'], ['GU', 'Piti Municipality'], ['GU', 'Santa Rita Municipality'], ['GU', 'Sinajana Municipality'], ['GU', 'Talofofo Municipality'], ['GU', 'Tamuning-Tumon-Harmon Municipality'], ['GU', 'Yigo Municipality'], ['GU', 'Yona Municipality'], ['GW', 'Bissau'], ['GW', 'Gabu'], ['GY', 'Demerara-Mahaica'], ['GY', 'East Berbice-Corentyne'], ['GY', 'Essequibo Islands-West Demerara'], ['GY', 'Mahaica-Berbice'], ['GY', 'Upper Demerara-Berbice'], ['HK', 'Hong Kong (SAR)'], ['HN', 'Atlantida'], ['HN', 'Choluteca'], ['HN', 'Colon'], ['HN', 'Comayagua'], ['HN', 'Copan'], ['HN', 'Cortes'], ['HN', 'El Paraiso'], ['HN', 'Francisco Morazan'], ['HN', 'Intibuca'], ['HN', 'Islas de la Bahia'], ['HN', 'La Paz'], ['HN', 'Lempira'], ['HN', 'Ocotepeque'], ['HN', 'Olancho'], ['HN', 'Santa Barbara'], ['HN', 'Valle'], ['HN', 'Yoro'], ['HR', 'Bjelovarsko-bilogorska zupanija'], ['HR', 'Brodsko-posavska zupanija'], ['HR', 'Dubrovacko-neretvanska zupanija'], ['HR', 'Grad Zagreb'], ['HR', 'Istarska zupanija'], ['HR', 'Karlovacka zupanija'], ['HR', 'Koprivnicko-krizevacka zupanija'], ['HR', 'Krapinsko-zagorska zupanija'], ['HR', 'Licko-senjska zupanija'], ['HR', 'Medimurska zupanija'], ['HR', 'Osjecko-baranjska zupanija'], ['HR', 'Pozesko-slavonska zupanija'], ['HR', 'Primorsko-goranska zupanija'], ['HR', 'Sibensko-kninska zupanija'], ['HR', 'Sisacko-moslavacka zupanija'], ['HR', 'Splitsko-dalmatinska zupanija'], ['HR', 'Varazdinska zupanija'], ['HR', 'Viroviticko-podravska zupanija'], ['HR', 'Vukovarsko-srijemska zupanija'], ['HR', 'Zadarska zupanija'], ['HR', 'Zagrebacka zupanija'], ['HT', 'Artibonite'], ['HT', 'Centre'], ['HT', 'Grand\' Anse'], ['HT', 'Nippes'], ['HT', 'Nord'], ['HT', 'Nord-Est'], ['HT', 'Ouest'], ['HT', 'Sud'], ['HT', 'Sud-Est'], ['HU', 'Bacs-Kiskun'], ['HU', 'Baranya'], ['HU', 'Bekes'], ['HU', 'Borsod-Abauj-Zemplen'], ['HU', 'Budapest'], ['HU', 'Csongrad'], ['HU', 'Fejer'], ['HU', 'Gyor-Moson-Sopron'], ['HU', 'Hajdu-Bihar'], ['HU', 'Heves'], ['HU', 'Jasz-Nagykun-Szolnok'], ['HU', 'Komarom-Esztergom'], ['HU', 'Nograd'], ['HU', 'Pest'], ['HU', 'Somogy'], ['HU', 'Szabolcs-Szatmar-Bereg'], ['HU', 'Tolna'], ['HU', 'Vas'], ['HU', 'Veszprem'], ['HU', 'Zala'], ['ID', 'Aceh'], ['ID', 'Bali'], ['ID', 'Bangka Belitung'], ['ID', 'Banten'], ['ID', 'Bengkulu'], ['ID', 'Gorontalo'], ['ID', 'Jakarta Raya'], ['ID', 'Jambi'], ['ID', 'Jawa Barat'], ['ID', 'Jawa Tengah'], ['ID', 'Jawa Timur'], ['ID', 'Kalimantan Barat'], ['ID', 'Kalimantan Selatan'], ['ID', 'Kalimantan Tengah'], ['ID', 'Kalimantan Timur'], ['ID', 'Kepulauan Riau'], ['ID', 'Lampung'], ['ID', 'Maluku'], ['ID', 'Maluku Utara'], ['ID', 'Nusa Tenggara Barat'], ['ID', 'Nusa Tenggara Timur'], ['ID', 'Papua'], ['ID', 'Papua Barat'], ['ID', 'Riau'], ['ID', 'Sulawesi Barat'], ['ID', 'Sulawesi Selatan'], ['ID', 'Sulawesi Tengah'], ['ID', 'Sulawesi Tenggara'], ['ID', 'Sulawesi Utara'], ['ID', 'Sumatera Barat'], ['ID', 'Sumatera Selatan'], ['ID', 'Sumatera Utara'], ['ID', 'Yogyakarta'], ['IE', 'Carlow'], ['IE', 'Cavan'], ['IE', 'Clare'], ['IE', 'Cork'], ['IE', 'Donegal'], ['IE', 'Dublin'], ['IE', 'Galway'], ['IE', 'Kerry'], ['IE', 'Kildare'], ['IE', 'Kilkenny'], ['IE', 'Laois'], ['IE', 'Leitrim'], ['IE', 'Limerick'], ['IE', 'Longford'], ['IE', 'Louth'], ['IE', 'Mayo'], ['IE', 'Meath'], ['IE', 'Monaghan'], ['IE', 'Offaly'], ['IE', 'Roscommon'], ['IE', 'Sligo'], ['IE', 'Tipperary'], ['IE', 'Waterford'], ['IE', 'Westmeath'], ['IE', 'Wexford'], ['IE', 'Wicklow'], ['IL', 'HaDarom'], ['IL', 'HaMerkaz'], ['IL', 'HaTsafon'], ['IL', 'Hefa'], ['IL', 'Tel-Aviv'], ['IL', 'Yerushalayim'], ['IM', 'Isle of Man'], ['IN', 'Andaman and Nicobar Islands'], ['IN', 'Andhra Pradesh'], ['IN', 'Arunachal Pradesh'], ['IN', 'Assam'], ['IN', 'Bihar'], ['IN', 'Chandigarh'], ['IN', 'Chhattisgarh'], ['IN', 'Dadra and Nagar Haveli'], ['IN', 'Daman and Diu'], ['IN', 'Delhi'], ['IN', 'Goa'], ['IN', 'Gujarat'], ['IN', 'Haryana'], ['IN', 'Himachal Pradesh'], ['IN', 'Jammu and Kashmir'], ['IN', 'Jharkhand'], ['IN', 'Karnataka'], ['IN', 'Kerala'], ['IN', 'Lakshadweep'], ['IN', 'Madhya Pradesh'], ['IN', 'Maharashtra'], ['IN', 'Manipur'], ['IN', 'Meghalaya'], ['IN', 'Mizoram'], ['IN', 'Nagaland'], ['IN', 'Odisha'], ['IN', 'Puducherry'], ['IN', 'Punjab'], ['IN', 'Rajasthan'], ['IN', 'Sikkim'], ['IN', 'Tamil Nadu'], ['IN', 'Telangana'], ['IN', 'Tripura'], ['IN', 'Uttar Pradesh'], ['IN', 'Uttarakhand'], ['IN', 'West Bengal'], ['IO', 'British Indian Ocean Territory'], ['IQ', 'Al Anbar'], ['IQ', 'Al Basrah'], ['IQ', 'Al Muthanna'], ['IQ', 'Al Qadisiyah'], ['IQ', 'An Najaf'], ['IQ', 'Arbil'], ['IQ', 'As Sulaymaniyah'], ['IQ', 'Babil'], ['IQ', 'Baghdad'], ['IQ', 'Dahuk'], ['IQ', 'Dhi Qar'], ['IQ', 'Diyala'], ['IQ', 'Karbala\''], ['IQ', 'Kirkuk'], ['IQ', 'Maysan'], ['IQ', 'Ninawa'], ['IQ', 'Salah ad Din'], ['IQ', 'Wasit'], ['IR', 'Alborz'], ['IR', 'Ardabil'], ['IR', 'Azarbayjan-e Gharbi'], ['IR', 'Azarbayjan-e Sharqi'], ['IR', 'Bushehr'], ['IR', 'Chahar Mahal va Bakhtiari'], ['IR', 'Esfahan'], ['IR', 'Fars'], ['IR', 'Gilan'], ['IR', 'Golestan'], ['IR', 'Hamadan'], ['IR', 'Hormozgan'], ['IR', 'Ilam'], ['IR', 'Kerman'], ['IR', 'Kermanshah'], ['IR', 'Khorasan-e Jonubi'], ['IR', 'Khorasan-e Razavi'], ['IR', 'Khorasan-e Shomali'], ['IR', 'Khuzestan'], ['IR', 'Kohgiluyeh va Bowyer Ahmad'], ['IR', 'Kordestan'], ['IR', 'Lorestan'], ['IR', 'Markazi'], ['IR', 'Mazandaran'], ['IR', 'Qazvin'], ['IR', 'Qom'], ['IR', 'Semnan'], ['IR', 'Sistan va Baluchestan'], ['IR', 'Tehran'], ['IR', 'Yazd'], ['IR', 'Zanjan'], ['IS', 'Austurland'], ['IS', 'Hofudborgarsvaedi utan Reykjavikur'], ['IS', 'Nordurland eystra'], ['IS', 'Nordurland vestra'], ['IS', 'Sudurland'], ['IS', 'Sudurnes'], ['IS', 'Vestfirdir'], ['IS', 'Vesturland'], ['IT', 'Abruzzo'], ['IT', 'Basilicata'], ['IT', 'Calabria'], ['IT', 'Campania'], ['IT', 'Emilia-Romagna'], ['IT', 'Friuli-Venezia Giulia'], ['IT', 'Lazio'], ['IT', 'Liguria'], ['IT', 'Lombardia'], ['IT', 'Marche'], ['IT', 'Molise'], ['IT', 'Piemonte'], ['IT', 'Puglia'], ['IT', 'Sardegna'], ['IT', 'Sicilia'], ['IT', 'Toscana'], ['IT', 'Trentino-Alto Adige'], ['IT', 'Umbria'], ['IT', 'Valle d\'Aosta'], ['IT', 'Veneto'], ['JE', 'Jersey'], ['JM', 'Clarendon'], ['JM', 'Hanover'], ['JM', 'Kingston'], ['JM', 'Manchester'], ['JM', 'Portland'], ['JM', 'Saint Andrew'], ['JM', 'Saint Ann'], ['JM', 'Saint Catherine'], ['JM', 'Saint Elizabeth'], ['JM', 'Saint James'], ['JM', 'Saint Mary'], ['JM', 'Saint Thomas'], ['JM', 'Trelawny'], ['JM', 'Westmoreland'], ['JO', 'Al \'Aqabah'], ['JO', 'Al \'Asimah'], ['JO', 'Al Balqa\''], ['JO', 'Al Karak'], ['JO', 'Al Mafraq'], ['JO', 'At Tafilah'], ['JO', 'Az Zarqa\''], ['JO', 'Irbid'], ['JO', 'Ma\'an'], ['JO', 'Madaba'], ['JP', 'Aichi'], ['JP', 'Akita'], ['JP', 'Aomori'], ['JP', 'Chiba'], ['JP', 'Ehime'], ['JP', 'Fukui'], ['JP', 'Fukuoka'], ['JP', 'Fukushima'], ['JP', 'Gifu'], ['JP', 'Gunma'], ['JP', 'Hiroshima'], ['JP', 'Hokkaido'], ['JP', 'Hyogo'], ['JP', 'Ibaraki'], ['JP', 'Ishikawa'], ['JP', 'Iwate'], ['JP', 'Kagawa'], ['JP', 'Kagoshima'], ['JP', 'Kanagawa'], ['JP', 'Kochi'], ['JP', 'Kumamoto'], ['JP', 'Kyoto'], ['JP', 'Mie'], ['JP', 'Miyagi'], ['JP', 'Miyazaki'], ['JP', 'Nagano'], ['JP', 'Nagasaki'], ['JP', 'Nara'], ['JP', 'Niigata'], ['JP', 'Oita'], ['JP', 'Okayama'], ['JP', 'Okinawa'], ['JP', 'Osaka'], ['JP', 'Saga'], ['JP', 'Saitama'], ['JP', 'Shiga'], ['JP', 'Shimane'], ['JP', 'Shizuoka'], ['JP', 'Tochigi'], ['JP', 'Tokushima'], ['JP', 'Tokyo'], ['JP', 'Tottori'], ['JP', 'Toyama'], ['JP', 'Wakayama'], ['JP', 'Yamagata'], ['JP', 'Yamaguchi'], ['JP', 'Yamanashi'], ['KE', 'Central'], ['KE', 'Coast'], ['KE', 'Eastern'], ['KE', 'Nairobi Area'], ['KE', 'North-Eastern'], ['KE', 'Nyanza'], ['KE', 'Rift Valley'], ['KE', 'Western'], ['KG', 'Batken'], ['KG', 'Bishkek'], ['KG', 'Chu'], ['KG', 'Jalal-Abad'], ['KG', 'Naryn'], ['KG', 'Osh'], ['KG', 'Talas'], ['KG', 'Ysyk-Kol'], ['KH', 'Baat Dambang'], ['KH', 'Banteay Mean Chey'], ['KH', 'Kampong Chaam'], ['KH', 'Kampong Chhnang'], ['KH', 'Kampong Spueu'], ['KH', 'Kampong Thum'], ['KH', 'Kampot'], ['KH', 'Kandaal'], ['KH', 'Kaoh Kong'], ['KH', 'Kracheh'], ['KH', 'Krong Kaeb'], ['KH', 'Krong Pailin'], ['KH', 'Krong Preah Sihanouk'], ['KH', 'Mondol Kiri'], ['KH', 'Otdar Mean Chey'], ['KH', 'Phnom Penh'], ['KH', 'Pousaat'], ['KH', 'Preah Vihear'], ['KH', 'Prey Veaeng'], ['KH', 'Rotanak Kiri'], ['KH', 'Siem Reab'], ['KH', 'Stueng Traeng'], ['KH', 'Svaay Rieng'], ['KH', 'Taakaev'], ['KI', 'Gilbert Islands'], ['KM', 'Anjouan'], ['KM', 'Grande Comore'], ['KN', 'Saint George Basseterre'], ['KN', 'Saint Paul Charlestown'], ['KP', 'P\'yongyang'], ['KR', 'Busan'], ['KR', 'Chungbuk'], ['KR', 'Chungnam'], ['KR', 'Daegu'], ['KR', 'Daejeon'], ['KR', 'Gangwon'], ['KR', 'Gwangju'], ['KR', 'Gyeongbuk'], ['KR', 'Gyeonggi'], ['KR', 'Gyeongnam'], ['KR', 'Incheon'], ['KR', 'Jeju'], ['KR', 'Jeonbuk'], ['KR', 'Jeonnam'], ['KR', 'Seoul'], ['KR', 'Ulsan'], ['KW', 'Al Ahmadi'], ['KW', 'Al Asimah'], ['KW', 'Al Farwaniyah'], ['KW', 'Al Jahra'], ['KW', 'Hawalli'], ['KW', 'Mubarak al Kabir'], ['KY', 'Cayman Islands'], ['KZ', 'Almaty'], ['KZ', 'Almaty oblysy'], ['KZ', 'Aqmola oblysy'], ['KZ', 'Aqtobe oblysy'], ['KZ', 'Astana'], ['KZ', 'Atyrau oblysy'], ['KZ', 'Batys Qazaqstan oblysy'], ['KZ', 'Bayqonyr'], ['KZ', 'Mangghystau oblysy'], ['KZ', 'Ongtustik Qazaqstan oblysy'], ['KZ', 'Pavlodar oblysy'], ['KZ', 'Qaraghandy oblysy'], ['KZ', 'Qostanay oblysy'], ['KZ', 'Qyzylorda oblysy'], ['KZ', 'Shyghys Qazaqstan oblysy'], ['KZ', 'Soltustik Qazaqstan oblysy'], ['KZ', 'Zhambyl oblysy'], ['LA', 'Attapu'], ['LA', 'Bolikhamxai'], ['LA', 'Champasak'], ['LA', 'Houaphan'], ['LA', 'Khammouan'], ['LA', 'Louang Namtha'], ['LA', 'Louangphabang'], ['LA', 'Oudomxai'], ['LA', 'Phongsali'], ['LA', 'Savannakhet'], ['LA', 'Vientiane'], ['LA', 'Xaignabouli'], ['LA', 'Xiangkhouang'], ['LB', 'Aakkar'], ['LB', 'Baalbek-Hermel'], ['LB', 'Beqaa'], ['LB', 'Beyrouth'], ['LB', 'Liban-Nord'], ['LB', 'Liban-Sud'], ['LB', 'Mont-Liban'], ['LB', 'Nabatiye'], ['LC', 'Castries'], ['LC', 'Dennery'], ['LC', 'Gros Islet'], ['LC', 'Laborie'], ['LC', 'Soufriere'], ['LC', 'Vieux Fort'], ['LI', 'Balzers'], ['LI', 'Eschen'], ['LI', 'Gamprin'], ['LI', 'Mauren'], ['LI', 'Planken'], ['LI', 'Ruggell'], ['LI', 'Schaan'], ['LI', 'Schellenberg'], ['LI', 'Triesen'], ['LI', 'Triesenberg'], ['LI', 'Vaduz'], ['LK', 'Central Province'], ['LK', 'Eastern Province'], ['LK', 'North Central Province'], ['LK', 'North Western Province'], ['LK', 'Northern Province'], ['LK', 'Sabaragamuwa Province'], ['LK', 'Southern Province'], ['LK', 'Uva Province'], ['LK', 'Western Province'], ['LR', 'Bong'], ['LR', 'Grand Bassa'], ['LR', 'Grand Gedeh'], ['LR', 'Maryland'], ['LR', 'Montserrado'], ['LR', 'Nimba'], ['LR', 'River Gee'], ['LR', 'Sinoe'], ['LS', 'Butha-Buthe'], ['LS', 'Leribe'], ['LS', 'Maseru'], ['LS', 'Mohale\'s Hoek'], ['LS', 'Quthing'], ['LT', 'Alytaus Apskritis'], ['LT', 'Kauno Apskritis'], ['LT', 'Klaipedos Apskritis'], ['LT', 'Marijampoles Apskritis'], ['LT', 'Panevezio Apskritis'], ['LT', 'Siauliu Apskritis'], ['LT', 'Taurages Apskritis'], ['LT', 'Telsiu Apskritis'], ['LT', 'Utenos Apskritis'], ['LT', 'Vilniaus Apskritis'], ['LU', 'Diekirch'], ['LU', 'Grevenmacher'], ['LU', 'Luxembourg'], ['LV', 'Adazu'], ['LV', 'Aglonas'], ['LV', 'Aizkraukles'], ['LV', 'Aizputes'], ['LV', 'Alojas'], ['LV', 'Aluksnes'], ['LV', 'Babites'], ['LV', 'Baltinavas'], ['LV', 'Balvu'], ['LV', 'Bauskas'], ['LV', 'Beverinas'], ['LV', 'Brocenu'], ['LV', 'Carnikavas'], ['LV', 'Cesu'], ['LV', 'Cesvaines'], ['LV', 'Daugavpils'], ['LV', 'Dobeles'], ['LV', 'Dundagas'], ['LV', 'Gulbenes'], ['LV', 'Iecavas'], ['LV', 'Incukalna'], ['LV', 'Jaunjelgavas'], ['LV', 'Jaunpiebalgas'], ['LV', 'Jaunpils'], ['LV', 'Jekabpils'], ['LV', 'Jelgava'], ['LV', 'Jelgavas'], ['LV', 'Jurmala'], ['LV', 'Kekavas'], ['LV', 'Kokneses'], ['LV', 'Kraslavas'], ['LV', 'Kuldigas'], ['LV', 'Liepaja'], ['LV', 'Liepajas'], ['LV', 'Limbazu'], ['LV', 'Lubanas'], ['LV', 'Ludzas'], ['LV', 'Madonas'], ['LV', 'Malpils'], ['LV', 'Ogres'], ['LV', 'Olaines'], ['LV', 'Ozolnieku'], ['LV', 'Preilu'], ['LV', 'Rezeknes'], ['LV', 'Riga'], ['LV', 'Rigas'], ['LV', 'Rojas'], ['LV', 'Salacgrivas'], ['LV', 'Saldus'], ['LV', 'Sejas'], ['LV', 'Siguldas'], ['LV', 'Skrundas'], ['LV', 'Stopinu'], ['LV', 'Talsu'], ['LV', 'Tukuma'], ['LV', 'Valkas'], ['LV', 'Valmieras'], ['LV', 'Vecumnieku'], ['LV', 'Ventspils'], ['LY', 'Al Butnan'], ['LY', 'Al Jabal al Akhdar'], ['LY', 'Al Jabal al Gharbi'], ['LY', 'Al Jafarah'], ['LY', 'Al Jufrah'], ['LY', 'Al Marj'], ['LY', 'Al Marqab'], ['LY', 'Al Wahat'], ['LY', 'An Nuqat al Khams'], ['LY', 'Az Zawiyah'], ['LY', 'Banghazi'], ['LY', 'Darnah'], ['LY', 'Misratah'], ['LY', 'Murzuq'], ['LY', 'Sabha'], ['LY', 'Surt'], ['LY', 'Tarabulus'], ['LY', 'Wadi ash Shati\''], ['MA', 'Chaouia-Ouardigha'], ['MA', 'Doukhala-Abda'], ['MA', 'Fes-Boulemane'], ['MA', 'Gharb-Chrarda-Beni Hssen'], ['MA', 'Grand Casablanca'], ['MA', 'Guelmim-Es Semara'], ['MA', 'L\'Oriental'], ['MA', 'Marrakech-Tensift-Al Haouz'], ['MA', 'Meknes-Tafilalet'], ['MA', 'Rabat-Sale-Zemmour-Zaer'], ['MA', 'Souss-Massa-Draa'], ['MA', 'Tadla-Azilal'], ['MA', 'Tanger-Tetouan'], ['MA', 'Taza-Al Hoceima-Taounate'], ['MC', 'Monaco'], ['MD', 'Anenii Noi'], ['MD', 'Balti'], ['MD', 'Basarabeasca'], ['MD', 'Bender'], ['MD', 'Briceni'], ['MD', 'Cahul'], ['MD', 'Calarasi'], ['MD', 'Cantemir'], ['MD', 'Causeni'], ['MD', 'Chisinau'], ['MD', 'Cimislia'], ['MD', 'Criuleni'], ['MD', 'Donduseni'], ['MD', 'Drochia'], ['MD', 'Dubasari'], ['MD', 'Edinet'], ['MD', 'Falesti'], ['MD', 'Floresti'], ['MD', 'Gagauzia, Unitatea teritoriala autonoma'], ['MD', 'Glodeni'], ['MD', 'Hincesti'], ['MD', 'Ialoveni'], ['MD', 'Leova'], ['MD', 'Nisporeni'], ['MD', 'Ocnita'], ['MD', 'Orhei'], ['MD', 'Rezina'], ['MD', 'Riscani'], ['MD', 'Singerei'], ['MD', 'Soldanesti'], ['MD', 'Soroca'], ['MD', 'Stefan Voda'], ['MD', 'Stinga Nistrului, unitatea teritoriala din'], ['MD', 'Straseni'], ['MD', 'Taraclia'], ['MD', 'Telenesti'], ['MD', 'Ungheni'], ['ME', 'Bar'], ['ME', 'Budva'], ['ME', 'Cetinje'], ['ME', 'Danilovgrad'], ['ME', 'Herceg-Novi'], ['ME', 'Kolasin'], ['ME', 'Kotor'], ['ME', 'Mojkovac'], ['ME', 'Niksic'], ['ME', 'Podgorica'], ['ME', 'Tivat'], ['ME', 'Ulcinj'], ['ME', 'Zabljak'], ['MF', 'Saint Martin'], ['MG', 'Antananarivo'], ['MG', 'Antsiranana'], ['MG', 'Fianarantsoa'], ['MG', 'Mahajanga'], ['MG', 'Toamasina'], ['MG', 'Toliara'], ['MH', 'Majuro Atoll'], ['MK', 'Aracinovo'], ['MK', 'Berovo'], ['MK', 'Bitola'], ['MK', 'Bogdanci'], ['MK', 'Bogovinje'], ['MK', 'Bosilovo'], ['MK', 'Brvenica'], ['MK', 'Centar Zupa'], ['MK', 'Cesinovo-Oblesevo'], ['MK', 'Cucer Sandevo'], ['MK', 'Debar'], ['MK', 'Delcevo'], ['MK', 'Demir Hisar'], ['MK', 'Dojran'], ['MK', 'Dolneni'], ['MK', 'Gevgelija'], ['MK', 'Gostivar'], ['MK', 'Ilinden'], ['MK', 'Kavadarci'], ['MK', 'Kicevo'], ['MK', 'Kocani'], ['MK', 'Kondovo'], ['MK', 'Kratovo'], ['MK', 'Kriva Palanka'], ['MK', 'Krusevo'], ['MK', 'Kumanovo'], ['MK', 'Lipkovo'], ['MK', 'Lozovo'], ['MK', 'Makedonska Kamenica'], ['MK', 'Mavrovo i Rostusa'], ['MK', 'Negotino'], ['MK', 'Novo Selo'], ['MK', 'Ohrid'], ['MK', 'Pehcevo'], ['MK', 'Petrovec'], ['MK', 'Prilep'], ['MK', 'Probistip'], ['MK', 'Radovis'], ['MK', 'Rankovce'], ['MK', 'Resen'], ['MK', 'Rosoman'], ['MK', 'Skopje'], ['MK', 'Sopiste'], ['MK', 'Stip'], ['MK', 'Struga'], ['MK', 'Strumica'], ['MK', 'Studenicani'], ['MK', 'Sveti Nikole'], ['MK', 'Tearce'], ['MK', 'Tetovo'], ['MK', 'Valandovo'], ['MK', 'Vasilevo'], ['MK', 'Veles'], ['MK', 'Vinica'], ['MK', 'Vrapciste'], ['MK', 'Zelenikovo'], ['MK', 'Zelino'], ['ML', 'Bamako'], ['ML', 'Gao'], ['ML', 'Kayes'], ['ML', 'Kidal'], ['ML', 'Koulikoro'], ['ML', 'Mopti'], ['ML', 'Segou'], ['ML', 'Sikasso'], ['ML', 'Tombouctou'], ['MM', 'Ayeyawady'], ['MM', 'Bago'], ['MM', 'Magway'], ['MM', 'Mandalay'], ['MM', 'Mon'], ['MM', 'Nay Pyi Taw'], ['MM', 'Sagaing'], ['MM', 'Shan'], ['MM', 'Yangon'], ['MN', 'Arhangay'], ['MN', 'Bayanhongor'], ['MN', 'Darhan uul'], ['MN', 'Dornod'], ['MN', 'Dornogovi'], ['MN', 'Govi-Altay'], ['MN', 'Hovsgol'], ['MN', 'Omnogovi'], ['MN', 'Orhon'], ['MN', 'Ovorhangay'], ['MN', 'Selenge'], ['MN', 'Tov'], ['MN', 'Ulaanbaatar'], ['MN', 'Uvs'], ['MO', 'Macau'], ['MP', 'Northern Mariana Islands'], ['MQ', 'Martinique'], ['MR', 'Assaba'], ['MR', 'Brakna'], ['MR', 'Dakhlet Nouadhibou'], ['MR', 'Guidimaka'], ['MR', 'Inchiri'], ['MR', 'Nouakchott'], ['MR', 'Tiris Zemmour'], ['MR', 'Trarza'], ['MS', 'Saint Anthony'], ['MS', 'Saint Peter'], ['MT', 'Malta'], ['MU', 'Black River'], ['MU', 'Flacq'], ['MU', 'Grand Port'], ['MU', 'Moka'], ['MU', 'Pamplemousses'], ['MU', 'Plaines Wilhems'], ['MU', 'Port Louis'], ['MU', 'Riviere du Rempart'], ['MU', 'Savanne'], ['MV', 'Alifu'], ['MV', 'Baa'], ['MV', 'Gaafu Dhaalu'], ['MV', 'Haa Alifu'], ['MV', 'Haa Dhaalu'], ['MV', 'Kaafu'], ['MV', 'Laamu'], ['MV', 'Maale'], ['MV', 'Meemu'], ['MV', 'Noonu'], ['MV', 'Raa'], ['MV', 'Seenu'], ['MV', 'Thaa'], ['MW', 'Balaka'], ['MW', 'Blantyre'], ['MW', 'Lilongwe'], ['MW', 'Machinga'], ['MW', 'Mangochi'], ['MW', 'Mzimba'], ['MW', 'Ntchisi'], ['MW', 'Salima'], ['MW', 'Zomba'], ['MX', 'Aguascalientes'], ['MX', 'Baja California'], ['MX', 'Baja California Sur'], ['MX', 'Campeche'], ['MX', 'Chiapas'], ['MX', 'Chihuahua'], ['MX', 'Coahuila'], ['MX', 'Colima'], ['MX', 'Distrito Federal'], ['MX', 'Durango'], ['MX', 'Guanajuato'], ['MX', 'Guerrero'], ['MX', 'Hidalgo'], ['MX', 'Jalisco'], ['MX', 'Mexico'], ['MX', 'Michoacan'], ['MX', 'Morelos'], ['MX', 'Nayarit'], ['MX', 'Nuevo Leon'], ['MX', 'Oaxaca'], ['MX', 'Puebla'], ['MX', 'Queretaro'], ['MX', 'Quintana Roo'], ['MX', 'San Luis Potosi'], ['MX', 'Sinaloa'], ['MX', 'Sonora'], ['MX', 'Tabasco'], ['MX', 'Tamaulipas'], ['MX', 'Tlaxcala'], ['MX', 'Veracruz'], ['MX', 'Yucatan'], ['MX', 'Zacatecas'], ['MY', 'Johor'], ['MY', 'Kedah'], ['MY', 'Kelantan'], ['MY', 'Melaka'], ['MY', 'Negeri Sembilan'], ['MY', 'Pahang'], ['MY', 'Perak'], ['MY', 'Perlis'], ['MY', 'Pulau Pinang'], ['MY', 'Sabah'], ['MY', 'Sarawak'], ['MY', 'Selangor'], ['MY', 'Terengganu'], ['MY', 'Wilayah Persekutuan Kuala Lumpur'], ['MY', 'Wilayah Persekutuan Labuan'], ['MY', 'Wilayah Persekutuan Putrajaya'], ['MZ', 'Cabo Delgado'], ['MZ', 'Gaza'], ['MZ', 'Inhambane'], ['MZ', 'Manica'], ['MZ', 'Maputo'], ['MZ', 'Nampula'], ['MZ', 'Niassa'], ['MZ', 'Sofala'], ['MZ', 'Tete'], ['MZ', 'Zambezia'], ['NA', 'Erongo'], ['NA', 'Hardap'], ['NA', 'Karas'], ['NA', 'Khomas'], ['NA', 'Kunene'], ['NA', 'Ohangwena'], ['NA', 'Okavango'], ['NA', 'Omaheke'], ['NA', 'Omusati'], ['NA', 'Oshana'], ['NA', 'Oshikoto'], ['NA', 'Otjozondjupa'], ['NA', 'Zambezi'], ['NC', 'Province Nord'], ['NC', 'Province Sud'], ['NC', 'Province des iles Loyaute'], ['NE', 'Agadez'], ['NE', 'Diffa'], ['NE', 'Dosso'], ['NE', 'Niamey'], ['NE', 'Tahoua'], ['NE', 'Zinder'], ['NF', 'Norfolk Island'], ['NG', 'Abia'], ['NG', 'Abuja Federal Capital Territory'], ['NG', 'Adamawa'], ['NG', 'Akwa Ibom'], ['NG', 'Anambra'], ['NG', 'Bauchi'], ['NG', 'Bayelsa'], ['NG', 'Benue'], ['NG', 'Borno'], ['NG', 'Cross River'], ['NG', 'Delta'], ['NG', 'Ebonyi'], ['NG', 'Edo'], ['NG', 'Ekiti'], ['NG', 'Enugu'], ['NG', 'Gombe'], ['NG', 'Imo'], ['NG', 'Jigawa'], ['NG', 'Kaduna'], ['NG', 'Kano'], ['NG', 'Katsina'], ['NG', 'Kebbi'], ['NG', 'Kogi'], ['NG', 'Kwara'], ['NG', 'Lagos'], ['NG', 'Nasarawa'], ['NG', 'Niger'], ['NG', 'Ogun'], ['NG', 'Ondo'], ['NG', 'Osun'], ['NG', 'Oyo'], ['NG', 'Plateau'], ['NG', 'Rivers'], ['NG', 'Sokoto'], ['NG', 'Taraba'], ['NG', 'Yobe'], ['NG', 'Zamfara'], ['NI', 'Atlantico Norte'], ['NI', 'Atlantico Sur'], ['NI', 'Boaco'], ['NI', 'Carazo'], ['NI', 'Chinandega'], ['NI', 'Chontales'], ['NI', 'Esteli'], ['NI', 'Granada'], ['NI', 'Jinotega'], ['NI', 'Leon'], ['NI', 'Madriz'], ['NI', 'Managua'], ['NI', 'Masaya'], ['NI', 'Matagalpa'], ['NI', 'Nueva Segovia'], ['NI', 'Rio San Juan'], ['NI', 'Rivas'], ['NL', 'Drenthe'], ['NL', 'Flevoland'], ['NL', 'Fryslan'], ['NL', 'Gelderland'], ['NL', 'Groningen'], ['NL', 'Limburg'], ['NL', 'Noord-Brabant'], ['NL', 'Noord-Holland'], ['NL', 'Overijssel'], ['NL', 'Utrecht'], ['NL', 'Zeeland'], ['NL', 'Zuid-Holland'], ['NO', 'Akershus'], ['NO', 'Aust-Agder'], ['NO', 'Buskerud'], ['NO', 'Finnmark'], ['NO', 'Hedmark'], ['NO', 'Hordaland'], ['NO', 'More og Romsdal'], ['NO', 'Nord-Trondelag'], ['NO', 'Nordland'], ['NO', 'Oppland'], ['NO', 'Oslo'], ['NO', 'Ostfold'], ['NO', 'Rogaland'], ['NO', 'Sogn og Fjordane'], ['NO', 'Sor-Trondelag'], ['NO', 'Telemark'], ['NO', 'Troms'], ['NO', 'Vest-Agder'], ['NO', 'Vestfold'], ['NP', 'Bagmati'], ['NP', 'Bheri'], ['NP', 'Dhawalagiri'], ['NP', 'Gandaki'], ['NP', 'Janakpur'], ['NP', 'Karnali'], ['NP', 'Kosi'], ['NP', 'Lumbini'], ['NP', 'Mahakali'], ['NP', 'Mechi'], ['NP', 'Narayani'], ['NP', 'Rapti'], ['NP', 'Sagarmatha'], ['NP', 'Seti'], ['NR', 'Yaren'], ['NU', 'Niue'], ['NZ', 'Auckland'], ['NZ', 'Bay of Plenty'], ['NZ', 'Canterbury'], ['NZ', 'Gisborne'], ['NZ', 'Hawke\'s Bay'], ['NZ', 'Manawatu-Wanganui'], ['NZ', 'Marlborough'], ['NZ', 'Nelson'], ['NZ', 'Northland'], ['NZ', 'Otago'], ['NZ', 'Southland'], ['NZ', 'Taranaki'], ['NZ', 'Tasman'], ['NZ', 'Waikato'], ['NZ', 'Wellington'], ['NZ', 'West Coast'], ['OM', 'Ad Dakhiliyah'], ['OM', 'Al Buraymi'], ['OM', 'Al Wusta'], ['OM', 'Az Zahirah'], ['OM', 'Janub al Batinah'], ['OM', 'Janub ash Sharqiyah'], ['OM', 'Masqat'], ['OM', 'Musandam'], ['OM', 'Shamal al Batinah'], ['OM', 'Shamal ash Sharqiyah'], ['OM', 'Zufar'], ['PA', 'Bocas del Toro'], ['PA', 'Chiriqui'], ['PA', 'Cocle'], ['PA', 'Colon'], ['PA', 'Darien'], ['PA', 'Herrera'], ['PA', 'Los Santos'], ['PA', 'Panama'], ['PA', 'San Blas'], ['PA', 'Veraguas'], ['PE', 'Amazonas'], ['PE', 'Ancash'], ['PE', 'Apurimac'], ['PE', 'Arequipa'], ['PE', 'Ayacucho'], ['PE', 'Cajamarca'], ['PE', 'Callao'], ['PE', 'Cusco'], ['PE', 'Huancavelica'], ['PE', 'Huanuco'], ['PE', 'Ica'], ['PE', 'Junin'], ['PE', 'La Libertad'], ['PE', 'Lambayeque'], ['PE', 'Lima'], ['PE', 'Loreto'], ['PE', 'Madre de Dios'], ['PE', 'Moquegua'], ['PE', 'Pasco'], ['PE', 'Piura'], ['PE', 'Puno'], ['PE', 'San Martin'], ['PE', 'Tacna'], ['PE', 'Tumbes'], ['PE', 'Ucayali'], ['PF', 'Iles Marquises'], ['PF', 'Iles Sous-le-Vent'], ['PF', 'Iles du Vent'], ['PG', 'East New Britain'], ['PG', 'Enga'], ['PG', 'Gulf'], ['PG', 'Madang'], ['PG', 'Manus'], ['PG', 'Morobe'], ['PG', 'National Capital District'], ['PG', 'New Ireland'], ['PG', 'Southern Highlands'], ['PG', 'Western Highlands'], ['PH', 'Abra'], ['PH', 'Agusan del Norte'], ['PH', 'Agusan del Sur'], ['PH', 'Aklan'], ['PH', 'Albay'], ['PH', 'Antique'], ['PH', 'Apayao'], ['PH', 'Aurora'], ['PH', 'Basilan'], ['PH', 'Bataan'], ['PH', 'Batanes'], ['PH', 'Batangas'], ['PH', 'Benguet'], ['PH', 'Bohol'], ['PH', 'Bukidnon'], ['PH', 'Bulacan'], ['PH', 'Cagayan'], ['PH', 'Camarines Norte'], ['PH', 'Camarines Sur'], ['PH', 'Camiguin'], ['PH', 'Capiz'], ['PH', 'Catanduanes'], ['PH', 'Cavite'], ['PH', 'Cebu'], ['PH', 'Cotabato'], ['PH', 'Davao Oriental'], ['PH', 'Davao del Sur'], ['PH', 'Eastern Samar'], ['PH', 'Ifugao'], ['PH', 'Ilocos Norte'], ['PH', 'Ilocos Sur'], ['PH', 'Iloilo'], ['PH', 'Isabela'], ['PH', 'La Union'], ['PH', 'Laguna'], ['PH', 'Lanao del Norte'], ['PH', 'Lanao del Sur'], ['PH', 'Leyte'], ['PH', 'Maguindanao'], ['PH', 'Marinduque'], ['PH', 'Masbate'], ['PH', 'Mindoro Occidental'], ['PH', 'Mindoro Oriental'], ['PH', 'Misamis Occidental'], ['PH', 'Misamis Oriental'], ['PH', 'Mountain Province'], ['PH', 'National Capital Region'], ['PH', 'Negros Occidental'], ['PH', 'Negros Oriental'], ['PH', 'Northern Samar'], ['PH', 'Nueva Ecija'], ['PH', 'Nueva Vizcaya'], ['PH', 'Palawan'], ['PH', 'Pampanga'], ['PH', 'Pangasinan'], ['PH', 'Quezon'], ['PH', 'Quirino'], ['PH', 'Rizal'], ['PH', 'Romblon'], ['PH', 'Samar (Western Samar)'], ['PH', 'Siquijor'], ['PH', 'Sorsogon'], ['PH', 'South Cotabato'], ['PH', 'Southern Leyte'], ['PH', 'Sultan Kudarat'], ['PH', 'Sulu'], ['PH', 'Surigao del Norte'], ['PH', 'Surigao del Sur'], ['PH', 'Tarlac'], ['PH', 'Tawi-Tawi'], ['PH', 'Zambales'], ['PH', 'Zamboanga del Norte'], ['PH', 'Zamboanga del Sur'], ['PK', 'Azad Kashmir'], ['PK', 'Balochistan'], ['PK', 'Federally Administered Tribal Areas'], ['PK', 'Gilgit-Baltistan'], ['PK', 'Islamabad'], ['PK', 'Khyber Pakhtunkhwa'], ['PK', 'Punjab'], ['PK', 'Sindh'], ['PL', 'Dolnoslaskie'], ['PL', 'Kujawsko-Pomorskie'], ['PL', 'Lodzkie'], ['PL', 'Lubelskie'], ['PL', 'Lubuskie'], ['PL', 'Malopolskie'], ['PL', 'Mazowieckie'], ['PL', 'Opolskie'], ['PL', 'Podkarpackie'], ['PL', 'Podlaskie'], ['PL', 'Pomorskie'], ['PL', 'Slaskie'], ['PL', 'Swietokrzyskie'], ['PL', 'Warminsko-Mazurskie'], ['PL', 'Wielkopolskie'], ['PL', 'Zachodniopomorskie'], ['PM', 'Saint Pierre and Miquelon'], ['PN', 'Pitcairn Islands'], ['PR', 'Adjuntas'], ['PR', 'Aguada'], ['PR', 'Aguadilla'], ['PR', 'Aguas Buenas'], ['PR', 'Aibonito'], ['PR', 'Anasco'], ['PR', 'Arecibo'], ['PR', 'Arroyo'], ['PR', 'Barceloneta'], ['PR', 'Barranquitas'], ['PR', 'Bayamon'], ['PR', 'Cabo Rojo'], ['PR', 'Caguas'], ['PR', 'Camuy'], ['PR', 'Canovanas'], ['PR', 'Carolina'], ['PR', 'Catano'], ['PR', 'Cayey'], ['PR', 'Ceiba'], ['PR', 'Ciales'], ['PR', 'Cidra'], ['PR', 'Coamo'], ['PR', 'Comerio'], ['PR', 'Corozal'], ['PR', 'Culebra'], ['PR', 'Dorado'], ['PR', 'Fajardo'], ['PR', 'Florida'], ['PR', 'Guanica'], ['PR', 'Guayama'], ['PR', 'Guayanilla'], ['PR', 'Guaynabo'], ['PR', 'Gurabo'], ['PR', 'Hatillo'], ['PR', 'Hormigueros'], ['PR', 'Humacao'], ['PR', 'Isabela'], ['PR', 'Juana Diaz'], ['PR', 'Lajas'], ['PR', 'Lares'], ['PR', 'Las Marias'], ['PR', 'Las Piedras'], ['PR', 'Loiza'], ['PR', 'Luquillo'], ['PR', 'Manati'], ['PR', 'Maricao'], ['PR', 'Maunabo'], ['PR', 'Mayaguez'], ['PR', 'Moca'], ['PR', 'Morovis'], ['PR', 'Municipio de Jayuya'], ['PR', 'Municipio de Juncos'], ['PR', 'Naguabo'], ['PR', 'Naranjito'], ['PR', 'Patillas'], ['PR', 'Penuelas'], ['PR', 'Ponce'], ['PR', 'Quebradillas'], ['PR', 'Rincon'], ['PR', 'Rio Grande'], ['PR', 'Sabana Grande'], ['PR', 'Salinas'], ['PR', 'San German'], ['PR', 'San Juan'], ['PR', 'San Lorenzo'], ['PR', 'San Sebastian'], ['PR', 'Santa Isabel Municipio'], ['PR', 'Toa Alta'], ['PR', 'Toa Baja'], ['PR', 'Trujillo Alto'], ['PR', 'Utuado'], ['PR', 'Vega Alta'], ['PR', 'Vega Baja'], ['PR', 'Vieques'], ['PR', 'Villalba'], ['PR', 'Yabucoa'], ['PR', 'Yauco'], ['PS', 'Gaza'], ['PS', 'West Bank'], ['PT', 'Aveiro'], ['PT', 'Beja'], ['PT', 'Braga'], ['PT', 'Braganca'], ['PT', 'Castelo Branco'], ['PT', 'Coimbra'], ['PT', 'Evora'], ['PT', 'Faro'], ['PT', 'Guarda'], ['PT', 'Leiria'], ['PT', 'Lisboa'], ['PT', 'Portalegre'], ['PT', 'Porto'], ['PT', 'Regiao Autonoma da Madeira'], ['PT', 'Regiao Autonoma dos Acores'], ['PT', 'Santarem'], ['PT', 'Setubal'], ['PT', 'Viana do Castelo'], ['PT', 'Vila Real'], ['PT', 'Viseu'], ['PW', 'Airai'], ['PW', 'Koror'], ['PW', 'Melekeok'], ['PW', 'Peleliu'], ['PY', 'Alto Parana'], ['PY', 'Asuncion'], ['PY', 'Boqueron'], ['PY', 'Caaguazu'], ['PY', 'Canindeyu'], ['PY', 'Central'], ['PY', 'Concepcion'], ['PY', 'Cordillera'], ['PY', 'Guaira'], ['PY', 'Itapua'], ['PY', 'Misiones'], ['PY', 'Neembucu'], ['PY', 'Paraguari'], ['PY', 'Presidente Hayes'], ['PY', 'San Pedro'], ['QA', 'Ad Dawhah'], ['QA', 'Al Khawr wa adh Dhakhirah'], ['QA', 'Al Wakrah'], ['QA', 'Ar Rayyan'], ['QA', 'Ash Shamal'], ['QA', 'Az Za\'ayin'], ['QA', 'Umm Salal'], ['RE', 'Reunion'], ['RO', 'Alba'], ['RO', 'Arad'], ['RO', 'Arges'], ['RO', 'Bacau'], ['RO', 'Bihor'], ['RO', 'Bistrita-Nasaud'], ['RO', 'Botosani'], ['RO', 'Braila'], ['RO', 'Brasov'], ['RO', 'Bucuresti'], ['RO', 'Buzau'], ['RO', 'Calarasi'], ['RO', 'Caras-Severin'], ['RO', 'Cluj'], ['RO', 'Constanta'], ['RO', 'Covasna'], ['RO', 'Dambovita'], ['RO', 'Dolj'], ['RO', 'Galati'], ['RO', 'Giurgiu'], ['RO', 'Gorj'], ['RO', 'Harghita'], ['RO', 'Hunedoara'], ['RO', 'Ialomita'], ['RO', 'Iasi'], ['RO', 'Ilfov'], ['RO', 'Maramures'], ['RO', 'Mehedinti'], ['RO', 'Mures'], ['RO', 'Neamt'], ['RO', 'Olt'], ['RO', 'Prahova'], ['RO', 'Salaj'], ['RO', 'Satu Mare'], ['RO', 'Sibiu'], ['RO', 'Suceava'], ['RO', 'Teleorman'], ['RO', 'Timis'], ['RO', 'Tulcea'], ['RO', 'Valcea'], ['RO', 'Vaslui'], ['RO', 'Vrancea'], ['RS', 'Central Serbia'], ['RS', 'Kosovo'], ['RS', 'Vojvodina'], ['RU', 'Adygeya, Respublika'], ['RU', 'Altay, Respublika'], ['RU', 'Altayskiy kray'], ['RU', 'Amurskaya oblast\''], ['RU', 'Arkhangel\'skaya oblast\''], ['RU', 'Astrakhanskaya oblast\''], ['RU', 'Bashkortostan, Respublika'], ['RU', 'Belgorodskaya oblast\''], ['RU', 'Bryanskaya oblast\''], ['RU', 'Buryatiya, Respublika'], ['RU', 'Chechenskaya Respublika'], ['RU', 'Chelyabinskaya oblast\''], ['RU', 'Chukotskiy avtonomnyy okrug'], ['RU', 'Chuvashskaya Respublika'], ['RU', 'Dagestan, Respublika'], ['RU', 'Ingushetiya, Respublika'], ['RU', 'Irkutskaya oblast\''], ['RU', 'Ivanovskaya oblast\''], ['RU', 'Kabardino-Balkarskaya Respublika'], ['RU', 'Kaliningradskaya oblast\''], ['RU', 'Kalmykiya, Respublika'], ['RU', 'Kaluzhskaya oblast\''], ['RU', 'Kamchatskiy kray'], ['RU', 'Karachayevo-Cherkesskaya Respublika'], ['RU', 'Kareliya, Respublika'], ['RU', 'Kemerovskaya oblast\''], ['RU', 'Khabarovskiy kray'], ['RU', 'Khakasiya, Respublika'], ['RU', 'Khanty-Mansiyskiy avtonomnyy okrug-Yugra'], ['RU', 'Kirovskaya oblast\''], ['RU', 'Komi, Respublika'], ['RU', 'Kostromskaya oblast\''], ['RU', 'Krasnodarskiy kray'], ['RU', 'Krasnoyarskiy kray'], ['RU', 'Kurganskaya oblast\''], ['RU', 'Kurskaya oblast\''], ['RU', 'Leningradskaya oblast\''], ['RU', 'Lipetskaya oblast\''], ['RU', 'Magadanskaya oblast\''], ['RU', 'Mariy El, Respublika'], ['RU', 'Mordoviya, Respublika'], ['RU', 'Moskovskaya oblast\''], ['RU', 'Moskva'], ['RU', 'Murmanskaya oblast\''], ['RU', 'Nenetskiy avtonomnyy okrug'], ['RU', 'Nizhegorodskaya oblast\''], ['RU', 'Novgorodskaya oblast\''], ['RU', 'Novosibirskaya oblast\''], ['RU', 'Omskaya oblast\''], ['RU', 'Orenburgskaya oblast\''], ['RU', 'Orlovskaya oblast\''], ['RU', 'Penzenskaya oblast\''], ['RU', 'Permskiy kray'], ['RU', 'Primorskiy kray'], ['RU', 'Pskovskaya oblast\''], ['RU', 'Rostovskaya oblast\''], ['RU', 'Ryazanskaya oblast\''], ['RU', 'Sakha, Respublika'], ['RU', 'Sakhalinskaya oblast\''], ['RU', 'Samarskaya oblast\''], ['RU', 'Sankt-Peterburg'], ['RU', 'Saratovskaya oblast\''], ['RU', 'Severnaya Osetiya-Alaniya, Respublika'], ['RU', 'Smolenskaya oblast\''], ['RU', 'Stavropol\'skiy kray'], ['RU', 'Sverdlovskaya oblast\''], ['RU', 'Tambovskaya oblast\''], ['RU', 'Tatarstan, Respublika'], ['RU', 'Tomskaya oblast\''], ['RU', 'Tul\'skaya oblast\''], ['RU', 'Tverskaya oblast\''], ['RU', 'Tyumenskaya oblast\''], ['RU', 'Tyva, Respublika'], ['RU', 'Udmurtskaya Respublika'], ['RU', 'Ul\'yanovskaya oblast\''], ['RU', 'Vladimirskaya oblast\''], ['RU', 'Volgogradskaya oblast\''], ['RU', 'Vologodskaya oblast\''], ['RU', 'Voronezhskaya oblast\''], ['RU', 'Yamalo-Nenetskiy avtonomnyy okrug'], ['RU', 'Yaroslavskaya oblast\''], ['RU', 'Yevreyskaya avtonomnaya oblast\''], ['RU', 'Zabaykal\'skiy kray'], ['RW', 'Est'], ['RW', 'Nord'], ['RW', 'Ouest'], ['RW', 'Sud'], ['RW', 'Ville de Kigali'], ['SA', '\'Asir'], ['SA', 'Al Bahah'], ['SA', 'Al Hudud ash Shamaliyah'], ['SA', 'Al Jawf'], ['SA', 'Al Madinah al Munawwarah'], ['SA', 'Al Qasim'], ['SA', 'Ar Riyad'], ['SA', 'Ash Sharqiyah'], ['SA', 'Ha\'il'], ['SA', 'Jazan'], ['SA', 'Makkah al Mukarramah'], ['SA', 'Najran'], ['SA', 'Tabuk'], ['SB', 'Guadalcanal'], ['SC', 'English River'], ['SD', 'Blue Nile'], ['SD', 'Gedaref'], ['SD', 'Gezira'], ['SD', 'Kassala'], ['SD', 'Khartoum'], ['SD', 'North Darfur'], ['SD', 'North Kordofan'], ['SD', 'Northern'], ['SD', 'Red Sea'], ['SD', 'River Nile'], ['SD', 'Sennar'], ['SD', 'South Darfur'], ['SD', 'South Kordofan'], ['SD', 'White Nile'], ['SE', 'Blekinge Lan'], ['SE', 'Dalarnas Lan'], ['SE', 'Gavleborgs Lan'], ['SE', 'Gotlands Lan'], ['SE', 'Hallands Lan'], ['SE', 'Jamtlands Lan'], ['SE', 'Jonkopings Lan'], ['SE', 'Kalmar Lan'], ['SE', 'Kronobergs Lan'], ['SE', 'Norrbottens Lan'], ['SE', 'Orebro Lan'], ['SE', 'Ostergotlands Lan'], ['SE', 'Skane Lan'], ['SE', 'Sodermanlands Lan'], ['SE', 'Stockholms Lan'], ['SE', 'Uppsala Lan'], ['SE', 'Varmlands Lan'], ['SE', 'Vasterbottens Lan'], ['SE', 'Vasternorrlands Lan'], ['SE', 'Vastmanlands Lan'], ['SE', 'Vastra Gotaland'], ['SG', 'Singapore'], ['SH', 'Saint Helena'], ['SI', 'Ajdovscina'], ['SI', 'Bled'], ['SI', 'Bohinj'], ['SI', 'Borovnica'], ['SI', 'Bovec'], ['SI', 'Brezice'], ['SI', 'Brezovica'], ['SI', 'Celje'], ['SI', 'Cerknica'], ['SI', 'Cerkno'], ['SI', 'Crensovci'], ['SI', 'Crnomelj'], ['SI', 'Destrnik'], ['SI', 'Divaca'], ['SI', 'Domzale'], ['SI', 'Dravograd'], ['SI', 'Gornja Radgona'], ['SI', 'Grosuplje'], ['SI', 'Hoce-Slivnica'], ['SI', 'Horjul'], ['SI', 'Hrastnik'], ['SI', 'Idrija'], ['SI', 'Ig'], ['SI', 'Ilirska Bistrica'], ['SI', 'Ivancna Gorica'], ['SI', 'Izola-Isola'], ['SI', 'Jesenice'], ['SI', 'Kamnik'], ['SI', 'Kanal'], ['SI', 'Kidricevo'], ['SI', 'Kobarid'], ['SI', 'Kocevje'], ['SI', 'Koper-Capodistria'], ['SI', 'Kranj'], ['SI', 'Kranjska Gora'], ['SI', 'Krsko'], ['SI', 'Lasko'], ['SI', 'Lenart'], ['SI', 'Lendava'], ['SI', 'Litija'], ['SI', 'Ljubljana'], ['SI', 'Ljutomer'], ['SI', 'Log-Dragomer'], ['SI', 'Logatec'], ['SI', 'Lovrenc na Pohorju'], ['SI', 'Maribor'], ['SI', 'Medvode'], ['SI', 'Menges'], ['SI', 'Metlika'], ['SI', 'Mezica'], ['SI', 'Miklavz na Dravskem Polju'], ['SI', 'Miren-Kostanjevica'], ['SI', 'Mislinja'], ['SI', 'Mozirje'], ['SI', 'Murska Sobota'], ['SI', 'Muta'], ['SI', 'Nova Gorica'], ['SI', 'Novo Mesto'], ['SI', 'Odranci'], ['SI', 'Oplotnica'], ['SI', 'Ormoz'], ['SI', 'Piran'], ['SI', 'Pivka'], ['SI', 'Poljcane'], ['SI', 'Polzela'], ['SI', 'Postojna'], ['SI', 'Prebold'], ['SI', 'Prevalje'], ['SI', 'Ptuj'], ['SI', 'Racam'], ['SI', 'Radece'], ['SI', 'Radenci'], ['SI', 'Radlje ob Dravi'], ['SI', 'Radovljica'], ['SI', 'Ravne na Koroskem'], ['SI', 'Ribnica'], ['SI', 'Rogaska Slatina'], ['SI', 'Ruse'], ['SI', 'Sempeter-Vrtojba'], ['SI', 'Sencur'], ['SI', 'Sentilj'], ['SI', 'Sentjur pri Celju'], ['SI', 'Sevnica'], ['SI', 'Sezana'], ['SI', 'Skofja Loka'], ['SI', 'Skofljica'], ['SI', 'Slovenj Gradec'], ['SI', 'Slovenska Bistrica'], ['SI', 'Slovenske Konjice'], ['SI', 'Sostanj'], ['SI', 'Store'], ['SI', 'Straza'], ['SI', 'Tolmin'], ['SI', 'Trbovlje'], ['SI', 'Trebnje'], ['SI', 'Trzic'], ['SI', 'Trzin'], ['SI', 'Turnisce'], ['SI', 'Velenje'], ['SI', 'Vipava'], ['SI', 'Vodice'], ['SI', 'Vojnik'], ['SI', 'Vrhnika'], ['SI', 'Vuzenica'], ['SI', 'Zagorje ob Savi'], ['SI', 'Zalec'], ['SI', 'Zelezniki'], ['SI', 'Ziri'], ['SI', 'Zrece'], ['SI', 'Zuzemberk'], ['SJ', 'Svalbard and Jan Mayen'], ['SK', 'Banskobystricky kraj'], ['SK', 'Bratislavsky kraj'], ['SK', 'Kosicky kraj'], ['SK', 'Nitriansky kraj'], ['SK', 'Presovsky kraj'], ['SK', 'Trenciansky kraj'], ['SK', 'Trnavsky kraj'], ['SK', 'Zilinsky kraj'], ['SL', 'Eastern'], ['SL', 'Northern'], ['SL', 'Western Area'], ['SM', 'San Marino'], ['SM', 'Serravalle'], ['SN', 'Dakar'], ['SN', 'Diourbel'], ['SN', 'Fatick'], ['SN', 'Kaffrine'], ['SN', 'Kaolack'], ['SN', 'Kedougou'], ['SN', 'Kolda'], ['SN', 'Louga'], ['SN', 'Matam'], ['SN', 'Saint-Louis'], ['SN', 'Tambacounda'], ['SN', 'Thies'], ['SN', 'Ziguinchor'], ['SO', 'Banaadir'], ['SO', 'Jubbada Hoose'], ['SO', 'Mudug'], ['SO', 'Nugaal'], ['SO', 'Togdheer'], ['SO', 'Woqooyi Galbeed'], ['SR', 'Commewijne'], ['SR', 'Nickerie'], ['SR', 'Para'], ['SR', 'Paramaribo'], ['SR', 'Saramacca'], ['SR', 'Wanica'], ['SS', 'Central Equatoria'], ['SS', 'Eastern Equatoria'], ['SS', 'Lakes'], ['SS', 'Unity'], ['SS', 'Upper Nile'], ['SS', 'Western Equatoria'], ['ST', 'Principe'], ['ST', 'Sao Tome'], ['SV', 'Ahuachapan'], ['SV', 'Cabanas'], ['SV', 'Chalatenango'], ['SV', 'Cuscatlan'], ['SV', 'La Libertad'], ['SV', 'La Paz'], ['SV', 'La Union'], ['SV', 'Morazan'], ['SV', 'San Miguel'], ['SV', 'San Salvador'], ['SV', 'San Vicente'], ['SV', 'Santa Ana'], ['SV', 'Sonsonate'], ['SV', 'Usulutan'], ['SX', 'Sint Maarten'], ['SY', 'Al Hasakah'], ['SY', 'Al Ladhiqiyah'], ['SY', 'Ar Raqqah'], ['SY', 'As Suwayda\''], ['SY', 'Dar\'a'], ['SY', 'Dimashq'], ['SY', 'Halab'], ['SY', 'Hamah'], ['SY', 'Hims'], ['SY', 'Idlib'], ['SY', 'Rif Dimashq'], ['SY', 'Tartus'], ['SZ', 'Hhohho'], ['SZ', 'Lubombo'], ['SZ', 'Manzini'], ['TC', 'Turks and Caicos Islands'], ['TD', 'Chari-Baguirmi'], ['TD', 'Guera'], ['TD', 'Hadjer Lamis'], ['TD', 'Kanem'], ['TD', 'Logone-Occidental'], ['TD', 'Mayo-Kebbi-Est'], ['TD', 'Ouaddai'], ['TG', 'Kara'], ['TG', 'Maritime'], ['TG', 'Plateaux'], ['TH', 'Amnat Charoen'], ['TH', 'Ang Thong'], ['TH', 'Buri Ram'], ['TH', 'Chachoengsao'], ['TH', 'Chai Nat'], ['TH', 'Chaiyaphum'], ['TH', 'Chanthaburi'], ['TH', 'Chiang Mai'], ['TH', 'Chiang Rai'], ['TH', 'Chon Buri'], ['TH', 'Chumphon'], ['TH', 'Kalasin'], ['TH', 'Kamphaeng Phet'], ['TH', 'Kanchanaburi'], ['TH', 'Khon Kaen'], ['TH', 'Krabi'], ['TH', 'Krung Thep Maha Nakhon'], ['TH', 'Lampang'], ['TH', 'Lamphun'], ['TH', 'Loei'], ['TH', 'Lop Buri'], ['TH', 'Mae Hong Son'], ['TH', 'Maha Sarakham'], ['TH', 'Mukdahan'], ['TH', 'Nakhon Nayok'], ['TH', 'Nakhon Pathom'], ['TH', 'Nakhon Phanom'], ['TH', 'Nakhon Ratchasima'], ['TH', 'Nakhon Sawan'], ['TH', 'Nakhon Si Thammarat'], ['TH', 'Nan'], ['TH', 'Narathiwat'], ['TH', 'Nong Bua Lam Phu'], ['TH', 'Nong Khai'], ['TH', 'Nonthaburi'], ['TH', 'Pathum Thani'], ['TH', 'Pattani'], ['TH', 'Phangnga'], ['TH', 'Phatthalung'], ['TH', 'Phayao'], ['TH', 'Phetchabun'], ['TH', 'Phetchaburi'], ['TH', 'Phichit'], ['TH', 'Phitsanulok'], ['TH', 'Phra Nakhon Si Ayutthaya'], ['TH', 'Phrae'], ['TH', 'Phuket'], ['TH', 'Prachin Buri'], ['TH', 'Prachuap Khiri Khan'], ['TH', 'Ranong'], ['TH', 'Ratchaburi'], ['TH', 'Rayong'], ['TH', 'Roi Et'], ['TH', 'Sa Kaeo'], ['TH', 'Sakon Nakhon'], ['TH', 'Samut Prakan'], ['TH', 'Samut Sakhon'], ['TH', 'Samut Songkhram'], ['TH', 'Saraburi'], ['TH', 'Satun'], ['TH', 'Si Sa Ket'], ['TH', 'Sing Buri'], ['TH', 'Songkhla'], ['TH', 'Sukhothai'], ['TH', 'Suphan Buri'], ['TH', 'Surat Thani'], ['TH', 'Surin'], ['TH', 'Tak'], ['TH', 'Trang'], ['TH', 'Trat'], ['TH', 'Ubon Ratchathani'], ['TH', 'Udon Thani'], ['TH', 'Uthai Thani'], ['TH', 'Uttaradit'], ['TH', 'Yala'], ['TH', 'Yasothon'], ['TJ', 'Khatlon'], ['TJ', 'Kuhistoni Badakhshon'], ['TJ', 'Regions of Republican Subordination'], ['TJ', 'Sughd'], ['TJ', 'Tajikistan'], ['TK', 'Tokelau'], ['TL', 'Dili'], ['TM', 'Ahal'], ['TM', 'Balkan'], ['TM', 'Lebap'], ['TM', 'Mary'], ['TN', 'Aiana'], ['TN', 'Al Mahdia'], ['TN', 'Al Munastir'], ['TN', 'Bajah'], ['TN', 'Ben Arous'], ['TN', 'Bizerte'], ['TN', 'El Kef'], ['TN', 'Gabes'], ['TN', 'Gafsa'], ['TN', 'Jendouba'], ['TN', 'Kairouan'], ['TN', 'Kasserine'], ['TN', 'Kebili'], ['TN', 'Madanin'], ['TN', 'Manouba'], ['TN', 'Nabeul'], ['TN', 'Sfax'], ['TN', 'Sidi Bou Zid'], ['TN', 'Siliana'], ['TN', 'Sousse'], ['TN', 'Tataouine'], ['TN', 'Tozeur'], ['TN', 'Tunis'], ['TN', 'Zaghouan'], ['TO', 'Tongatapu'], ['TO', 'Vava\'u'], ['TR', 'Adana'], ['TR', 'Adiyaman'], ['TR', 'Afyonkarahisar'], ['TR', 'Agri'], ['TR', 'Aksaray'], ['TR', 'Amasya'], ['TR', 'Ankara'], ['TR', 'Antalya'], ['TR', 'Ardahan'], ['TR', 'Artvin'], ['TR', 'Aydin'], ['TR', 'Balikesir'], ['TR', 'Bartin'], ['TR', 'Batman'], ['TR', 'Bayburt'], ['TR', 'Bilecik'], ['TR', 'Bingol'], ['TR', 'Bitlis'], ['TR', 'Bolu'], ['TR', 'Burdur'], ['TR', 'Bursa'], ['TR', 'Canakkale'], ['TR', 'Cankiri'], ['TR', 'Corum'], ['TR', 'Denizli'], ['TR', 'Diyarbakir'], ['TR', 'Duzce'], ['TR', 'Edirne'], ['TR', 'Elazig'], ['TR', 'Erzincan'], ['TR', 'Erzurum'], ['TR', 'Eskisehir'], ['TR', 'Gaziantep'], ['TR', 'Giresun'], ['TR', 'Gumushane'], ['TR', 'Hakkari'], ['TR', 'Hatay'], ['TR', 'Igdir'], ['TR', 'Isparta'], ['TR', 'Istanbul'], ['TR', 'Izmir'], ['TR', 'Kahramanmaras'], ['TR', 'Karabuk'], ['TR', 'Karaman'], ['TR', 'Kars'], ['TR', 'Kastamonu'], ['TR', 'Kayseri'], ['TR', 'Kilis'], ['TR', 'Kirikkale'], ['TR', 'Kirklareli'], ['TR', 'Kirsehir'], ['TR', 'Kocaeli'], ['TR', 'Konya'], ['TR', 'Kutahya'], ['TR', 'Malatya'], ['TR', 'Manisa'], ['TR', 'Mardin'], ['TR', 'Mersin'], ['TR', 'Mugla'], ['TR', 'Mus'], ['TR', 'Nevsehir'], ['TR', 'Nigde'], ['TR', 'Ordu'], ['TR', 'Osmaniye'], ['TR', 'Rize'], ['TR', 'Sakarya'], ['TR', 'Samsun'], ['TR', 'Sanliurfa'], ['TR', 'Siirt'], ['TR', 'Sinop'], ['TR', 'Sirnak'], ['TR', 'Sivas'], ['TR', 'Tekirdag'], ['TR', 'Tokat'], ['TR', 'Trabzon'], ['TR', 'Tunceli'], ['TR', 'Usak'], ['TR', 'Van'], ['TR', 'Yalova'], ['TR', 'Yozgat'], ['TR', 'Zonguldak'], ['TT', 'Arima'], ['TT', 'Caroni'], ['TT', 'Mayaro'], ['TT', 'Port-of-Spain'], ['TT', 'Saint Andrew'], ['TT', 'Saint George'], ['TT', 'San Fernando'], ['TT', 'Tobago'], ['TT', 'Trinidad and Tobago'], ['TT', 'Victoria'], ['TV', 'Tuvalu'], ['TW', 'Fu-chien'], ['TW', 'Kao-hsiung'], ['TW', 'T\'ai-pei'], ['TW', 'T\'ai-wan'], ['TZ', 'Arusha'], ['TZ', 'Dar es Salaam'], ['TZ', 'Dodoma'], ['TZ', 'Iringa'], ['TZ', 'Kagera'], ['TZ', 'Kaskazini Unguja'], ['TZ', 'Kigoma'], ['TZ', 'Kilimanjaro'], ['TZ', 'Kusini Pemba'], ['TZ', 'Kusini Unguja'], ['TZ', 'Lindi'], ['TZ', 'Manyara'], ['TZ', 'Mara'], ['TZ', 'Mbeya'], ['TZ', 'Mjini Magharibi'], ['TZ', 'Morogoro'], ['TZ', 'Mtwara'], ['TZ', 'Mwanza'], ['TZ', 'Pwani'], ['TZ', 'Rukwa'], ['TZ', 'Ruvuma'], ['TZ', 'Shinyanga'], ['TZ', 'Singida'], ['TZ', 'Tabora'], ['TZ', 'Tanga'], ['UA', 'Avtonomna Respublika Krym'], ['UA', 'Cherkas\'ka Oblast\''], ['UA', 'Chernihivs\'ka Oblast\''], ['UA', 'Chernivets\'ka Oblast\''], ['UA', 'Dnipropetrovs\'ka Oblast\''], ['UA', 'Donets\'ka Oblast\''], ['UA', 'Ivano-Frankivs\'ka Oblast\''], ['UA', 'Kharkivs\'ka Oblast\''], ['UA', 'Khersons\'ka Oblast\''], ['UA', 'Khmel\'nyts\'ka Oblast\''], ['UA', 'Kirovohrads\'ka Oblast\''], ['UA', 'Kyiv'], ['UA', 'Kyivs\'ka Oblast\''], ['UA', 'L\'vivs\'ka Oblast\''], ['UA', 'Luhans\'ka Oblast\''], ['UA', 'Mykolaivs\'ka Oblast\''], ['UA', 'Odes\'ka Oblast\''], ['UA', 'Poltavs\'ka Oblast\''], ['UA', 'Rivnens\'ka Oblast\''], ['UA', 'Sevastopol\''], ['UA', 'Sums\'ka Oblast\''], ['UA', 'Ternopil\'s\'ka Oblast\''], ['UA', 'Vinnyts\'ka Oblast\''], ['UA', 'Volyns\'ka Oblast\''], ['UA', 'Zakarpats\'ka Oblast\''], ['UA', 'Zaporiz\'ka Oblast\''], ['UA', 'Zhytomyrs\'ka Oblast\''], ['UG', 'Bugiri'], ['UG', 'Gulu'], ['UG', 'Hoima'], ['UG', 'Jinja'], ['UG', 'Kabale'], ['UG', 'Kampala'], ['UG', 'Kamwenge'], ['UG', 'Kasese'], ['UG', 'Lira'], ['UG', 'Masaka'], ['UG', 'Mbale'], ['UG', 'Mbarara'], ['UG', 'Mityana'], ['UG', 'Moyo'], ['UG', 'Mukono'], ['UG', 'Tororo'], ['UG', 'Wakiso'], ['UM', 'Palmyra Atoll'], ['US', 'Alabama'], ['US', 'Alaska'], ['US', 'Arizona'], ['US', 'Arkansas'], ['US', 'California'], ['US', 'Colorado'], ['US', 'Connecticut'], ['US', 'Delaware'], ['US', 'District Of Columbia'], ['US', 'Florida'], ['US', 'Georgia'], ['US', 'Hawaii'], ['US', 'Idaho'], ['US', 'Illinois'], ['US', 'Indiana'], ['US', 'Iowa'], ['US', 'Kansas'], ['US', 'Kentucky'], ['US', 'Louisiana'], ['US', 'Maine'], ['US', 'Maryland'], ['US', 'Massachusetts'], ['US', 'Michigan'], ['US', 'Minnesota'], ['US', 'Mississippi'], ['US', 'Missouri'], ['US', 'Montana'], ['US', 'Nebraska'], ['US', 'Nevada'], ['US', 'New Hampshire'], ['US', 'New Jersey'], ['US', 'New Mexico'], ['US', 'New York'], ['US', 'North Carolina'], ['US', 'North Dakota'], ['US', 'Ohio'], ['US', 'Oklahoma'], ['US', 'Oregon'], ['US', 'Pennsylvania'], ['US', 'Rhode Island'], ['US', 'South Carolina'], ['US', 'South Dakota'], ['US', 'Tennessee'], ['US', 'Texas'], ['US', 'Utah'], ['US', 'Vermont'], ['US', 'Virginia'], ['US', 'Washington'], ['US', 'West Virginia'], ['US', 'Wisconsin'], ['US', 'Wyoming'], ['UY', 'Artigas'], ['UY', 'Canelones'], ['UY', 'Cerro Largo'], ['UY', 'Colonia'], ['UY', 'Durazno'], ['UY', 'Flores'], ['UY', 'Florida'], ['UY', 'Lavalleja'], ['UY', 'Maldonado'], ['UY', 'Montevideo'], ['UY', 'Paysandu'], ['UY', 'Rio Negro'], ['UY', 'Rivera'], ['UY', 'Rocha'], ['UY', 'Salto'], ['UY', 'San Jose'], ['UY', 'Soriano'], ['UY', 'Tacuarembo'], ['UY', 'Treinta y Tres'], ['UZ', 'Andijon'], ['UZ', 'Buxoro'], ['UZ', 'Farg\'ona'], ['UZ', 'Jizzax'], ['UZ', 'Namangan'], ['UZ', 'Navoiy'], ['UZ', 'Qashqadaryo'], ['UZ', 'Qoraqalpog\'iston Respublikasi'], ['UZ', 'Samarqand'], ['UZ', 'Sirdaryo'], ['UZ', 'Surxondaryo'], ['UZ', 'Toshkent'], ['UZ', 'Xorazm'], ['VA', 'Vatican City'], ['VC', 'Charlotte'], ['VC', 'Saint George'], ['VE', 'Amazonas'], ['VE', 'Anzoategui'], ['VE', 'Apure'], ['VE', 'Aragua'], ['VE', 'Barinas'], ['VE', 'Bolivar'], ['VE', 'Carabobo'], ['VE', 'Cojedes'], ['VE', 'Delta Amacuro'], ['VE', 'Distrito Federal'], ['VE', 'Falcon'], ['VE', 'Guarico'], ['VE', 'Lara'], ['VE', 'Merida'], ['VE', 'Miranda'], ['VE', 'Monagas'], ['VE', 'Nueva Esparta'], ['VE', 'Portuguesa'], ['VE', 'Sucre'], ['VE', 'Tachira'], ['VE', 'Trujillo'], ['VE', 'Vargas'], ['VE', 'Yaracuy'], ['VE', 'Zulia'], ['VG', 'British Virgin Islands'], ['VI', 'Virgin Islands'], ['VN', 'An Giang'], ['VN', 'Bac Giang'], ['VN', 'Bac Kan'], ['VN', 'Bac Lieu'], ['VN', 'Bac Ninh'], ['VN', 'Ben Tre'], ['VN', 'Binh Dinh'], ['VN', 'Binh Duong'], ['VN', 'Binh Phuoc'], ['VN', 'Binh Thuan'], ['VN', 'Ca Mau'], ['VN', 'Can Tho'], ['VN', 'Cao Bang'], ['VN', 'Da Nang'], ['VN', 'Dak Lak'], ['VN', 'Dien Bien'], ['VN', 'Dong Nai'], ['VN', 'Dong Thap'], ['VN', 'Gia Lai'], ['VN', 'Ha Giang'], ['VN', 'Ha Nam'], ['VN', 'Ha Noi'], ['VN', 'Ha Tinh'], ['VN', 'Hai Duong'], ['VN', 'Hai Phong'], ['VN', 'Ho Chi Minh'], ['VN', 'Hoa Binh'], ['VN', 'Hung Yen'], ['VN', 'Khanh Hoa'], ['VN', 'Kien Giang'], ['VN', 'Lai Chau'], ['VN', 'Lam Dong'], ['VN', 'Lang Son'], ['VN', 'Lao Cai'], ['VN', 'Long An'], ['VN', 'Nam Dinh'], ['VN', 'Nghe An'], ['VN', 'Ninh Binh'], ['VN', 'Ninh Thuan'], ['VN', 'Phu Tho'], ['VN', 'Phu Yen'], ['VN', 'Quang Binh'], ['VN', 'Quang Nam'], ['VN', 'Quang Ngai'], ['VN', 'Quang Ninh'], ['VN', 'Quang Tri'], ['VN', 'Soc Trang'], ['VN', 'Son La'], ['VN', 'Tay Ninh'], ['VN', 'Thai Binh'], ['VN', 'Thai Nguyen'], ['VN', 'Thanh Hoa'], ['VN', 'Thua Thien-Hue'], ['VN', 'Tien Giang'], ['VN', 'Tra Vinh'], ['VN', 'Tuyen Quang'], ['VN', 'Vinh Long'], ['VN', 'Vinh Phuc'], ['VN', 'Yen Bai'], ['VU', 'Sanma'], ['VU', 'Shefa'], ['VU', 'Tafea'], ['WF', 'Wallis and Futuna Islands'], ['WS', 'A\'ana'], ['WS', 'Tuamasaga'], ['YE', '\'Adan'], ['YE', 'Amanat al \'Asimah'], ['YE', 'Hadramawt'], ['YE', 'Lahij'], ['YE', 'Shabwah'], ['YE', 'Ta\'izz'], ['YT', 'Bandraboua'], ['YT', 'Chiconi'], ['YT', 'Dzaoudzi'], ['YT', 'Mamoudzou'], ['YT', 'Tsingoni'], ['ZA', 'Eastern Cape'], ['ZA', 'Free State'], ['ZA', 'Gauteng'], ['ZA', 'KwaZulu-Natal'], ['ZA', 'Limpopo'], ['ZA', 'Mpumalanga'], ['ZA', 'North-West'], ['ZA', 'Northern Cape'], ['ZA', 'Western Cape'], ['ZM', 'Central'], ['ZM', 'Copperbelt'], ['ZM', 'Eastern'], ['ZM', 'Luapula'], ['ZM', 'Lusaka'], ['ZM', 'North-Western'], ['ZM', 'Northern'], ['ZM', 'Southern'], ['ZM', 'Western'], ['ZW', 'Bulawayo'], ['ZW', 'Harare'], ['ZW', 'Manicaland'], ['ZW', 'Mashonaland Central'], ['ZW', 'Mashonaland East'], ['ZW', 'Mashonaland West'], ['ZW', 'Masvingo'], ['ZW', 'Matabeleland North'], ['ZW', 'Matabeleland South'], ['ZW', 'Midlands']];

		$this->db->query('CREATE TABLE IF NOT EXISTS `' . DB_PREFIX . "ip_country_redirect` (`rule_id` INT(11) NOT NULL AUTO_INCREMENT, `origins` TEXT NOT NULL, `mode` CHAR(1) NOT NULL DEFAULT '1', `from` TEXT NOT NULL, `to` TEXT NOT NULL, `code` INT(11) NOT NULL DEFAULT '301', `status` TINYINT(1) NOT NULL DEFAULT '0', PRIMARY KEY (`rule_id`), INDEX `idx_status` (`status`))");

		$this->db->query('CREATE TABLE IF NOT EXISTS `' . DB_PREFIX . 'ip2location_country` (`country_code` CHAR(2) NOT NULL, `country_name` VARCHAR(50) NOT NULL, PRIMARY KEY (`country_code`))');

		foreach ($countries as $code => $name) {
			$this->db->query('INSERT INTO `' . DB_PREFIX . "ip2location_country` VALUES ('" . $code . "', '" . addslashes($name) . "')");
		}

		$this->db->query('CREATE TABLE IF NOT EXISTS `' . DB_PREFIX . 'ip2location_region` (`country_code` CHAR(2) NOT NULL, `region_name` VARCHAR(100) NOT NULL, INDEX `idx_country_code` (`country_code`))');

		$marker = '';
		foreach ($regions as $row) {
			if ($marker != $row[0]) {
				$marker = $row[0];
				$this->db->query('INSERT INTO `' . DB_PREFIX . "ip2location_region` VALUES ('" . $row[0] . "', '*')");
			}

			$this->db->query('INSERT INTO `' . DB_PREFIX . "ip2location_region` VALUES ('" . $row[0] . "', '" . addslashes($row[1]) . "')");
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "startup` (`code`, `action`, `status`, `sort_order`) VALUES ('ip2location', 'catalog/extension/ip2location/startup/ip_country_redirect', 1, 0)");
	}

	public function uninstall(): void
	{
		$this->load->model('setting/setting');

		$tables = [
			'ip_country_redirect',
			'ip2location_country',
			'ip2location_region',
		];

		foreach ($tables as $table) {
			$this->db->query('DROP TABLE IF EXISTS `' . DB_PREFIX . $table . '`');
		}

		$this->db->query("DELETE FROM `" . DB_PREFIX . "startup` WHERE `code` = 'ip2location'");

		$this->model_setting_setting->deleteSetting('module_ip_country_redirect');
	}

	protected function checkPermission()
	{
		if (!$this->user->hasPermission('modify', $this->path)) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	protected function getSortLink($sort)
	{
		$order = (isset($this->request->get['order'])) ? $this->request->get['order'] : 'desc';
		$order = ($order == 'desc') ? 'asc' : 'desc';

		return $this->url->link($this->request->get['route'], [
			'user_token' => $this->session->data['user_token'],
			'sort'       => $sort,
			'order'      => $order,
		]);
	}

	protected function getOrigins()
	{
		$this->load->model('setting/setting');
		$settings = $this->model_setting_setting->getSetting('module_ip_country_redirect');

		$data['*-*'] = [
			'code' => '*-*',
			'text' => $this->language->get('text_all_countries'),
		];

		if (isset($settings['ip2location_rediect_bin_path'])) {
			require_once DIR_EXTENSION . 'ip2location/system/library/IP2Location.php';

			$db = new \Opencart\System\Library\IP2Location($settings['ip2location_rediect_bin_path']);
			$records = $db->lookup('8.8.8.8', \Opencart\System\Library\IP2Location::ALL);

			$this->isRegionSupported = ($records['regionName'] != '-' && !preg_match('/unavailable/', $records['regionName']));
		}

		$query = $this->db->query('SELECT * FROM `' . DB_PREFIX . 'ip2location_country` c JOIN `' . DB_PREFIX . 'ip2location_region` r ON c.`country_code` = r.`country_code` ORDER BY c.`country_name`, r.`region_name`');

		foreach ($query->rows as $result) {
			if (!$this->isRegionSupported && $result['region_name'] != '*') {
				continue;
			}

			$data[$result['country_code'] . '-' . $result['region_name']] = [
				'code' => $result['country_code'] . '-' . $result['region_name'],
				'text' => ($result['region_name'] == '*') ? $result['country_name'] : ($result['country_name'] . ' - ' . $result['region_name']),
			];
		}

		return $data;
	}

	protected function getRules($total = false)
	{
		$sort = (isset($this->request->get['sort'])) ? $this->request->get['sort'] : 'from';
		$sort = (!\in_array($sort, ['from', 'to', 'code', 'status'])) ? 'from' : $sort;

		$order = (isset($this->request->get['order'])) ? $this->request->get['order'] : 'desc';
		$order = ($order == 'desc') ? 'asc' : 'desc';

		$sql = 'SELECT ' . (($total) ? 'COUNT(*) AS `total`' : '*') . ' FROM `' . DB_PREFIX . 'ip_country_redirect` ORDER BY `' . $sort . '`';

		if ($order == 'desc') {
			$sql .= ' DESC';
		}

		$results = $this->db->query($sql);

		if ($total) {
			return (int) $results->row['total'];
		}

		return $results->rows;
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

			if (filter_var($xip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
				$ip = $xip;
			}
		}

		if (isset($_SERVER['DEV_MODE'])) {
			$ip = '175.144.151.253';
		}

		return $ip;
	}
}
