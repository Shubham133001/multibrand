<?php
use WHMCS\Database\Capsule;
use PHPMailer\PHPMailer\Exception;
// function getalldataofbrand(){
//     global $GLOBALS;
//     $host = $_SERVER['HTTP_HOST'];
//     $systemurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$host";
//     $GLOBALS['CONFIG']['SystemURL'] = $systemurl;
//     $GLOBALS['CONFIG']['Domain'] = $host;

//     $return['SystemURL'] = $systemurl;
//     $return['Domain'] = $host;
//     return $return;
// }
// getalldataofbrand();

// Override general settings
// add_hook('ClientAreaPage', 0, function ($vars) {
//     global $CONFIG;
//     $data = getalldataofbrand();
//     // print_r($data); die;
//     if(empty($data)){
//         return $vars;
//     }
//     $settings = $data['brandsettings'];
//     $system_url = $settings['system_url'];
//     $host = parse_url($system_url, PHP_URL_HOST);

//     $GLOBALS['CONFIG']['SystemURL'] = $system_url;
//     $vars['systemurl'] = $system_url;
//     $vars['systemsslurl'] = $system_url;
//     $vars['systemNonSSLURL'] = $system_url;
//     $GLOBALS['CONFIG']['Domain'] = $host;
//     return $vars;
// });


// show addon price in configproduct 
// add_hook('ClientAreaFooterOutput', 0, function ($vars) {
//     $data = getalldataofbrand();

//     $settings = $data['brandsettings'];
    
//     $system_url = $settings['system_url'];
//     $vars['systemurl'] = $system_url;
//     $vars['systemsslurl'] = $system_url;
//     $vars['systemNonSSLURL'] = $system_url;

//     $host = parse_url($system_url, PHP_URL_HOST);
//     $GLOBALS['CONFIG']['SystemURL'] = $system_url;
//     $GLOBALS['CONFIG']['Domain'] = $host;
//     return $vars;
// });

// SMTP overide 
// add_hook('EmailPreSend', 0, function ($vars) {
//     try {
//         $data = getalldataofbrand();
//         if(empty($data)){
//             return;
//         }
//         $brandId = $data['brandId'];
//         $settings = $data['brandsettings'];
//         $systemurl = $settings['system_url'] ?? "";
//         $company_name = $settings['company_name'] ?? "";
//         $logo = $settings['logo'] ?? "";
//         $signature = $settings['signature'] ?? "";
//         $domain = $settings['domain'] ?? "";

//         $abc['company_name'] = $systemurl;
//         $abc['companyname'] = $systemurl;
//         $abc['company_domain'] = $domain;
//         $abc['company_logo_url'] = $logo;
//         $abc['whmcs_url'] = $systemurl;
//         $abc['whmcs_link'] = '<a href="'.$systemurl.'">'.$systemurl.'</a>';
//         $abc['signature'] = $signature;
//         return $abc;

//     } catch (\Exception $e) {
//         logActivity("EmailPreSend error: " . $e->getMessage());
//     }
// });