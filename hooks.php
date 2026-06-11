<?php
/**
 * Multi Brand Hooks
 * 
 * Automatically applies brand settings based on the current domain.
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
include 'multidomain.php';

// file_put_contents(__DIR__ . '/hooks_loaded.log', "Loaded at " . date('Y-m-d H:i:s') . " | URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);

use WHMCS\Database\Capsule;

// Dynamically create service brands table if missing to ensure seamless upgrades
try {
    if (!Capsule::schema()->hasTable('mod_multibrand_service_brands')) {
        Capsule::schema()->create('mod_multibrand_service_brands', function ($table) {
            $table->increments('id');
            $table->integer('service_id')->unique();
            $table->integer('brand_id');
            $table->timestamps();
        });
    }
} catch (\Exception $e) {}

// Dynamically create ticket brands table if missing to ensure seamless upgrades
try {
    if (!Capsule::schema()->hasTable('mod_multibrand_ticket_brands')) {
        Capsule::schema()->create('mod_multibrand_ticket_brands', function ($table) {
            $table->increments('id');
            $table->integer('ticket_id')->unique();
            $table->integer('brand_id');
            $table->timestamps();
        });
    }
} catch (\Exception $e) {}

// Dynamically add is_branded column to mod_multibrand_invoice_brands table if missing to ensure seamless upgrades
try {
    if (Capsule::schema()->hasTable('mod_multibrand_invoice_brands')) {
        if (!Capsule::schema()->hasColumn('mod_multibrand_invoice_brands', 'is_branded')) {
            Capsule::schema()->table('mod_multibrand_invoice_brands', function ($table) {
                $table->tinyInteger('is_branded')->default(0);
            });
        }
    }
} catch (\Exception $e) {}

/**
 * Early Form POST Interceptor
 * Intercepts POST requests containing 'multibrand_id' on admin edit pages
 * to ensure the selected brand is successfully saved before WHMCS redirects.
 */
if (isset($_POST['multibrand_id'])) {
    $brandId = (int)$_POST['multibrand_id'];
    $scriptFilename = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFilenameClean = str_replace('.php', '', $scriptFilename);

    // Only process for specific admin files
    $supportedFiles = ['invoices', 'supportannouncements', 'supportdownloads', 'orders', 'ordersadd', 'ordersedit', 'clientsservices', 'supporttickets'];

    if (in_array($scriptFilenameClean, $supportedFiles)) {
        try {
            if ($scriptFilenameClean == 'invoices') {
                $invoiceId = (int)($_REQUEST['id'] ?? $_REQUEST['invoiceid'] ?? $_POST['id'] ?? $_POST['invoiceid'] ?? 0);
                if ($invoiceId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_invoice_brands')->insert(['invoice_id' => $invoiceId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif ($scriptFilenameClean == 'supportannouncements') {
                $announcementId = (int)($_REQUEST['id'] ?? $_POST['id'] ?? 0);
                if ($announcementId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_announcement_brands')->where('announcement_id', $announcementId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_announcement_brands')->where('announcement_id', $announcementId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_announcement_brands')->where('announcement_id', $announcementId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_announcement_brands')->insert(['announcement_id' => $announcementId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif ($scriptFilenameClean == 'supportdownloads') {
                $downloadId = (int)($_REQUEST['id'] ?? $_POST['id'] ?? 0);
                if ($downloadId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_download_brands')->where('download_id', $downloadId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_download_brands')->where('download_id', $downloadId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_download_brands')->where('download_id', $downloadId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_download_brands')->insert(['download_id' => $downloadId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif ($scriptFilenameClean == 'clientsservices') {
                $serviceId = (int)($_REQUEST['id'] ?? $_POST['id'] ?? 0);
                if ($serviceId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_service_brands')->where('service_id', $serviceId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_service_brands')->where('service_id', $serviceId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_service_brands')->where('service_id', $serviceId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_service_brands')->insert(['service_id' => $serviceId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif (in_array($scriptFilenameClean, ['ordersedit', 'orders'])) {
                $orderId = (int)($_REQUEST['id'] ?? $_REQUEST['orderid'] ?? $_POST['id'] ?? $_POST['orderid'] ?? 0);
                if ($orderId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_order_brands')->insert(['order_id' => $orderId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif ($scriptFilenameClean == 'supporttickets') {
                // print_r($_REQUEST);die();
                $ticketId = (int)($_REQUEST['id'] ?? $_REQUEST['ticketid'] ?? $_POST['id'] ?? $_POST['ticketid'] ?? 0);
                if ($ticketId > 0) {
                    if ($brandId === 0) {
                        Capsule::table('mod_multibrand_ticket_brands')->where('ticket_id', $ticketId)->delete();
                    } else {
                        $exists = Capsule::table('mod_multibrand_ticket_brands')->where('ticket_id', $ticketId)->exists();
                        if ($exists) {
                            Capsule::table('mod_multibrand_ticket_brands')->where('ticket_id', $ticketId)->update(['brand_id' => $brandId, 'updated_at' => date('Y-m-d H:i:s')]);
                        } else {
                            Capsule::table('mod_multibrand_ticket_brands')->insert(['ticket_id' => $ticketId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            } elseif ($scriptFilenameClean == 'ordersadd' && isset($_POST['userid'])) {
                $clientId = (int)$_POST['userid'];
                if ($clientId > 0) {
                    register_shutdown_function(function() use ($clientId, $brandId) {
                        try {
                            $latestOrder = Capsule::table('tblorders')->where('userid', $clientId)->orderBy('id', 'desc')->first();
                            if ($latestOrder) {
                                $orderId = $latestOrder->id;
                                $exists = Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->exists();
                                if (!$exists && $brandId > 0) {
                                    Capsule::table('mod_multibrand_order_brands')->insert(['order_id' => $orderId, 'brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                                }

                                // Save service brand for each service in the order
                                if ($brandId > 0) {
                                    $services = Capsule::table('tblhosting')->where('orderid', $orderId)->get();
                                    foreach ($services as $service) {
                                        $serviceExists = Capsule::table('mod_multibrand_service_brands')->where('service_id', $service->id)->exists();
                                        if (!$serviceExists) {
                                            Capsule::table('mod_multibrand_service_brands')->insert([
                                                'service_id' => $service->id,
                                                'brand_id' => $brandId,
                                                'created_at' => date('Y-m-d H:i:s'),
                                                'updated_at' => date('Y-m-d H:i:s')
                                            ]);
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {}
                    });
                }
            }
        } catch (\Exception $e) {
            // Ignore DB errors on early intercept
        }
    }
}

/**
 * Centered Domain Matcher Helper
 * Safely parses request and DB brand domains to match hostnames correctly.
 * Returns the matched brand, falling back to the active default brand.
 */
if (!function_exists('get_multibrand_active_brand')) {
function get_multibrand_active_brand()
{
    $requestHost = strtolower($_SERVER['SERVER_NAME']);
    $requestHostClean = ltrim($requestHost, 'www.');
// print_r($_SESSION);die();
    // If client is logged in and has manually switched brand context, use it
    if (isset($_SESSION['uid']) && $_SESSION['uid'] > 0 && isset($_SESSION['multibrand_brand_id'])) {
        $brandId = (int)$_SESSION['multibrand_brand_id'];
        if ($brandId > 0) {
            try {
                $isAssigned = Capsule::table('mod_multibrand_client_brands')
                    ->where('client_id', $_SESSION['uid'])
                    ->where('brand_id', $brandId)
                    ->exists();
                if ($isAssigned) {
                    $brand = Capsule::table('mod_multibrand_brands')
                        ->where('id', $brandId)
                        ->where('status', 1)
                        ->first();
                    if ($brand) {
                        // Validate that the session brand's domain matches the current request domain
                        $brandUrl = $brand->domain;
                        if ($brandUrl) {
                            if (strpos($brandUrl, '://') === false) {
                                $brandUrl = 'http://' . $brandUrl;
                            }
                            $brandHost = strtolower(parse_url($brandUrl, PHP_URL_HOST));
                            $brandHostClean = ltrim($brandHost, 'www.');

                            if ($brandHostClean !== '' && $brandHostClean !== $requestHostClean && strpos($brandHostClean, $requestHostClean) === false && strpos($requestHostClean, $brandHostClean) === false) {
                                $brand = null;
                            }
                        }
                        
                        if ($brand) {
                            return $brand;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }
    }

    try {
        $brands = Capsule::table('mod_multibrand_brands')
            ->where('status', 1)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        // Exact/clean hostname match first
        foreach ($brands as $brand) {
            $brandUrl = $brand->domain;
            if (strpos($brandUrl, '://') === false) {
                $brandUrl = 'http://' . $brandUrl;
            }
            $brandHost = strtolower(parse_url($brandUrl, PHP_URL_HOST));
            $brandHostClean = ltrim($brandHost, 'www.');

            if ($requestHostClean === $brandHostClean) {
                return $brand;
            }
        }

        // Substring hostname match next
        foreach ($brands as $brand) {
            $brandUrl = $brand->domain;
            if (strpos($brandUrl, '://') === false) {
                $brandUrl = 'http://' . $brandUrl;
            }
            $brandHost = strtolower(parse_url($brandUrl, PHP_URL_HOST));
            $brandHostClean = ltrim($brandHost, 'www.');

            if (strpos($brandHostClean, $requestHostClean) !== false || strpos($requestHostClean, $brandHostClean) !== false) {
                return $brand;
            }
        }

        // Fallback to active default brand
        return Capsule::table('mod_multibrand_brands')
            ->where('is_default', 1)
            ->where('status', 1)
            ->first();

    } catch (\Exception $e) {
        return null;
    }
}
}

/**
 * Clean Domain Matcher Helper (No Fallback)
 * Checks if the current request domain exists as a brand in our module.
 * Returns the matched brand, or null if no exact match is found.
 */
if (!function_exists('get_multibrand_brand_by_domain')) {
function get_multibrand_brand_by_domain()
{
    $requestHost = strtolower($_SERVER['SERVER_NAME']);
    $requestHostClean = ltrim($requestHost, 'www.');

    try {
        $brands = Capsule::table('mod_multibrand_brands')
            ->where('status', 1)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        // Exact/clean hostname match first
        foreach ($brands as $brand) {
            $brandUrl = $brand->domain;
            if (strpos($brandUrl, '://') === false) {
                $brandUrl = 'http://' . $brandUrl;
            }
            $brandHost = strtolower(parse_url($brandUrl, PHP_URL_HOST));
            $brandHostClean = ltrim($brandHost, 'www.');

            if ($requestHostClean === $brandHostClean) {
                return $brand;
            }
        }

        // Substring hostname match next
        foreach ($brands as $brand) {
            $brandUrl = $brand->domain;
            if (strpos($brandUrl, '://') === false) {
                $brandUrl = 'http://' . $brandUrl;
            }
            $brandHost = strtolower(parse_url($brandUrl, PHP_URL_HOST));
            $brandHostClean = ltrim($brandHost, 'www.');

            if (strpos($brandHostClean, $requestHostClean) !== false || strpos($requestHostClean, $brandHostClean) !== false) {
                return $brand;
            }
        }
    } catch (\Exception $e) {
        // Ignore
    }

    return null;
}
}

/**
 * Client Area Initialization Hook
 * Dynamically changes the active global template theme in memory before the Smarty engine boots.
 * This ensures WHMCS loads the brand's custom theme fully (headers, footers, CSS) and prevents mismatches.
 * Also intercepts manual client-side brand switch requests.
 */
add_hook('ClientAreaInit', 1, function ($vars) {
    // Handle manual brand context switch request
    if (isset($_GET['brand_switch'])) {
        $brandId = (int)$_GET['brand_switch'];
        $loggedInClientId = (int)($_SESSION['uid'] ?? 0);
        
        if ($loggedInClientId > 0 && $brandId > 0) {
            try {
                $isAssigned = Capsule::table('mod_multibrand_client_brands')
                    ->where('client_id', $loggedInClientId)
                    ->where('brand_id', $brandId)
                    ->exists();
                    
                if ($isAssigned) {
                    $_SESSION['multibrand_brand_id'] = $brandId;
                    
                    // Redirect back to clean URL to remove brand_switch query param
                    $redirectUrl = strtok($_SERVER["REQUEST_URI"], '?');
                    $params = $_GET;
                    unset($params['brand_switch']);
                    if (!empty($params)) {
                        $redirectUrl .= '?' . http_build_query($params);
                    }
                    header("Location: " . $redirectUrl);
                    exit;
                }
            } catch (\Exception $e) {}
        }
    }

    $brand = get_multibrand_active_brand();
    if ($brand) {
        // Dynamic Brand-wise System URL Overrides
        if (!empty($brand->system_url)) {
            $GLOBALS['CONFIG']['SystemURL'] = $brand->system_url;
            $GLOBALS['CONFIG']['SystemSSLURL'] = $brand->system_url;
        }
        if ($brand->system_theme) {
            $theme = strtolower($brand->system_theme);
            $GLOBALS['CONFIG']['Template'] = $theme;
            $GLOBALS['CONFIG']['systpl'] = $theme;
        }
        if ($brand->default_language) {
            $lang = strtolower($brand->default_language);
            if (!isset($_SESSION['Language'])) {
                $_SESSION['Language'] = $lang;
            }
            $GLOBALS['CONFIG']['Language'] = $lang;
        }

        // Restrict and set default currency based on brand settings
        $allowedCurrencies = [];
        if (!empty($brand->brand_currencies)) {
            $currencyCodes = array_map('trim', explode(',', $brand->brand_currencies));
            if (!empty($currencyCodes)) {
                $allowedCurrencies = Capsule::table('tblcurrencies')
                    ->whereIn('code', $currencyCodes)
                    ->pluck('id')
                    ->toArray();
            }
        }

        $requestedCurrencyId = isset($_GET['currency']) ? (int)$_GET['currency'] : 0;
        $currentCurrencyId = isset($_SESSION['currency']) ? (int)$_SESSION['currency'] : 0;

        if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
            // Visitors (not logged in)
            $defaultCurrencyId = 0;
            if (!empty($brand->default_currency)) {
                $dc = Capsule::table('tblcurrencies')->where('code', $brand->default_currency)->first();
                if ($dc) {
                    $defaultCurrencyId = $dc->id;
                }
            }

            if ($defaultCurrencyId > 0 && !empty($allowedCurrencies) && !in_array($defaultCurrencyId, $allowedCurrencies)) {
                $allowedCurrencies[] = $defaultCurrencyId;
            }

            if ($currentCurrencyId === 0 || (!empty($allowedCurrencies) && !in_array($currentCurrencyId, $allowedCurrencies)) || ($requestedCurrencyId > 0 && !empty($allowedCurrencies) && !in_array($requestedCurrencyId, $allowedCurrencies))) {
                if ($defaultCurrencyId > 0) {
                    $_SESSION['currency'] = $defaultCurrencyId;
                } elseif (!empty($allowedCurrencies)) {
                    $_SESSION['currency'] = $allowedCurrencies[0];
                }
            }
        } else {
            // Logged-in clients: enforce brand currencies if configured
            if (!empty($allowedCurrencies) && !in_array($currentCurrencyId, $allowedCurrencies)) {
                $_SESSION['currency'] = $allowedCurrencies[0];
            }
        }
    }
});

/**
 * Dynamic Multi-Brand Client Stats Helper
 * Dynamically computes brand-specific totals for a logged-in client.
 */
if (!function_exists('get_multibrand_client_stats')) {
function get_multibrand_client_stats($clientId, $brandId)
{
    static $stats = [];
    $cacheKey = (int)$clientId . '_' . (int)$brandId;
    if (isset($stats[$cacheKey])) {
        return $stats[$cacheKey];
    }

    $activeServices = 0;
    $totalServices = 0;
    $activeDomains = 0;
    $totalDomains = 0;
    $unpaidInvoices = 0;
    $totalInvoices = 0;
    $activeTickets = 0;
    $totalTickets = 0;

    if ($clientId > 0 && $brandId > 0) {
        try {
            $activeServices = (int)Capsule::table('tblhosting')
                ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                ->where('tblhosting.userid', $clientId)
                ->where('mod_multibrand_service_brands.brand_id', $brandId)
                ->where('tblhosting.domainstatus', 'Active')
                ->count();

            $totalServices = (int)Capsule::table('tblhosting')
                ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                ->where('tblhosting.userid', $clientId)
                ->where('mod_multibrand_service_brands.brand_id', $brandId)
                ->count();

            $activeDomains = (int)Capsule::table('tbldomains')
                ->join('mod_multibrand_order_brands', 'tbldomains.orderid', '=', 'mod_multibrand_order_brands.order_id')
                ->where('tbldomains.userid', $clientId)
                ->where('mod_multibrand_order_brands.brand_id', $brandId)
                ->where('tbldomains.status', 'Active')
                ->count();

            $totalDomains = (int)Capsule::table('tbldomains')
                ->join('mod_multibrand_order_brands', 'tbldomains.orderid', '=', 'mod_multibrand_order_brands.order_id')
                ->where('tbldomains.userid', $clientId)
                ->where('mod_multibrand_order_brands.brand_id', $brandId)
                ->count();

            $unpaidInvoices = (int)Capsule::table('tblinvoices')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->where('tblinvoices.userid', $clientId)
                ->where('mod_multibrand_invoice_brands.brand_id', $brandId)
                ->where('tblinvoices.status', 'Unpaid')
                ->count();

            $totalInvoices = (int)Capsule::table('tblinvoices')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->where('tblinvoices.userid', $clientId)
                ->where('mod_multibrand_invoice_brands.brand_id', $brandId)
                ->count();

            $activeTickets = (int)Capsule::table('tbltickets')
                ->join('mod_multibrand_ticket_brands', 'tbltickets.id', '=', 'mod_multibrand_ticket_brands.ticket_id')
                ->where('tbltickets.userid', $clientId)
                ->where('mod_multibrand_ticket_brands.brand_id', $brandId)
                ->where('tbltickets.status', '!=', 'Closed')
                ->count();

            $totalTickets = (int)Capsule::table('tbltickets')
                ->join('mod_multibrand_ticket_brands', 'tbltickets.id', '=', 'mod_multibrand_ticket_brands.ticket_id')
                ->where('tbltickets.userid', $clientId)
                ->where('mod_multibrand_ticket_brands.brand_id', $brandId)
                ->count();
        } catch (\Exception $e) {}
    }

    $stats[$cacheKey] = [
        'active_services' => $activeServices,
        'total_services'  => $totalServices,
        'active_domains'  => $activeDomains,
        'total_domains'   => $totalDomains,
        'unpaid_invoices' => $unpaidInvoices,
        'total_invoices'  => $totalInvoices,
        'active_tickets'  => $activeTickets,
        'total_tickets'   => $totalTickets,
    ];

    return $stats[$cacheKey];
}
}

/**
 * Client Area Page Hook
 * Overrides company name, logo, language, and other branding elements.
 * Also handles Maintenance Mode, custom TOS, and HTML Invoice page overrides.
 * Performs dynamic brand-wise data filtering (services, invoices, support tickets) on templates.
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    try {
        $brand = null;
        $filename = $vars['filename'] ?? '';
        // file_put_contents(__DIR__ . '/requested_filenames.log', "----------------------------------------\n" . $filename . ' | ' . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);

        // If viewing a specific HTML invoice, resolve brand dynamically by the invoice
        if ($filename == 'viewinvoice' && isset($_GET['id'])) {
            $invoiceId = (int)$_GET['id'];
            try {
                $invBrand = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->first();
                if ($invBrand) {
                    $brand = Capsule::table('mod_multibrand_brands')->where('id', $invBrand->brand_id)->where('status', 1)->first();
                }
            } catch (\Exception $e) {}
        }

        if (!$brand) {
            $brand = get_multibrand_active_brand();
        }

        // file_put_contents(__DIR__ . '/requested_filenames.log', "Resolved Brand: " . ($brand ? $brand->brand_name . " (ID: " . $brand->id . ")" : "NONE") . "\n", FILE_APPEND);
        
        $productsType = isset($vars['products']) ? gettype($vars['products']) : 'NOT SET';
        $productGroupsType = isset($vars['productgroups']) ? gettype($vars['productgroups']) : 'NOT SET';
        // file_put_contents(__DIR__ . '/requested_filenames.log', "Vars products type: $productsType | productgroups type: $productGroupsType\n", FILE_APPEND);

        if ($brand) {
            // file_put_contents(__DIR__ . '/requested_filenames.log', "Brand products_branding setting: " . ($brand->products_branding ? '1' : '0') . " | price_override setting: " . ($brand->price_override ? '1' : '0') . "\n", FILE_APPEND);
        // Maintenance Mode handling
        if ($brand->maintenance_mode) {
            if ($brand->maintenance_mode_redirect_url) {
                $url = trim($brand->maintenance_mode_redirect_url);
                if (!preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }
                header('Location: ' . $url);
                exit;
            } else {
                $message = $brand->maintenance_mode_message ?: "We are currently performing maintenance and will be back shortly.";
                die('<div style="text-align:center; margin-top:50px; font-family: sans-serif;"><h1>Maintenance Mode</h1><p>' . nl2br(htmlspecialchars($message)) . '</p></div>');
            }
        }
         if (!empty($brand->system_url)) {
            $GLOBALS['CONFIG']['SystemURL'] = $systemurl;
            $GLOBALS['CONFIG']['SystemSSLURL'] = $brand->system_url;
            $vars['systemurl'] = $brand->system_url;
            $vars['systemsslurl'] = $brand->system_url;
            $vars['systemNonSSLURL'] = $brand->system_url;
            $GLOBALS['CONFIG']['Domain'] = parse_url($brand->system_url, PHP_URL_HOST);
        }
          if ($brand->system_theme) {
            
            $theme = strtolower($brand->system_theme);
            $GLOBALS['CONFIG']['Template'] = $theme;
            $GLOBALS['CONFIG']['systpl'] = $theme;
        }
        if ($brand->default_language) {
            // print_r($brand->default_language);die();
            $lang = strtolower($brand->default_language);
            if (!isset($_SESSION['Language'])) {
                $_SESSION['Language'] = $lang;
            }
            $GLOBALS['CONFIG']['Language'] = $lang;
        }
        // print('<pre>');           
        // print_r($GLOBALS);die();
        $overrides = [];

        if (!empty($brand->system_url)) {
            $overrides['systemurl'] = $brand->system_url;
            $overrides['systemsslurl'] = $brand->system_url;
            $overrides['systemNonSSLURL'] = $brand->system_url;
        }

        if ($brand->company_name) {
            $overrides['companyname'] = $brand->company_name;
        }

        if ($brand->logo_url) {
            $overrides['logo'] = $brand->logo_url;
            $overrides['assetLogoPath'] = $brand->logo_url;
        }

        if ($brand->default_language) {
            $overrides['language'] = strtolower($brand->default_language);
        }

        if ($brand->tos_url) {
            $overrides['tosurl'] = $brand->tos_url;
        }

        if ($brand->order_template) {
            $overrides['carttemplate'] = $brand->order_template;
        }

        // Custom HTML Invoice layout variables override
        if ($filename == 'viewinvoice' && $brand->pay_to_text) {
            $overrides['payto'] = nl2br($brand->pay_to_text);
        }

        // --- Brand-wise Shopping Cart Catalog Filtering & Pricing Overrides ---
        $prodCount = isset($vars['products']) && is_array($vars['products']) ? count($vars['products']) : 'NOT ARRAY';
        $groupCount = isset($vars['productgroups']) && is_array($vars['productgroups']) ? count($vars['productgroups']) : 'NOT ARRAY';
        // file_put_contents(__DIR__ . '/requested_filenames.log', "Cart override check: filename = $filename | products_branding = " . ($brand->products_branding ? '1' : '0') . " | products count: $prodCount | productgroups count: $groupCount\n", FILE_APPEND);
        if ((in_array($filename, ['cart', 'index']) || isset($vars['productgroups']) || isset($vars['products'])) && $brand->products_branding) {
            $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
            $brandProductIds = isset($pricingOverrides['products']) ? array_keys($pricingOverrides['products']) : [];
            // file_put_contents(__DIR__ . '/requested_filenames.log', "Entered cart override block. Brand Product IDs: " . implode(',', $brandProductIds) . "\n", FILE_APPEND);

            // Filter products list for sale in current category and apply brand pricing overrides
            if (isset($vars['products']) && is_array($vars['products'])) {
                $filteredProducts = [];
                foreach ($vars['products'] as $product) {
                    $productId = 0;
                    if (is_array($product)) {
                        $productId = isset($product['id']) ? (int)$product['id'] : (isset($product['pid']) ? (int)$product['pid'] : 0);
                    } elseif (is_object($product)) {
                        $productId = isset($product->id) ? (int)$product->id : (isset($product->pid) ? (int)$product->pid : 0);
                    }
                    
                    // Apply branding catalog visibility filter (if brand has attached products)
                    if (!empty($brandProductIds) && !in_array($productId, $brandProductIds)) {
                        continue;
                    }

                    // Apply brand pricing overrides if active
                    if ($brand->price_override && $productId > 0) {
                        $currencyId = (int)($vars['activeCurrency']['id'] ?? $_SESSION['currency'] ?? 1);
                        $rates = $pricingOverrides['products'][$productId]['pricing'][$currencyId] ?? [];
                        // file_put_contents(__DIR__ . '/cart_debug_output.txt', "Product ID: $productId | Currency ID: $currencyId | Rates: " . print_r($rates, true) . "\n", FILE_APPEND);
                        
                        if (!empty($rates)) {
                            $isObj = is_object($product);
                            $paytype = $isObj ? ($product->paytype ?? '') : ($product['paytype'] ?? '');
                            $pricing = $isObj ? (array)($product->pricing ?? []) : ($product['pricing'] ?? []);
                            
                            if ($paytype == 'onetime') {
                                $newPrice = isset($rates['monthly']) && $rates['monthly'] !== '' ? (float)$rates['monthly'] : null;
                                $newSetup = isset($rates['msetupfee']) && $rates['msetupfee'] !== '' ? (float)$rates['msetupfee'] : null;
                                
                                if ($newPrice !== null) {
                                    $pricing['rawpricing']['monthly'] = number_format($newPrice, 2, '.', '');
                                    $pricing['rawpricing']['simple'] = formatCurrency($newPrice, $currencyId);
                                }
                                if ($newSetup !== null) {
                                    $pricing['rawpricing']['msetupfee'] = number_format($newSetup, 2, '.', '');
                                }
                                
                                $priceVal = $newPrice !== null ? $newPrice : (float)($pricing['rawpricing']['monthly'] ?? 0);
                                $setupVal = $newSetup !== null ? $newSetup : (float)($pricing['rawpricing']['msetupfee'] ?? 0);
                                
                                $onetimeString = formatCurrency($priceVal, $currencyId);
                                if ($setupVal > 0) {
                                    $setupLang = $vars['_LANG']['ordersetupfee'] ?? 'Setup Fee';
                                    $onetimeString .= ' + ' . formatCurrency($setupVal, $currencyId) . ' ' . $setupLang;
                                }
                                $pricing['onetime'] = $onetimeString;
                                $pricing['cycles']['onetime'] = $onetimeString;
                                
                                $currencyObj = \WHMCS\Billing\Currency::find($currencyId);
                                if ($currencyObj) {
                                    if (!is_array($pricing['minprice'] ?? null)) {
                                        $pricing['minprice'] = [];
                                    }
                                    $pricing['minprice']['price'] = new \WHMCS\View\Formatter\Price($priceVal, $currencyObj);
                                    $pricing['minprice']['setupFee'] = new \WHMCS\View\Formatter\Price($setupVal, $currencyObj);
                                    $pricing['minprice']['cycle'] = 'onetime';
                                    $pricing['minprice']['simple'] = formatCurrency($priceVal, $currencyId);
                                }
                            } elseif ($paytype == 'recurring') {
                                $cyclesKeys = [
                                    'monthly' => ['price' => 'monthly', 'setup' => 'msetupfee', 'lang' => 'orderpaymenttermmonthly'],
                                    'quarterly' => ['price' => 'quarterly', 'setup' => 'qsetupfee', 'lang' => 'orderpaymenttermquarterly'],
                                    'semiannually' => ['price' => 'semiannually', 'setup' => 'ssetupfee', 'lang' => 'orderpaymenttermsemiannually'],
                                    'annually' => ['price' => 'annually', 'setup' => 'asetupfee', 'lang' => 'orderpaymenttermannually'],
                                    'biennially' => ['price' => 'biennially', 'setup' => 'bsetupfee', 'lang' => 'orderpaymenttermbiennially'],
                                    'triennially' => ['price' => 'triennially', 'setup' => 'tsetupfee', 'lang' => 'orderpaymenttermtriennially'],
                                ];
                                
                                $enabledPrices = [];
                                $enabledSetups = [];
                                
                                foreach ($cyclesKeys as $cycle => $cData) {
                                    $origPrice = (float)($pricing['rawpricing'][$cycle] ?? -1.00);
                                    if ($origPrice >= 0) {
                                        $newPrice = isset($rates[$cData['price']]) && $rates[$cData['price']] !== '' ? (float)$rates[$cData['price']] : null;
                                        $newSetup = isset($rates[$cData['setup']]) && $rates[$cData['setup']] !== '' ? (float)$rates[$cData['setup']] : null;
                                        
                                        if ($newPrice !== null) {
                                            $pricing['rawpricing'][$cycle] = number_format($newPrice, 2, '.', '');
                                        }
                                        if ($newSetup !== null) {
                                            $pricing['rawpricing'][$cData['setup']] = number_format($newSetup, 2, '.', '');
                                        }
                                        
                                        $priceVal = $newPrice !== null ? $newPrice : $origPrice;
                                        $setupVal = $newSetup !== null ? $newSetup : (float)($pricing['rawpricing'][$cData['setup']] ?? 0);
                                        
                                        $cycleString = formatCurrency($priceVal, $currencyId) . ' ' . ($vars['_LANG'][$cData['lang']] ?? '');
                                        if ($setupVal > 0) {
                                            $setupLang = $vars['_LANG']['ordersetupfee'] ?? 'Setup Fee';
                                            $cycleString .= ' + ' . formatCurrency($setupVal, $currencyId) . ' ' . $setupLang;
                                        }
                                        $pricing[$cycle] = $cycleString;
                                        $pricing['cycles'][$cycle] = $cycleString;
                                        
                                        $enabledPrices[$cycle] = $priceVal;
                                        $enabledSetups[$cycle] = $setupVal;
                                    }
                                }
                                
                                if (!empty($enabledPrices)) {
                                    $minPriceVal = -1;
                                    $minSetupVal = 0;
                                    $cycleOrder = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];
                                    foreach ($cycleOrder as $cycle) {
                                        if (isset($enabledPrices[$cycle])) {
                                            $minPriceVal = $enabledPrices[$cycle];
                                            $minSetupVal = $enabledSetups[$cycle] ?? 0;
                                            break;
                                        }
                                    }
                                    
                                    if ($minPriceVal >= 0) {
                                        $currencyObj = \WHMCS\Billing\Currency::find($currencyId);
                                        if ($currencyObj) {
                                            if (!is_array($pricing['minprice'] ?? null)) {
                                                $pricing['minprice'] = [];
                                            }
                                            $pricing['minprice']['price'] = new \WHMCS\View\Formatter\Price($minPriceVal, $currencyObj);
                                            $pricing['minprice']['setupFee'] = new \WHMCS\View\Formatter\Price($minSetupVal, $currencyObj);
                                            
                                            // Determine min price cycle term
                                            $minCycle = 'monthly';
                                            foreach ($cycleOrder as $cycle) {
                                                if (isset($enabledPrices[$cycle]) && $enabledPrices[$cycle] == $minPriceVal) {
                                                    $minCycle = $cycle;
                                                    break;
                                                }
                                            }
                                            $pricing['minprice']['cycle'] = $minCycle;
                                            $pricing['minprice']['simple'] = formatCurrency($minPriceVal, $currencyId);
                                        }
                                    }
                                }
                            }
                            
                            if ($isObj) {
                                $product->pricing = $pricing;
                            } else {
                                $product['pricing'] = $pricing;
                            }
                        }
                    }

                    $filteredProducts[] = $product;
                }
                $overrides['products'] = $filteredProducts;
            }

            // Filter product groups (categories) lists to only show groups containing branded products
            if (!empty($brandProductIds)) {
                $brandGroupIds = [];
                try {
                    $brandGroupIds = Capsule::table('tblproducts')
                        ->whereIn('id', $brandProductIds)
                        ->pluck('gid')
                        ->unique()
                        ->toArray();
                } catch (\Exception $e) {}

                if (isset($vars['productgroups']) && is_array($vars['productgroups'])) {
                    $filteredGroups = [];
                    foreach ($vars['productgroups'] as $group) {
                        $groupId = 0;
                        if (is_array($group)) {
                            $groupId = isset($group['id']) ? (int)$group['id'] : (isset($group['gid']) ? (int)$group['gid'] : 0);
                        } elseif (is_object($group)) {
                            $groupId = isset($group->id) ? (int)$group->id : (isset($group->gid) ? (int)$group->gid : 0);
                        }
                        if ($groupId > 0 && in_array($groupId, $brandGroupIds)) {
                            $filteredGroups[] = $group;
                        }
                    }
                    $overrides['productgroups'] = $filteredGroups;
                }
            }
        }

        // --- Brand-wise Client Area Data Filtering ---
        $clientId = (int)($_SESSION['uid'] ?? 0);
        if ($clientId > 0) {
            try {
                // Fetch brand mappings lists
                $brandServiceIds = Capsule::table('mod_multibrand_service_brands')
                    ->where('brand_id', $brand->id)
                    ->pluck('service_id')
                    ->toArray();

                $brandInvoiceIds = Capsule::table('mod_multibrand_invoice_brands')
                    ->where('brand_id', $brand->id)
                    ->pluck('invoice_id')
                    ->toArray();

                $brandTicketIds = Capsule::table('mod_multibrand_ticket_brands')
                    ->where('brand_id', $brand->id)
                    ->pluck('ticket_id')
                    ->toArray();

                $brandDomainIds = Capsule::table('tbldomains')
                    ->join('mod_multibrand_order_brands', 'tbldomains.orderid', '=', 'mod_multibrand_order_brands.order_id')
                    ->where('mod_multibrand_order_brands.brand_id', $brand->id)
                    ->pluck('tbldomains.id')
                    ->toArray();

                // Helper to filter items in list (handles arrays and objects)
                $filterEntityList = function ($list, $allowedIds) {
                    if (!is_array($list)) {
                        return $list;
                    }
                    $filtered = [];
                    foreach ($list as $item) {
                        $id = 0;
                        if (is_array($item)) {
                            $id = isset($item['id']) ? (int)$item['id'] : 0;
                        } elseif (is_object($item)) {
                            $id = isset($item->id) ? (int)$item->id : 0;
                        }
                        if ($id > 0 && in_array($id, $allowedIds)) {
                            $filtered[] = $item;
                        }
                    }
                    return $filtered;
                };

                // Filter services lists
                $serviceKeys = ['services', 'activeServices', 'activeservices', 'activeproducts', 'activeProducts'];
                foreach ($serviceKeys as $key) {
                    if (isset($vars[$key]) && is_array($vars[$key])) {
                        $overrides[$key] = $filterEntityList($vars[$key], $brandServiceIds);
                    }
                }

                // Filter invoices lists
                $invoiceKeys = ['invoices', 'unpaidInvoices', 'unpaidinvoices', 'dueinvoices', 'dueInvoices', 'recentinvoices', 'recentInvoices'];
                foreach ($invoiceKeys as $key) {
                    if (isset($vars[$key]) && is_array($vars[$key])) {
                        $overrides[$key] = $filterEntityList($vars[$key], $brandInvoiceIds);
                    }
                }

                // Filter tickets lists
                $ticketKeys = ['tickets', 'recentTickets', 'recenttickets', 'activeTickets', 'activetickets'];
                foreach ($ticketKeys as $key) {
                    if (isset($vars[$key]) && is_array($vars[$key])) {
                        $overrides[$key] = $filterEntityList($vars[$key], $brandTicketIds);
                    }
                }

                // Filter domains lists
                $domainKeys = ['domains', 'activeDomains', 'activedomains'];
                foreach ($domainKeys as $key) {
                    if (isset($vars[$key]) && is_array($vars[$key])) {
                        $overrides[$key] = $filterEntityList($vars[$key], $brandDomainIds);
                    }
                }

                // Fetch statistics for matching brand
                $stats = get_multibrand_client_stats($clientId, $brand->id);

                if (isset($vars['clientsstats']) && is_array($vars['clientsstats'])) {
                    $cStats = $vars['clientsstats'];

                    $cStats['productsnumactive'] = $stats['active_services'];
                    $cStats['productsnum'] = $stats['total_services'];
                    $cStats['hostingnumactive'] = $stats['active_services'];
                    $cStats['hostingnum'] = $stats['total_services'];
                    $cStats['servicesnumactive'] = $stats['active_services'];
                    $cStats['servicesnum'] = $stats['total_services'];

                    $cStats['numactivedomains'] = $stats['active_domains'];
                    $cStats['numdomains'] = $stats['total_domains'];

                    $cStats['numunpaidinvoices'] = $stats['unpaid_invoices'];
                    $cStats['numdueinvoices'] = $stats['unpaid_invoices'];
                    $cStats['invoicesnumunpaid'] = $stats['unpaid_invoices'];
                    $cStats['invoicesnum'] = $stats['total_invoices'];

                    $cStats['numactivetickets'] = $stats['active_tickets'];
                    $cStats['numtickets'] = $stats['total_tickets'];
                    $cStats['ticketsnumactive'] = $stats['active_tickets'];

                    $overrides['clientsstats'] = $cStats;
                }

                // Override pagination totalresults & counts for specific listing pages
                $action = $_GET['action'] ?? '';
                if ($filename == 'clientarea' && $action == 'services') {
                    $overrides['totalresults'] = $stats['total_services'];
                    $overrides['numservices'] = $stats['total_services'];
                } elseif ($filename == 'clientarea' && $action == 'domains') {
                    $overrides['totalresults'] = $stats['total_domains'];
                    $overrides['numdomains'] = $stats['total_domains'];
                } elseif ($filename == 'clientarea' && $action == 'invoices') {
                    $overrides['totalresults'] = $stats['total_invoices'];
                    $overrides['numinvoices'] = $stats['total_invoices'];

                    // Recalculate brand-wise unpaid balance for page context
                    $brandUnpaidInvoices = Capsule::table('tblinvoices')
                        ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                        ->where('tblinvoices.userid', $clientId)
                        ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                        ->where('tblinvoices.status', 'Unpaid')
                        ->select('tblinvoices.total', 'tblinvoices.credit')
                        ->get();

                    $brandTotalBalance = 0.00;
                    foreach ($brandUnpaidInvoices as $inv) {
                        $brandTotalBalance += (float)$inv->total - (float)($inv->credit ?? 0.00);
                    }

                    $client = Capsule::table('tblclients')->where('id', $clientId)->first();
                    $currencyId = $client ? (int)$client->currency : 1;
                    if (function_exists('formatCurrency')) {
                        $brandBalanceFormatted = formatCurrency($brandTotalBalance, $currencyId);
                    } else {
                        $brandBalanceFormatted = '$' . number_format($brandTotalBalance, 2);
                    }

                    $overrides['totalbalance'] = $brandBalanceFormatted;
                    $overrides['numdueinvoices'] = $stats['unpaid_invoices'];
                    $overrides['numunpaidinvoices'] = $stats['unpaid_invoices'];
                } elseif ($filename == 'supporttickets' && empty($action)) {
                    $overrides['totalresults'] = $stats['total_tickets'];
                    $overrides['numtickets'] = $stats['total_tickets'];
                }
            } catch (\Exception $e) {}
        }

        if (isset($GLOBALS['smarty']) && is_object($GLOBALS['smarty']) && method_exists($GLOBALS['smarty'], 'assign')) {
            foreach ($overrides as $key => $value) {
                $GLOBALS['smarty']->assign($key, $value);
            }
        }
        return $overrides;
    }
    return [];
    } catch (\Throwable $t) {
        // file_put_contents(__DIR__ . '/hook_errors.log', "ClientAreaPage Error: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n", FILE_APPEND);
        return [];
    }
});

/**
 * Client Area Primary Sidebar Hook
 * Updates sidebar navigation badge counts (My Services, My Invoices, Support Tickets) dynamically
 */
add_hook('ClientAreaPrimarySidebar', 1, function ($primarySidebar) {
    $brand = get_multibrand_active_brand();
    $clientId = (int)($_SESSION['uid'] ?? 0);

    if ($brand && $clientId > 0) {
        $stats = get_multibrand_client_stats($clientId, $brand->id);

        // 1. My Services / My Products sidebar
        $servicesItem = $primarySidebar->getChild('My Services');
        if ($servicesItem) {
            $activeServicesChild = $servicesItem->getChild('Active Services') ?: $servicesItem->getChild('My Services');
            if ($activeServicesChild) {
                $activeServicesChild->setBadge($stats['active_services']);
            }
        }

        // 2. My Invoices / Invoices sidebar
        $invoicesItem = $primarySidebar->getChild('My Invoices');
        if ($invoicesItem) {
            $unpaidInvoicesChild = $invoicesItem->getChild('Unpaid Invoices') ?: $invoicesItem->getChild('My Invoices');
            if ($unpaidInvoicesChild) {
                $unpaidInvoicesChild->setBadge($stats['unpaid_invoices']);
            }
        }

        // 3. Support / Tickets sidebar
        $supportItem = $primarySidebar->getChild('Support');
        if ($supportItem) {
            $ticketsChild = $supportItem->getChild('Support Tickets') ?: $supportItem->getChild('Tickets');
            if ($ticketsChild) {
                $ticketsChild->setBadge($stats['active_tickets']);
            }
        }

        // 4. Invoices Status Filter Sidebar
        $invoicesStatusFilter = $primarySidebar->getChild('My Invoices Status Filter');
        if ($invoicesStatusFilter) {
            try {
                $statusCounts = Capsule::table('tblinvoices')
                    ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                    ->where('tblinvoices.userid', $clientId)
                    ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                    ->select('tblinvoices.status', Capsule::raw('count(*) as count'))
                    ->groupBy('tblinvoices.status')
                    ->pluck('count', 'status')
                    ->toArray();

                foreach (['Paid', 'Unpaid', 'Cancelled', 'Refunded', 'Draft', 'Collections'] as $status) {
                    $child = $invoicesStatusFilter->getChild($status);
                    if ($child) {
                        $count = isset($statusCounts[$status]) ? (int)$statusCounts[$status] : 0;
                        $child->setBadge($count);
                    }
                }
            } catch (\Exception $e) {}
        }

        // 5. My Invoices Summary Panel (header count and body text balance)
        $invoicesSummary = $primarySidebar->getChild('My Invoices Summary');
        if ($invoicesSummary) {
            try {
                // Header label: "X Invoices Due"
                $invoicesSummary->setLabel($stats['unpaid_invoices'] . ' Invoices Due');

                // Recalculate client-wide (total) unpaid count and formatted balance to replace in body
                $clientTotalUnpaidCount = Capsule::table('tblinvoices')
                    ->where('userid', $clientId)
                    ->where('status', 'Unpaid')
                    ->count();

                $clientTotalUnpaidInvoices = Capsule::table('tblinvoices')
                    ->where('userid', $clientId)
                    ->where('status', 'Unpaid')
                    ->select('total', 'credit')
                    ->get();
                $clientTotalBalance = 0.00;
                foreach ($clientTotalUnpaidInvoices as $inv) {
                    $clientTotalBalance += (float)$inv->total - (float)($inv->credit ?? 0.00);
                }

                $client = Capsule::table('tblclients')->where('id', $clientId)->first();
                $currencyId = $client ? (int)$client->currency : 1;

                if (function_exists('formatCurrency')) {
                    $clientTotalBalanceFormatted = formatCurrency($clientTotalBalance, $currencyId);
                } else {
                    $clientTotalBalanceFormatted = '$' . number_format($clientTotalBalance, 2);
                }

                // Brand-specific unpaid balance
                $brandUnpaidInvoices = Capsule::table('tblinvoices')
                    ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                    ->where('tblinvoices.userid', $clientId)
                    ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                    ->where('tblinvoices.status', 'Unpaid')
                    ->select('tblinvoices.total', 'tblinvoices.credit')
                    ->get();

                $brandTotalBalance = 0.00;
                foreach ($brandUnpaidInvoices as $inv) {
                    $brandTotalBalance += (float)$inv->total - (float)($inv->credit ?? 0.00);
                }

                if (function_exists('formatCurrency')) {
                    $brandBalanceFormatted = formatCurrency($brandTotalBalance, $currencyId);
                } else {
                    $brandBalanceFormatted = '$' . number_format($brandTotalBalance, 2);
                }

                // Clean swap in body HTML
                $bodyHtml = $invoicesSummary->getBodyHtml();
                if ($bodyHtml) {
                    // String replace values
                    $bodyHtml = str_replace((string)$clientTotalUnpaidCount, (string)$stats['unpaid_invoices'], $bodyHtml);
                    $bodyHtml = str_replace($clientTotalBalanceFormatted, $brandBalanceFormatted, $bodyHtml);
                    $invoicesSummary->setBodyHtml($bodyHtml);
                }
            } catch (\Exception $e) {}
        }

        // 6. Services Status Filter
        $servicesStatusFilter = $primarySidebar->getChild('My Services Status Filter');
        if ($servicesStatusFilter) {
            try {
                $statusCounts = Capsule::table('tblhosting')
                    ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                    ->where('tblhosting.userid', $clientId)
                    ->where('mod_multibrand_service_brands.brand_id', $brand->id)
                    ->select('tblhosting.domainstatus', Capsule::raw('count(*) as count'))
                    ->groupBy('tblhosting.domainstatus')
                    ->pluck('count', 'domainstatus')
                    ->toArray();

                foreach (['Active', 'Pending', 'Suspended', 'Terminated', 'Cancelled'] as $status) {
                    $child = $servicesStatusFilter->getChild($status);
                    if ($child) {
                        $count = isset($statusCounts[$status]) ? (int)$statusCounts[$status] : 0;
                        $child->setBadge($count);
                    }
                }
            } catch (\Exception $e) {}
        }

        // 7. Ticket List Status Filter
        $ticketStatusFilter = $primarySidebar->getChild('Ticket List Status Filter');
        if ($ticketStatusFilter) {
            try {
                $statusCounts = Capsule::table('tbltickets')
                    ->join('mod_multibrand_ticket_brands', 'tbltickets.id', '=', 'mod_multibrand_ticket_brands.ticket_id')
                    ->where('tbltickets.userid', $clientId)
                    ->where('mod_multibrand_ticket_brands.brand_id', $brand->id)
                    ->select('tbltickets.status', Capsule::raw('count(*) as count'))
                    ->groupBy('tbltickets.status')
                    ->pluck('count', 'status')
                    ->toArray();

                foreach (['Open', 'Answered', 'Customer-Reply', 'Closed'] as $status) {
                    $child = $ticketStatusFilter->getChild($status);
                    if ($child) {
                        $count = isset($statusCounts[$status]) ? (int)$statusCounts[$status] : 0;
                        $child->setBadge($count);
                    }
                }
            } catch (\Exception $e) {}
        }
    }
});

/**
 * Client Area Secondary Sidebar Hook
 * Updates secondary panel header badge counts dynamically
 */
add_hook('ClientAreaSecondarySidebar', 1, function ($secondarySidebar) {
    $brand = get_multibrand_active_brand();
    $clientId = (int)($_SESSION['uid'] ?? 0);

    if ($brand && $clientId > 0) {
        $stats = get_multibrand_client_stats($clientId, $brand->id);

        foreach ($secondarySidebar->getChildren() as $panel) {
            $panelName = strtolower($panel->getName());
            if (strpos($panelName, 'service') !== false || strpos($panelName, 'product') !== false) {
                $panel->setBadge($stats['active_services']);
            } elseif (strpos($panelName, 'invoice') !== false || strpos($panelName, 'billing') !== false) {
                $panel->setBadge($stats['unpaid_invoices']);
            } elseif (strpos($panelName, 'ticket') !== false || strpos($panelName, 'support') !== false) {
                $panel->setBadge($stats['active_tickets']);
            }
        }
    }
});

/**
 * Client Area Homepage Panels Hook
 * Filters the active dashboard panels to show only brand-specific items
 */
add_hook('ClientAreaHomepagePanels', 1, function ($homePagePanels) {
    $brand = get_multibrand_active_brand();
    $clientId = (int)($_SESSION['uid'] ?? 0);

    if ($brand && $clientId > 0) {
        $stats = get_multibrand_client_stats($clientId, $brand->id);

        // 1. Filter "Active Products/Services" panel
        $productsPanel = $homePagePanels->getChild('Active Products/Services');
        if ($productsPanel) {
            $brandServiceIds = Capsule::table('mod_multibrand_service_brands')
                ->where('brand_id', $brand->id)
                ->pluck('service_id')
                ->toArray();

            foreach ($productsPanel->getChildren() as $child) {
                $uri = $child->getUri();
                preg_match('/id=(\d+)/', $uri, $matches);
                $serviceId = isset($matches[1]) ? (int)$matches[1] : 0;
                if ($serviceId > 0 && !in_array($serviceId, $brandServiceIds)) {
                    $productsPanel->removeChild($child->getName());
                }
            }
        }

        // 2. Filter "Overdue Invoices" panel
        $invoicesPanel = $homePagePanels->getChild('Overdue Invoices');
        if ($invoicesPanel) {
            $brandInvoiceIds = Capsule::table('mod_multibrand_invoice_brands')
                ->where('brand_id', $brand->id)
                ->pluck('invoice_id')
                ->toArray();

            foreach ($invoicesPanel->getChildren() as $child) {
                $uri = $child->getUri();
                preg_match('/id=(\d+)/', $uri, $matches);
                $invoiceId = isset($matches[1]) ? (int)$matches[1] : 0;
                if ($invoiceId > 0 && !in_array($invoiceId, $brandInvoiceIds)) {
                    $invoicesPanel->removeChild($child->getName());
                }
            }

            // Recalculate and swap body HTML counts and balance
            $clientTotalUnpaidCount = Capsule::table('tblinvoices')
                ->where('userid', $clientId)
                ->where('status', 'Unpaid')
                ->count();

            $clientTotalUnpaidInvoices = Capsule::table('tblinvoices')
                ->where('userid', $clientId)
                ->where('status', 'Unpaid')
                ->select('total', 'credit')
                ->get();
            $clientTotalBalance = 0.00;
            foreach ($clientTotalUnpaidInvoices as $inv) {
                $clientTotalBalance += (float)$inv->total - (float)($inv->credit ?? 0.00);
            }

            $client = Capsule::table('tblclients')->where('id', $clientId)->first();
            $currencyId = $client ? (int)$client->currency : 1;

            if (function_exists('formatCurrency')) {
                $clientTotalBalanceFormatted = formatCurrency($clientTotalBalance, $currencyId);
            } else {
                $clientTotalBalanceFormatted = '$' . number_format($clientTotalBalance, 2);
            }

            $brandUnpaidInvoices = Capsule::table('tblinvoices')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->where('tblinvoices.userid', $clientId)
                ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                ->where('tblinvoices.status', 'Unpaid')
                ->select('tblinvoices.total', 'tblinvoices.credit')
                ->get();

            $brandTotalBalance = 0.00;
            foreach ($brandUnpaidInvoices as $inv) {
                $brandTotalBalance += (float)$inv->total - (float)($inv->credit ?? 0.00);
            }

            if (function_exists('formatCurrency')) {
                $brandBalanceFormatted = formatCurrency($brandTotalBalance, $currencyId);
            } else {
                $brandBalanceFormatted = '$' . number_format($brandTotalBalance, 2);
            }

            $bodyHtml = $invoicesPanel->getBodyHtml();
            if ($bodyHtml) {
                $bodyHtml = str_replace((string)$clientTotalUnpaidCount, (string)$stats['unpaid_invoices'], $bodyHtml);
                $bodyHtml = str_replace($clientTotalBalanceFormatted, $brandBalanceFormatted, $bodyHtml);
                $invoicesPanel->setBodyHtml($bodyHtml);
            }
        }

        // 3. Filter "Recent Support Tickets" panel
        $ticketsPanel = $homePagePanels->getChild('Recent Support Tickets');
        if ($ticketsPanel) {
            $brandTicketIds = Capsule::table('mod_multibrand_ticket_brands')
                ->where('brand_id', $brand->id)
                ->pluck('ticket_id')
                ->toArray();

            foreach ($ticketsPanel->getChildren() as $child) {
                $uri = $child->getUri();
                $ticketId = 0;
                if (preg_match('/id=(\d+)/', $uri, $matches)) {
                    $ticketId = (int)$matches[1];
                } elseif (preg_match('/tid=([^&]+)/', $uri, $matches)) {
                    $tid = $matches[1];
                    try {
                        $ticketId = (int)Capsule::table('tbltickets')->where('tid', $tid)->value('id');
                    } catch (\Exception $e) {}
                }

                if ($ticketId > 0 && !in_array($ticketId, $brandTicketIds)) {
                    $ticketsPanel->removeChild($child->getName());
                }
            }
        }
    }
});

/**
 * Order Page Hook
 * Dynamically overrides the cart template matching the brand's order template
 */
add_hook('OrderPage', 1, function ($vars) {
    try {
        $brand = get_multibrand_active_brand();
        $overrides = [];
        // file_put_contents(__DIR__ . '/requested_filenames.log', "OrderPage Hook Executed! Brand ID: " . ($brand ? $brand->id : 'NONE') . " | CartTemplate Override: " . ($brand->order_template ?? 'NONE') . "\n", FILE_APPEND);
        if ($brand && $brand->order_template) {
            $overrides['carttemplate'] = $brand->order_template;
        }

        if ($brand && $brand->products_branding) {
            $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
            $brandProductIds = isset($pricingOverrides['products']) ? array_keys($pricingOverrides['products']) : [];

            // Filter products list for sale in current category and apply brand pricing overrides
            if (isset($vars['products']) && is_array($vars['products'])) {
                $filteredProducts = [];
                foreach ($vars['products'] as $product) {
                    $productId = 0;
                    if (is_array($product)) {
                        $productId = isset($product['id']) ? (int)$product['id'] : (isset($product['pid']) ? (int)$product['pid'] : 0);
                    } elseif (is_object($product)) {
                        $productId = isset($product->id) ? (int)$product->id : (isset($product->pid) ? (int)$product->pid : 0);
                    }
                    
                    // Apply branding catalog visibility filter (if brand has attached products)
                    if (!empty($brandProductIds) && !in_array($productId, $brandProductIds)) {
                        continue;
                    }

                    // Apply brand pricing overrides if active
                    if ($brand->price_override && $productId > 0) {
                        $currencyId = (int)($vars['activeCurrency']['id'] ?? $_SESSION['currency'] ?? 1);
                        $rates = $pricingOverrides['products'][$productId]['pricing'][$currencyId] ?? [];
                        
                        if (!empty($rates)) {
                            $isObj = is_object($product);
                            $paytype = $isObj ? ($product->paytype ?? '') : ($product['paytype'] ?? '');
                            $pricing = $isObj ? (array)($product->pricing ?? []) : ($product['pricing'] ?? []);
                            
                            if ($paytype == 'onetime') {
                                $newPrice = isset($rates['monthly']) && $rates['monthly'] !== '' ? (float)$rates['monthly'] : null;
                                $newSetup = isset($rates['msetupfee']) && $rates['msetupfee'] !== '' ? (float)$rates['msetupfee'] : null;
                                
                                if ($newPrice !== null) {
                                    $pricing['rawpricing']['monthly'] = number_format($newPrice, 2, '.', '');
                                    $pricing['rawpricing']['simple'] = formatCurrency($newPrice, $currencyId);
                                }
                                if ($newSetup !== null) {
                                    $pricing['rawpricing']['msetupfee'] = number_format($newSetup, 2, '.', '');
                                }
                                
                                $priceVal = $newPrice !== null ? $newPrice : (float)($pricing['rawpricing']['monthly'] ?? 0);
                                $setupVal = $newSetup !== null ? $newSetup : (float)($pricing['rawpricing']['msetupfee'] ?? 0);
                                
                                $onetimeString = formatCurrency($priceVal, $currencyId);
                                if ($setupVal > 0) {
                                    $setupLang = $vars['_LANG']['ordersetupfee'] ?? 'Setup Fee';
                                    $onetimeString .= ' + ' . formatCurrency($setupVal, $currencyId) . ' ' . $setupLang;
                                }
                                $pricing['onetime'] = $onetimeString;
                                $pricing['cycles']['onetime'] = $onetimeString;
                                
                                $currencyObj = \WHMCS\Billing\Currency::find($currencyId);
                                if ($currencyObj) {
                                    if (!is_array($pricing['minprice'] ?? null)) {
                                        $pricing['minprice'] = [];
                                    }
                                    $pricing['minprice']['price'] = new \WHMCS\View\Formatter\Price($priceVal, $currencyObj);
                                    $pricing['minprice']['setupFee'] = new \WHMCS\View\Formatter\Price($setupVal, $currencyObj);
                                    $pricing['minprice']['cycle'] = 'onetime';
                                    $pricing['minprice']['simple'] = formatCurrency($priceVal, $currencyId);
                                }
                            } elseif ($paytype == 'recurring') {
                                $cyclesKeys = [
                                    'monthly' => ['price' => 'monthly', 'setup' => 'msetupfee', 'lang' => 'orderpaymenttermmonthly'],
                                    'quarterly' => ['price' => 'quarterly', 'setup' => 'qsetupfee', 'lang' => 'orderpaymenttermquarterly'],
                                    'semiannually' => ['price' => 'semiannually', 'setup' => 'ssetupfee', 'lang' => 'orderpaymenttermsemiannually'],
                                    'annually' => ['price' => 'annually', 'setup' => 'asetupfee', 'lang' => 'orderpaymenttermannually'],
                                    'biennially' => ['price' => 'biennially', 'setup' => 'bsetupfee', 'lang' => 'orderpaymenttermbiennially'],
                                    'triennially' => ['price' => 'triennially', 'setup' => 'tsetupfee', 'lang' => 'orderpaymenttermtriennially'],
                                ];
                                
                                $enabledPrices = [];
                                $enabledSetups = [];
                                
                                foreach ($cyclesKeys as $cycle => $cData) {
                                    $origPrice = (float)($pricing['rawpricing'][$cycle] ?? -1.00);
                                    if ($origPrice >= 0) {
                                        $newPrice = isset($rates[$cData['price']]) && $rates[$cData['price']] !== '' ? (float)$rates[$cData['price']] : null;
                                        $newSetup = isset($rates[$cData['setup']]) && $rates[$cData['setup']] !== '' ? (float)$rates[$cData['setup']] : null;
                                        
                                        if ($newPrice !== null) {
                                            $pricing['rawpricing'][$cycle] = number_format($newPrice, 2, '.', '');
                                        }
                                        if ($newSetup !== null) {
                                            $pricing['rawpricing'][$cData['setup']] = number_format($newSetup, 2, '.', '');
                                        }
                                        
                                        $priceVal = $newPrice !== null ? $newPrice : $origPrice;
                                        $setupVal = $newSetup !== null ? $newSetup : (float)($pricing['rawpricing'][$cData['setup']] ?? 0);
                                        
                                        $cycleString = formatCurrency($priceVal, $currencyId) . ' ' . ($vars['_LANG'][$cData['lang']] ?? '');
                                        if ($setupVal > 0) {
                                            $setupLang = $vars['_LANG']['ordersetupfee'] ?? 'Setup Fee';
                                            $cycleString .= ' + ' . formatCurrency($setupVal, $currencyId) . ' ' . $setupLang;
                                        }
                                        $pricing[$cycle] = $cycleString;
                                        $pricing['cycles'][$cycle] = $cycleString;
                                        
                                        $enabledPrices[$cycle] = $priceVal;
                                        $enabledSetups[$cycle] = $setupVal;
                                    }
                                }
                                
                                if (!empty($enabledPrices)) {
                                    $minPriceVal = -1;
                                    $minSetupVal = 0;
                                    $cycleOrder = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];
                                    foreach ($cycleOrder as $cycle) {
                                        if (isset($enabledPrices[$cycle])) {
                                            $minPriceVal = $enabledPrices[$cycle];
                                            $minSetupVal = $enabledSetups[$cycle] ?? 0;
                                            break;
                                        }
                                    }
                                    
                                    if ($minPriceVal >= 0) {
                                        $currencyObj = \WHMCS\Billing\Currency::find($currencyId);
                                        if ($currencyObj) {
                                            if (!is_array($pricing['minprice'] ?? null)) {
                                                $pricing['minprice'] = [];
                                            }
                                            $pricing['minprice']['price'] = new \WHMCS\View\Formatter\Price($minPriceVal, $currencyObj);
                                            $pricing['minprice']['setupFee'] = new \WHMCS\View\Formatter\Price($minSetupVal, $currencyObj);
                                            
                                            $minCycle = 'monthly';
                                            foreach ($cycleOrder as $cycle) {
                                                if (isset($enabledPrices[$cycle]) && $enabledPrices[$cycle] == $minPriceVal) {
                                                    $minCycle = $cycle;
                                                    break;
                                                }
                                            }
                                            $pricing['minprice']['cycle'] = $minCycle;
                                            $pricing['minprice']['simple'] = formatCurrency($minPriceVal, $currencyId);
                                        }
                                    }
                                }
                            }
                            
                            if ($isObj) {
                                $product->pricing = $pricing;
                            } else {
                                $product['pricing'] = $pricing;
                            }
                        }
                    }

                    $filteredProducts[] = $product;
                }
                $overrides['products'] = $filteredProducts;
            }

            // Filter product groups (categories)
            if (!empty($brandProductIds) && isset($vars['productgroups']) && is_array($vars['productgroups'])) {
                $brandGroupIds = [];
                try {
                    $brandGroupIds = Capsule::table('tblproducts')
                        ->whereIn('id', $brandProductIds)
                        ->pluck('gid')
                        ->unique()
                        ->toArray();
                } catch (\Exception $e) {}

                $filteredGroups = [];
                foreach ($vars['productgroups'] as $group) {
                    $groupId = 0;
                    if (is_array($group)) {
                        $groupId = isset($group['id']) ? (int)$group['id'] : (isset($group['gid']) ? (int)$group['gid'] : 0);
                    } elseif (is_object($group)) {
                        $groupId = isset($group->id) ? (int)$group->id : (isset($group->gid) ? (int)$group->gid : 0);
                    }
                    if ($groupId > 0 && in_array($groupId, $brandGroupIds)) {
                        $filteredGroups[] = $group;
                    }
                }
                $overrides['productgroups'] = $filteredGroups;
            }
        }

        if (isset($GLOBALS['smarty']) && is_object($GLOBALS['smarty']) && method_exists($GLOBALS['smarty'], 'assign')) {
            foreach ($overrides as $key => $value) {
                $GLOBALS['smarty']->assign($key, $value);
            }
        }

        return $overrides;
    } catch (\Throwable $t) {
        // file_put_contents(__DIR__ . '/hook_errors.log', "OrderPage Error: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n", FILE_APPEND);
        return [];
    }
});

/**
 * Invoice PDF Template Hook
 * Dynamically brands the downloaded invoice PDF document
 */
add_hook('InvoicePdfTemplate', 1, function ($vars) {
    $invoiceId = $vars['invoiceid'];
    $brand = null;
    
    try {
        $invBrand = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->first();
        if ($invBrand) {
            $brand = Capsule::table('mod_multibrand_brands')->where('id', $invBrand->brand_id)->where('status', 1)->first();
        }
    } catch (\Exception $e) {}
    
    if (!$brand) {
        $brand = get_multibrand_active_brand();
    }
    
    if ($brand) {
        $overrides = [];
        
        if ($brand->company_name) {
            $overrides['companyname'] = $brand->company_name;
        }
        
        if ($brand->logo_url) {
            $overrides['logo'] = $brand->logo_url;
        }
        
        if ($brand->pay_to_text) {
            $overrides['payto'] = $brand->pay_to_text;
        }
        
        return $overrides;
    }
});

/**
 * Client Area Primary Navbar Hook
 * Appends the Brand Switcher dropdown menu if enabled and client has multiple assigned brands
 */
add_hook('ClientAreaPrimaryNavbar', 1, function ($primaryNavbar) {
    $brand = get_multibrand_active_brand();
    if (!$brand || !$brand->brand_switcher) {
        return;
    }
    
    $loggedInClientId = (int)($_SESSION['uid'] ?? 0);
    if ($loggedInClientId === 0) {
        return;
    }
    
    try {
        $assignedBrands = Capsule::table('mod_multibrand_client_brands')
            ->join('mod_multibrand_brands', 'mod_multibrand_client_brands.brand_id', '=', 'mod_multibrand_brands.id')
            ->where('mod_multibrand_client_brands.client_id', $loggedInClientId)
            ->where('mod_multibrand_brands.status', 1)
            ->select('mod_multibrand_brands.id', 'mod_multibrand_brands.brand_name', 'mod_multibrand_brands.system_url', 'mod_multibrand_brands.domain')
            ->get();
            
        if ($assignedBrands->count() > 1) {
            // Add "Brands" dropdown menu item
            $brandsMenu = $primaryNavbar->addChild('Brands', [
                'label' => 'Brands',
                'uri' => '#',
                'order' => 90, // Places it next to account settings
            ]);
            
            foreach ($assignedBrands as $ab) {
                // Determine switcher URL
                $requestHost = strtolower($_SERVER['SERVER_NAME'] ?? '');
                $requestHostClean = ltrim($requestHost, 'www.');
                
                $brandHostClean = '';
                if ($ab->domain) {
                    $brandHost = strtolower(parse_url($ab->system_url ?: 'http://' . $ab->domain, PHP_URL_HOST));
                    $brandHostClean = ltrim($brandHost, 'www.');
                }
                
                if (empty($brandHostClean) || $brandHostClean === $requestHostClean) {
                    // Same domain: switch context via query parameter on current script
                    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? 'clientarea.php');
                    if ($scriptName === 'viewinvoice.php') {
                        $scriptName = 'clientarea.php';
                    }
                    $url = $scriptName . '?brand_switch=' . $ab->id;
                } else {
                    // Different domain: redirect directly to the brand's home domain
                    $url = $ab->system_url ?: ($ab->domain ? 'http://' . $ab->domain : '#');
                }
                
                $brandsMenu->addChild($ab->brand_name, [
                    'label' => $ab->brand_name,
                    'uri' => $url,
                    'order' => 10,
                ]);
            }
        }
    } catch (\Exception $e) {}
});

/**
 * Client Logout Hook
 * Clears active brand context overrides on logout
 */
add_hook('ClientLogout', 1, function ($vars) {
    unset($_SESSION['multibrand_brand_id']);
});

/**
 * Client Area Footer Output Hook
 * Handles department filtering inside Open Support Ticket page dynamically
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $brand = get_multibrand_active_brand();
    // print_r($filename);
    // print_r($brand);die();
    if ($brand && $brand->ticket_departments) {
        $brandDepts = array_values(array_filter(array_map('intval', explode(',', $brand->ticket_departments))));
        $filename = $vars['filename'] ?? '';
        
        
        if (in_array($filename, ['supporttickets', 'submitticket']) && !empty($brandDepts)) {
            $jsonDepts = json_encode($brandDepts);
            return "
            <script>
                $(document).ready(function() {
                    var brandDepts = $jsonDepts;
                    
                    // 1. Hide non-assigned departments in Step 1 (selection grids/links)
                    $('a').each(function() {
                        var href = $(this).attr('href') || '';
                        var match = href.match(/deptid=(\d+)/);
                        if (match) {
                            var deptId = parseInt(match[1], 10);
                            if ($.inArray(deptId, brandDepts) === -1) {
                                var parentElement = $(this).closest('.panel, .list-group-item, .dept-box, .btn, tr, .col-md-6, .col-sm-6, .margin-bottom');
                                if (parentElement.length) {
                                    parentElement.hide();
                                } else {
                                    // Fallback for paragraph list styles (e.g. Twenty-One)
                                    var pParent = $(this).closest('p');
                                    if (pParent.length) {
                                        if (pParent.next('p.text-muted').length) {
                                            pParent.next('p.text-muted').hide();
                                        }
                                        pParent.hide();
                                    }
                                }
                            }
                        }
                    });
                    
                    // 2. Remove non-assigned departments from Dropdown list in Step 2 (open ticket form)
                    var select = $('select[name=\"deptid\"]');
                    if (select.length) {
                        select.find('option').each(function() {
                            var val = parseInt($(this).val(), 10);
                            if (val > 0 && $.inArray(val, brandDepts) === -1) {
                                $(this).remove();
                            }
                        });
                    }
                });
            </script>
            ";
        }
    }
});

/**
 * Safe Multi-Brand Outgoing Email & Configuration Override Manager
 */
if (!class_exists('MultiBrandEmailOverrideManager')) {
    class MultiBrandEmailOverrideManager
    {
        
        private static $backup = null;
        private static $isShutdownRegistered = false;
        public static function apply($brand)
        {
            if (self::$backup !== null) {
                return;
            }
            $configKeys = [
                'MailType',
                'SMTPHost',
                'SMTPPort',
                'SMTPUsername',
                'SMTPPassword',
                'SMTPSSL',
                'DisableEmailSending',
                'EmailCSS',
                'EmailGlobalHeader',
                'EmailGlobalFooter',
                'SystemEmailsFromEmail',
                'SystemEmailsFromName'
            ];

            // 1. Backup original configuration
            self::$backup = [];
            foreach ($configKeys as $key) {
                $row = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', $key)->first();
                self::$backup[$key] = $row ? $row->value : null;
            }

            // 2. Prepare overrides
            $overrides = [];

            // SMTP overrides
            $smtp = json_decode(htmlspecialchars_decode($brand->smtp_settings ?: '{}'), true);
            if (!empty($smtp['override'])) {
                $overrides['MailType'] = !empty($smtp['mail_type']) ? $smtp['mail_type'] : 'SMTP';
                $overrides['SMTPHost'] = !empty($smtp['hostname']) ? $smtp['hostname'] : '';
                $overrides['SMTPPort'] = !empty($smtp['port']) ? $smtp['port'] : '';
                $overrides['SMTPUsername'] = !empty($smtp['username']) ? $smtp['username'] : '';
                if (isset($smtp['password']) && $smtp['password'] !== '') {
                    $overrides['SMTPPassword'] = encrypt($smtp['password']);
                }
                $overrides['SMTPSSL'] = !empty($smtp['ssl_type']) ? $smtp['ssl_type'] : '';
                
                if (!empty($smtp['disable_email'])) {
                    $overrides['DisableEmailSending'] = 'on';
                } else {
                    $overrides['DisableEmailSending'] = '';
                }
            }

            // CSS/Header/Footer template overrides
            $email_templates = json_decode(htmlspecialchars_decode($brand->email_template_settings ?: '{}'), true);
            if (!empty($email_templates['css'])) {
                $overrides['EmailCSS'] = $email_templates['css'];
            }
            if (!empty($email_templates['header'])) {
                $overrides['EmailGlobalHeader'] = $email_templates['header'];
            }
            if (!empty($email_templates['footer'])) {
                $overrides['EmailGlobalFooter'] = $email_templates['footer'];
            }

            // From Name and From Email overrides
            if (!empty($brand->email_address)) {
                $overrides['SystemEmailsFromEmail'] = $brand->email_address;
            }
            if (!empty($brand->company_name)) {
                $overrides['SystemEmailsFromName'] = $brand->company_name;
            }
            // print_r($overrides);die();
            // 3. Apply overrides
            foreach ($overrides as $key => $val) {
                \WHMCS\Database\Capsule::table('tblconfiguration')->updateOrInsert(['setting' => $key], ['value' => $val]);
                if (method_exists('WHMCS\\Config\\Setting', 'updateRuntimeConfigCache')) {
                    \WHMCS\Config\Setting::updateRuntimeConfigCache($key, $val);
                }
            }

            // 4. Shutdown fallback restorer
            if (!self::$isShutdownRegistered) {
                register_shutdown_function([self::class, 'restore']);
                self::$isShutdownRegistered = true;
            }
        }

        public static function restore()
        {
            if (self::$backup === null) {
                return;
            }

            // Restore original settings
            foreach (self::$backup as $key => $val) {
                if ($val === null) {
                    \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', $key)->delete();
                } else {
                    \WHMCS\Database\Capsule::table('tblconfiguration')->updateOrInsert(['setting' => $key], ['value' => $val]);
                }
                if (method_exists('WHMCS\\Config\\Setting', 'updateRuntimeConfigCache')) {
                    \WHMCS\Config\Setting::updateRuntimeConfigCache($key, $val === null ? '' : $val);
                }
            }

            self::$backup = null;
        }
    }
}

/**
 * Email Pre Send Hook
 * Overrides brand SMTP server credentials, custom templates, and styling details in outgoing emails
 */
add_hook('EmailPreSend', 1, function ($vars) {
    $brand = null;
    $resolvedBrandId = 0;

    // 1. Try to find brand by related entity in $vars['relid']
    $relid = isset($vars['relid']) ? (int)$vars['relid'] : 0;
    $messagename = isset($vars['messagename']) ? $vars['messagename'] : '';

    if ($relid > 0) {
        // Invoice-related emails
        if (stripos($messagename, 'Invoice') !== false || stripos($messagename, 'Payment') !== false || stripos($messagename, 'Credit') !== false) {
            try {
                $invBrand = \WHMCS\Database\Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $relid)->first();
                if ($invBrand) {
                    $resolvedBrandId = $invBrand->brand_id;
                } else {
                    $invoice = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $relid)->first();
                    if ($invoice) {
                        $clientBrand = \WHMCS\Database\Capsule::table('mod_multibrand_client_brands')->where('client_id', $invoice->userid)->first();
                        if ($clientBrand) {
                            $resolvedBrandId = $clientBrand->brand_id;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }
        // Support/Ticket-related emails
        elseif (stripos($messagename, 'Ticket') !== false || stripos($messagename, 'Support') !== false) {
            try {
                $ticketBrand = \WHMCS\Database\Capsule::table('mod_multibrand_ticket_brands')->where('ticket_id', $relid)->first();
                if ($ticketBrand) {
                    $resolvedBrandId = $ticketBrand->brand_id;
                } else {
                    $ticket = \WHMCS\Database\Capsule::table('tbltickets')->where('id', $relid)->first();
                    if ($ticket) {
                        $clientBrand = \WHMCS\Database\Capsule::table('mod_multibrand_client_brands')->where('client_id', $ticket->userid)->first();
                        if ($clientBrand) {
                            $resolvedBrandId = $clientBrand->brand_id;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }
        // Service/Product emails
        elseif (stripos($messagename, 'Welcome') !== false || stripos($messagename, 'Service') !== false || stripos($messagename, 'Hosting') !== false || stripos($messagename, 'Product') !== false || stripos($messagename, 'Suspension') !== false || stripos($messagename, 'Termination') !== false) {
            try {
                $serviceBrand = \WHMCS\Database\Capsule::table('mod_multibrand_service_brands')->where('service_id', $relid)->first();
                if ($serviceBrand) {
                    $resolvedBrandId = $serviceBrand->brand_id;
                } else {
                    $service = \WHMCS\Database\Capsule::table('tblhosting')->where('id', $relid)->first();
                    if ($service) {
                        $clientBrand = \WHMCS\Database\Capsule::table('mod_multibrand_client_brands')->where('client_id', $service->userid)->first();
                        if ($clientBrand) {
                            $resolvedBrandId = $clientBrand->brand_id;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }
        // Fallback: If $relid is a client ID directly
        if ($resolvedBrandId === 0) {
            try {
                $clientExists = \WHMCS\Database\Capsule::table('tblclients')->where('id', $relid)->exists();
                if ($clientExists) {
                    $clientBrand = \WHMCS\Database\Capsule::table('mod_multibrand_client_brands')->where('client_id', $relid)->first();
                    if ($clientBrand) {
                        $resolvedBrandId = $clientBrand->brand_id;
                    }
                }
            } catch (\Exception $e) {}
        }
    }

    // 2. If not resolved yet, check client ID from mergefields
    if ($resolvedBrandId === 0 && isset($vars['mergefields'])) {
        $clientIdsKeys = ['client_id', 'userid', 'clientid', 'id'];
        foreach ($clientIdsKeys as $ck) {
            if (isset($vars['mergefields'][$ck]) && is_numeric($vars['mergefields'][$ck])) {
                $cid = (int)$vars['mergefields'][$ck];
                try {
                    $clientExists = \WHMCS\Database\Capsule::table('tblclients')->where('id', $cid)->exists();
                    if ($clientExists) {
                        $clientBrand = \WHMCS\Database\Capsule::table('mod_multibrand_client_brands')->where('client_id', $cid)->first();
                        if ($clientBrand) {
                            $resolvedBrandId = $clientBrand->brand_id;
                            break;
                        }
                    }
                } catch (\Exception $e) {}
            }
        }
    }

    // 3. Look up brand details if brand ID is resolved
    if ($resolvedBrandId > 0) {
        try {
            $brand = \WHMCS\Database\Capsule::table('mod_multibrand_brands')->where('id', $resolvedBrandId)->where('status', 1)->first();
        } catch (\Exception $e) {}
    }

    // 4. Fallback to active domain domain-match brand
    if (!$brand) {
        $brand = get_multibrand_active_brand();
    }

    if ($brand) {
        // Apply configuration overrides temporarily in tblconfiguration
        MultiBrandEmailOverrideManager::apply($brand);

        // Populate custom merge fields for email headers/footers
        $merge_fields = [];
        $merge_fields['company_name'] = !empty($brand->company_name) ? $brand->company_name : '';
        $merge_fields['company_domain'] = !empty($brand->domain) ? $brand->domain : '';
        $merge_fields['company_logo_url'] = !empty($brand->logo_url) ? $brand->logo_url : '';
        $merge_fields['whmcs_url'] = !empty($brand->system_url) ? $brand->system_url : '';
        $merge_fields['whmcs_link'] = !empty($brand->system_url) ? '<a href="' . $brand->system_url . '">' . (!empty($brand->company_name) ? $brand->company_name : $brand->brand_name) . '</a>' : '';
        $merge_fields['signature'] = !empty($brand->signature) ? nl2br($brand->signature) : '';
        $merge_fields['date'] = date('Y-m-d');
        $merge_fields['time'] = date('H:i:s');

        // Look up custom template overrides
        $brandedTemplate = null;
        try {
            $brandedTemplate = \WHMCS\Database\Capsule::table('mod_multibrand_email_templates')
                ->where('brand_id', $brand->id)
                ->where('template_name', $messagename)
                ->where('status', 1)
                ->first();
        } catch (\Exception $e) {}

        $smtp = json_decode(htmlspecialchars_decode($brand->smtp_settings ?: '{}'), true);
        $hasSmtpOverride = !empty($smtp['override']);

        if ($brandedTemplate || $hasSmtpOverride) {
            $get_protected_property = function ($obj, $propName) {
                try {
                    $ref = new \ReflectionClass($obj);
                    if ($ref->hasProperty($propName)) {
                        $prop = $ref->getProperty($propName);
                        $prop->setAccessible(true);
                        return $prop->getValue($obj);
                    }
                } catch (\Exception $e) {}
                return null;
            };

            $render_email_template = function ($templateString, $mergeFields) {
                try {
                    $smarty = new \Smarty();
                    if (defined('ROOTDIR')) {
                        $smarty->setCompileDir(ROOTDIR . '/templates_c');
                        $smarty->setCacheDir(ROOTDIR . '/templates_c');
                    } else {
                        $smarty->setCompileDir(__DIR__ . '/../../../templates_c');
                        $smarty->setCacheDir(__DIR__ . '/../../../templates_c');
                    }
                    $smarty->assign($mergeFields);
                    return $smarty->fetch('string:' . $templateString);
                } catch (\Exception $e) {
                    return $templateString;
                }
            };

            // Find WHMCS Mailer/Emailer object in backtrace
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
            $mailEntity = null;
            foreach ($trace as $step) {
                if (isset($step['object']) && (stripos(get_class($step['object']), 'Mail') !== false || is_a($step['object'], '\\WHMCS\\Mail\\Emailer') || is_subclass_of($step['object'], '\\WHMCS\\Mail\\Emailer'))) {
                    $mailEntity = $step['object'];
                    break;
                }
            }

            if ($mailEntity) {
                $messageObj = $get_protected_property($mailEntity, 'message');
                if ($messageObj) {
                    // Extract fields
                    $to = $get_protected_property($messageObj, 'to') ?: [];
                    $cc = $get_protected_property($messageObj, 'cc') ?: [];
                    $bcc = $get_protected_property($messageObj, 'bcc') ?: [];
                    $replyTo = $get_protected_property($messageObj, 'replyTo') ?: [];
                    $attachments = $get_protected_property($messageObj, 'attachments') ?: [];
                    $headers = $get_protected_property($messageObj, 'headers') ?: [];

                    $defaultSubject = $get_protected_property($messageObj, 'subject') ?: '';
                    $defaultBody = $get_protected_property($messageObj, 'body') ?: '';

                    // Determine language
                    $clientLang = '';
                    $clientId = (int)$get_protected_property($mailEntity, 'recipientClientId');
                    if ($clientId > 0) {
                        try {
                            $client = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->first();
                            if ($client) {
                                $clientLang = strtolower($client->language);
                            }
                        } catch (\Exception $e) {}
                    }
                    if (empty($clientLang) && !empty($brand->default_language)) {
                        $clientLang = strtolower($brand->default_language);
                    }

                    $subject = '';
                    $body = '';
                    if ($brandedTemplate) {
                        $translations = json_decode(htmlspecialchars_decode($brandedTemplate->translations ?: '{}'), true) ?: [];
                        if (!empty($clientLang) && isset($translations[$clientLang])) {
                            $subject = $translations[$clientLang]['subject'];
                            $body = $translations[$clientLang]['message'];
                        }
                        if (empty($subject) && isset($translations['default'])) {
                            $subject = $translations['default']['subject'];
                            $body = $translations['default']['message'];
                        }
                    }

                    if (empty($subject)) {
                        $subject = $defaultSubject;
                    }
                    if (empty($body)) {
                        $body = $defaultBody;
                    }

                    // Render styling elements
                    $email_templates = json_decode(htmlspecialchars_decode($brand->email_template_settings ?: '{}'), true);
                    $css = !empty($email_templates['css']) ? $email_templates['css'] : '';
                    $header = !empty($email_templates['header']) ? $email_templates['header'] : '';
                    $footer = !empty($email_templates['footer']) ? $email_templates['footer'] : '';

                    if (empty($css)) {
                        try {
                            $cssRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'EmailCSS')->first();
                            $css = $cssRow ? $cssRow->value : '';
                        } catch (\Exception $e) {}
                    }
                    if (empty($header)) {
                        try {
                            $headerRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'EmailGlobalHeader')->first();
                            $header = $headerRow ? $headerRow->value : '';
                        } catch (\Exception $e) {}
                    }
                    if (empty($footer)) {
                        try {
                            $footerRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'EmailGlobalFooter')->first();
                            $footer = $footerRow ? $footerRow->value : '';
                        } catch (\Exception $e) {}
                    }

                    // Compile template
                    $allMergeFields = array_merge($vars['mergefields'] ?: [], $merge_fields);
                    $allMergeFields['email_css'] = $css;

                    $compiledSubject = $render_email_template($subject, $allMergeFields);
                    $compiledBody = $render_email_template($body, $allMergeFields);
                    $compiledHeader = $render_email_template($header, $allMergeFields);
                    $compiledFooter = $render_email_template($footer, $allMergeFields);

                    $finalHtmlBody = $compiledHeader . "\n" . $compiledBody . "\n" . $compiledFooter;
                    $compiledPlainBody = strip_tags($compiledBody);

                    // Determine SMTP settings
                    if ($hasSmtpOverride) {
                        $smtp_host = !empty($smtp['hostname']) ? $smtp['hostname'] : '';
                        $smtp_port = !empty($smtp['port']) ? $smtp['port'] : '587';
                        $smtp_user = !empty($smtp['username']) ? $smtp['username'] : '';
                        $smtp_pass = isset($smtp['password']) ? $smtp['password'] : '';
                        $smtp_secure = !empty($smtp['ssl_type']) ? strtolower($smtp['ssl_type']) : '';
                        $smtp_mail_type = !empty($smtp['mail_type']) ? $smtp['mail_type'] : 'SMTP';
                    } else {
                        // Load system config
                        try {
                            $mailConfigRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'MailConfig')->first();
                            if ($mailConfigRow && $mailConfigRow->value) {
                                $mailConfig = json_decode(decrypt($mailConfigRow->value), true);
                                $smtp_host = isset($mailConfig['configuration']['host']) ? $mailConfig['configuration']['host'] : '';
                                $smtp_port = isset($mailConfig['configuration']['port']) ? $mailConfig['configuration']['port'] : '';
                                $smtp_user = isset($mailConfig['configuration']['username']) ? $mailConfig['configuration']['username'] : '';
                                $smtp_pass = isset($mailConfig['configuration']['password']) ? $mailConfig['configuration']['password'] : '';
                                $smtp_secure = isset($mailConfig['configuration']['secure']) ? strtolower($mailConfig['configuration']['secure']) : '';
                                $smtp_mail_type = (isset($mailConfig['module']) && $mailConfig['module'] === 'SmtpMail') ? 'SMTP' : 'mail';
                            } else {
                                $smtp_host = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SMTPHost')->value('value') ?: '';
                                $smtp_port = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SMTPPort')->value('value') ?: '';
                                $smtp_user = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SMTPUsername')->value('value') ?: '';
                                $smtp_pass = decrypt(\WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SMTPPassword')->value('value') ?: '');
                                $smtp_secure = strtolower(\WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SMTPSSL')->value('value') ?: '');
                                $smtp_mail_type = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'MailType')->value('value') ?: 'mail';
                            }
                        } catch (\Exception $e) {
                            $smtp_host = ''; $smtp_port = '587'; $smtp_user = ''; $smtp_pass = ''; $smtp_secure = ''; $smtp_mail_type = 'mail';
                        }
                    }

                    // Send using PHPMailer
                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->CharSet = 'UTF-8';

                        if (strtolower($smtp_mail_type) === 'smtp') {
                            $mail->isSMTP();
                            $mail->Host = $smtp_host;
                            $mail->Port = $smtp_port;
                            $mail->SMTPAuth = !empty($smtp_user);
                            $mail->Username = $smtp_user;
                            $mail->Password = $smtp_pass;
                            if ($smtp_secure === 'ssl' || $smtp_secure === 'tls') {
                                $mail->SMTPSecure = $smtp_secure;
                            } else {
                                $mail->SMTPSecure = '';
                                $mail->SMTPAutoTLS = false;
                            }
                        } else {
                            $mail->isMail();
                        }

                        $fromEmail = !empty($brand->email_address) ? $brand->email_address : '';
                        $fromName = !empty($brand->company_name) ? $brand->company_name : $brand->brand_name;

                        if (empty($fromEmail)) {
                            try {
                                $fromEmailRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SystemEmailsFromEmail')->first();
                                $fromEmail = $fromEmailRow ? $fromEmailRow->value : 'noreply@yourdomain.com';
                            } catch (\Exception $e) {}
                        }
                        if (empty($fromName)) {
                            try {
                                $fromNameRow = \WHMCS\Database\Capsule::table('tblconfiguration')->where('setting', 'SystemEmailsFromName')->first();
                                $fromName = $fromNameRow ? $fromNameRow->value : 'System';
                            } catch (\Exception $e) {}
                        }

                        $mail->setFrom($fromEmail, $fromName);

                        if (!empty($replyTo)) {
                            foreach ($replyTo as $rEmail => $rDetails) {
                                if (!empty($rEmail)) {
                                    $mail->addReplyTo($rEmail, isset($rDetails[1]) ? $rDetails[1] : '');
                                }
                            }
                        } else {
                            $mail->addReplyTo($fromEmail, $fromName);
                        }

                        if (!empty($to)) {
                            foreach ($to as $tEmail => $tDetails) {
                                if (!empty($tEmail)) {
                                    $mail->addAddress($tEmail, isset($tDetails[1]) ? $tDetails[1] : '');
                                }
                            }
                        }

                        if (!empty($cc)) {
                            foreach ($cc as $cEmail => $cDetails) {
                                if (!empty($cEmail)) {
                                    $mail->addCC($cEmail, isset($cDetails[1]) ? $cDetails[1] : '');
                                }
                            }
                        }
                        if ($brandedTemplate && !empty($brandedTemplate->copy_to)) {
                            $ccEmails = array_map('trim', explode(',', $brandedTemplate->copy_to));
                            foreach ($ccEmails as $cEmail) {
                                if (!empty($cEmail)) {
                                    $mail->addCC($cEmail);
                                }
                            }
                        }

                        if (!empty($bcc)) {
                            foreach ($bcc as $bEmail => $bDetails) {
                                if (!empty($bEmail)) {
                                    $mail->addBCC($bEmail, isset($bDetails[1]) ? $bDetails[1] : '');
                                }
                            }
                        }
                        if ($brandedTemplate && !empty($brandedTemplate->blind_copy_to)) {
                            $bccEmails = array_map('trim', explode(',', $brandedTemplate->blind_copy_to));
                            foreach ($bccEmails as $bEmail) {
                                if (!empty($bEmail)) {
                                    $mail->addBCC($bEmail);
                                }
                            }
                        }

                        if (!empty($attachments)) {
                            foreach ($attachments as $attachment) {
                                if (is_array($attachment)) {
                                    $path = isset($attachment['path']) ? $attachment['path'] : (isset($attachment[0]) ? $attachment[0] : '');
                                    $name = isset($attachment['filename']) ? $attachment['filename'] : (isset($attachment[1]) ? $attachment[1] : '');
                                    if (file_exists($path)) {
                                        $mail->addAttachment($path, $name);
                                    }
                                } elseif (is_string($attachment) && file_exists($attachment)) {
                                    $mail->addAttachment($attachment);
                                }
                            }
                        }

                        $mail->Subject = $compiledSubject;
                        $mail->isHTML(true);
                        $mail->Body = $finalHtmlBody;
                        $mail->AltBody = $compiledPlainBody;

                        $mail->send();

                        // Log email success in tblemails
                        try {
                            $ccStr = '';
                            if (!empty($cc)) {
                                $ccStr = implode(',', array_keys($cc));
                            }
                            $bccStr = '';
                            if (!empty($bcc)) {
                                $bccStr = implode(',', array_keys($bcc));
                            }
                            $toStr = '';
                            if (!empty($to)) {
                                $toStr = implode(',', array_keys($to));
                            }

                            \WHMCS\Database\Capsule::table('tblemails')->insert([
                                'userid' => $clientId,
                                'subject' => $compiledSubject,
                                'message' => $finalHtmlBody,
                                'date' => date('Y-m-d H:i:s'),
                                'to' => $toStr,
                                'cc' => $ccStr,
                                'bcc' => $bccStr,
                            ]);
                        } catch (\Exception $dbEx) {}

                        $clientName = '';
                        if (isset($allMergeFields['client_name'])) {
                            $clientName = $allMergeFields['client_name'];
                        } elseif ($clientId > 0) {
                            try {
                                $client = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->first();
                                if ($client) {
                                    $clientName = $client->firstname . ' ' . $client->lastname;
                                }
                            } catch (\Exception $e) {}
                        }

                        if (function_exists('logActivity')) {
                            logActivity("Email Sent to " . $clientName . " (" . $compiledSubject . ") - Client ID: " . $clientId);
                        }

                        // Restore settings
                        MultiBrandEmailOverrideManager::restore();

                        // Abort WHMCS default sending
                        return ['abortsend' => true];

                    } catch (\Exception $mailEx) {
                        if (function_exists('logActivity')) {
                            logActivity("Email Sending Failed - SMTP Error: " . $mailEx->getMessage() . " (Subject: " . $compiledSubject . ")");
                        }
                    }
                }
            }
        }

        // Rest of the normal template lookup for merge fields (if not intercepted)
        $overrides = [];
        try {
            if ($brandedTemplate) {
                // Resolve client's language
                $clientLang = '';
                $clientId = 0;

                if (isset($vars['mergefields']['client_id'])) {
                    $clientId = (int)$vars['mergefields']['client_id'];
                } elseif (isset($vars['mergefields']['userid'])) {
                    $clientId = (int)$vars['mergefields']['userid'];
                } elseif (isset($vars['mergefields']['clientid'])) {
                    $clientId = (int)$vars['mergefields']['clientid'];
                }

                if ($clientId > 0) {
                    $client = \WHMCS\Database\Capsule::table('tblclients')->where('id', $clientId)->first();
                    if ($client) {
                        $clientLang = strtolower($client->language);
                    }
                }

                if (empty($clientLang) && !empty($brand->default_language)) {
                    $clientLang = strtolower($brand->default_language);
                }

                $translations = json_decode(htmlspecialchars_decode($brandedTemplate->translations ?: '{}'), true) ?: [];

                $subject = '';
                $message = '';

                if (!empty($clientLang) && isset($translations[$clientLang])) {
                    $subject = $translations[$clientLang]['subject'];
                    $message = $translations[$clientLang]['message'];
                }

                if (empty($subject) && isset($translations['default'])) {
                    $subject = $translations['default']['subject'];
                    $message = $translations['default']['message'];
                }

                if (!empty($subject)) {
                    $overrides['subject'] = $subject;
                }
                if (!empty($message)) {
                    $overrides['message'] = $message;
                }
                if (!empty($brandedTemplate->copy_to)) {
                    $overrides['cc'] = $brandedTemplate->copy_to;
                }
                if (!empty($brandedTemplate->blind_copy_to)) {
                    $overrides['bcc'] = $brandedTemplate->blind_copy_to;
                }
            }
        } catch (\Exception $e) {}

        $returnVars = ['mergefields' => $merge_fields];
        if (!empty($overrides)) {
            $returnVars = array_merge($returnVars, $overrides);
        }
        return $returnVars;
    }
});

/**
 * Email Post Send Hook
 * Restores the original WHMCS configuration immediately after sending
 */
add_hook('EmailPostSend', 1, function ($vars) {
    MultiBrandEmailOverrideManager::restore();
});

/**
 * Override Config Hook
 * Dynamically changes WHMCS configuration settings
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    $brand = get_multibrand_active_brand();

    if ($brand) {
        $html = '<!-- Multi Brand Applied: ' . htmlspecialchars($brand->brand_name) . ' -->';
        
        // Inject brand custom color and logo stylesheet dynamically
        // if ($brand->brand_color) {
        //     $color = htmlspecialchars($brand->brand_color);
        //     $html .= "
        //     <style>
        //         /* Root CSS Custom Properties override for modern templates (e.g. Twenty One) */
        //         :root {
        //             --primary-color: $color !important;
        //             --primary: $color !important;
        //             --primary-bg-color: $color !important;
        //             --brand-color: $color !important;
        //         }
        //         /* Auto-injected Premium Brand Colors */
        //         .navbar-main, .navbar-default, .primary-nav, .brand-color-bg, .navbar-main .navbar-nav > li > a:hover {
        //             background-color: $color !important;
        //             border-color: $color !important;
        //         }
        //         .btn-primary, .btn-primary:hover, .btn-primary:focus, .btn-primary:active, .btn-primary.active, .btn-info, .btn-info:hover {
        //             background-color: $color !important;
        //             border-color: $color !important;
        //         }
        //         a, a:hover, a:focus, .text-primary, .navbar-main .navbar-nav > .active > a, .navbar-main .navbar-nav > .active > a:hover {
        //             color: $color !important;
        //         }
        //         .sidebar .panel-header, .panel-primary > .panel-heading, .panel-primary, .panel-sidebar > .panel-heading, .list-group-item.active, .list-group-item.active:hover, .list-group-item.active:focus {
        //             background-color: $color !important;
        //             border-color: $color !important;
        //         }
        //         /* Dynamic visual accents */
        //         .label-primary, .badge-primary, .badge-info, .label-info {
        //             background-color: $color !important;
        //         }
        //     </style>
        //     ";
        // }
        
        if ($brand->logo_url) {
            $logo = htmlspecialchars($brand->logo_url);
            $html .= "
            <style>
                .navbar-brand img, .logo img, #header .logo img, #logo img, .logo-img {
                    content: url('$logo') !important;
                }
            </style>
            <script>
                $(document).ready(function() {
                    $('.navbar-brand img, .logo img, #header .logo img, #logo img, .logo-img').each(function() {
                        $(this).attr('src', '$logo');
                    });
                });
            </script>
            ";
        }
        
        return $html;
    }
});

/**
 * Client Add Hook
 * Records which brand/domain the client registered through
 */
add_hook('ClientAdd', 1, function ($vars) {
    $userid = isset($vars['userid']) ? $vars['userid'] : (isset($vars['user_id']) ? $vars['user_id'] : 0);
    $brand = get_multibrand_brand_by_domain();

    if ($brand && $userid) {
        try {
            $exists = Capsule::table('mod_multibrand_client_brands')
                ->where('client_id', $userid)
                ->where('brand_id', $brand->id)
                ->exists();
            if (!$exists) {
                Capsule::table('mod_multibrand_client_brands')->insert([
                    'client_id' => $userid,
                    'brand_id' => $brand->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }
});

/**
 * Client Login Hook
 * Updates/Saves client brand on login based on domain
 */
add_hook('UserLogin', 1, function ($vars) {
    $userid = isset($vars['user']['id']) ? $vars['user']['id'] : (isset($vars['user']['id']) ? $vars['user']['id'] : 0);
    $brand = get_multibrand_brand_by_domain();
    if ($brand && $userid && $brand->auto_client_assignment) {
        try {
            $exists = Capsule::table('mod_multibrand_client_brands')
                ->where('client_id', $userid)
                ->where('brand_id', $brand->id)
                ->exists();
            if (!$exists) {
                Capsule::table('mod_multibrand_client_brands')->insert([
                    'client_id' => $userid,
                    'brand_id' => $brand->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }
});

/**
 * Admin Area Client Summary Page Hook
 * Displays the brand name and color near the email address
 */
add_hook('AdminAreaClientSummaryPage', 1, function ($vars) {
    $userid = $vars['userid'];

    // Get all client brands
    $clientBrands = Capsule::table('mod_multibrand_client_brands')
        ->join('mod_multibrand_brands', 'mod_multibrand_client_brands.brand_id', '=', 'mod_multibrand_brands.id')
        ->where('mod_multibrand_client_brands.client_id', $userid)
        ->select('mod_multibrand_brands.brand_name', 'mod_multibrand_brands.brand_color')
        ->get();

    $tagsHtml = '';
    if ($clientBrands->count() > 0) {
        foreach ($clientBrands as $cb) {
            $name = htmlspecialchars($cb->brand_name);
            $color = htmlspecialchars($cb->brand_color ?: '#666');
            $tagsHtml .= " <span class='label' style='background-color: $color; color: #fff; font-size: 0.85em; padding: 2px 8px; border-radius: 4px; vertical-align: middle; margin-left: 5px; font-weight: bold;'>$name</span>";
        }
    }

    // Fetch all hosting service brand mappings for this client
    $serviceBrandsMap = [];
    try {
        $clientServiceIds = Capsule::table('tblhosting')
            ->where('userid', $userid)
            ->pluck('id')
            ->toArray();
            
        if (!empty($clientServiceIds)) {
            $sBrands = Capsule::table('mod_multibrand_service_brands')
                ->join('mod_multibrand_brands', 'mod_multibrand_service_brands.brand_id', '=', 'mod_multibrand_brands.id')
                ->whereIn('mod_multibrand_service_brands.service_id', $clientServiceIds)
                ->select('mod_multibrand_service_brands.service_id', 'mod_multibrand_brands.brand_name', 'mod_multibrand_brands.brand_color')
                ->get();
                
            foreach ($sBrands as $sb) {
                $serviceBrandsMap[$sb->service_id] = [
                    'brand_name' => $sb->brand_name,
                    'brand_color' => $sb->brand_color ?: '#666'
                ];
            }
        }
    } catch (\Exception $e) {}

    // Fetch all invoice brand mappings for this client
    $invoiceBrandsMap = [];
    try {
        $clientInvoiceIds = Capsule::table('tblinvoices')
            ->where('userid', $userid)
            ->pluck('id')
            ->toArray();
            
        if (!empty($clientInvoiceIds)) {
            $iBrands = Capsule::table('mod_multibrand_invoice_brands')
                ->join('mod_multibrand_brands', 'mod_multibrand_invoice_brands.brand_id', '=', 'mod_multibrand_brands.id')
                ->whereIn('mod_multibrand_invoice_brands.invoice_id', $clientInvoiceIds)
                ->select('mod_multibrand_invoice_brands.invoice_id', 'mod_multibrand_brands.brand_name', 'mod_multibrand_brands.brand_color')
                ->get();
                
            foreach ($iBrands as $ib) {
                $invoiceBrandsMap[$ib->invoice_id] = [
                    'brand_name' => $ib->brand_name,
                    'brand_color' => $ib->brand_color ?: '#666'
                ];
            }
        }
    } catch (\Exception $e) {}

    return "
    <script>
        $(document).ready(function() {
            var brandTags = \"" . addslashes($tagsHtml) . "\";
            
            // Find email link or email td cell to place badge inside the cell
            var emailLink = $('a[href^=\"mailto:\"]');
            if (emailLink.length) {
                if (!emailLink.next('.mb-brand-badge').length) {
                    emailLink.after('<span class=\"mb-brand-badge\">' + brandTags + '</span>');
                }
            } else {
                var emailTd = $('td:contains(\"@\")').first();
                if (emailTd.length) {
                    if (!emailTd.find('.mb-brand-badge').length) {
                        emailTd.append('<span class=\"mb-brand-badge\">' + brandTags + '</span>');
                    }
                }
            }
            
            // Service & Invoice Brand mappings
            var serviceBrands = " . json_encode($serviceBrandsMap) . ";
            var invoiceBrands = " . json_encode($invoiceBrandsMap) . ";
            
            function decorateClientSummaryTables() {
                $('table').each(function() {
                    var table = $(this);
                    
                    // 1. PRODUCTS / SERVICES TABLE DECORATION
                    var productCol = table.find('th:contains(\"Product/Service\")');
                    if (productCol.length > 0) {
                        var idColIndex = -1;
                        
                        // Find ID column index
                        table.find('th').each(function(index) {
                            var text = $(this).text().trim();
                            if (text === 'ID') {
                                idColIndex = index;
                            }
                        });
                        
                        if (idColIndex !== -1) {
                            // Add \"Brand\" header to each header row if not present
                            table.find('thead tr, tr:first').each(function() {
                                var headerRow = $(this);
                                if (headerRow.find('th').length > 0) {
                                    var brandHeader = headerRow.find('th:contains(\"Brand\")');
                                    if (brandHeader.length === 0) {
                                        var lastTh = headerRow.find('th').last();
                                        lastTh.after('<th class=\"text-center mb-brand-header\" style=\"font-weight: bold;\">Brand</th>');
                                    }
                                }
                            });
                            
                            // Loop over each row in tbody
                            table.find('tbody tr').each(function() {
                                var row = $(this);
                                
                                // Prevent duplicate processing of the same row
                                if (row.hasClass('mb-processed-row')) {
                                    return;
                                }
                                
                                var cells = row.find('td');
                                
                                // Handle empty table placeholder (colspan)
                                if (cells.length === 1 && cells.first().attr('colspan')) {
                                    var currentColspan = parseInt(cells.first().attr('colspan'), 10);
                                    cells.first().attr('colspan', currentColspan + 1);
                                    row.addClass('mb-processed-row');
                                    return;
                                }
                                
                                if (cells.length > idColIndex) {
                                    var serviceId = cells.eq(idColIndex).text().trim();
                                    var badge = '';
                                    
                                    if (serviceId && serviceBrands[serviceId]) {
                                        var brand = serviceBrands[serviceId];
                                        var name = brand.brand_name;
                                        var color = brand.brand_color || '#666';
                                        badge = \"<span class='label' style='background-color: \" + color + \"; color: #fff; font-size: 0.8em; padding: 2px 6px; border-radius: 3px; font-weight: bold; vertical-align: middle;'>\" + name + \"</span>\";
                                    }
                                    
                                    // Insert the new cell after the last actions cell
                                    var lastCell = cells.last();
                                    lastCell.after('</td class=\"text-center mb-brand-cell\" style=\"vertical-align: middle;\">' + badge + '</td>');
                                    row.addClass('mb-processed-row');
                                }
                            });
                        }
                    }
                    
                    // 2. INVOICES TABLE DECORATION
                    var invoiceCol = table.find('th:contains(\"Invoice #\")');
                    if (invoiceCol.length > 0) {
                        var idColIndex = -1;
                        
                        // Find \"Invoice #\" column index
                        table.find('th').each(function(index) {
                            var text = $(this).text().trim();
                            if (text === 'Invoice #') {
                                idColIndex = index;
                            }
                        });
                        
                        if (idColIndex !== -1) {
                            // Add \"Brand\" header to each header row if not present
                            table.find('thead tr, tr:first').each(function() {
                                var headerRow = $(this);
                                if (headerRow.find('th').length > 0) {
                                    var brandHeader = headerRow.find('th:contains(\"Brand\")');
                                    if (brandHeader.length === 0) {
                                        var lastTh = headerRow.find('th').last();
                                        lastTh.after('<th class=\"text-center mb-brand-header\" style=\"font-weight: bold;\">Brand</th>');
                                    }
                                }
                            });
                            
                            // Loop over each row in tbody
                            table.find('tbody tr').each(function() {
                                var row = $(this);
                                
                                // Prevent duplicate processing of the same row
                                if (row.hasClass('mb-processed-row')) {
                                    return;
                                }
                                
                                var cells = row.find('td');
                                
                                // Handle empty table placeholder (colspan)
                                if (cells.length === 1 && cells.first().attr('colspan')) {
                                    var currentColspan = parseInt(cells.first().attr('colspan'), 10);
                                    cells.first().attr('colspan', currentColspan + 1);
                                    row.addClass('mb-processed-row');
                                    return;
                                }
                                
                                if (cells.length > idColIndex) {
                                    var invoiceIdText = cells.eq(idColIndex).text().trim();
                                    
                                    // Extract only numeric invoice ID
                                    var invoiceId = invoiceIdText.replace(/[^0-9]/g, '');
                                    
                                    var badge = '';
                                    if (invoiceId && invoiceBrands[invoiceId]) {
                                        var brand = invoiceBrands[invoiceId];
                                        var name = brand.brand_name;
                                        var color = brand.brand_color || '#666';
                                        badge = \"<span class='label' style='background-color: \" + color + \"; color: #fff; font-size: 0.8em; padding: 2px 6px; border-radius: 3px; font-weight: bold; vertical-align: middle;'>\" + name + \"</span>\";
                                    }
                                    
                                    // Insert the new cell after the last actions cell
                                    var lastCell = cells.last();
                                    lastCell.after('<td class=\"text-center mb-brand-cell\" style=\"vertical-align: middle;\">' + badge + '</td>');
                                    row.addClass('mb-processed-row');
                                }
                            });
                        }
                    }
                });
            }
            
            // Run initially
            decorateClientSummaryTables();
            
            // Re-run on datatables page changes, searches, sorting
            $(document).on('draw.dt', 'table', function() {
                decorateClientSummaryTables();
            });
            
            // Re-run on general AJAX loads / tab switches
            $(document).ajaxSuccess(function() {
                decorateClientSummaryTables();
            });
        });
    </script>
    ";
});

/**
 * Admin Area Client Profile Tab Fields Hook
 * Adds brand selection listbox to the client profile edit page
 */
add_hook('AdminAreaClientProfileTabFields', 1, function ($vars) {
    $userid = $vars['userid'];

    // Get all assigned brand IDs for this client
    $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
        ->where('client_id', $userid)
        ->pluck('brand_id')
        ->toArray();

    $brands = Capsule::table('mod_multibrand_brands')->get();

    $selectHtml = '<input type="hidden" name="multibrand_submitted" value="1">';
    $selectHtml .= '<select name="multibrand_ids[]" multiple="multiple" class="form-control" style="height: 100px; width: 100%; max-width: 500px; font-family: inherit; font-size: 0.95em; padding: 4px;">';
    
    foreach ($brands as $brand) {
        $selected = in_array($brand->id, $assignedBrandIds) ? ' selected' : '';
        // Add option styling with brand color for custom premium touch matching user mockup
        $color = htmlspecialchars($brand->brand_color ?: '#333');
        $selectHtml .= '<option value="' . $brand->id . '"' . $selected . ' style="padding: 4px 8px; color: ' . $color . '; font-weight: bold;">' . htmlspecialchars($brand->brand_name) . '</option>';
    }
    
    $selectHtml .= '</select>';

    if (empty($brands) || count($brands) == 0) {
        $selectHtml = '<input type="hidden" name="multibrand_submitted" value="1">';
        $selectHtml .= '<span class="text-muted">No active brands configured.</span>';
    }

    return [
        'Brand' => $selectHtml
    ];
});

/**
 * Client Edit Hook
 * Saves the brand assignment when the client profile is updated
 */
add_hook('ClientEdit', 1, function ($vars) {
    $clientId = $vars['userid'];

    // 1. If edited from the Admin Profile tab (manually selecting the brands via checkboxes)
    if (isset($_POST['multibrand_submitted'])) {
        $submittedBrandIds = isset($_POST['multibrand_ids']) ? array_map('intval', $_POST['multibrand_ids']) : [];

        try {
            // Delete all existing brand associations for this client first
            Capsule::table('mod_multibrand_client_brands')->where('client_id', $clientId)->delete();

            // Insert the selected brand associations
            foreach ($submittedBrandIds as $brandId) {
                if ($brandId > 0) {
                    Capsule::table('mod_multibrand_client_brands')->insert([
                        'client_id' => $clientId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log error
        }
    } else {
        // 2. If edited from the client area, save/update based on active domain (only if domain matches a brand in our module)
        $brand = get_multibrand_brand_by_domain();
        if ($brand && $clientId) {
            try {
                $exists = Capsule::table('mod_multibrand_client_brands')
                    ->where('client_id', $clientId)
                    ->where('brand_id', $brand->id)
                    ->exists();
                if (!$exists) {
                    Capsule::table('mod_multibrand_client_brands')->insert([
                        'client_id' => $clientId,
                        'brand_id' => $brand->id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
});

/**
 * Admin Area Footer Output Hook
 * Unified injection for brand badges on 10 lists and dropdown inputs on edit pages
 */
add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    $supportedFilenames = [
        'clients',
        'cancelrequests',
        'clientsdomainlist',
        'clientsdomains',
        'orders',
        'ordersadd',
        'ordersedit',
        'clientsaddonslist',
        'supporttickets',
        'invoices',
        'supportannouncements',
        'supportdownloads',
        'clientsservices',
        'clientshostinglist',
        'index',
        'clientsprofile',
        'clientsinvoices'
    ];

    if (!in_array($vars['filename'], $supportedFilenames)) {
        return '';
    }

    try {
        // Fetch all active brands
        $brands = Capsule::table('mod_multibrand_brands')->get();
        $brandMap = [];
        $brandsList = [];
        foreach ($brands as $brand) {
            $depts = [];
            if ($brand->ticket_departments) {
                $depts = array_values(array_filter(array_map('intval', explode(',', $brand->ticket_departments))));
            }
            $brandData = [
                'id' => $brand->id,
                'name' => htmlspecialchars($brand->brand_name),
                'color' => htmlspecialchars($brand->brand_color ?: '#666'),
                'departments' => $depts
            ];
            $brandMap[$brand->id] = $brandData;
            $brandsList[] = $brandData;
        }

        $filename = $vars['filename'];
        $action = $_REQUEST['action'] ?? '';
// print_r($action);die();
        // Filter brands List by client assigned brands if we are on the ticket view/edit page
        if ($filename == 'supporttickets' && ($action == 'view' || $action == 'viewticket') && isset($_REQUEST['id'])) {
            $ticketId = (int)$_REQUEST['id'];
            $ticketClientId = 0;
            try {
                $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first();
                if ($ticket) {
                    $ticketClientId = (int)$ticket->userid;
                }
            } catch (\Exception $e) {}

            $assignedBrandIds = [];
            if ($ticketClientId > 0) {
                try {
                    $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
                        ->where('client_id', $ticketClientId)
                        ->pluck('brand_id')
                        ->toArray();
                } catch (\Exception $e) {}
            }

            if (!empty($assignedBrandIds)) {
                $filteredBrandsList = [];
                foreach ($brandsList as $brand) {
                    if (in_array($brand['id'], $assignedBrandIds)) {
                        $filteredBrandsList[] = $brand;
                    }
                }
                $brandsList = $filteredBrandsList;
            }
        }

        // Dynamically map new WHMCS route paths for services listing to traditional filename
        if ($filename == 'index') {
            $isServicesRoute = (isset($_GET['rp']) && $_GET['rp'] == '/admin/services') || 
                               (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], '/admin/services') !== false || strpos($_SERVER['REQUEST_URI'], '/services') !== false));
            if ($isServicesRoute) {
                $filename = 'clientshostinglist';
            } else {
                return '';
            }
        }

        // 1. Check if we are on an edit/detail page for Category B entities
        // -- Client Profile Edit Page --
        if ($filename == 'clientsprofile') {
            $clientId = (int)($_REQUEST['userid'] ?? $_REQUEST['id'] ?? 0);
            $assignedBrandIds = [];
            if ($clientId > 0) {
                try {
                    $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
                        ->where('client_id', $clientId)
                        ->pluck('brand_id')
                        ->toArray();
                } catch (\Exception $e) {}
            }

            $brandsHtml = '<input type="hidden" name="multibrand_submitted" value="1">';
            $brandsHtml .= '<select name="multibrand_ids[]" multiple="multiple" class="form-control" style="height: 100px; width: 100%; max-width: 500px; font-family: inherit; font-size: 0.95em; padding: 4px;">';
            
            foreach ($brandsList as $brand) {
                $selected = in_array($brand['id'], $assignedBrandIds) ? ' selected' : '';
                $brandsHtml .= '<option value="' . $brand['id'] . '"' . $selected . ' style="padding: 4px 8px; color: ' . $brand['color'] . '; font-weight: bold;">' . $brand['name'] . '</option>';
            }
            $brandsHtml .= '</select>';

            if (empty($brandsList)) {
                $brandsHtml = '<input type="hidden" name="multibrand_submitted" value="1">';
                $brandsHtml .= '<span class="text-muted">No active brands configured.</span>';
            }

            return "
            <script>
                $(document).ready(function() {
                    var brandsHtml = \"" . addslashes($brandsHtml) . "\";
                    
                    var targetRow = $('textarea[name=\"notes\"]').closest('tr');
                    if (targetRow.length && $('#multibrand_profile_row').length === 0) {
                        var rowHtml = '<tr id=\"multibrand_profile_row\">' +
                            '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">Brand</td>' +
                            '  <td class=\"fieldarea\" colspan=\"3\">' + brandsHtml + '</td>' +
                            '</tr>';
                        targetRow.after(rowHtml);
                        console.log('Brand profile field injected');
                    }
                });
            </script>
            ";
        }

        // -- Client Invoices Tab Page --
        if ($filename == 'clientsinvoices') {
            $userid = (int)($_REQUEST['userid'] ?? $_REQUEST['id'] ?? 0);
            
            // Fetch all invoice brand mappings for this client
            $invoiceBrandsMap = [];
            try {
                $clientInvoiceIds = Capsule::table('tblinvoices')
                    ->where('userid', $userid)
                    ->pluck('id')
                    ->toArray();
                    
                if (!empty($clientInvoiceIds)) {
                    $iBrands = Capsule::table('mod_multibrand_invoice_brands')
                        ->join('mod_multibrand_brands', 'mod_multibrand_invoice_brands.brand_id', '=', 'mod_multibrand_brands.id')
                        ->whereIn('mod_multibrand_invoice_brands.invoice_id', $clientInvoiceIds)
                        ->select('mod_multibrand_invoice_brands.invoice_id', 'mod_multibrand_brands.brand_name', 'mod_multibrand_brands.brand_color')
                        ->get();
                        
                    foreach ($iBrands as $ib) {
                        $invoiceBrandsMap[$ib->invoice_id] = [
                            'brand_name' => $ib->brand_name,
                            'brand_color' => $ib->brand_color ?: '#666'
                        ];
                    }
                }
            } catch (\Exception $e) {}

            return "
            <script>
                $(document).ready(function() {
                    var invoiceBrands = " . json_encode($invoiceBrandsMap) . ";
                    
                    function decorateInvoicesTable() {
                        $('table').each(function() {
                            var table = $(this);
                            var invoiceCol = table.find('th:contains(\"Invoice #\")');
                            if (invoiceCol.length > 0) {
                                var idColIndex = -1;
                                
                                // Find \"Invoice #\" column index
                                table.find('th').each(function(index) {
                                    var text = $(this).text().trim();
                                    if (text === 'Invoice #') {
                                        idColIndex = index;
                                    }
                                });
                                
                                if (idColIndex !== -1) {
                                    // Add \"Brand\" header to each header row if not present
                                    table.find('thead tr, tr:first').each(function() {
                                        var headerRow = $(this);
                                        if (headerRow.find('th').length > 0) {
                                            var brandHeader = headerRow.find('th:contains(\"Brand\")');
                                            if (brandHeader.length === 0) {
                                                var lastTh = headerRow.find('th').last();
                                                lastTh.after('<th class=\"text-center mb-brand-header\" style=\"font-weight: bold; width: 120px;\">Brand</th>');
                                            }
                                        }
                                    });
                                    
                                    // Loop over each row in tbody
                                    table.find('tbody tr').each(function() {
                                        var row = $(this);
                                        
                                        // Prevent duplicate processing of the same row
                                        if (row.hasClass('mb-processed-row')) {
                                            return;
                                        }
                                        
                                        var cells = row.find('td');
                                        
                                        // Handle empty table placeholder (colspan)
                                        if (cells.length === 1 && cells.first().attr('colspan')) {
                                            var currentColspan = parseInt(cells.first().attr('colspan'), 10);
                                            cells.first().attr('colspan', currentColspan + 1);
                                            row.addClass('mb-processed-row');
                                            return;
                                        }
                                        
                                        if (cells.length > idColIndex) {
                                            var invoiceIdText = cells.eq(idColIndex).text().trim();
                                            var invoiceId = invoiceIdText.replace(/[^0-9]/g, '');
                                            
                                            var badge = '';
                                            if (invoiceId && invoiceBrands[invoiceId]) {
                                                var brand = invoiceBrands[invoiceId];
                                                var name = brand.brand_name;
                                                var color = brand.brand_color || '#666';
                                                badge = \"<span class='label' style='background-color: \" + color + \"; color: #fff; font-size: 0.8em; padding: 2px 6px; border-radius: 3px; font-weight: bold; vertical-align: middle;'>\" + name + \"</span>\";
                                            }
                                            
                                            // Insert the new cell after the last actions cell
                                            var lastCell = cells.last();
                                            lastCell.after('<td class=\"text-center mb-brand-cell\" style=\"vertical-align: middle;\">' + badge + '</td>');
                                            row.addClass('mb-processed-row');
                                        }
                                    });
                                }
                            }
                        });
                    }
                    
                    // Run initially
                    decorateInvoicesTable();
                    
                    // Re-run on datatables page changes, searches, sorting
                    $(document).on('draw.dt', 'table', function() {
                        decorateInvoicesTable();
                    });
                    
                    // Re-run on general AJAX loads / tab switches
                    $(document).ajaxSuccess(function() {
                        decorateInvoicesTable();
                    });
                });
            </script>
            ";
        }

        // -- Service Edit Page --
        if ($filename == 'clientsservices') {
            $serviceId = (int)($_REQUEST['id'] ?? 0);
            $explicitBrandId = 0;
            $clientId = 0;
            if ($serviceId > 0) {
                $explicitServiceBrand = Capsule::table('mod_multibrand_service_brands')
                    ->where('service_id', $serviceId)
                    ->first();
                if ($explicitServiceBrand) {
                    $explicitBrandId = $explicitServiceBrand->brand_id;
                }
                try {
                    $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
                    if ($service) {
                        $clientId = (int)$service->userid;
                    }
                } catch (\Exception $e) {}
            }

            if ($clientId === 0) {
                $clientId = (int)($_REQUEST['userid'] ?? $_REQUEST['id'] ?? 0);
            }

            $assignedBrandIds = [];
            if ($clientId > 0) {
                try {
                    $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
                        ->where('client_id', $clientId)
                        ->pluck('brand_id')
                        ->toArray();
                } catch (\Exception $e) {}
            }

            $filteredBrandsList = [];
            if (!empty($assignedBrandIds)) {
                foreach ($brandsList as $brand) {
                    if (in_array($brand['id'], $assignedBrandIds)) {
                        $filteredBrandsList[] = $brand;
                    }
                }
            }
            if (empty($filteredBrandsList)) {
                $filteredBrandsList = $brandsList;
            }

            $jsonBrandsList = json_encode($filteredBrandsList);

            return "
            <script>
                $(document).ready(function() {
                    var brandsList = $jsonBrandsList;
                    var explicitBrandId = $explicitBrandId;
                    console.log('Service Brand Dropdown Initialized. Current Brand ID:', explicitBrandId);

                    // Locate details table row (typically Billing Cycle, Status, or Payment Method)
                    var paymentRow = $('select[name=\"paymentmethod\"]').closest('tr');
                    if (!paymentRow.length) {
                        paymentRow = $('select[name=\"status\"]').closest('tr');
                    }
                    if (!paymentRow.length) {
                        paymentRow = $('select[name=\"packageid\"]').closest('tr');
                    }

                    if (paymentRow.length && $('#multibrand_service_row').length === 0) {
                        var dropdownHtml = '<tr id=\"multibrand_service_row\">' +
                            '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">Service Brand</td>' +
                            '  <td class=\"fieldarea\">' +
                            '    <select name=\"multibrand_id\" class=\"form-control select-inline\" style=\"min-width: 250px; font-weight: bold; padding: 4px;\">' +
                            '      <option value=\"0\"' + (explicitBrandId === 0 ? ' selected' : '') + '>None (No Brand)</option>';

                        $.each(brandsList, function(index, brand) {
                            var selected = (brand.id === explicitBrandId) ? ' selected' : '';
                            dropdownHtml += '      <option value=\"' + brand.id + '\"' + selected + ' style=\"color: ' + brand.color + '; font-weight: bold;\">' + brand.name + '</option>';
                        });

                        dropdownHtml += '    </select>' +
                            '    <span style=\"margin-left: 10px; font-size: 0.9em; color: #666;\">(Select explicit brand or select None)</span>' +
                            '  </td>' +
                            '</tr>';

                        paymentRow.after(dropdownHtml);
                        console.log('Service Brand dropdown injected into page');
                    }

                    function syncBrandValue() {
                        var selectedVal = $('select[name=\"multibrand_id\"]').val();
                        if (selectedVal === undefined || selectedVal === null) {
                            selectedVal = explicitBrandId.toString();
                        }
                        
                        $('form').each(function() {
                            var form = $(this);
                            form.find('input[type=\"hidden\"][name=\"multibrand_id\"]').remove();
                            form.append('<input type=\"hidden\" name=\"multibrand_id\" value=\"' + selectedVal + '\">');
                        });
                        console.log('syncBrandValue: Updated hidden input in all forms with value:', selectedVal);
                    }

                    syncBrandValue();
                    $(document).on('change', 'select[name=\"multibrand_id\"]', function() {
                        console.log('Service brand dropdown changed to:', $(this).val());
                        syncBrandValue();
                    });
                    
                    $('form').on('submit', function(e) {
                        console.log('Form submitted, syncing service brand value');
                        syncBrandValue();
                        return true;
                    });
                    
                    $(document).on('click', 'input[type=\"submit\"], button[type=\"submit\"], .btn-primary, .btn', function(e) {
                        console.log('Submit button clicked, syncing service brand value');
                        syncBrandValue();
                    });
                });
            </script>
            ";
        }

        // -- Invoice Edit Page --
        if ($filename == 'invoices' && $action == 'edit') {
            $invoiceId = (int)$_REQUEST['id'];
            $explicitBrandId = 0;
            $clientId = 0;
            $explicitInvoiceBrand = Capsule::table('mod_multibrand_invoice_brands')
                ->where('invoice_id', $invoiceId)
                ->first();
            if ($explicitInvoiceBrand) {
                $explicitBrandId = $explicitInvoiceBrand->brand_id;
            }
            if ($invoiceId > 0) {
                try {
                    $invoice = Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
                    if ($invoice) {
                        $clientId = (int)$invoice->userid;
                    }
                } catch (\Exception $e) {}
            }
            if ($clientId === 0) {
                $clientId = (int)($_REQUEST['userid'] ?? 0);
            }

            $assignedBrandIds = [];
            if ($clientId > 0) {
                try {
                    $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
                        ->where('client_id', $clientId)
                        ->pluck('brand_id')
                        ->toArray();
                } catch (\Exception $e) {}
            }

            $filteredBrandsList = [];
            if (!empty($assignedBrandIds)) {
                foreach ($brandsList as $brand) {
                    if (in_array($brand['id'], $assignedBrandIds)) {
                        $filteredBrandsList[] = $brand;
                    }
                }
            }
            if (empty($filteredBrandsList)) {
                $filteredBrandsList = $brandsList;
            }

            $jsonBrandsList = json_encode($filteredBrandsList);

            return "
            <script>
                $(document).ready(function() {
                    var brandsList = $jsonBrandsList;
                    var explicitBrandId = $explicitBrandId;
                    console.log('Invoice Brand Dropdown Initialized. Current Brand ID:', explicitBrandId);

                    // Locate details table
                    var paymentRow = $('select[name=\"paymentmethod\"]').closest('tr');
                    if (!paymentRow.length) {
                        paymentRow = $('select[name=\"status\"]').closest('tr');
                    }

                    if (paymentRow.length && $('#multibrand_invoice_row').length === 0) {
                        var dropdownHtml = '<tr id=\"multibrand_invoice_row\">' +
                            '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">Invoice Brand</td>' +
                            '  <td class=\"fieldarea\">' +
                            '    <select name=\"multibrand_id\" class=\"form-control select-inline\" style=\"min-width: 250px; font-weight: bold; padding: 4px;\">' +
                            '      <option value=\"0\"' + (explicitBrandId === 0 ? ' selected' : '') + '>None (No Brand)</option>';

                        $.each(brandsList, function(index, brand) {
                            var selected = (brand.id === explicitBrandId) ? ' selected' : '';
                            dropdownHtml += '      <option value=\"' + brand.id + '\"' + selected + ' style=\"color: ' + brand.color + '; font-weight: bold;\">' + brand.name + '</option>';
                        });

                        dropdownHtml += '    </select>' +
                            '    <span style=\"margin-left: 10px; font-size: 0.9em; color: #666;\">(Select explicit brand or select None)</span>' +
                            '  </td>' +
                            '</tr>';

                        paymentRow.after(dropdownHtml);
                        console.log('Invoice Brand dropdown injected into page');
                    }

                    function syncBrandValue() {
                        var selectedVal = $('select[name=\"multibrand_id\"]').val();
                        if (selectedVal === undefined || selectedVal === null) {
                            selectedVal = explicitBrandId.toString();
                        }
                        console.log('syncBrandValue: Updated hidden input in all forms with value:', selectedVal);
                    }

                    syncBrandValue();
                    $(document).on('change', 'select[name=\"multibrand_id\"]', function() {
                        console.log('Brand dropdown changed to:', $(this).val());
                        syncBrandValue();
                    });
                    
                    // Intercept form submission to ensure brand value is synced
                    $('form').on('submit', function(e) {
                        console.log('Form submitted, syncing brand value');
                        syncBrandValue();
                        var hiddenInput = $(this).find('input[name=\"multibrand_id\"]');
                        if (hiddenInput.length) {
                            console.log('multibrand_id hidden input value before submit:', hiddenInput.val());
                        }
                        return true;
                    });
                    
                    // Also sync on button clicks
                    $(document).on('click', 'input[type=\"submit\"], button[type=\"submit\"], .btn-primary, .btn', function(e) {
                        console.log('Submit button clicked, syncing brand value');
                        syncBrandValue();
                    });
                });
            </script>
            ";
        }
        // -- Add New Order / Edit Order Page --
        $isOrdersAddPage = $filename === 'ordersadd' || ($filename === 'orders' && $action === 'add');
        $hasOrderId = (isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0) || (isset($_REQUEST['orderid']) && (int)$_REQUEST['orderid'] > 0);
        $isOrdersEditPage = $filename === 'ordersedit' || ($filename === 'orders' && (in_array($action, ['edit', 'view']) || $hasOrderId));
        
        if ($isOrdersAddPage || $isOrdersEditPage) {
            $orderId = (int)(($isOrdersEditPage) ? ($_REQUEST['id'] ?? $_REQUEST['orderid'] ?? 0) : 0);
            $explicitBrandId = 0;
            if ($orderId > 0) {
                $explicitOrderBrand = Capsule::table('mod_multibrand_order_brands')
                    ->where('order_id', $orderId)
                    ->first();
                if ($explicitOrderBrand) {
                    $explicitBrandId = $explicitOrderBrand->brand_id;
                }
            }

            // Fetch client brands map
            $clientBrandMap = [];
            try {
                $clientBrands = Capsule::table('mod_multibrand_client_brands')->get();
                foreach ($clientBrands as $cb) {
                    if (isset($brandMap[$cb->brand_id])) {
                        $clientBrandMap[$cb->client_id][] = $brandMap[$cb->brand_id];
                    }
                }
            } catch (\Exception $e) {}

            $jsonBrandsList = json_encode($brandsList);
            $jsonClientBrandMap = json_encode($clientBrandMap);

            return "
            <script>
                $(document).ready(function() {
                    var brandsList = $jsonBrandsList;
                    var explicitBrandId = $explicitBrandId;
                    var clientBrandMap = $jsonClientBrandMap;
                    console.log('Order Brand Dropdown Initialized. Current Brand ID:', explicitBrandId);

                    // Locate a key field in the form (Client field is usually first)
                    var clientRow = $('label:contains(\"Client\")').closest('div').parent();
                    if (!clientRow.length) {
                        clientRow = $('td:contains(\"Client\")').closest('tr');
                    }

                    if (clientRow.length && $('#multibrand_order_row').length === 0) {
                        var dropdownHtml = '';
                        if (clientRow.is('tr')) {
                            dropdownHtml = '<tr id=\"multibrand_order_row\">' +
                                '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">Order Brand</td>' +
                                '  <td class=\"fieldarea\">' +
                                '    <select name=\"multibrand_id\" class=\"form-control select-inline\" style=\"min-width: 250px; font-weight: bold; padding: 4px;\"></select>' +
                                '    <span style=\"margin-left: 10px; font-size: 0.9em; color: #666;\">(Select explicit brand or select None)</span>' +
                                '  </td>' +
                                '</tr>';
                        } else {
                            dropdownHtml = '<div id=\"multibrand_order_row\" style=\"margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;\">' +
                                '  <label style=\"font-weight: block; display: block; margin-bottom: 5px;\">Order Brand</label>' +
                                '  <select name=\"multibrand_id\" class=\"form-control\" style=\"max-width: 300px;\"></select>' +
                                '  <small style=\"display: block; margin-top: 5px; color: #666;\">(Select a brand for this order)</small>' +
                                '</div>';
                        }

                        clientRow.after(dropdownHtml);
                        console.log('Order Brand dropdown injected into page');
                    }

                    function syncBrandValue() {
                        var selectedVal = $('select[name=\"multibrand_id\"]').val();
                        if (selectedVal === undefined || selectedVal === null) {
                            selectedVal = explicitBrandId.toString();
                        }
                        
                        $('form').each(function() {
                            var form = $(this);
                            form.find('input[type=\"hidden\"][name=\"multibrand_id\"]').remove();
                            form.append('<input type=\"hidden\" name=\"multibrand_id\" value=\"' + selectedVal + '\">');
                        });
                        console.log('syncBrandValue: Updated hidden input in all forms with value:', selectedVal);
                    }

                    function getSelectedClientId() {
                        var userIdElem = $('select[name=\"userid\"], input[name=\"userid\"]');
                        if (userIdElem.length) {
                            return userIdElem.val() || \"\";
                        }
                        return \"\";
                    }

                    function filterBrandDropdown(clientId) {
                        var brandSelect = $('select[name=\"multibrand_id\"]');
                        if (!brandSelect.length) return;

                        var currentSelected = brandSelect.val();
                        brandSelect.empty();

                        // Always add \"None (No Brand)\" option
                        var noneSelected = (explicitBrandId === 0 && !currentSelected) || currentSelected == \"0\";
                        brandSelect.append($('<option>', {
                            value: '0',
                            text: 'None (No Brand)',
                            selected: noneSelected
                        }));

                        var filteredBrands = [];
                        var isFiltered = false;

                        if (clientId && clientBrandMap[clientId] && clientBrandMap[clientId].length > 0) {
                            filteredBrands = clientBrandMap[clientId];
                            isFiltered = true;
                        } else {
                            // Fallback to all brands if no client selected or client has no assigned brands
                            filteredBrands = brandsList;
                        }

                        $.each(filteredBrands, function(index, brand) {
                            var isSelected = (brand.id == explicitBrandId) || (brand.id == currentSelected);
                            var option = $('<option>', {
                                value: brand.id,
                                text: brand.name,
                                selected: isSelected
                            }).css({
                                'color': brand.color,
                                'font-weight': 'bold'
                            });
                            brandSelect.append(option);
                        });

                        // Premium auto-select: if client has exactly one brand and no other brand is currently selected, select it automatically!
                        if (isFiltered && filteredBrands.length > 0) {
                            var hasSelected = false;
                            $.each(filteredBrands, function(index, brand) {
                                if (brand.id == currentSelected) {
                                    hasSelected = true;
                                }
                            });
                            if (!hasSelected && explicitBrandId == 0) {
                                brandSelect.val(filteredBrands[0].id);
                            }
                        }

                        syncBrandValue();
                    }

                    // Initial population on page load
                    var lastClientId = getSelectedClientId();
                    filterBrandDropdown(lastClientId);

                    // Listen to explicit change events
                    $(document).on('change', 'select[name=\"userid\"], input[name=\"userid\"]', function() {
                        var clientId = $(this).val();
                        console.log('Client selection changed (event):', clientId);
                        filterBrandDropdown(clientId);
                    });

                    // Periodic poll fallback for programmatically changed client values
                    function checkClientChange() {
                        var currentClientId = getSelectedClientId();
                        if (currentClientId !== lastClientId) {
                            console.log('Client selection changed (poll):', lastClientId, '->', currentClientId);
                            lastClientId = currentClientId;
                            filterBrandDropdown(currentClientId);
                        }
                    }
                    setInterval(checkClientChange, 500);

                    $(document).on('change', 'select[name=\"multibrand_id\"]', function() {
                        console.log('Order brand dropdown changed to:', $(this).val());
                        syncBrandValue();
                    });
                    
                    $('form').on('submit', function(e) {
                        console.log('Form submitted, syncing order brand value');
                        syncBrandValue();
                        return true;
                    });
                    
                    $(document).on('click', 'input[type=\"submit\"], button[type=\"submit\"], .btn-primary, .btn', function(e) {
                        console.log('Submit button clicked, syncing order brand value');
                        syncBrandValue();
                    });
                });
            </script>
            ";
        }

        // -- Open Support Ticket Page --
        $isOpenTicketPage = $filename === 'supporttickets' && $action === 'open';

        if ($isOpenTicketPage) {
            // Fetch client brands map
            $clientBrandMap = [];
            try {
                $clientBrands = Capsule::table('mod_multibrand_client_brands')->get();
                foreach ($clientBrands as $cb) {
                    if (isset($brandMap[$cb->brand_id])) {
                        $clientBrandMap[$cb->client_id][] = $brandMap[$cb->brand_id];
                    }
                }
            } catch (\Exception $e) {}

            $jsonBrandsList = json_encode($brandsList);
            $jsonClientBrandMap = json_encode($clientBrandMap);

            return "
            <script>
                $(document).ready(function() {
                    var brandsList = $jsonBrandsList;
                    var clientBrandMap = $jsonClientBrandMap;
                    console.log('Open Ticket Brand Dropdown Initialized');

                    // Locate details table row (typically Department or Client row)
                    var targetRow = $('#contentarea select[name=\"deptid\"]').last();
                    if (!targetRow.length) {
                        targetRow = $('select[name=\"deptid\"]').last();
                    }
                    targetRow = targetRow.closest('tr');
                    
                    if (!targetRow.length) {
                        targetRow = $('td:contains(\"Client\")').closest('tr');
                    }

                    if (targetRow.length && $('#multibrand_ticket_row').length === 0) {
                        var dropdownHtml = '<tr id=\"multibrand_ticket_row\">' +
                            '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">Ticket Brand</td>' +
                            '  <td class=\"fieldarea\">' +
                            '    <select name=\"multibrand_id\" class=\"form-control select-inline\" style=\"min-width: 250px; font-weight: bold; padding: 4px;\"></select>' +
                            '    <span style=\"margin-left: 10px; font-size: 0.9em; color: #666;\">(Select explicit brand or select None)</span>' +
                            '  </td>' +
                            '</tr>';
                        targetRow.after(dropdownHtml);
                        console.log('Ticket Brand dropdown injected');
                    }

                    // Save all original department options
                    var deptSelect = $('#contentarea select[name=\"deptid\"]').last();
                    if (!deptSelect.length) {
                        deptSelect = $('select[name=\"deptid\"]').last();
                    }
                    var originalDepts = [];
                    if (deptSelect.length) {
                        deptSelect.find('option').each(function() {
                            originalDepts.push({
                                value: $(this).val(),
                                text: $(this).text(),
                                selected: $(this).is(':selected')
                            });
                        });
                    }

                    function getSelectedClientId() {
                        var hiddenClient = $('#clientinput, input[name=\"client\"]');
                        if (hiddenClient.length && hiddenClient.val()) {
                            return hiddenClient.val();
                        }
                        
                        var clientSearch = $('#selectClientSearch, select[name=\"clientSearch\"]');
                        if (clientSearch.length && clientSearch.val()) {
                            return clientSearch.val();
                        }

                        var userId = $('input[name=\"userid\"], select[name=\"userid\"]');
                        if (userId.length && userId.val()) {
                            return userId.val();
                        }

                        var clientId = $('input[name=\"clientid\"], select[name=\"clientid\"]');
                        if (clientId.length && clientId.val()) {
                            return clientId.val();
                        }

                        return \"\";
                    }

                    function filterDepartmentDropdown(brandId) {
                        var deptSelect = $('#contentarea select[name=\"deptid\"]').last();
                        if (!deptSelect.length) {
                            deptSelect = $('select[name=\"deptid\"]').last();
                        }
                        if (!deptSelect.length || !originalDepts.length) return;

                        var currentSelected = deptSelect.val();
                        deptSelect.empty();

                        var brandDepts = null;
                        $.each(brandsList, function(index, brand) {
                            if (brand.id == brandId) {
                                brandDepts = brand.departments;
                            }
                        });

                        // Rebuild and filter options
                        var filteredCount = 0;
                        $.each(originalDepts, function(index, dept) {
                            // Always allow empty or unselected values
                            var isPlaceholder = !dept.value || dept.value == \"0\";
                            
                            // Check if department is associated with this brand
                            var isMapped = isPlaceholder;
                            if (brandDepts && brandDepts.length > 0) {
                                if ($.inArray(parseInt(dept.value), brandDepts) !== -1) {
                                    isMapped = true;
                                }
                            } else {
                                // Fallback: if brand has no departments mapped or brandId is 0, show all departments!
                                isMapped = true;
                            }

                            if (isMapped) {
                                var isSelected = (dept.value == currentSelected) || (filteredCount === 0 && !currentSelected && dept.selected);
                                deptSelect.append($('<option>', {
                                    value: dept.value,
                                    text: dept.text,
                                    selected: isSelected
                                }));
                                filteredCount++;
                            }
                        });
                    }

                    function filterTicketBrandDropdown(clientId) {
                        var brandSelect = $('select[name=\"multibrand_id\"]');
                        if (!brandSelect.length) return;

                        var currentSelected = brandSelect.val();
                        brandSelect.empty();

                        // Always add \"None (No Brand)\" option
                        var noneSelected = !currentSelected || currentSelected == \"0\";
                        brandSelect.append($('<option>', {
                            value: '0',
                            text: 'None (No Brand)',
                            selected: noneSelected
                        }));

                        var filteredBrands = [];
                        var isFiltered = false;

                        if (clientId && clientBrandMap[clientId] && clientBrandMap[clientId].length > 0) {
                            filteredBrands = clientBrandMap[clientId];
                            isFiltered = true;
                        } else {
                            filteredBrands = brandsList;
                        }

                        $.each(filteredBrands, function(index, brand) {
                            var isSelected = (brand.id == currentSelected);
                            var option = $('<option>', {
                                value: brand.id,
                                text: brand.name,
                                selected: isSelected
                            }).css({
                                'color': brand.color,
                                'font-weight': 'bold'
                            });
                            brandSelect.append(option);
                        });

                        // Premium auto-select: if client has exactly one brand and no other brand is currently selected, select it automatically!
                        if (isFiltered && filteredBrands.length > 0) {
                            var hasSelected = false;
                            $.each(filteredBrands, function(index, brand) {
                                if (brand.id == currentSelected) {
                                    hasSelected = true;
                                }
                            });
                            if (!hasSelected) {
                                brandSelect.val(filteredBrands[0].id);
                            }
                        }

                        // Re-filter the department dropdown based on the newly selected brand!
                        var finalBrandId = brandSelect.val() || \"0\";
                        filterDepartmentDropdown(finalBrandId);
                    }

                    // Initial population on page load
                    var lastClientId = getSelectedClientId();
                    filterTicketBrandDropdown(lastClientId);

                    // Listen to explicit client change events
                    $(document).on('change', 'input[name=\"client\"], select[name=\"clientSearch\"], select[name=\"userid\"], input[name=\"userid\"], select[name=\"clientid\"], input[name=\"clientid\"]', function() {
                        var clientId = $(this).val();
                        console.log('Client selection changed (event):', clientId);
                        filterTicketBrandDropdown(clientId);
                    });

                    // Listen to explicit brand dropdown changes
                    $(document).on('change', 'select[name=\"multibrand_id\"]', function() {
                        var brandId = $(this).val();
                        console.log('Ticket Brand changed (event):', brandId);
                        filterDepartmentDropdown(brandId);
                    });

                    // Periodic poll fallback for programmatically changed client values
                    function checkClientChange() {
                        var currentClientId = getSelectedClientId();
                        if (currentClientId !== lastClientId) {
                            console.log('Client selection changed (poll):', lastClientId, '->', currentClientId);
                            lastClientId = currentClientId;
                            filterTicketBrandDropdown(currentClientId);
                        }
                    }
                    setInterval(checkClientChange, 500);
                });
            </script>
            ";
        }

        // -- Announcements, Downloads, and KB Edit/Add Pages --
        $isGlobalFile = in_array($filename, ['supportannouncements', 'supportdownloads']);
        $hasEditAction = in_array($action, ['manage', 'edit', 'add', 'article']);
        $hasId = isset($_REQUEST['id']) && (int)$_REQUEST['id'] > 0;
        $isGlobalEditPage = $isGlobalFile && ($hasEditAction || $hasId);
        
        if ($isGlobalEditPage) {
            $explicitBrandId = 0;
            $id = (int)($_REQUEST['id'] ?? 0);

            if ($filename == 'supportannouncements' && $id > 0) {
                $explicitBrand = Capsule::table('mod_multibrand_announcement_brands')->where('announcement_id', $id)->first();
                if ($explicitBrand) {
                    $explicitBrandId = $explicitBrand->brand_id;
                }
            } elseif ($filename == 'supportdownloads' && $id > 0) {
                $explicitBrand = Capsule::table('mod_multibrand_download_brands')->where('download_id', $id)->first();
                if ($explicitBrand) {
                    $explicitBrandId = $explicitBrand->brand_id;
                }
            }

            $jsonBrandsList = json_encode($brandsList);

            return "
            <script>
                $(document).ready(function() {
                    var brandsList = $jsonBrandsList;
                    var explicitBrandId = $explicitBrandId;
                    var filename = '$filename';

                    function injectEditDropdown() {
                        var titleInput = $('input[name=\"title\"], input[name=\"date\"]').first();
                        if (titleInput.length) {
                            var targetRow = titleInput.closest('tr');
                            if (targetRow.length && $('#multibrand_edit_row').length === 0) {
                                var labelText = 'Brand';
                                if (filename === 'supportannouncements') {
                                    labelText = 'Announcement Brand';
                                } else if (filename === 'supportdownloads') {
                                    labelText = 'Download Brand';
                                }

                                var dropdownHtml = '<tr id=\"multibrand_edit_row\">' +
                                    '  <td class=\"fieldlabel\" style=\"font-weight: bold; width: 15%;\">' + labelText + '</td>' +
                                    '  <td class=\"fieldarea\">' +
                                    '    <select id=\"multibrand_id_sel\" class=\"form-control select-inline\" style=\"min-width: 250px; font-weight: bold; padding: 4px;\">' +
                                    '      <option value=\"0\"' + (explicitBrandId === 0 ? ' selected' : '') + '>Global</option>';

                                $.each(brandsList, function(index, brand) {
                                    var selected = (brand.id === explicitBrandId) ? ' selected' : '';
                                    dropdownHtml += '      <option value=\"' + brand.id + '\"' + selected + ' style=\"color: ' + brand.color + '; font-weight: bold;\">' + brand.name + '</option>';
                                });

                                dropdownHtml += '    </select>' +
                                    '    <span style=\"margin-left: 10px; font-size: 0.9em; color: #666;\">(Select explicit brand or select Global)</span>' +
                                    '  </td>' +
                                    '</tr>';

                                targetRow.before(dropdownHtml);
                                syncBrandValue();
                            }
                        }
                    }

                    function syncBrandValue() {
                        var selectedVal = $('#multibrand_id_sel').val();
                        if (selectedVal === undefined || selectedVal === null) {
                            selectedVal = explicitBrandId.toString();
                        }
                        $('form').each(function() {
                            var form = $(this);
                            form.find('input[type=\"hidden\"][name=\"multibrand_id\"]').remove();
                            form.append('<input type=\"hidden\" name=\"multibrand_id\" value=\"' + selectedVal + '\">');
                        });
                    }

                    injectEditDropdown();
                    setInterval(injectEditDropdown, 300);

                    $(document).on('change', '#multibrand_id_sel', function() {
                        syncBrandValue();
                    });
                    $(document).on('submit', 'form', function() {
                        syncBrandValue();
                    });
                    $(document).on('click', 'input[type=\"submit\"], button, .btn', function() {
                        syncBrandValue();
                    });
                });
            </script>
            ";
        }
        // 2. Otherwise, we are in List View Mode
        // Fetch client brands map
        $clientBrands = Capsule::table('mod_multibrand_client_brands')->get();
        $clientBrandMap = [];
        foreach ($clientBrands as $cb) {
            if (isset($brandMap[$cb->brand_id])) {
                $clientBrandMap[$cb->client_id][] = $brandMap[$cb->brand_id];
            }
        }

        // Fetch invoice brands map
        $invoiceBrands = Capsule::table('mod_multibrand_invoice_brands')->get();
        $invoiceBrandMap = [];
        foreach ($invoiceBrands as $ib) {
            if (isset($brandMap[$ib->brand_id])) {
                $invoiceBrandMap[$ib->invoice_id] = $brandMap[$ib->brand_id];
            }
        }

        // Fetch announcement brands map
        $announcementBrands = Capsule::table('mod_multibrand_announcement_brands')->get();
        $announcementBrandMap = [];
        foreach ($announcementBrands as $ab) {
            if (isset($brandMap[$ab->brand_id])) {
                $announcementBrandMap[$ab->announcement_id] = $brandMap[$ab->brand_id];
            }
        }

        // Fetch download brands map
        $downloadBrands = Capsule::table('mod_multibrand_download_brands')->get();
        $downloadBrandMap = [];
        foreach ($downloadBrands as $db) {
            if (isset($brandMap[$db->brand_id])) {
                $downloadBrandMap[$db->download_id] = $brandMap[$db->brand_id];
            }
        }



        // Fetch order brands map
        $orderBrands = Capsule::table('mod_multibrand_order_brands')->get();
        $orderBrandMap = [];
        foreach ($orderBrands as $ob) {
            if (isset($brandMap[$ob->brand_id])) {
                $orderBrandMap[$ob->order_id] = $brandMap[$ob->brand_id];
            }
        }

        // Fetch service brands map
        $serviceBrands = Capsule::table('mod_multibrand_service_brands')->get();
        $serviceBrandMap = [];
        foreach ($serviceBrands as $sb) {
            if (isset($brandMap[$sb->brand_id])) {
                $serviceBrandMap[$sb->service_id] = $brandMap[$sb->brand_id];
            }
        }

        // Fetch explicit ticket brands strictly based on mod_multibrand_ticket_brands table
        $ticketBrandMap = [];
        try {
            $etBrands = Capsule::table('mod_multibrand_ticket_brands')->get();
            foreach ($etBrands as $etb) {
                if (isset($brandMap[$etb->brand_id])) {
                    $ticketBrandMap[$etb->ticket_id] = [$brandMap[$etb->brand_id]];
                }
            }
        } catch (\Exception $e) {}

        $jsonBrandsList = json_encode($brandsList);
        $jsonClientBrandMap = json_encode($clientBrandMap);
        $jsonInvoiceBrandMap = json_encode($invoiceBrandMap);
        $jsonAnnouncementBrandMap = json_encode($announcementBrandMap);
        $jsonDownloadBrandMap = json_encode($downloadBrandMap);
        $jsonOrderBrandMap = json_encode($orderBrandMap);
        $jsonServiceBrandMap = json_encode($serviceBrandMap);
        $jsonTicketBrandMap = json_encode($ticketBrandMap);
    // print_r($filename);die();
        return "
        <script>
            $(document).ready(function() {
                var brandsList = $jsonBrandsList;
                var clientBrands = $jsonClientBrandMap;
                var invoiceBrands = $jsonInvoiceBrandMap;
                var announcementBrands = $jsonAnnouncementBrandMap;
                var downloadBrands = $jsonDownloadBrandMap;
                var orderBrands = $jsonOrderBrandMap;
                var serviceBrands = $jsonServiceBrandMap;
                var ticketBrands = $jsonTicketBrandMap;
                var filename = '$filename';

                function injectBrandsColumn() {
                    var table = $('.datatable, .table').first();
                    if (!table.length) return;

                    table.find('tr').each(function() {
                        var row = $(this);
                        if (row.find('th').length) {
                            var headerCell = row.find('th.brands-header');
                            if (headerCell.length === 0) {
                                row.append('<th class=\"text-center brands-header\" style=\"width: 120px; font-weight: bold;\">Brands</th>');
                            } else if (!headerCell.is(':last-child')) {
                                row.append(headerCell);
                            }
                        } else if (row.find('td').length) {
                            var bodyCell = row.find('td.brands-column');
                            var entityId = null;
                            var clientId = null;

                            row.find('a').each(function() {
                                var href = $(this).attr('href') || '';
                                
                                var clientMatch = href.match(/clientssummary\\.php\\?userid=(\d+)/) || 
                                                  href.match(/clients\\.php\\?action=edit&id=(\d+)/) || 
                                                  href.match(/clientssummary\\.php\\?id=(\d+)/);
                                if (clientMatch) {
                                    clientId = clientMatch[1];
                                }

                                if (filename === 'invoices') {
                                    var invMatch = href.match(/invoices\\.php\\?action=edit&id=(\d+)/) || href.match(/invoices\\.php\\?action=edit&invoiceid=(\d+)/);
                                    if (invMatch) {
                                        entityId = invMatch[1];
                                    }
                                } else if (filename === 'supporttickets') {
                                    var tMatch = href.match(/supporttickets\\.php\\?action=view&id=(\d+)/) || href.match(/supporttickets\\.php\\?.*id=(\d+)/);
                                    if (tMatch) {
                                        entityId = tMatch[1];
                                    }
                                } else if (filename === 'orders') {
                                    var ordMatch = href.match(/orders\\.php\\?(?:.*&)?orderid=(\d+)/) || href.match(/orders\\.php\\?(?:.*&)?id=(\d+)/);
                                    if (ordMatch) {
                                        entityId = ordMatch[1];
                                    }
                                } else if (filename === 'supportannouncements') {
                                    var annMatch = href.match(/supportannouncements\\.php\\?(?:action=manage|sub=edit|action=edit)&id=(\d+)/) || href.match(/supportannouncements\\.php\\?.*id=(\d+)/);
                                    if (annMatch) {
                                        entityId = annMatch[1];
                                    }
                                } else if (filename === 'supportdownloads') {
                                    var dlMatch = href.match(/supportdownloads\\.php\\?(?:action=manage|sub=edit|action=edit)&id=(\d+)/) || href.match(/supportdownloads\\.php\\?.*id=(\d+)/);
                                    if (dlMatch) {
                                        entityId = dlMatch[1];
                                    }
                                } else if (filename === 'clientshostinglist' || filename === 'clientsservices') {
                                    var svcMatch = href.match(/clientsservices\\.php\\?(?:.*&)?id=(\d+)/);
                                    if (svcMatch) {
                                        entityId = svcMatch[1];
                                    }
                                }
                            });

                            if (!entityId) {
                                var cb = row.find('input[type=\"checkbox\"]');
                                if (cb.length) {
                                    var v = cb.val();
                                    if (v && v !== 'on' && !isNaN(parseInt(v))) {
                                        entityId = parseInt(v);
                                    }
                                }
                            }

                            if (filename === 'orders' && !entityId) {
                                var idText = row.find('td:nth-child(2)').text().trim();
                                if (idText && !isNaN(parseInt(idText))) {
                                    entityId = parseInt(idText);
                                }
                            }

                            if (filename === 'clientshostinglist' && !entityId) {
                                var idText = row.find('td:nth-child(2)').text().trim();
                                if (idText && !isNaN(parseInt(idText))) {
                                    entityId = parseInt(idText);
                                }
                            }

                            if (filename === 'clients' && !clientId) {
                                var idCell = row.find('td:nth-child(2)');
                                if (idCell.length) {
                                    clientId = idCell.text().trim();
                                }
                            }

                            var badgesHtml = '';

                            if (filename === 'invoices') {
                                if (entityId && invoiceBrands[entityId]) {
                                    var brand = invoiceBrands[entityId];
                                    badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                }
                            } else if (filename === 'supporttickets') {
                                if (entityId && ticketBrands[entityId]) {
                                    var tBrands = ticketBrands[entityId];
                                    if (!Array.isArray(tBrands)) {
                                        tBrands = [tBrands];
                                    }
                                    $.each(tBrands, function(index, brand) {
                                        badgesHtml += '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                    });
                                }
                            } else if (filename === 'orders') {
                                if (entityId && orderBrands[entityId]) {
                                    var brand = orderBrands[entityId];
                                    badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                }
                            } else if (filename === 'clientshostinglist' || filename === 'clientsservices') {
                                if (entityId && serviceBrands[entityId]) {
                                    var brand = serviceBrands[entityId];
                                    badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                }
                            } else if (filename === 'supportannouncements') {
                                if (entityId) {
                                    if (announcementBrands[entityId]) {
                                        var brand = announcementBrands[entityId];
                                        badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                    } else {
                                        badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color: #888; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">Global</span></div>';
                                    }
                                }
                            } else if (filename === 'supportdownloads') {
                                if (entityId) {
                                    if (downloadBrands[entityId]) {
                                        var brand = downloadBrands[entityId];
                                        badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                    } else {
                                        badgesHtml = '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color: #888; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">Global</span></div>';
                                    }
                                }

                            } else {
                                // Category A (Client-linked mappings)
                                if (clientId && clientBrands[clientId]) {
                                    var cBrands = clientBrands[clientId];
                                    $.each(cBrands, function(index, brand) {
                                        badgesHtml += '<div style=\"margin-bottom: 3px;\"><span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 2px 6px; border-radius: 3px; display: inline-block; font-weight: bold; text-transform: uppercase;\">' + brand.name + '</span></div>';
                                    });
                                }
                            }

                            if (bodyCell.length === 0) {
                                row.append('<td class=\"text-center brands-column\" style=\"vertical-align: middle;\">' + badgesHtml + '</td>');
                            } else {
                                bodyCell.html(badgesHtml);
                                if (!bodyCell.is(':last-child')) {
                                    row.append(bodyCell);
                                }
                            }
                        }
                    });
                }

                // Ticket View Page Injection
                if (filename === 'supporttickets') {
                    function injectTicketViewBadge() {
                        var urlParams = new URLSearchParams(window.location.search);
                        if ((urlParams.get('action') === 'view' || urlParams.get('action') === 'viewticket')) {
                            var ticketId = urlParams.get('id');
                            if (ticketId) {
                                // 1. Render Read-Only Badge in Header
                                if (ticketBrands[ticketId]) {
                                    var tBrands = ticketBrands[ticketId];
                                    if (!Array.isArray(tBrands)) {
                                        tBrands = [tBrands];
                                    }
                                    var badgeHtml = '';
                                    $.each(tBrands, function(index, brand) {
                                        badgeHtml += ' <span class=\"label\" style=\"background-color:' + brand.color + '; color: #fff; font-size: 0.85em; padding: 4px 10px; border-radius: 4px; font-weight: bold; margin-left: 10px; vertical-align: middle; text-transform: uppercase;\">' + brand.name + '</span>';
                                    });
                                    
                                    var header = $('.page-header h1, h2:contains(\"#\"), #contentarea h1').first();
                                    if (header.length && $('#ticket_brand_badge').length === 0) {
                                        header.append('<span id=\"ticket_brand_badge\">' + badgeHtml + '</span>');
                                    }
                                }

                                // 2. Render Editable Brand Selection Dropdown in Options/Details
                                var deptField = $('#frmTicketOptions select[name=\"deptid\"]').first();
                                if (!deptField.length) {
                                    deptField = $('select[name=\"deptid\"]').first();
                                }
                                if (deptField.length && $('#multibrand_ticket_edit_field').length === 0) {
                                    var currentBrandId = 0;
                                    if (ticketBrands[ticketId]) {
                                        var tBrands = ticketBrands[ticketId];
                                        var firstBrand = Array.isArray(tBrands) ? tBrands[0] : tBrands;
                                        if (firstBrand) {
                                            currentBrandId = firstBrand.id;
                                        }
                                    }

                                    var dropdownHtml = '';
                                    var deptRow = deptField.closest('tr');
                                    if (deptRow.length) {
                                         // Tabular layout - matching Department field column layout
                                         dropdownHtml = '<tr id=\"multibrand_ticket_edit_field\">' +
                                             '  <td class=\"fieldlabel\" style=\"font-weight: bold;\">Ticket Brand</td>' +
                                             '  <td class=\"fieldarea\">' +
                                             '    <select name=\"multibrand_id\" class=\"form-control select-inline\" style=\"width: 100%; max-width: 250px; font-weight: bold; padding: 4px;\">' +
                                             '      <option value=\"0\"' + (currentBrandId === 0 ? ' selected' : '') + '>None (No Brand)</option>';

                                         $.each(brandsList, function(index, brand) {
                                             var selected = (brand.id === currentBrandId) ? ' selected' : '';
                                             dropdownHtml += '      <option value=\"' + brand.id + '\"' + selected + ' style=\"color: ' + brand.color + '; font-weight: bold;\">' + brand.name + '</option>';
                                         });

                                         dropdownHtml += '    </select>' +
                                             '  </td>' +
                                             '  <td class=\"fieldlabel\">&nbsp;</td>' +
                                             '  <td class=\"fieldarea\">&nbsp;</td>' +
                                             '</tr>';
                                         deptRow.after(dropdownHtml);
                                    } else {
                                        // Form-group or block layout
                                        var deptGroup = deptField.closest('.form-group');
                                        if (!deptGroup.length) {
                                            deptGroup = deptField.parent();
                                        }
                                        
                                        dropdownHtml = '<div id=\"multibrand_ticket_edit_field\" class=\"form-group\">' +
                                            '  <label class=\"control-label\" style=\"font-weight: bold;\">Ticket Brand</label>' +
                                            '  <select name=\"multibrand_id\" class=\"form-control\" style=\"font-weight: bold;\">' +
                                            '    <option value=\"0\"' + (currentBrandId === 0 ? ' selected' : '') + '>None (No Brand)</option>';

                                        $.each(brandsList, function(index, brand) {
                                            var selected = (brand.id === currentBrandId) ? ' selected' : '';
                                            dropdownHtml += '    <option value=\"' + brand.id + '\"' + selected + ' style=\"color: ' + brand.color + '; font-weight: bold;\">' + brand.name + '</option>';
                                        });
                                        dropdownHtml += '  </select>' +
                                            '</div>';
                                        deptGroup.after(dropdownHtml);
                                    }
                                     console.log('Ticket Brand edit dropdown injected into Options tab form');
                                 }

                                 // 3. Dynamic client change listener on Ticket View Page
                                 function filterTicketViewBrandDropdown(clientId) {
                                     var brandSelect = $('#frmTicketOptions select[name=\"multibrand_id\"], select[name=\"multibrand_id\"]');
                                     if (!brandSelect.length) return;

                                     var currentSelected = brandSelect.val();
                                     brandSelect.empty();

                                     // Always add \"None (No Brand)\" option
                                     var noneSelected = !currentSelected || currentSelected == \"0\";
                                     brandSelect.append($('<option>', {
                                         value: '0',
                                         text: 'None (No Brand)',
                                         selected: noneSelected
                                     }));

                                     var filteredBrands = [];
                                     var isFiltered = false;

                                     if (clientId && clientBrands[clientId] && clientBrands[clientId].length > 0) {
                                         filteredBrands = clientBrands[clientId];
                                         isFiltered = true;
                                     } else {
                                         filteredBrands = brandsList;
                                     }

                                     $.each(filteredBrands, function(index, brand) {
                                         var isSelected = (brand.id == currentSelected);
                                         var option = $('<option>', {
                                             value: brand.id,
                                             text: brand.name,
                                             selected: isSelected
                                         }).css({
                                             'color': brand.color,
                                             'font-weight': 'bold'
                                         });
                                         brandSelect.append(option);
                                     });

                                     // Auto-select: if client has exactly one brand and no brand is currently selected, select it automatically!
                                     if (isFiltered && filteredBrands.length > 0) {
                                         var hasSelected = false;
                                         $.each(filteredBrands, function(index, brand) {
                                             if (brand.id == currentSelected) {
                                                 hasSelected = true;
                                             }
                                         });
                                         if (!hasSelected) {
                                             brandSelect.val(filteredBrands[0].id);
                                         }
                                     }
                                 }

                                 // Listen to client dropdown changes in Options tab
                                 $(document).on('change', '#frmTicketOptions select[name=\"userid\"], #frmTicketOptions input[name=\"userid\"], select[name=\"userid\"], input[name=\"userid\"]', function() {
                                     var clientId = $(this).val();
                                     console.log('Ticket view client selection changed (event):', clientId);
                                     filterTicketViewBrandDropdown(clientId);
                                 });

                                 // Periodic poll fallback for client changes on ticket view page
                                 var lastViewClientId = $('#frmTicketOptions select[name=\"userid\"], #frmTicketOptions input[name=\"userid\"], select[name=\"userid\"], input[name=\"userid\"]').first().val() || \"\";
                                 function checkViewClientChange() {
                                     var currentClientId = $('#frmTicketOptions select[name=\"userid\"], #frmTicketOptions input[name=\"userid\"], select[name=\"userid\"], input[name=\"userid\"]').first().val() || \"\";
                                     if (currentClientId !== lastViewClientId) {
                                         console.log('Ticket view client selection changed (poll):', lastViewClientId, '->', currentClientId);
                                         lastViewClientId = currentClientId;
                                         filterTicketViewBrandDropdown(currentClientId);
                                     }
                                 }
                                 setInterval(checkViewClientChange, 500);
                            }
                        }
                    }
                    injectTicketViewBadge();
                    setTimeout(injectTicketViewBadge, 100);
                    setTimeout(injectTicketViewBadge, 300);
                    setTimeout(injectTicketViewBadge, 600);
                    setInterval(injectTicketViewBadge, 1000);
                }

                injectBrandsColumn();
                setTimeout(injectBrandsColumn, 100);
                setTimeout(injectBrandsColumn, 300);
                setTimeout(injectBrandsColumn, 600);
                setTimeout(injectBrandsColumn, 1200);

                $(document).on('draw.dt', function() {
                    injectBrandsColumn();
                });
                $(document).ajaxComplete(function() {
                    injectBrandsColumn();
                });
                setInterval(injectBrandsColumn, 500);
            });
        </script>
        ";
    } catch (\Exception $e) {
        return "<!-- MultiBrand Footer Error: " . $e->getMessage() . " -->";
    }
});

/**
 * Invoice Edit Hook
 * Saves brand assignment when the invoice is updated in the new mod_multibrand_invoice_brands table
 */
add_hook('UpdateInvoiceTotal', 1, function ($vars) {
    // print_r($_POST);die();
    $invoiceId = (int)$vars['invoiceid'];
    if (isset($_POST['multibrand_id'])) {
        $brandId = (int)$_POST['multibrand_id'];

        try {
            if ($brandId === 0) {
                // Revert to inherited: remove explicit invoice brand mapping
                Capsule::table('mod_multibrand_invoice_brands')
                    ->where('invoice_id', $invoiceId)
                    ->delete();
            } else {
                $exists = Capsule::table('mod_multibrand_invoice_brands')
                    ->where('invoice_id', $invoiceId)
                    ->exists();

                if ($exists) {
                    Capsule::table('mod_multibrand_invoice_brands')
                        ->where('invoice_id', $invoiceId)
                        ->update([
                            'brand_id' => $brandId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    Capsule::table('mod_multibrand_invoice_brands')->insert([
                        'invoice_id' => $invoiceId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (\Exception $e) {
            try {
                
            } catch (\Exception $ex) {}
        }
    }
});

/**
 * Invoice Created Hook
 * Automatically links a newly created invoice to the client's first assigned brand
 */
add_hook('InvoiceCreated', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    try {
        $brandId = 0;

        // 1. Try to find brand from associated invoice items (renewal/services/domains)
        $items = Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
        foreach ($items as $item) {
            if (in_array($item->type, ['Hosting', 'Service']) && $item->relid > 0) {
                $sb = Capsule::table('mod_multibrand_service_brands')->where('service_id', $item->relid)->first();
                if ($sb && $sb->brand_id > 0) {
                    $brandId = $sb->brand_id;
                    break;
                }
            } elseif ($item->type == 'Addon' && $item->relid > 0) {
                $addon = Capsule::table('tblhostingaddons')->find($item->relid);
                if ($addon && $addon->hostingid > 0) {
                    $sb = Capsule::table('mod_multibrand_service_brands')->where('service_id', $addon->hostingid)->first();
                    if ($sb && $sb->brand_id > 0) {
                        $brandId = $sb->brand_id;
                        break;
                    }
                }
            } elseif ($item->type == 'Upgrade' && $item->relid > 0) {
                $upgrade = Capsule::table('tblupgrades')->find($item->relid);
                if ($upgrade && $upgrade->relid > 0) {
                    $sb = Capsule::table('mod_multibrand_service_brands')->where('service_id', $upgrade->relid)->first();
                    if ($sb && $sb->brand_id > 0) {
                        $brandId = $sb->brand_id;
                        break;
                    }
                }
            }
        }

        // 2. Fall back to current active request brand if we are in frontend context
        if ($brandId === 0 && !defined('ADMIN_AREA')) {
            $activeBrand = get_multibrand_active_brand();
            if ($activeBrand) {
                $brandId = $activeBrand->id;
            }
        }

        // 3. Fall back to the client's first assigned brand
        if ($brandId === 0) {
            $invoice = Capsule::table('tblinvoices')->find($invoiceId);
            if ($invoice && $invoice->userid) {
                $clientBrand = Capsule::table('mod_multibrand_client_brands')
                    ->where('client_id', $invoice->userid)
                    ->first();
                if ($clientBrand) {
                    $brandId = $clientBrand->brand_id;
                }
            }
        }

        // 4. Update or insert the invoice brand mapping
        if ($brandId > 0) {
            $exists = Capsule::table('mod_multibrand_invoice_brands')
                ->where('invoice_id', $invoiceId)
                ->first();
                
            $isBranded = false;
            if ($exists) {
                Capsule::table('mod_multibrand_invoice_brands')
                    ->where('invoice_id', $invoiceId)
                    ->update([
                        'brand_id' => $brandId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                $isBranded = (bool)$exists->is_branded;
            } else {
                Capsule::table('mod_multibrand_invoice_brands')->insert([
                    'invoice_id' => $invoiceId,
                    'brand_id' => $brandId,
                    'is_branded' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            // Assign sequential invoice number branding on creation if proforma is disabled
            try {
                $brand = Capsule::table('mod_multibrand_brands')->find($brandId);
                if ($brand && $brand->invoice_number_branding && !$brand->proforma_invoice) {
                    $invoice = Capsule::table('tblinvoices')->find($invoiceId);
                    if ($invoice && !$isBranded) {
                        $total = (float)$invoice->total;
                        if ($total > 0 || $brand->zero_invoices_number_branding) {
                            Capsule::transaction(function() use ($brandId, $invoiceId, $invoice) {
                                $brandRow = Capsule::table('mod_multibrand_brands')
                                    ->where('id', $brandId)
                                    ->lockForUpdate()
                                    ->first();
                                
                                $invBrandRow = Capsule::table('mod_multibrand_invoice_brands')
                                    ->where('invoice_id', $invoiceId)
                                    ->lockForUpdate()
                                    ->first();

                                if ($brandRow && $invBrandRow && !$invBrandRow->is_branded && $brandRow->invoice_number_branding && !$brandRow->proforma_invoice) {
                                    $nextNum = $brandRow->next_sequential_number ?: 1;
                                    $format = $brandRow->sequential_invoice_number_format ?: '{NUMBER}';
                                    
                                    $timestamp = strtotime($invoice->date ?: date('Y-m-d'));
                                    $year = date('Y', $timestamp);
                                    $month = date('m', $timestamp);
                                    $day = date('d', $timestamp);
                                    
                                    $invoiceNum = str_replace(
                                        ['{YEAR}', '{MONTH}', '{DAY}', '{NUMBER}'],
                                        [$year, $month, $day, $nextNum],
                                        $format
                                    );
                                    
                                    Capsule::table('tblinvoices')
                                        ->where('id', $invoiceId)
                                        ->update(['invoicenum' => $invoiceNum]);
                                        
                                    Capsule::table('mod_multibrand_brands')
                                        ->where('id', $brandId)
                                        ->update([
                                            'next_sequential_number' => $nextNum + 1,
                                            'updated_at' => date('Y-m-d H:i:s')
                                        ]);

                                    Capsule::table('mod_multibrand_invoice_brands')
                                        ->where('invoice_id', $invoiceId)
                                        ->update([
                                            'is_branded' => 1,
                                            'updated_at' => date('Y-m-d H:i:s')
                                        ]);
                                }
                            });
                        }
                    }
                }
            } catch (\Exception $ex) {}
        }
    } catch (\Exception $e) {
        // Ignore gracefully
    }
});

add_hook('InvoicePaid', 1, function ($vars) {
    $invoiceId = (int)$vars['invoiceid'];
    try {
        $invBrand = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->first();
        if ($invBrand && $invBrand->brand_id > 0) {
            $brandId = $invBrand->brand_id;
            $isBranded = (bool)$invBrand->is_branded;

            $brand = Capsule::table('mod_multibrand_brands')->find($brandId);
            if ($brand && $brand->invoice_number_branding && $brand->proforma_invoice) {
                $invoice = Capsule::table('tblinvoices')->find($invoiceId);
                if ($invoice && !$isBranded) {
                    $total = (float)$invoice->total;
                    if ($total > 0 || $brand->zero_invoices_number_branding) {
                        Capsule::transaction(function() use ($brandId, $invoiceId, $invoice) {
                            $brandRow = Capsule::table('mod_multibrand_brands')
                                ->where('id', $brandId)
                                ->lockForUpdate()
                                ->first();

                            $invBrandRow = Capsule::table('mod_multibrand_invoice_brands')
                                ->where('invoice_id', $invoiceId)
                                ->lockForUpdate()
                                ->first();

                            if ($brandRow && $invBrandRow && !$invBrandRow->is_branded && $brandRow->invoice_number_branding && $brandRow->proforma_invoice) {
                                $nextNum = $brandRow->next_sequential_number ?: 1;
                                $format = $brandRow->sequential_invoice_number_format ?: '{NUMBER}';
                                
                                $timestamp = strtotime($invoice->date ?: date('Y-m-d'));
                                $year = date('Y', $timestamp);
                                $month = date('m', $timestamp);
                                $day = date('d', $timestamp);
                                
                                $invoiceNum = str_replace(
                                    ['{YEAR}', '{MONTH}', '{DAY}', '{NUMBER}'],
                                    [$year, $month, $day, $nextNum],
                                    $format
                                );
                                
                                Capsule::table('tblinvoices')
                                    ->where('id', $invoiceId)
                                    ->update(['invoicenum' => $invoiceNum]);
                                    
                                Capsule::table('mod_multibrand_brands')
                                    ->where('id', $brandId)
                                    ->update([
                                        'next_sequential_number' => $nextNum + 1,
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);

                                Capsule::table('mod_multibrand_invoice_brands')
                                    ->where('invoice_id', $invoiceId)
                                    ->update([
                                        'is_branded' => 1,
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                            }
                        });
                    }
                }
            }
        }
    } catch (\Exception $e) {}
});

/**
 * Announcement Created Hook
 * Automatically links a newly created announcement to the selected brand
 */
add_hook('AnnouncementAdd', 1, function ($vars) {
    $announcementId = (int)$vars['announcementid'];
    if ($announcementId > 0 && isset($_POST['multibrand_id'])) {
        $brandId = (int)$_POST['multibrand_id'];
        if ($brandId > 0) {
            try {
                Capsule::table('mod_multibrand_announcement_brands')->insert([
                    'announcement_id' => $announcementId,
                    'brand_id' => $brandId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {}
        }
    }
});

/**
 * Download Created Hook
 * Automatically links a newly created download to the selected brand
 */
add_hook('DownloadAdd', 1, function ($vars) {
    $downloadId = (int)$vars['downloadid'];
    if ($downloadId > 0 && isset($_POST['multibrand_id'])) {
        $brandId = (int)$_POST['multibrand_id'];
        if ($brandId > 0) {
            try {
                Capsule::table('mod_multibrand_download_brands')->insert([
                    'download_id' => $downloadId,
                    'brand_id' => $brandId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Exception $e) {}
        }
    }
});



/**
 * Order Created Hook
 * Saves brand assignment when a new order is created
 */
add_hook('AfterShoppingCartCheckout', 1, function ($vars) {
    // print_r($_POST);die();
    $orderId = (int)$vars['OrderID'];
    if ($orderId > 0) {
        $brandId = 0;
        if (isset($_POST['multibrand_id'])) {
            $brandId = (int)$_POST['multibrand_id'];
        } else {
            $activeBrand = get_multibrand_active_brand();
            if ($activeBrand) {
                $brandId = $activeBrand->id;
            }
        }

        if ($brandId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->exists();
                if (!$exists) {
                    Capsule::table('mod_multibrand_order_brands')->insert([
                        'order_id' => $orderId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }

                // Save service brand for each service in the order
                $services = Capsule::table('tblhosting')->where('orderid', $orderId)->get();
                foreach ($services as $service) {
                    $serviceExists = Capsule::table('mod_multibrand_service_brands')->where('service_id', $service->id)->exists();
                    if (!$serviceExists) {
                        Capsule::table('mod_multibrand_service_brands')->insert([
                            'service_id' => $service->id,
                            'brand_id' => $brandId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                // Associate the checkout invoice to the same brand
                $order = Capsule::table('tblorders')->find($orderId);
                if ($order && $order->invoiceid > 0) {
                    $invoiceId = (int)$order->invoiceid;
                    $invExists = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->exists();
                    if ($invExists) {
                        Capsule::table('mod_multibrand_invoice_brands')
                            ->where('invoice_id', $invoiceId)
                            ->update([
                                'brand_id' => $brandId,
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                    } else {
                        Capsule::table('mod_multibrand_invoice_brands')->insert([
                            'invoice_id' => $invoiceId,
                            'brand_id' => $brandId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            } catch (\Exception $e) {}
        }
    }
});

/**
 * Admin Service Edit Hook
 * Saves brand assignment when a service is updated in the admin area
 */
add_hook('AdminServiceEdit', 1, function ($vars) {
    $serviceId = (int)$vars['serviceid'];
    if (isset($_POST['multibrand_id'])) {
        $brandId = (int)$_POST['multibrand_id'];
        try {
            if ($brandId === 0) {
                Capsule::table('mod_multibrand_service_brands')
                    ->where('service_id', $serviceId)
                    ->delete();
            } else {
                $exists = Capsule::table('mod_multibrand_service_brands')
                    ->where('service_id', $serviceId)
                    ->exists();

                if ($exists) {
                    Capsule::table('mod_multibrand_service_brands')
                        ->where('service_id', $serviceId)
                        ->update([
                            'brand_id' => $brandId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    Capsule::table('mod_multibrand_service_brands')->insert([
                        'service_id' => $serviceId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }
});

/**
 * Order Edit Hook
 * Saves brand assignment when an order is updated
 */
add_hook('OrderEdit', 1, function ($vars) {
    $orderId = (int)$vars['orderid'];
    if ($orderId > 0 && isset($_POST['multibrand_id'])) {
        $brandId = (int)$_POST['multibrand_id'];

        try {
            if ($brandId === 0) {
                Capsule::table('mod_multibrand_order_brands')
                    ->where('order_id', $orderId)
                    ->delete();
            } else {
                $exists = Capsule::table('mod_multibrand_order_brands')
                    ->where('order_id', $orderId)
                    ->exists();

                if ($exists) {
                    Capsule::table('mod_multibrand_order_brands')
                        ->where('order_id', $orderId)
                        ->update([
                            'brand_id' => $brandId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    Capsule::table('mod_multibrand_order_brands')->insert([
                        'order_id' => $orderId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }
});

/**
 * Ticket Open Hook (Client Area)
 * Automatically links a newly created ticket to the current active brand
 */
add_hook('TicketOpen', 1, function ($vars) {
    multibrand_save_ticket_brand($vars);
});

/**
 * Ticket Open Hook (Admin Area)
 * Automatically links a newly created ticket to the selected brand
 */
add_hook('TicketOpenAdmin', 1, function ($vars) {
    multibrand_save_ticket_brand($vars);
});

/**
 * Helper to save ticket brand association
 */
if (!function_exists('multibrand_save_ticket_brand')) {
function multibrand_save_ticket_brand($vars) {
    $ticketId = (int)$vars['ticketid'];
    if ($ticketId > 0) {
        try {
            $brandId = 0;
            if (isset($_POST['multibrand_id'])) {
                $brandId = (int)$_POST['multibrand_id'];
            }
            
            if ($brandId === 0) {
                $activeBrand = get_multibrand_active_brand();
                if ($activeBrand) {
                    $brandId = $activeBrand->id;
                }
            }

            if ($brandId > 0) {
                // Check if mapping already exists
                $exists = Capsule::table('mod_multibrand_ticket_brands')
                    ->where('ticket_id', $ticketId)
                    ->exists();
                if ($exists) {
                    Capsule::table('mod_multibrand_ticket_brands')
                        ->where('ticket_id', $ticketId)
                        ->update([
                            'brand_id' => $brandId,
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                } else {
                    Capsule::table('mod_multibrand_ticket_brands')->insert([
                        'ticket_id' => $ticketId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }
}
}

/**
 * Hook to dynamically override product/service pricing based on brand
 */
add_hook('OrderProductPricingOverride', 1, function ($vars) {
    $brand = get_multibrand_active_brand();
    if (!$brand || !$brand->price_override) {
        return;
    }

    try {
        $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
        $pid = $vars['pid'];
        $productOverrides = $pricingOverrides['products'][$pid]['pricing'] ?? [];
        
        if (!empty($productOverrides)) {
            $currencyId = (int)($_SESSION['currency'] ?? 1);
            $rates = $productOverrides[$currencyId] ?? [];
            if (!empty($rates)) {
                $cycle = strtolower($vars['proddata']['billingcycle'] ?? '');
                
                if ($cycle == 'monthly' && isset($rates['monthly']) && $rates['monthly'] !== '') {
                    return [
                        'setup' => $rates['msetupfee'] !== '' ? $rates['msetupfee'] : '0.00',
                        'recurring' => $rates['monthly']
                    ];
                } elseif ($cycle == 'quarterly' && isset($rates['quarterly']) && $rates['quarterly'] !== '') {
                    return [
                        'setup' => $rates['qsetupfee'] !== '' ? $rates['qsetupfee'] : '0.00',
                        'recurring' => $rates['quarterly']
                    ];
                } elseif ($cycle == 'semiannually' && isset($rates['semiannually']) && $rates['semiannually'] !== '') {
                    return [
                        'setup' => $rates['ssetupfee'] !== '' ? $rates['ssetupfee'] : '0.00',
                        'recurring' => $rates['semiannually']
                    ];
                } elseif ($cycle == 'annually' && isset($rates['annually']) && $rates['annually'] !== '') {
                    return [
                        'setup' => $rates['asetupfee'] !== '' ? $rates['asetupfee'] : '0.00',
                        'recurring' => $rates['annually']
                    ];
                } elseif ($cycle == 'biennially' && isset($rates['biennially']) && $rates['biennially'] !== '') {
                    return [
                        'setup' => $rates['bsetupfee'] !== '' ? $rates['bsetupfee'] : '0.00',
                        'recurring' => $rates['biennially']
                    ];
                } elseif ($cycle == 'triennially' && isset($rates['triennially']) && $rates['triennially'] !== '') {
                    return [
                        'setup' => $rates['tsetupfee'] !== '' ? $rates['tsetupfee'] : '0.00',
                        'recurring' => $rates['triennially']
                    ];
                } elseif (($cycle == 'onetime' || $cycle == 'one time') && isset($rates['monthly']) && $rates['monthly'] !== '') {
                    return [
                        'setup' => $rates['msetupfee'] !== '' ? $rates['msetupfee'] : '0.00',
                        'recurring' => $rates['monthly']
                    ];
                }
            }
        }
    } catch (\Exception $e) {}
});

/**
 * Hook to dynamically override addon pricing based on brand
 */
add_hook('OrderAddonPricingOverride', 1, function ($vars) {
    $brand = get_multibrand_active_brand();
    if (!$brand || !$brand->price_override) {
        return;
    }

    try {
        $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
        $addonId = $vars['addonid'];
        $addonOverrides = $pricingOverrides['addons'][$addonId]['pricing'] ?? [];
        
        if (!empty($addonOverrides)) {
            $currencyId = (int)($_SESSION['currency'] ?? 1);
            $rates = $addonOverrides[$currencyId] ?? [];
            if (!empty($rates)) {
                $cycles = [
                    'monthly' => ['price' => 'monthly', 'setup' => 'msetupfee'],
                    'quarterly' => ['price' => 'quarterly', 'setup' => 'qsetupfee'],
                    'semiannually' => ['price' => 'semiannually', 'setup' => 'ssetupfee'],
                    'annually' => ['price' => 'annually', 'setup' => 'asetupfee'],
                    'biennially' => ['price' => 'biennially', 'setup' => 'bsetupfee'],
                    'triennially' => ['price' => 'triennially', 'setup' => 'tsetupfee']
                ];
                
                foreach ($cycles as $c => $keys) {
                    if (isset($rates[$keys['price']]) && $rates[$keys['price']] !== '') {
                        return [
                            'setup' => $rates[$keys['setup']] !== '' ? $rates[$keys['setup']] : '0.00',
                            'recurring' => $rates[$keys['price']]
                        ];
                    }
                }
            }
        }
    } catch (\Exception $e) {}
});

/**
 * Hook to dynamically override domain pricing based on brand
 */
add_hook('OrderDomainPricingOverride', 1, function ($vars) {
    $brand = get_multibrand_active_brand();
    if (!$brand || !$brand->price_override) {
        return;
    }

    try {
        $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
        $sld = $vars['sld'];
        $tld = $vars['tld'];
        $regperiod = (int)$vars['regperiod'];
        
        $domainTemplate = Capsule::table('tbldomainpricing')->where('extension', $tld)->first();
        if ($domainTemplate) {
            $domainId = $domainTemplate->id;
            $domainOverrides = $pricingOverrides['domains'][$domainId]['pricing'] ?? [];
            
            if (!empty($domainOverrides)) {
                $currencyId = (int)($_SESSION['currency'] ?? 1);
                $rates = $domainOverrides[$currencyId] ?? [];
                if (!empty($rates)) {
                    $regPriceKey = 'register' . $regperiod;
                    $transPriceKey = 'transfer' . $regperiod;
                    $renewPriceKey = 'renew' . $regperiod;
                    
                    if (isset($rates[$regPriceKey]) && $rates[$regPriceKey] !== '') {
                        return [
                            'register' => $rates[$regPriceKey],
                            'transfer' => $rates[$transPriceKey] !== '' ? $rates[$transPriceKey] : '0.00',
                            'renew'    => $rates[$renewPriceKey] !== '' ? $rates[$renewPriceKey] : '0.00',
                        ];
                    }
                }
            }
        }
    } catch (\Exception $e) {}
});

/**
 * Dynamic Payment Gateway Overrides for Multi Brand Addon
 */
if (!function_exists('get_multibrand_request_brand')) {
function get_multibrand_request_brand()
{
    static $brand = null;
    if ($brand !== null) {
        return $brand;
    }

    try {
        // 1. Identify by Invoice ID in request
        $invoiceId = (int)($_REQUEST['invoiceid'] ?? $_REQUEST['id'] ?? $_POST['invoiceid'] ?? $_GET['invoiceid'] ?? 0);
        if ($invoiceId > 0) {
            $invBrand = Capsule::table('mod_multibrand_invoice_brands')->where('invoice_id', $invoiceId)->first();
            if ($invBrand) {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $invBrand->brand_id)->first();
                if ($brand && $brand->status) {
                    return $brand;
                }
            }
        }

        // 2. Identify by Order ID in request
        $orderId = (int)($_REQUEST['orderid'] ?? $_POST['orderid'] ?? $_GET['orderid'] ?? 0);
        if ($orderId > 0) {
            $ordBrand = Capsule::table('mod_multibrand_order_brands')->where('order_id', $orderId)->first();
            if ($ordBrand) {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $ordBrand->brand_id)->first();
                if ($brand && $brand->status) {
                    return $brand;
                }
            }
        }

        // 3. Fall back to current request domain brand
        $brand = get_multibrand_active_brand();
        return $brand;
    } catch (\Exception $e) {
        return get_multibrand_active_brand();
    }
}
}

if (!function_exists('apply_brand_gateway_overrides')) {
function apply_brand_gateway_overrides($brand)
{
    if (!$brand || !$brand->payment_gateways) {
        return;
    }
    
    $brandGateways = json_decode(htmlspecialchars_decode($brand->payment_gateways), true);
    if (!is_array($brandGateways) || count($brandGateways) == 0) {
        return;
    }

    // Acquire MySQL session lock to prevent concurrent database override race conditions
    $lockAcquired = false;
    try {
        $lockResult = Capsule::select("SELECT GET_LOCK('multibrand_gateway_override', 15) as locked");
        if (!empty($lockResult) && isset($lockResult[0]->locked) && $lockResult[0]->locked == 1) {
            $lockAcquired = true;
        }
    } catch (\Exception $e) {
        $lockAcquired = false;
    }

    if (!$lockAcquired) {
        // Log or abort to prevent processing transaction under concurrent/wrong keys
        return;
    }

    // Mapping of expected setting keys for common payment gateways
    $gatewayExpectedKeys = [
        'stripe' => [
            'publishableKey' => 'client_id',
            'secretKey'      => 'secret',
        ],
        'paypal' => [
            'email'   => 'client_id',
            'sandbox' => 'test_mode',
        ],
        'paypalcheckout' => [
            'clientId'            => 'client_id',
            'clientSecret'        => 'secret',
            'sandboxClientId'     => 'client_id',
            'sandboxClientSecret' => 'secret',
            'sandbox'             => 'test_mode',
        ],
        'paypal_ppcpv' => [
            'clientId'            => 'client_id',
            'clientSecret'        => 'secret',
            'sandboxClientId'     => 'client_id',
            'sandboxClientSecret' => 'secret',
            'useSandbox'          => 'test_mode',
        ],
        'googlepay' => [
            'googleMerchantId' => 'client_id',
            'publicKey'        => 'client_id',
            'privateKey'       => 'secret',
        ],
        'nmi' => [
            'securityKey' => 'secret',
        ],
        'adyen' => [
            'apiKey'    => 'secret',
            'clientKey' => 'client_id',
        ],
        'asiapay' => [
            'merchantid' => 'client_id',
        ]
    ];

    $originals = [];

    foreach ($brandGateways as $bgw) {
        if (empty($bgw['status'])) {
            continue;
        }

        $gatewayName = $bgw['gateway'];
        
        if (!empty($bgw['is_whmcs'])) {
            continue;
        }

        try {
            // Verify if the gateway is activated in WHMCS (at least one setting row exists)
            $existingSettings = Capsule::table('tblpaymentgateways')
                ->where('gateway', $gatewayName)
                ->pluck('value', 'setting')
                ->toArray();

            if (empty($existingSettings)) {
                // Not active/configured at all in WHMCS
                continue;
            }

            $settingsToSet = [];

            // 1. Load expected keys for this gateway
            if (isset($gatewayExpectedKeys[$gatewayName])) {
                foreach ($gatewayExpectedKeys[$gatewayName] as $dbSetting => $bgwField) {
                    if ($bgwField === 'client_id') {
                        $settingsToSet[$dbSetting] = $bgw['client_id'] ?? '';
                    } elseif ($bgwField === 'secret') {
                        $settingsToSet[$dbSetting] = $bgw['secret'] ?? '';
                    } elseif ($bgwField === 'test_mode') {
                        $settingsToSet[$dbSetting] = !empty($bgw['test_mode']) ? 'on' : '';
                    }
                }
            }

            // 2. Load friendly name and currency conversion if configured
            if (!empty($bgw['friendly_name'])) {
                $settingsToSet['name'] = $bgw['friendly_name'];
            }
            if (!empty($bgw['convert_to'])) {
                $curr = Capsule::table('tblcurrencies')->where('code', $bgw['convert_to'])->first();
                if ($curr) {
                    $settingsToSet['convertto'] = $curr->id;
                }
            }

            // 3. Fallback: match any other existing keys in database case-insensitively (for custom/unmapped gateways)
            foreach ($existingSettings as $dbSetting => $dbValue) {
                $sName = strtolower($dbSetting);
                if (in_array($sName, ['clientid', 'client_id', 'client-id', 'publishablekey', 'publishkey', 'googlemerchantid', 'merchantid'])) {
                    $settingsToSet[$dbSetting] = $bgw['client_id'] ?? '';
                } elseif (in_array($sName, ['clientsecret', 'client_secret', 'secret', 'secretkey', 'secret_key', 'apikey', 'securitykey'])) {
                    $settingsToSet[$dbSetting] = $bgw['secret'] ?? '';
                } elseif (in_array($sName, ['testmode', 'test_mode', 'sandbox', 'usesandbox'])) {
                    $currentVal = $dbValue;
                    if ($currentVal === '1' || $currentVal === 1 || is_numeric($currentVal)) {
                        $settingsToSet[$dbSetting] = !empty($bgw['test_mode']) ? $currentVal : '0';
                    } else {
                        $settingsToSet[$dbSetting] = !empty($bgw['test_mode']) ? 'on' : '';
                    }
                }
            }

            // Apply settings
            foreach ($settingsToSet as $dbSetting => $newValue) {
                if (array_key_exists($dbSetting, $existingSettings)) {
                    // Update: backup original value if not already backed up
                    if (!isset($originals[$gatewayName][$dbSetting])) {
                        $originals[$gatewayName][$dbSetting] = [
                            'action' => 'update',
                            'value'  => $existingSettings[$dbSetting]
                        ];
                    }
                    Capsule::table('tblpaymentgateways')
                        ->where('gateway', $gatewayName)
                        ->where('setting', $dbSetting)
                        ->update(['value' => $newValue]);
                } else {
                    // Insert: track as inserted
                    if (!isset($originals[$gatewayName][$dbSetting])) {
                        $originals[$gatewayName][$dbSetting] = [
                            'action' => 'insert'
                        ];
                    }
                    Capsule::table('tblpaymentgateways')->insert([
                        'gateway' => $gatewayName,
                        'setting' => $dbSetting,
                        'value'   => $newValue
                    ]);
                }
            }
        } catch (\Exception $e) {}
    }

    // Register shutdown restore and lock release function
    if (count($originals) > 0 || $lockAcquired) {
        register_shutdown_function(function() use ($originals, $lockAcquired) {
            foreach ($originals as $gwName => $gwSettings) {
                foreach ($gwSettings as $settingName => $info) {
                    try {
                        if ($info['action'] === 'update') {
                            Capsule::table('tblpaymentgateways')
                                ->where('gateway', $gwName)
                                ->where('setting', $settingName)
                                ->update(['value' => $info['value']]);
                        } elseif ($info['action'] === 'insert') {
                            Capsule::table('tblpaymentgateways')
                                ->where('gateway', $gwName)
                                ->where('setting', $settingName)
                                ->delete();
                        }
                    } catch (\Exception $e) {}
                }
            }

            // Release MySQL lock
            if ($lockAcquired) {
                try {
                    Capsule::select("SELECT RELEASE_LOCK('multibrand_gateway_override')");
                } catch (\Exception $e) {}
            }
        });
    }
}
}

// Runtime Execution on Frontend
if (!defined('ADMIN_AREA')) {
    try {
        $requestBrand = get_multibrand_request_brand();
        if ($requestBrand) {
            apply_brand_gateway_overrides($requestBrand);
        }
    } catch (\Exception $e) {}
}

function getalldataofbrand(){
    global $GLOBALS;
    $host = $_SERVER['HTTP_HOST'];
    $systemurl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$host";
    $GLOBALS['CONFIG']['SystemURL'] = $systemurl;
    $GLOBALS['CONFIG']['Domain'] = $host;

    $return['SystemURL'] = $systemurl;
    $return['Domain'] = $host;
    return $return;
}
getalldataofbrand();


