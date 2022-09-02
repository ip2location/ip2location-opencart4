<?php

// Heading
$_['heading_title'] = 'IP2Location IP Country Redirect';

$_['text_success'] = 'Success: Changes saved';
$_['text_edit'] = 'Edit IP2Location Redirector';
$_['text_add_rule'] = 'Adding New Rule';
$_['text_ajax_no_response'] = 'No response';
$_['text_all_countries'] = 'All Countries';
$_['text_begins_with'] = 'Begins with';
$_['text_code_301'] = 'Permanent Redirect (301 Moved Permanently)';
$_['text_code_302'] = 'Temporary Redirect (302 Found)';
$_['text_code_404'] = 'Page Not Found (404 Not Found)';
$_['text_confirm_delete'] = 'Are you sure you want delete this rule?';
$_['text_confirm_leave_page'] = 'Are you sure? Unsaved data will be lost.';
$_['text_database'] = 'Database';
$_['text_developer'] = 'IP2Location IP Country Redirect v%1$s<br>&copy; %2$s %3$s.';
$_['text_equals_to'] = 'Equals to';
$_['text_extension'] = 'Extensions';
$_['text_local_binary_database'] = 'Local Binary Database';
$_['text_lookup'] = 'Lookup';
$_['text_module'] = 'Modules';
$_['text_new_rule'] = 'New Rule';
$_['text_not_show_again'] = 'Dont\'t show again';
$_['text_regular_expression'] = 'Regular Expression';
$_['text_remote_api'] = 'Remote API Service';
$_['text_rules'] = 'Rules';
$_['text_status'] = 'Status';
$_['text_module_enabled'] = 'Success: You have enabled IP2Location IP Country Redirect module.';
$_['text_module_disabled'] = 'Success: You have disabled IP2Location IP Country Redirect module.';
$_['text_success_add'] = 'Rule successfully added.';
$_['text_success_delete_rule'] = 'Rule successfully deleted.';
$_['text_success_ip_lookup'] = 'IP <strong>%1$s</strong> belongs to %2$s.';
$_['text_success_save_rule'] = 'Rule successfully modified.';
$_['text_success_save_settings'] = 'Settings successfully saved.';

// Entry
$_['entry_status'] = 'Status';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify this module.';
$_['error_add_rule'] = 'Couldn\'t add rule.';
$_['error_ajax'] = 'Error';
$_['error_delete_rule'] = 'Couldn\'t delete rule.';
$_['error_empty_origins'] = 'Origin value cannot be empty.';
$_['error_empty_from'] = 'From value cannot be empty.';
$_['error_empty_rule_id'] = 'Couldn\'t find rule. Try to reload page.';
$_['error_empty_to'] = 'To value cannot be empty.';
$_['error_from_not_unique'] = 'Such from value already exists. You can use search to find it.';
$_['error_request'] = 'Error, only POST request allowed.';
$_['error_save_rule'] = 'Error while saving rule.';
$_['error_warning_type'] = 'Not allowed.';
$_['error_from_to_same'] = 'From and to values cannot be the same.';
$_['error_database_not_found'] = 'Binary database file is not found.';
$_['error_database_invalid'] = 'Binary database file is invalid.';
$_['error_api_key_empty'] = 'API key cannot be empty.';
$_['error_api_key_invalid'] = 'API key is invalid.';
$_['error_save_settings'] = 'Error while saving settings.';
$_['error_ip_address_invalid'] = 'The IP address is invalid.';
$_['error_invalid_regular_expression'] = 'Invalid regular expression.';

// Button
$_['button_create'] = 'Create';
$_['button_ok'] = 'Ok';
$_['button_no'] = 'No';
$_['button_yes'] = 'Yes';
$_['button_lookup'] = 'Lookup';

// Column
$_['column_origin'] = 'Origin';
$_['column_from'] = 'From';
$_['column_to'] = 'To';
$_['column_code'] = 'Code';
$_['column_status'] = 'Status';

// Entry
$_['entry_origin'] = 'Origin';
$_['entry_from'] = 'From';
$_['entry_to'] = 'To';
$_['entry_code'] = 'Code';
$_['entry_status'] = 'Status';
$_['entry_per_page'] = 'Per page';
$_['entry_method'] = 'Lookup Method';
$_['entry_database_location'] = 'Database Location';
$_['entry_database_location_description'] = 'For country-level redirection, please download Free <a href="https://lite.ip2location.com/database/ip-country" target="_blank">DB1 LITE</a> database or <a href="https://www.ip2location.com/database/db1-ip-country" target="_blank">DB1 Commercial</a> database with high accuracy.<br><br>For region-level redirection, please download Free <a href="https://lite.ip2location.com/database/ip-country-region-city" target="_blank">DB3 LITE</a> database or <a href="https://www.ip2location.com/database/db3-ip-country-region-city" target="_blank">DB3 Commercial</a> database with high accuracy.';
$_['entry_remaining_credit'] = 'Remaining Credit: ';
$_['entry_api_key'] = 'API Key';
$_['entry_api_key_description'] = 'Subscribe to <a href="http://www.ip2location.com/web-service" target="_blank">IP2Location Web service</a> for API key.';
$_['entry_ip_address'] = 'IP Address';

// Help
$_['help_origin'] = 'Visitor from these origins will trigger the rule.';
$_['help_from'] = 'Where redirect triggers from, e.g: /index.php?route=common/home';
$_['help_to'] = 'Where we should redirect to, e.g: /index.php?route=product/product&path=20_27&product_id=41';
$_['help_code'] = 'Which status server should return, if you move page to another address and want search engine forget about old and know about new one, you should select 301 status';
$_['help_method'] = 'The method to lookup for geolocation information.';
$_['help_database_location'] = 'The absolute path to IP2Location binary database in your server. E.g.: /var/www/IP2LOCATION-LITE-IP-COUNTRY.BIN';
$_['help_api_key'] = 'The API key to access IP2Location Web service.';
