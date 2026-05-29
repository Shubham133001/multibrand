<?php

namespace WHMCS\Module\Addon\Multibrand\Admin;

use WHMCS\Database\Capsule;

class LicenseHelper
{
    /**
     * Perform the license check
     * 
     * @return array
     */
    public static function doCheckLicense()
    {
        // For development, we can bypass or return Active
        // return ['status' => 'Active'];

        $settings = array();
        $records = Capsule::table('tbladdonmodules')->where('module', 'multibrand')->get();
        foreach ($records as $record) {
            $settings[$record->setting] = $record->value;
        }

        if (isset($settings['license_key']) && !empty($settings['license_key'])) {
            $licenseKey = $settings['license_key'];
            $localKey = isset($settings['localkey']) ? $settings['localkey'] : '';

            $result = self::checkLicense($licenseKey, $localKey);

            if (isset($result['localkey']) && !empty($result['localkey'])) {
                $count = Capsule::table('tbladdonmodules')
                    ->where('module', 'multibrand')
                    ->where('setting', 'localkey')
                    ->count();

                if ($count > 0) {
                    Capsule::table('tbladdonmodules')
                        ->where('module', 'multibrand')
                        ->where('setting', 'localkey')
                        ->update(['value' => $result['localkey']]);
                } else {
                    Capsule::table('tbladdonmodules')->insert([
                        'module' => 'multibrand',
                        'setting' => 'localkey',
                        'value' => $result['localkey']
                    ]);
                }
            }

            $result['licensekey'] = $licenseKey;
            return $result;
        }

        return ['status' => 'licensekeynotfound'];
    }

    /**
     * The actual licensing check logic
     */
    public static function checkLicense($licensekey, $localkey = '')
    {
        $whmcsurl = "https://members.modulesstack.com/";
        $licensing_secret_key = "securecprm!@91";
        $localkeydays = 15;
        $allowcheckfaildays = 5;
        $check_token = time() . md5(mt_rand(1000000000, 9999999999) . $licensekey);
        $checkdate = date("Ymdhis");
        $domain = $_SERVER['SERVER_NAME'];
        $usersip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : (isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : '');
        $dirpath = dirname(__FILE__);
        $verifyfilepath = 'modules/servers/licensing/verify.php';
        $localkeyvalid = false;

        if ($localkey) {
            $localkey = str_replace("\n", '', $localkey);
            $localdata = substr($localkey, 0, strlen($localkey) - 32);
            $md5hash = substr($localkey, strlen($localkey) - 32);

            if ($md5hash == md5($localdata . $licensing_secret_key)) {
                $localdata = strrev($localdata);
                $md5hash = substr($localdata, 0, 32);
                $localdata = substr($localdata, 32);
                $localdata = base64_decode($localdata);
                $localkeyresults = unserialize($localdata);
                $originalcheckdate = isset($localkeyresults['checkdate']) ? $localkeyresults['checkdate'] : '';

                if ($md5hash == md5($originalcheckdate . $licensing_secret_key)) {
                    $localexpiry = date("Ymdhis", mktime(date("H"), date("i"), date("s"), date("m"), date("d") - $localkeydays, date("Y")));
                    if ($originalcheckdate > $localexpiry) {
                        $localkeyvalid = true;
                        $results = $localkeyresults;

                        if (isset($results['validdomain'])) {
                            $validdomains = explode(',', $results['validdomain']);
                            if (!in_array($_SERVER['SERVER_NAME'], $validdomains)) {
                                $localkeyvalid = false;
                                $results['status'] = "Invalid";
                            }
                        }
                    }
                }
            }
        }

        if (!$localkeyvalid) {
            $responseCode = 0;
            $postfields = array(
                'licensekey' => $licensekey,
                'domain' => $domain,
                'ip' => $usersip,
                'dir' => $dirpath,
                'check_token' => $check_token
            );

            $query_string = http_build_query($postfields);

            if (function_exists('curl_exec')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $whmcsurl . $verifyfilepath);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $data = curl_exec($ch);
                $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
            }

            if ($responseCode != 200) {
                $originalcheckdate = isset($localkeyresults['checkdate']) ? $localkeyresults['checkdate'] : '';
                $localexpiry = date("Ymdhis", mktime(date("H"), date("i"), date("s"), date("m"), date("d") - ($localkeydays + $allowcheckfaildays), date("Y")));
                if ($originalcheckdate > $localexpiry) {
                    $results = $localkeyresults;
                } else {
                    return ['status' => 'Invalid', 'description' => 'Remote Check Failed'];
                }
            } else {
                preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches);
                $results = array();
                foreach ($matches[1] as $k => $v) {
                    $results[$v] = $matches[2][$k];
                }
            }

            if (!is_array($results)) {
                return ['status' => 'Invalid', 'description' => 'Invalid License Server Response'];
            }

            if (isset($results['md5hash']) && $results['md5hash'] != md5($licensing_secret_key . $check_token)) {
                return ['status' => 'Invalid', 'description' => 'MD5 Checksum Verification Failed'];
            }

            if (isset($results['status']) && $results['status'] == "Active") {
                $results['checkdate'] = $checkdate;
                $data_encoded = serialize($results);
                $data_encoded = base64_encode($data_encoded);
                $data_encoded = md5($checkdate . $licensing_secret_key) . $data_encoded;
                $data_encoded = strrev($data_encoded);
                $data_encoded = $data_encoded . md5($data_encoded . $licensing_secret_key);
                $data_encoded = wordwrap($data_encoded, 80, "\n", true);
                $results['localkey'] = $data_encoded;
            }
            $results['remotecheck'] = true;
        }

        return $results;
    }
}
