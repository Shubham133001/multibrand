<?php

namespace WHMCS\Module\Addon\Multibrand\Admin;

use WHMCS\Database\Capsule;

class Controller
{
    /**
     * Index action - list all brands
     */
    public function index($vars)
    {
        $modulelink = $vars['modulelink'];

        // Check if table exists
        if (!Capsule::schema()->hasTable('mod_multibrand_brands')) {
            return '<div class="alert alert-warning">The database table "mod_multibrand_brands" is missing. Please deactivate and reactivate the module in Setup > Addon Modules.</div>';
        }

        // Dynamically add status column if it doesn't exist
        if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'status')) {
            try {
                Capsule::schema()->table('mod_multibrand_brands', function ($table) {
                    $table->boolean('status')->default(1)->after('is_default');
                });
            } catch (\Exception $e) {}
        }

        // Dynamically add new billing and settings columns if they don't exist
        $billingColumns = [
            'proforma_invoice' => ['type' => 'boolean', 'default' => 0],
            'invoice_number_branding' => ['type' => 'boolean', 'default' => 0],
            'zero_invoices_number_branding' => ['type' => 'boolean', 'default' => 0],
            'sequential_invoice_number_format' => ['type' => 'string', 'default' => NULL],
            'next_sequential_number' => ['type' => 'integer', 'default' => NULL],
            'brand_currencies' => ['type' => 'text', 'default' => NULL],
            'default_currency' => ['type' => 'string', 'default' => NULL],
            'products_branding' => ['type' => 'boolean', 'default' => 0],
            'price_override' => ['type' => 'boolean', 'default' => 0],
            'brand_switcher' => ['type' => 'boolean', 'default' => 0],
            'ticket_departments' => ['type' => 'text', 'default' => NULL],
            'order_template' => ['type' => 'string', 'default' => NULL],
            'default_language' => ['type' => 'string', 'default' => NULL],
            'auto_client_assignment' => ['type' => 'boolean', 'default' => 0],
            'tos_url' => ['type' => 'string', 'default' => NULL],
            'signature' => ['type' => 'text', 'default' => NULL],
        ];

        foreach ($billingColumns as $col => $info) {
            if (!Capsule::schema()->hasColumn('mod_multibrand_brands', $col)) {
                try {
                    Capsule::schema()->table('mod_multibrand_brands', function ($table) use ($col, $info) {
                        if ($info['type'] == 'boolean') {
                            $table->boolean($col)->default($info['default']);
                        } elseif ($info['type'] == 'integer') {
                            $table->integer($col)->nullable();
                        } elseif ($info['type'] == 'text') {
                            $table->text($col)->nullable();
                        } else {
                            $table->string($col)->nullable();
                        }
                    });
                } catch (\Exception $e) {}
            }
        }

        // Migrate client-brand unique index to support multiple brands
        if (Capsule::schema()->hasTable('mod_multibrand_client_brands')) {
            try {
                Capsule::schema()->table('mod_multibrand_client_brands', function ($table) {
                    try {
                        $table->dropUnique('mod_multibrand_client_brands_client_id_unique');
                    } catch (\Exception $e) {}
                    try {
                        $table->unique(['client_id', 'brand_id']);
                    } catch (\Exception $e) {}
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $brands = Capsule::table('mod_multibrand_brands')->get();

        $output = '<h2>Multi Brand Management</h2>';
        $output .= '<p>Manage multiple brand identities with unique names, logos, and system settings.</p>';

        $output .= '<div class="mb-10" style="margin-bottom: 20px;">
            <a href="' . $modulelink . '&action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Brand
            </a>
        </div>';

        $output .= '<table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
            <thead>
                <tr>
                    <th>Brand Name</th>
                    <th>Domain</th>
                    <th class="text-center" width="120">Status</th>
                    <th>Created At</th>
                    <th class="text-center" width="150">Actions</th>
                </tr>
            </thead>
            <tbody>';

        if (count($brands) > 0) {
            foreach ($brands as $brand) {
                $statusBadge = (!isset($brand->status) || $brand->status)
                    ? '<span class="label label-success" style="background-color: #5cb85c; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; display: inline-block;">ENABLED</span>'
                    : '<span class="label label-default" style="background-color: #777; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; display: inline-block;">DISABLED</span>';

                $createdAt = isset($brand->created_at) && $brand->created_at ? $brand->created_at : '-';

                $output .= '<tr>
                    <td>' . htmlspecialchars($brand->brand_name) . '</td>
                    <td><a href="http://' . htmlspecialchars($brand->domain) . '" target="_blank">' . htmlspecialchars($brand->domain) . '</a></td>
                    <td class="text-center">' . $statusBadge . '</td>
                    <td>' . htmlspecialchars($createdAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=relations&id=' . $brand->id . '" class="btn btn-sm btn-info" style="margin-right: 5px;" title="Relations Dashboard"><i class="fas fa-exchange-alt"></i></a>
                        <a href="' . $modulelink . '&action=edit&id=' . $brand->id . '" class="btn btn-sm btn-primary" style="margin-right: 5px;" title="Edit Brand"><i class="fas fa-edit"></i></a>
                        <a href="' . $modulelink . '&action=delete&id=' . $brand->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this brand?\')" title="Delete Brand"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="5" class="text-center">No brands found. Click "Add New Brand" to create one.</td></tr>';
        }

        $output .= '</tbody></table>';

        return print_r($output);
    }

    /**
     * Set brand as default
     */
    public function set_default($vars)
    {
        $id = (int) $_REQUEST['id'];

        try {
            // Unset current default
            Capsule::table('mod_multibrand_brands')->where('is_default', 1)->update(['is_default' => 0]);

            // Set new default
            Capsule::table('mod_multibrand_brands')->where('id', $id)->update(['is_default' => 1]);

            echo '<div class="alert alert-success">Brand set as default successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
        }

        return $this->index($vars);
    }

    /**
     * Add brand form
     */
    public function add($vars)
    {
        return $this->renderForm($vars);
    }

    /**
     * Consolidated Brand Details & Edit Dashboard
     */
    public function edit($vars)
    {
        $id = (int) $_REQUEST['id'];
        $modulelink = $vars['modulelink'];
        $brand = Capsule::table('mod_multibrand_brands')->where('id', $id)->first();

        if (!$brand) {
            return '<div class="alert alert-danger">Brand not found.</div>' . $this->index($vars);
        }

        // Dynamically migrate mod_multibrand_kb_brands to rename kb_id to article_id and update unique index
        if (Capsule::schema()->hasTable('mod_multibrand_kb_brands')) {
            if (!Capsule::schema()->hasColumn('mod_multibrand_kb_brands', 'article_id')) {
                try {
                    $existingRows = Capsule::table('mod_multibrand_kb_brands')->get()->toArray();
                    Capsule::schema()->dropIfExists('mod_multibrand_kb_brands');
                    Capsule::schema()->create('mod_multibrand_kb_brands', function ($table) {
                        $table->increments('id');
                        $table->integer('article_id');
                        $table->integer('brand_id');
                        $table->timestamps();
                        $table->unique(['article_id', 'brand_id']);
                    });
                    foreach ($existingRows as $row) {
                        $rowArr = (array) $row;
                        $articleId = isset($rowArr['kb_id']) ? $rowArr['kb_id'] : (isset($rowArr['article_id']) ? $rowArr['article_id'] : 0);
                        if ($articleId > 0) {
                            Capsule::table('mod_multibrand_kb_brands')->insert([
                                'article_id' => $articleId,
                                'brand_id' => $rowArr['brand_id'],
                                'created_at' => isset($rowArr['created_at']) ? $rowArr['created_at'] : date('Y-m-d H:i:s'),
                                'updated_at' => isset($rowArr['updated_at']) ? $rowArr['updated_at'] : date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    try {
                        Capsule::schema()->create('mod_multibrand_kb_brands', function ($table) {
                            $table->increments('id');
                            $table->integer('article_id');
                            $table->integer('brand_id');
                            $table->timestamps();
                            $table->unique(['article_id', 'brand_id']);
                        });
                    } catch (\Exception $ex) {}
                }
            } else {
                try {
                    Capsule::schema()->table('mod_multibrand_kb_brands', function ($table) {
                        try {
                            $table->dropUnique('mod_multibrand_kb_brands_kb_id_unique');
                        } catch (\Exception $e) {}
                        try {
                            $table->dropUnique('kb_id');
                        } catch (\Exception $e) {}
                        try {
                            $table->dropUnique('mod_multibrand_kb_brands_article_id_unique');
                        } catch (\Exception $e) {}
                        try {
                            $table->dropUnique('article_id');
                        } catch (\Exception $e) {}
                        try {
                            $table->unique(['article_id', 'brand_id']);
                        } catch (\Exception $e) {}
                    });
                } catch (\Exception $e) {}
            }
        } else {
            try {
                Capsule::schema()->create('mod_multibrand_kb_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('article_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['article_id', 'brand_id']);
                });
            } catch (\Exception $e) {}
        }

        // Dynamically migrate mod_multibrand_download_brands to update unique index to composite unique key
        if (Capsule::schema()->hasTable('mod_multibrand_download_brands')) {
            try {
                Capsule::schema()->table('mod_multibrand_download_brands', function ($table) {
                    try {
                        $table->dropUnique('mod_multibrand_download_brands_download_id_unique');
                    } catch (\Exception $e) {}
                    try {
                        $table->dropUnique('download_id');
                    } catch (\Exception $e) {}
                    try {
                        $table->unique(['download_id', 'brand_id']);
                    } catch (\Exception $e) {}
                });
            } catch (\Exception $e) {
                try {
                    $existingRows = Capsule::table('mod_multibrand_download_brands')->get()->toArray();
                    Capsule::schema()->dropIfExists('mod_multibrand_download_brands');
                    Capsule::schema()->create('mod_multibrand_download_brands', function ($table) {
                        $table->increments('id');
                        $table->integer('download_id');
                        $table->integer('brand_id');
                        $table->timestamps();
                        $table->unique(['download_id', 'brand_id']);
                    });
                    foreach ($existingRows as $row) {
                        $rowArr = (array) $row;
                        Capsule::table('mod_multibrand_download_brands')->insert([
                            'download_id' => $rowArr['download_id'],
                            'brand_id' => $rowArr['brand_id'],
                            'created_at' => isset($rowArr['created_at']) ? $rowArr['created_at'] : date('Y-m-d H:i:s'),
                            'updated_at' => isset($rowArr['updated_at']) ? $rowArr['updated_at'] : date('Y-m-d H:i:s'),
                        ]);
                    }
                } catch (\Exception $ex) {}
            }
        } else {
            try {
                Capsule::schema()->create('mod_multibrand_download_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('download_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['download_id', 'brand_id']);
                });
            } catch (\Exception $e) {}
        }

        // Dynamically migrate mod_multibrand_announcement_brands to update unique index to composite unique key
        if (Capsule::schema()->hasTable('mod_multibrand_announcement_brands')) {
            try {
                Capsule::schema()->table('mod_multibrand_announcement_brands', function ($table) {
                    try {
                        $table->dropUnique('mod_multibrand_announcement_brands_announcement_id_unique');
                    } catch (\Exception $e) {}
                    try {
                        $table->dropUnique('announcement_id');
                    } catch (\Exception $e) {}
                    try {
                        $table->dropUnique('mod_ann_brand_unique');
                    } catch (\Exception $e) {}
                    try {
                        $table->unique(['announcement_id', 'brand_id'], 'mod_ann_brand_unique');
                    } catch (\Exception $e) {}
                });
            } catch (\Exception $e) {
                try {
                    $existingRows = Capsule::table('mod_multibrand_announcement_brands')->get()->toArray();
                    Capsule::schema()->dropIfExists('mod_multibrand_announcement_brands');
                    Capsule::schema()->create('mod_multibrand_announcement_brands', function ($table) {
                        $table->increments('id');
                        $table->integer('announcement_id');
                        $table->integer('brand_id');
                        $table->timestamps();
                        $table->unique(['announcement_id', 'brand_id'], 'mod_ann_brand_unique');
                    });
                    foreach ($existingRows as $row) {
                        $rowArr = (array) $row;
                        Capsule::table('mod_multibrand_announcement_brands')->insert([
                            'announcement_id' => $rowArr['announcement_id'],
                            'brand_id' => $rowArr['brand_id'],
                            'created_at' => isset($rowArr['created_at']) ? $rowArr['created_at'] : date('Y-m-d H:i:s'),
                            'updated_at' => isset($rowArr['updated_at']) ? $rowArr['updated_at'] : date('Y-m-d H:i:s'),
                        ]);
                    }
                } catch (\Exception $ex) {}
            }
        } else {
            try {
                Capsule::schema()->create('mod_multibrand_announcement_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('announcement_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['announcement_id', 'brand_id'], 'mod_ann_brand_unique');
                });
            } catch (\Exception $e) {}
        }

        // Dynamically create mod_multibrand_promotion_brands if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_promotion_brands')) {
            try {
                Capsule::schema()->create('mod_multibrand_promotion_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('promotion_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['promotion_id', 'brand_id'], 'mod_promo_brand_unique');
                });
            } catch (\Exception $e) {}
        }

        // Dynamically create mod_multibrand_billable_brands if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_billable_brands')) {
            try {
                Capsule::schema()->create('mod_multibrand_billable_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('billable_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['billable_id', 'brand_id'], 'mod_bill_brand_unique');
                });
            } catch (\Exception $e) {}
        }

        // Dynamically create mod_multibrand_email_brands if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_email_brands')) {
            try {
                Capsule::schema()->create('mod_multibrand_email_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('email_id');
                    $table->integer('brand_id');
                    $table->timestamps();
                    $table->unique(['email_id', 'brand_id'], 'mod_email_brand_unique');
                });
            } catch (\Exception $e) {}
        }


        // Get themes
        $themes = $this->getAvailableThemes();
        $brandColor = ($brand->brand_color ?: '#d9534f');

        // Get order templates
        $orderTemplates = $this->getAvailableOrderTemplates();

        // Get languages
        $languages = $this->getAvailableLanguages();

        // Get ticket departments
        $departments = [];
        $deptMap = [];
        try {
            $departments = Capsule::table('tblticketdepartments')->orderBy('name', 'asc')->get();
            foreach ($departments as $dept) {
                $deptMap[$dept->id] = $dept->name;
            }
        } catch (\Exception $e) {}

        // Fetch active currencies in WHMCS
        $currencies = [];
        try {
            $currencies = Capsule::table('tblcurrencies')->orderBy('code', 'asc')->get();
        } catch (\Exception $e) {}

        // 1. Calculate Brand Information metrics
        $clientIds = Capsule::table('mod_multibrand_client_brands')
            ->where('brand_id', $brand->id)
            ->pluck('client_id')
            ->toArray();
        $clientsCount = count($clientIds);

        // Fetch brand-specific Knowledgebase articles relations
        $kbArticles = [];
        try {
            $kbArticles = Capsule::table('tblknowledgebase')
                ->join('mod_multibrand_kb_brands', 'tblknowledgebase.id', '=', 'mod_multibrand_kb_brands.article_id')
                ->where('mod_multibrand_kb_brands.brand_id', $brand->id)
                ->select('tblknowledgebase.*', 'mod_multibrand_kb_brands.created_at as assigned_at')
                ->get();
        } catch (\Exception $e) {}
        $kbCount = count($kbArticles);

        // Fetch unmapped KB articles for the Add modal dropdown selection
        $availableArticles = [];
        try {
            $assignedKbIds = Capsule::table('mod_multibrand_kb_brands')
                ->where('brand_id', $brand->id)
                ->pluck('article_id')
                ->toArray();
            $queryAvailable = Capsule::table('tblknowledgebase');
            if (!empty($assignedKbIds)) {
                $queryAvailable = $queryAvailable->whereNotIn('id', $assignedKbIds);
            }
            $availableArticles = $queryAvailable->where('parentid', 0)->get();
        } catch (\Exception $e) {}

        // Fetch brand-specific Downloads relations
        $downloadArticles = [];
        try {
            $downloadArticles = Capsule::table('tbldownloads')
                ->join('mod_multibrand_download_brands', 'tbldownloads.id', '=', 'mod_multibrand_download_brands.download_id')
                ->leftJoin('tbldownloadcats', 'tbldownloads.category', '=', 'tbldownloadcats.id')
                ->where('mod_multibrand_download_brands.brand_id', $brand->id)
                ->select(
                    'tbldownloads.*', 
                    'tbldownloadcats.name as category_name',
                    'mod_multibrand_download_brands.created_at as assigned_at'
                )
                ->get();
        } catch (\Exception $e) {}
        $downloadCount = count($downloadArticles);

        // Fetch unmapped Downloads for the Add modal dropdown selection
        $availableDownloads = [];
        try {
            $assignedDownloadIds = Capsule::table('mod_multibrand_download_brands')
                ->where('brand_id', $brand->id)
                ->pluck('download_id')
                ->toArray();
            $queryAvail = Capsule::table('tbldownloads')
                ->leftJoin('tbldownloadcats', 'tbldownloads.category', '=', 'tbldownloadcats.id')
                ->select('tbldownloads.*', 'tbldownloadcats.name as category_name');
            if (!empty($assignedDownloadIds)) {
                $queryAvail = $queryAvail->whereNotIn('tbldownloads.id', $assignedDownloadIds);
            }
            $availableDownloads = $queryAvail->get();
        } catch (\Exception $e) {}

        // Fetch brand-specific Announcements relations
        $announcementArticles = [];
        try {
            $announcementArticles = Capsule::table('tblannouncements')
                ->join('mod_multibrand_announcement_brands', 'tblannouncements.id', '=', 'mod_multibrand_announcement_brands.announcement_id')
                ->where('mod_multibrand_announcement_brands.brand_id', $brand->id)
                ->select('tblannouncements.*', 'mod_multibrand_announcement_brands.created_at as assigned_at')
                ->get();
        } catch (\Exception $e) {}
        $announcementCount = count($announcementArticles);

        // Fetch unmapped Announcements for the Add modal dropdown selection
        $availableAnnouncements = [];
        try {
            $assignedAnnounceIds = Capsule::table('mod_multibrand_announcement_brands')
                ->where('brand_id', $brand->id)
                ->pluck('announcement_id')
                ->toArray();
            $queryAvailAnn = Capsule::table('tblannouncements');
            if (!empty($assignedAnnounceIds)) {
                $queryAvailAnn = $queryAvailAnn->whereNotIn('id', $assignedAnnounceIds);
            }
            $availableAnnouncements = $queryAvailAnn->where('parentid', 0)->get();
        } catch (\Exception $e) {}

        // Fetch brand-specific Promotions relations
        $promoArticles = [];
        try {
            $promoArticles = Capsule::table('tblpromotions')
                ->join('mod_multibrand_promotion_brands', 'tblpromotions.id', '=', 'mod_multibrand_promotion_brands.promotion_id')
                ->where('mod_multibrand_promotion_brands.brand_id', $brand->id)
                ->select('tblpromotions.*', 'mod_multibrand_promotion_brands.created_at as assigned_at')
                ->get();
        } catch (\Exception $e) {}
        $promoCount = count($promoArticles);

        // Fetch unmapped Promotions for the Add modal dropdown selection
        $availablePromotions = [];
        try {
            $assignedPromoIds = Capsule::table('mod_multibrand_promotion_brands')
                ->where('brand_id', $brand->id)
                ->pluck('promotion_id')
                ->toArray();
            $queryAvailPromo = Capsule::table('tblpromotions');
            if (!empty($assignedPromoIds)) {
                $queryAvailPromo = $queryAvailPromo->whereNotIn('id', $assignedPromoIds);
            }
            $availablePromotions = $queryAvailPromo->get();
        } catch (\Exception $e) {}

        $thisMonthSale = 0;
        if (!empty($clientIds)) {
            try {
                $thisMonthSale = Capsule::table('tblinvoices')
                    ->whereIn('userid', $clientIds)
                    ->where('date', '>=', date('Y-m-01'))
                    ->sum('total');
            } catch (\Exception $e) {}
        }

        $allTimeSale = 0;
        if (!empty($clientIds)) {
            try {
                $allTimeSale = Capsule::table('tblinvoices')
                    ->whereIn('userid', $clientIds)
                    ->sum('total');
            } catch (\Exception $e) {}
        }

        $thisMonthSaleFormatted = '$' . number_format($thisMonthSale, 2) . ' USD';
        $allTimeSaleFormatted = '$' . number_format($allTimeSale, 2) . ' USD';
        $updatedAt = isset($brand->updated_at) && $brand->updated_at ? $brand->updated_at : '-';

        // 2. Fetch WHMCS Products, Addons, and Domains
        $products = [];
        try {
            $products = Capsule::table('tblproducts')->orderBy('name', 'asc')->get();
        } catch (\Exception $e) {}

        $addons = [];
        try {
            $addons = Capsule::table('tbladdons')->orderBy('name', 'asc')->get();
        } catch (\Exception $e) {}

        $domains = [];
        try {
            $domains = Capsule::table('tbldomainpricing')->orderBy('extension', 'asc')->get();
        } catch (\Exception $e) {}

        // 3. Fetch Relations (Clients, Services, Invoices)
        $clients = [];
        if (!empty($clientIds)) {
            $clients = Capsule::table('tblclients')
                ->whereIn('id', $clientIds)
                ->get();
        }

        $services = [];
        if (!empty($clientIds)) {
            $services = Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                ->where('mod_multibrand_service_brands.brand_id', $brand->id)
                ->whereIn('tblhosting.userid', $clientIds)
                ->select(
                    'tblhosting.*', 
                    'tblproducts.name as product_name', 
                    'tblclients.firstname', 
                    'tblclients.lastname'
                )
                ->get();
        }

        $invoices = [];
        if (!empty($clientIds)) {
            $invoices = Capsule::table('tblinvoices')
                ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->leftJoin('mod_multibrand_brands', 'mod_multibrand_invoice_brands.brand_id', '=', 'mod_multibrand_brands.id')
                ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                ->whereIn('tblinvoices.userid', $clientIds)
                ->select(
                    'tblinvoices.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname',
                    'mod_multibrand_invoice_brands.brand_id',
                    'mod_multibrand_brands.brand_name'
                )
                ->get();
        }

        $quotes = [];
        if (!empty($clientIds)) {
            try {
                $quotes = Capsule::table('tblquotes')
                    ->join('tblclients', 'tblquotes.userid', '=', 'tblclients.id')
                    ->whereIn('tblquotes.userid', $clientIds)
                    ->select('tblquotes.*', 'tblclients.firstname', 'tblclients.lastname')
                    ->get();
            } catch (\Exception $e) {}
        }

        $tickets = [];
        try {
            $deptIds = array_filter(array_map('intval', explode(',', $brand->ticket_departments ?: '')));
            $query = Capsule::table('tbltickets')
                ->join('tblclients', 'tbltickets.userid', '=', 'tblclients.id');
            
            if (!empty($clientIds) && !empty($deptIds)) {
                $query->where(function($q) use ($clientIds, $deptIds) {
                    $q->whereIn('tbltickets.userid', $clientIds)
                      ->orWhereIn('tbltickets.deptid', $deptIds);
                });
            } elseif (!empty($clientIds)) {
                $query->whereIn('tbltickets.userid', $clientIds);
            } elseif (!empty($deptIds)) {
                $query->whereIn('tbltickets.deptid', $deptIds);
            } else {
                $query->whereRaw('1 = 0');
            }
            
            $tickets = $query->select('tbltickets.*', 'tblclients.firstname', 'tblclients.lastname')
                ->get();
        } catch (\Exception $e) {}

        $billableItems = [];
        try {
            $billableItems = Capsule::table('tblbillableitems')
                ->join('mod_multibrand_billable_brands', 'tblbillableitems.id', '=', 'mod_multibrand_billable_brands.billable_id')
                ->join('tblclients', 'tblbillableitems.userid', '=', 'tblclients.id')
                ->where('mod_multibrand_billable_brands.brand_id', $brand->id)
                ->select(
                    'tblbillableitems.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname',
                    'mod_multibrand_billable_brands.created_at as assigned_at'
                )
                ->get();
        } catch (\Exception $e) {}
        $billableCount = count($billableItems);

        // Fetch unmapped Billable Items for the Add modal dropdown selection
        $availableBillables = [];
        try {
            $assignedBillableIds = Capsule::table('mod_multibrand_billable_brands')
                ->where('brand_id', $brand->id)
                ->pluck('billable_id')
                ->toArray();
            $queryAvailBill = Capsule::table('tblbillableitems')
                ->join('tblclients', 'tblbillableitems.userid', '=', 'tblclients.id')
                ->select('tblbillableitems.*', 'tblclients.firstname', 'tblclients.lastname');
            if (!empty($assignedBillableIds)) {
                $queryAvailBill = $queryAvailBill->whereNotIn('tblbillableitems.id', $assignedBillableIds);
            }
            $availableBillables = $queryAvailBill->get();
        } catch (\Exception $e) {}

        $emails = [];
        try {
            $emails = Capsule::table('tblemails')
                ->join('mod_multibrand_email_brands', 'tblemails.id', '=', 'mod_multibrand_email_brands.email_id')
                ->join('tblclients', 'tblemails.userid', '=', 'tblclients.id')
                ->where('mod_multibrand_email_brands.brand_id', $brand->id)
                ->select(
                    'tblemails.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname',
                    'mod_multibrand_email_brands.created_at as assigned_at'
                )
                ->get();
        } catch (\Exception $e) {}
        $emailCount = count($emails);

        // Fetch unmapped Email logs for the Add modal dropdown selection
        $availableEmails = [];
        try {
            $assignedEmailIds = Capsule::table('mod_multibrand_email_brands')
                ->where('brand_id', $brand->id)
                ->pluck('email_id')
                ->toArray();
            $queryAvailEmail = Capsule::table('tblemails')
                ->join('tblclients', 'tblemails.userid', '=', 'tblclients.id')
                ->select(
                    'tblemails.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname', 
                    'tblclients.companyname', 
                    'tblclients.email as client_email'
                );
            if (!empty($assignedEmailIds)) {
                $queryAvailEmail = $queryAvailEmail->whereNotIn('tblemails.id', $assignedEmailIds);
            }
            $availableEmails = $queryAvailEmail->get();
        } catch (\Exception $e) {}


        // Output CSS Styles and HTML Structure matching the screenshots
        $output = '
        <style>
            .relations-tabs {
                border-bottom: 1px solid #ddd !important;
                margin-bottom: 25px !important;
                display: flex !important;
                flex-wrap: wrap !important;
                list-style: none !important;
                padding: 0 !important;
            }
            .relations-tabs li {
                margin-bottom: -1px !important;
            }
            .relations-tabs li a {
                display: block !important;
                padding: 10px 16px !important;
                font-size: 0.9em !important;
                color: #555 !important;
                text-decoration: none !important;
                font-weight: 600 !important;
                border-bottom: 3px solid transparent !important;
                transition: all 0.2s ease !important;
            }
            .relations-tabs li a:hover {
                color: #333 !important;
                background: #f5f5f5 !important;
                border-radius: 4px 4px 0 0 !important;
            }
            .relations-tabs li.active a {
                color: ' . $brandColor . ' !important;
                border-bottom: 3px solid ' . $brandColor . ' !important;
                font-weight: bold !important;
            }
            .action-bar-right {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .action-circle-btn {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: 1px solid #ccc;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #555;
                background: #fff;
                cursor: pointer;
                transition: all 0.15s ease;
                text-decoration: none;
            }
            .action-circle-btn:hover {
                background: #f0f0f0;
                color: #000;
                border-color: #999;
                text-decoration: none;
            }
            
            /* Custom Settings Nav Tabs Styling to match mockup perfectly */
            .nav-tabs {
                border-bottom: 1px solid #e1e1e1 !important;
            }
            .nav-tabs li {
                margin-bottom: -1px !important;
            }
            .nav-tabs li a {
                border: none !important;
                background: transparent !important;
                color: #555 !important;
                font-weight: 600 !important;
                padding: 10px 16px !important;
                border-bottom: 3px solid transparent !important;
                transition: all 0.2s ease !important;
                font-size: 0.95em !important;
            }
            .nav-tabs li.active a {
                color: ' . $brandColor . ' !important;
                border-bottom: 3px solid ' . $brandColor . ' !important;
                font-weight: bold !important;
            }
            .nav-tabs li a:hover {
                color: #333 !important;
                border-bottom-color: #ccc !important;
            }
            
            /* Premium Switch Toggle with Text Inside */
            .mb-switch {
                position: relative;
                display: inline-block;
                width: 90px !important;
                height: 28px !important;
                margin: 0 !important;
                padding: 0 !important;
                cursor: pointer;
            }
            .mb-switch input {
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
            }
            .mb-slider {
                position: absolute !important;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #ccc !important;
                transition: .3s !important;
                border-radius: 4px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: flex-end !important;
                padding-right: 8px !important;
                user-select: none;
            }
            .mb-slider:after {
                content: "Disabled" !important;
                color: #fff !important;
                font-size: 10px !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
            }
            .mb-slider:before {
                position: absolute !important;
                content: "" !important;
                height: 20px !important;
                width: 20px !important;
                left: 4px !important;
                bottom: 4px !important;
                background-color: white !important;
                transition: .3s !important;
                border-radius: 2px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.3) !important;
            }
            .mb-switch input:checked + .mb-slider {
                background-color: #5cb85c !important;
                justify-content: flex-start !important;
                padding-left: 8px !important;
                padding-right: 0 !important;
            }
            .mb-switch input:checked + .mb-slider:after {
                content: "Enabled" !important;
            }
            .mb-switch input:checked + .mb-slider:before {
                transform: translateX(62px) !important;
            }
            
            /* Currencies Tag Selector Tags */
            .currency-tag {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #f8f9fa;
                border: 1px solid #dcdcdc;
                padding: 4px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 0.88em;
                margin: 0;
                font-weight: bold;
                color: #555;
                transition: all 0.15s ease;
                user-select: none;
            }
            .currency-tag:hover {
                background: #e9ecef;
                border-color: #ccc;
                color: #333;
                text-decoration: none;
            }
            .currency-tag.selected {
                background: ' . $brandColor . ' !important;
                color: #fff !important;
                border-color: ' . $brandColor . ' !important;
            }
            .currency-tag .tag-close {
                font-weight: 800;
                opacity: 0.6;
                font-size: 1.1em;
            }
            .currency-tag.selected .tag-close {
                opacity: 1;
            }
            
            /* Standard form fields helpers */
            .input-300 { width: 300px !important; }
            .input-400 { width: 400px !important; }
            .flex-form-group {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }
            .flex-form-label {
                width: 200px;
                font-weight: 600;
                color: #555;
                font-size: 0.95em;
            }
            .select2-container {
                width: 100% !important;
            }
            .select2-container .select2-selection--multiple {
                min-height: 34px !important;
                border: 1px solid #ccc !important;
                border-radius: 4px !important;
            }
        </style>

        <!-- Breadcrumb / Header -->
        <div style="font-family: \'Outfit\', \'Segoe UI\', sans-serif; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
            <div style="font-size: 1.15em; color: #555;">
                <a href="' . $modulelink . '" style="color: #007bff; text-decoration: none;">Brands</a> / <span style="font-weight: 600; color: #333;">Details</span>
            </div>
            <div>
                <a href="' . $modulelink . '" class="btn btn-default" style="border-radius: 4px;"><i class="fas fa-arrow-left"></i> Back to Brands</a>
            </div>
        </div>

        <form method="post" action="' . $modulelink . '&action=save" enctype="multipart/form-data">
            <input type="hidden" name="id" value="' . $brand->id . '">
            <input type="hidden" name="status_submitted" value="1">
            
            <!-- TOP ROW (BRAND INFORMATION & SETTINGS) -->
            <div style="display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 30px; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
                
                <!-- LEFT CARD: BRAND INFORMATION -->
                <div style="flex: 1; min-width: 320px; max-width: 380px;">
                    <div style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; height: 100%;">
                        <div style="padding: 15px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-info-circle" style="margin-right: 8px;"></i> Brand Information</span>
                            <a class="action-circle-btn" style="width: 24px; height: 24px; font-size: 0.8em;" href=""><i class="fas fa-sync-alt"></i></a>
                        </div>
                        
                        <div style="padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6;">
                                <span style="color: #666; font-size: 0.95em;">Name</span>
                                <span style="font-weight: 700; color: #fff; background: ' . $brandColor . '; padding: 4px 12px; border-radius: 4px; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($brand->brand_name) . '</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6;">
                                <span style="color: #666; font-size: 0.95em;">Domain</span>
                                <span style="font-weight: 500; color: #444; font-size: 0.95em;">' . htmlspecialchars($brand->domain) . '</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6;">
                                <span style="color: #666; font-size: 0.95em;">Active Clients</span>
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: #17a2b8; color: #fff; border-radius: 50%; font-size: 0.8em; font-weight: bold;">' . $clientsCount . '</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6;">
                                <span style="color: #666; font-size: 0.95em;">This Month Sale</span>
                                <span style="font-weight: bold; color: #28a745; font-size: 1.05em;">' . $thisMonthSaleFormatted . '</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6;">
                                <span style="color: #666; font-size: 0.95em;">All Time Sale</span>
                                <span style="font-weight: bold; color: #333; font-size: 1.05em;">' . $allTimeSaleFormatted . '</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f6f6f6; margin-bottom: 25px;">
                                <span style="color: #666; font-size: 0.95em;">Updated On</span>
                                <span style="color: #555; font-size: 0.9em;">' . htmlspecialchars($updatedAt) . '</span>
                            </div>
                            
                            <!-- LOGO BOX -->
                            <div style="border: 1px solid #e2e2e2; border-radius: 6px; padding: 20px; text-align: center; background: #fafafa; margin-bottom: 15px;">';
        if ($brand->logo_url) {
            $output .= '<img src="' . htmlspecialchars($brand->logo_url) . '" style="max-height: 80px; max-width: 100%; object-fit: contain;" alt="Brand Logo">';
        } else {
            $output .= '<span style="color: #aaa; font-style: italic;">No Logo Selected</span>';
        }
        $output .= '        </div>
                            <div style="font-size: 0.82em; color: #888; text-align: center; line-height: 1.4;">
                                You can set a custom logo for the current brand. Click save in the settings box to apply.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- RIGHT CARD: SETTINGS -->
                <div style="flex: 2; min-width: 500px;">
                    <div style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; display: flex; flex-direction: column; height: 100%;">
                        <div style="padding: 10px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-cog" style="margin-right: 8px;"></i> Settings</span>
                            <ul class="nav nav-tabs" style="border-bottom: none; margin: 0;">
                                <li class="active"><a href="#set-general" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">General</a></li>
                                <li><a href="#set-billing" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Billing</a></li>
                                <li><a href="#set-gateways" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Payment Gateways</a></li>
                                <li><a href="#set-smtp" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">SMTP</a></li>
                                <li><a href="#set-emails" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Email Templates</a></li>
                                <li><a href="#set-maintenance" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Maintenance</a></li>
                            </ul>
                        </div>
                        
                        <div class="tab-content" style="padding: 25px; flex-grow: 1;">
                            <!-- GENERAL TAB -->
                            <div class="tab-pane active" id="set-general" style="padding-top: 10px;">
                                
                                <!-- Row 1 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Default Brand -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Default Brand</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="is_default" value="1" ' . ($brand->is_default ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">If enabled, this brand will be marked as the default one. Only one brand can be set as default.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Brand Switcher -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Brand Switcher</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="brand_switcher" value="1" ' . ($brand->brand_switcher ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Allow clients to switch between brands. If a client is assigned to one brand only, the switcher is not displayed in the client area navigation menu.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Status -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Status</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="status" value="1" ' . ($brand->status ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The current brand\'s status. Enable this option to set up a branding for this domain.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Ticket Department -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Ticket Department</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <select name="ticket_departments[]" class="form-control select2" multiple data-placeholder="Select ticket departments..." style="width: 100%;">
                                                    ';
        $selectedDepts = explode(',', $brand->ticket_departments ?: '');
        foreach ($departments as $dept) {
            $selected = in_array($dept->id, $selectedDepts) ? 'selected' : '';
            $output .= '<option value="' . $dept->id . '" ' . $selected . '>' . htmlspecialchars($dept->name) . '</option>';
        }
        $output .= '
                                                </select>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Select ticket departments that will be assigned to this brand. This will allow you to determine which departments should be used to answer tickets opened by the brand\'s clients.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 3 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Products / Services Branding -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Products / Services Branding</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="products_branding" value="1" ' . ($brand->products_branding ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">This option allows you to assign WHMCS products/addons/domains to the brand.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Template -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Template</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <select name="system_theme" class="form-control" style="width: 100%;">
                                                    ';
        foreach ($themes as $theme) {
            $selected = ($brand->system_theme == $theme) ? ' selected' : '';
            $output .= '<option value="' . $theme . '"' . $selected . '>' . ucfirst($theme) . '</option>';
        }
        $output .= '
                                                </select>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Select a template that will be used in the brand\'s client area.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 4 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Price Override -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Price Override</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="price_override" value="1" ' . ($brand->price_override ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Enable this option to use the brand pricing instead of the WHMCS pricing.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Order Template -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Order Template</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <select name="order_template" class="form-control" style="width: 100%;">
                                                    ';
        foreach ($orderTemplates as $ot) {
            $selected = ($brand->order_template == $ot) ? ' selected' : '';
            $output .= '<option value="' . $ot . '"' . $selected . '>' . ucfirst($ot) . '</option>';
        }
        $output .= '
                                                </select>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Select an order template that will be used in the brand\'s cart.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 5 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Color -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Color</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <div style="display: flex !important; align-items: center !important; gap: 8px !important;">
                                                    <input type="color" name="brand_color" value="' . ($brand->brand_color ?: '#2162a3') . '" class="form-control" style="width: 50px; height: 32px; padding: 2px; border-radius: 4px;">
                                                    <input type="text" class="form-control" style="width: 100px; text-transform: uppercase; font-weight: bold; background: #fff;" value="' . ltrim($brand->brand_color ?: '2162A3', '#') . '" readonly>
                                                </div>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The selected color will be used only in the admin area to help you quickly find features of the brand.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Default Language -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Default Language</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <select name="default_language" class="form-control" style="width: 100%;">
                                                    ';
        foreach ($languages as $lang) {
            $selected = ($brand->default_language == $lang) ? ' selected' : '';
            $output .= '<option value="' . $lang . '"' . $selected . '>' . ucfirst($lang) . '</option>';
        }
        $output .= '
                                                </select>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The selected language will be set as default for the current brand.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 6 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- System URL -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">System URL</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="system_url" value="' . htmlspecialchars($brand->system_url) . '" class="form-control" style="width: 100%;" required>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The URL to your WHMCS installation through branded domain</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Auto Client Assignment -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 4px !important; flex-shrink: 0 !important;">Auto Client Assignment</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; min-width: 0 !important;">
                                                <label class="mb-switch" style="flex-shrink: 0 !important; margin-bottom: 0 !important;">
                                                    <input type="checkbox" name="auto_client_assignment" value="1" ' . ($brand->auto_client_assignment ? 'checked' : '') . '>
                                                    <span class="mb-slider"></span>
                                                </label>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">When this option is enabled, a client who logs in to this brand will be automatically assigned to it.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 7 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Email Address -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Email Address</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="email" name="email_address" value="' . htmlspecialchars($brand->email_address) . '" class="form-control" style="width: 100%;" required>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The brand\'s email address. All branded messages will use this email address for the FROM field.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- TOS URL -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">TOS URL</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="tos_url" value="' . htmlspecialchars($brand->tos_url) . '" class="form-control" style="width: 100%;">
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The URL of Terms Of Service. A URL starts with either \'http\' or \'https\'.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 8 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Company Name -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Company Name</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="company_name" value="' . htmlspecialchars($brand->company_name) . '" class="form-control" style="width: 100%;" required>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">This is the name that will be displayed to clients in the branded client area.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Signature -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Signature</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <textarea name="signature" rows="4" class="form-control" style="width: 100%; font-family: inherit;">' . htmlspecialchars($brand->signature) . '</textarea>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Provide an email signature for the current brand.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 9 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Domain -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Domain</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="domain" value="' . htmlspecialchars($brand->domain) . '" class="form-control" style="width: 100%;" required>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The branded domain configured for this brand (e.g. example.com).</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Brand Name -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Brand Name</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="brand_name" value="' . htmlspecialchars($brand->brand_name) . '" class="form-control" style="width: 100%;" required>
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Internal brand name for identification.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 10 -->
                                <div style="display: flex !important; justify-content: space-between !important; margin-bottom: 20px !important; width: 100% !important; align-items: flex-start !important;">
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Logo URL -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Logo URL</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="text" name="logo_url" value="' . htmlspecialchars($brand->logo_url) . '" class="form-control" style="width: 100%;">
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">The URL of the brand\'s logo image.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 48% !important; flex-shrink: 0 !important; min-width: 0 !important; box-sizing: border-box !important;">
                                        <!-- Upload New Logo -->
                                        <div style="display: flex !important; align-items: flex-start !important; gap: 15px !important; width: 100% !important;">
                                            <div style="width: 140px !important; font-weight: bold !important; color: #444 !important; font-size: 0.95em !important; padding-top: 6px !important; flex-shrink: 0 !important;">Upload New Logo</div>
                                            <div style="flex: 1 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 0 !important;">
                                                <input type="file" name="logo_upload" class="form-control" style="width: 100%;">
                                                <span style="font-size: 0.82em !important; color: #777 !important; line-height: 1.4 !important;">Upload a new logo image file.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            
                            <!-- BILLING TAB -->
                            <div class="tab-pane" id="set-billing">
                                <div style="display: flex; flex-wrap: wrap; gap: 30px;">
                                    
                                    <!-- Left Column -->
                                    <div style="flex: 1; min-width: 250px;">
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 8px; font-size: 0.95em;">Invoice Pay To Text</div>
                                            <textarea name="pay_to_text" rows="12" class="form-control" style="width: 100%; min-height: 240px; font-family: inherit;">' . htmlspecialchars($brand->pay_to_text) . '</textarea>
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">This text is displayed on the invoice as the \'Pay To\' details.</div>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Column -->
                                    <div style="flex: 1.2; min-width: 320px;">
                                        
                                        <!-- Proforma Invoice Switch -->
                                        <div style="margin-bottom: 25px; display: flex; align-items: flex-start; gap: 15px;">
                                            <label class="mb-switch" style="flex-shrink: 0;">
                                                <input type="checkbox" name="proforma_invoice" value="1" ' . ($brand->proforma_invoice ? 'checked' : '') . ' onchange="var bdg = this.parentElement.nextElementSibling.querySelector(\'.status-badge\'); bdg.textContent = this.checked ? \'Enabled\' : \'Disabled\'; bdg.style.backgroundColor = this.checked ? \'#5cb85c\' : \'#777\';">
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-weight: 600; color: #444; font-size: 1.05em;">Proforma Invoice</span>
                                                    <span class="status-badge" style="background-color: ' . ($brand->proforma_invoice ? '#5cb85c' : '#777') . '; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase; display: inline-block;">' . ($brand->proforma_invoice ? 'Enabled' : 'Disabled') . '</span>
                                                </div>
                                                <span style="font-size: 0.85em; color: #777; line-height: 1.4;">This option allows proforma invoicing for unpaid invoices.</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Invoice Number Branding Switch -->
                                        <div style="margin-bottom: 25px; display: flex; align-items: flex-start; gap: 15px;">
                                            <label class="mb-switch" style="flex-shrink: 0;">
                                                <input type="checkbox" name="invoice_number_branding" value="1" ' . ($brand->invoice_number_branding ? 'checked' : '') . ' onchange="var bdg = this.parentElement.nextElementSibling.querySelector(\'.status-badge\'); bdg.textContent = this.checked ? \'Enabled\' : \'Disabled\'; bdg.style.backgroundColor = this.checked ? \'#5cb85c\' : \'#777\';">
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-weight: 600; color: #444; font-size: 1.05em;">Invoice Number Branding</span>
                                                    <span class="status-badge" style="background-color: ' . ($brand->invoice_number_branding ? '#5cb85c' : '#777') . '; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase; display: inline-block;">' . ($brand->invoice_number_branding ? 'Enabled' : 'Disabled') . '</span>
                                                </div>
                                                <span style="font-size: 0.85em; color: #777; line-height: 1.4;">This will allow you to set a different invoice number for the current brand.</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Zero Invoices Number Branding Switch -->
                                        <div style="margin-bottom: 25px; display: flex; align-items: flex-start; gap: 15px;">
                                            <label class="mb-switch" style="flex-shrink: 0;">
                                                <input type="checkbox" name="zero_invoices_number_branding" value="1" ' . ($brand->zero_invoices_number_branding ? 'checked' : '') . ' onchange="var bdg = this.parentElement.nextElementSibling.querySelector(\'.status-badge\'); bdg.textContent = this.checked ? \'Enabled\' : \'Disabled\'; bdg.style.backgroundColor = this.checked ? \'#5cb85c\' : \'#777\';">
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <span style="font-weight: 600; color: #444; font-size: 1.05em;">Zero Invoices Number Branding</span>
                                                    <span class="status-badge" style="background-color: ' . ($brand->zero_invoices_number_branding ? '#5cb85c' : '#777') . '; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase; display: inline-block;">' . ($brand->zero_invoices_number_branding ? 'Enabled' : 'Disabled') . '</span>
                                                </div>
                                                <span style="font-size: 0.85em; color: #777; line-height: 1.4;">If enabled, invoices with total 0 will receive another brand number.</span>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 5px; font-size: 0.95em;">Sequential Invoice Number Format</div>
                                            <input type="text" name="sequential_invoice_number_format" value="' . htmlspecialchars($brand->sequential_invoice_number_format ?: '{YEAR}/{MONTH}/{DAY}/{NUMBER}') . '" class="form-control" style="width: 100%;">
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">Available auto-insert tags are: {YEAR} {MONTH} {DAY} {NUMBER}.</div>
                                        </div>
                                        
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 5px; font-size: 0.95em;">Next Sequential Number</div>
                                            <input type="number" name="next_sequential_number" value="' . htmlspecialchars($brand->next_sequential_number !== null ? $brand->next_sequential_number : '') . '" class="form-control" style="width: 100%;">
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">Change this option only if you want to reset the automatic sequential numbering.</div>
                                        </div>
                                        
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 8px; font-size: 0.95em;">Brand Currencies</div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 8px; padding: 12px; border: 1px solid #ccc; border-radius: 4px; background: #fff; min-height: 50px; align-items: center;">';
                                                $supportedCurrencies = explode(',', $brand->brand_currencies ?: '');
                                                if (count($currencies) > 0) {
                                                    foreach ($currencies as $curr) {
                                                        $isSelected = in_array($curr->code, $supportedCurrencies);
                                                        $selectedClass = $isSelected ? 'selected' : '';
                                                        $checkedAttr = $isSelected ? 'checked' : '';
                                                        $output .= '
                                                        <label class="currency-tag ' . $selectedClass . '" onclick="var cb = this.querySelector(\'input\'); cb.checked = !cb.checked; this.classList.toggle(\'selected\', cb.checked); event.preventDefault();">
                                                            <input type="checkbox" name="brand_currencies[]" value="' . htmlspecialchars($curr->code) . '" ' . $checkedAttr . ' style="display: none !important;">
                                                            <span class="tag-close">&times;</span> ' . htmlspecialchars($curr->code) . '
                                                        </label>';
                                                    }
                                                } else {
                                                    $output .= '<span style="color: #888; font-style: italic; font-size: 0.9em;">No currencies defined in WHMCS.</span>';
                                                }
                                                $output .= '
                                            </div>
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">Choose the currencies that will be supported by the brand. Click tags to select/deselect them.</div>
                                        </div>
                                        
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-weight: 600; color: #555; margin-bottom: 5px; font-size: 0.95em;">Default Currency</div>
                                            <select name="default_currency" class="form-control" style="width: 100%;">
                                                <option value="">None</option>';
                                                if (count($currencies) > 0) {
                                                    foreach ($currencies as $curr) {
                                                        $selected = ($brand->default_currency == $curr->code) ? 'selected' : '';
                                                        $output .= '<option value="' . htmlspecialchars($curr->code) . '" ' . $selected . '>' . htmlspecialchars($curr->code) . '</option>';
                                                    }
                                                }
                                                $output .= '
                                            </select>
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">Set the default currency that will be displayed for clients that are not logged in.</div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                            <!-- PAYMENT GATEWAYS TAB -->
                            <div class="tab-pane" id="set-gateways">
                                <h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-credit-card" style="margin-right: 8px;"></i> Payment Gateways</h4>
                                <p style="color: #777; font-size: 0.9em; line-height: 1.5; margin-bottom: 20px;">Configure brand-specific payment gateway credentials and override standard gateway configurations.</p>
                                <div style="border: 1px solid #e2e2e2; border-radius: 6px; padding: 25px; background: #fafafa; text-align: center;">
                                    <i class="fas fa-plug" style="font-size: 2.5em; color: #ccc; margin-bottom: 12px;"></i>
                                    <div style="font-weight: 600; color: #555; font-size: 1em; margin-bottom: 5px;">Payment Gateways Overrides</div>
                                    <div style="font-size: 0.85em; color: #888;">Configure merchant credentials specific to this brand for PayPal, Stripe, and other gateways.</div>
                                </div>
                            </div>
                            
                            <!-- SMTP TAB -->
                            <div class="tab-pane" id="set-smtp">
                                <h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-paper-plane" style="margin-right: 8px;"></i> SMTP Configuration</h4>
                                <p style="color: #777; font-size: 0.9em; line-height: 1.5; margin-bottom: 20px;">Set up a custom outgoing mail server for emails sent under this brand\'s domain name.</p>
                                <div style="border: 1px solid #e2e2e2; border-radius: 6px; padding: 25px; background: #fafafa; text-align: center;">
                                    <i class="fas fa-envelope-open-text" style="font-size: 2.5em; color: #ccc; margin-bottom: 12px;"></i>
                                    <div style="font-weight: 600; color: #555; font-size: 1em; margin-bottom: 5px;">SMTP Server Branding</div>
                                    <div style="font-size: 0.85em; color: #888;">Allows mail delivery using dedicated SMTP credentials (Host, Port, Username, Password, SSL/TLS).</div>
                                </div>
                            </div>
                            
                            <!-- EMAIL TEMPLATES TAB -->
                            <div class="tab-pane" id="set-emails">
                                <h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-envelope" style="margin-right: 8px;"></i> Email Templates</h4>
                                <p style="color: #777; font-size: 0.9em; line-height: 1.5; margin-bottom: 20px;">Manage custom headers, footers, and templates for system emails dispatched by this brand.</p>
                                <div style="border: 1px solid #e2e2e2; border-radius: 6px; padding: 25px; background: #fafafa; text-align: center;">
                                    <i class="fas fa-paint-brush" style="font-size: 2.5em; color: #ccc; margin-bottom: 12px;"></i>
                                    <div style="font-weight: 600; color: #555; font-size: 1em; margin-bottom: 5px;">Email Template Branding</div>
                                    <div style="font-size: 0.85em; color: #888;">Define dedicated wrappers, logos, and signatures for customer communications.</div>
                                </div>
                            </div>
                            
                            <!-- MAINTENANCE TAB -->
                            <div class="tab-pane" id="set-maintenance">
                                <div style="margin-bottom: 25px; display: flex; align-items: flex-start; gap: 15px;">
                                    <label class="mb-switch" style="flex-shrink: 0;">
                                        <input type="checkbox" name="maintenance_mode" value="1" ' . ($brand->maintenance_mode ? 'checked' : '') . ' onchange="var bdg = this.parentElement.nextElementSibling.querySelector(\'.status-badge\'); bdg.textContent = this.checked ? \'Enabled\' : \'Disabled\'; bdg.style.backgroundColor = this.checked ? \'#5cb85c\' : \'#777\';">
                                        <span class="mb-slider"></span>
                                    </label>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-weight: 600; color: #444; font-size: 1.05em;">Maintenance Mode</span>
                                            <span class="status-badge" style="background-color: ' . ($brand->maintenance_mode ? '#5cb85c' : '#777') . '; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase; display: inline-block;">' . ($brand->maintenance_mode ? 'Enabled' : 'Disabled') . '</span>
                                        </div>
                                        <span style="font-size: 0.85em; color: #777; line-height: 1.4;">Prevents client area access when enabled.</span>
                                    </div>
                                </div>
                                <div style="margin-bottom: 20px;">
                                    <div style="font-weight: 600; color: #555; margin-bottom: 8px; font-size: 0.95em;">Maintenance Mode Message</div>
                                    <textarea name="maintenance_mode_message" rows="5" class="form-control" style="width: 100%; max-width: 600px;">' . htmlspecialchars($brand->maintenance_mode_message) . '</textarea>
                                </div>
                                <div class="flex-form-group">
                                    <div class="flex-form-label">Maintenance Redirect URL</div>
                                    <input type="text" name="maintenance_mode_redirect_url" value="' . htmlspecialchars($brand->maintenance_mode_redirect_url) . '" class="form-control input-400">
                                </div>
                            </div>
                        </div>
                        
                        <div style="padding: 15px 25px; background: #fafafa; border-top: 1px solid #f0f0f0; text-align: right;">
                            <button type="submit" class="btn btn-success" style="padding: 7px 25px; font-weight: 600; border-radius: 4px;"><i class="fas fa-save" style="margin-right: 6px;"></i> Save Changes</button>
                        </div>
                    </div>
                </div>
                
            </div>
        </form>

        <!-- SERVICES PRICING BOX -->
        <div style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 30px; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
            <div style="padding: 12px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-dollar-sign" style="margin-right: 8px;"></i> Services Pricing</span>
                <ul class="nav nav-tabs" style="border-bottom: none; margin: 0;">
                    <li class="active"><a href="#price-products" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Products</a></li>
                    <li><a href="#price-addons" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Addons</a></li>
                    <li><a href="#price-domains" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Domains</a></li>
                </ul>
            </div>
            
            <div class="tab-content" style="padding: 20px;">
                <!-- PRODUCTS TAB -->
                <div class="tab-pane active" id="price-products">
                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox" checked></th>
                                <th>Product Name</th>
                                <th>Payment Type</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($products) > 0) {
            foreach ($products as $p) {
                $payType = ucfirst($p->paytype);
                if ($payType == 'Free') { $payType = 'Free Account'; }
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" checked></td>
                    <td><a style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($p->name) . '</a></td>
                    <td>' . $payType . '</td>
                    <td class="text-center">
                        <span class="label" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px;" title="Pricing Override">$</span>
                        <span class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;" title="Delete Product Override"><i class="fas fa-trash-alt"></i></span>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="4" class="text-center">No products found in WHMCS.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
                
                <!-- ADDONS TAB -->
                <div class="tab-pane" id="price-addons">
                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox" checked></th>
                                <th>Addon Name</th>
                                <th>Billing Cycle</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($addons) > 0) {
            foreach ($addons as $addon) {
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" checked></td>
                    <td><a style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($addon->name) . '</a></td>
                    <td>' . htmlspecialchars($addon->billingcycle) . '</td>
                    <td class="text-center">
                        <span class="label" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px;">$</span>
                        <span class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;"><i class="fas fa-trash-alt"></i></span>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="4" class="text-center">No product addons found in WHMCS.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
                
                <!-- DOMAINS TAB -->
                <div class="tab-pane" id="price-domains">
                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox" checked></th>
                                <th>TLD / Extension</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($domains) > 0) {
            foreach ($domains as $domain) {
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" checked></td>
                    <td style="font-weight: 600;">' . htmlspecialchars($domain->extension) . '</td>
                    <td class="text-center">
                        <span class="label" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px;">$</span>
                        <span class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;"><i class="fas fa-trash-alt"></i></span>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="3" class="text-center">No domain pricing found in WHMCS.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RELATIONS BOX -->
        <div id="mb-relations-box" style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
            <div style="padding: 12px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-exchange-alt" style="margin-right: 8px;"></i> Relations</span>
                <ul class="relations-tabs" role="tablist" style="border-bottom: none; margin: 0; display: flex; flex-wrap: wrap;">
                    <li class="active"><a href="#tab-clients" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Clients (' . count($clients) . ')</a></li>
                    <li><a href="#tab-services" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Services (' . count($services) . ')</a></li>
                    <li><a href="#tab-invoices" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Invoices (' . count($invoices) . ')</a></li>
                    <li><a href="#tab-quotes" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Quotes (' . count($quotes) . ')</a></li>
                    <li><a href="#tab-tickets" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Tickets (' . count($tickets) . ')</a></li>
                    <li><a href="#tab-knowledgebase" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Knowledgebase (' . $kbCount . ')</a></li>
                    <li><a href="#tab-downloads" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Downloads (' . $downloadCount . ')</a></li>
                    <li><a href="#tab-announcements" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Announcements (' . $announcementCount . ')</a></li>
                    <li><a href="#tab-promotions" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Promotions (' . $promoCount . ')</a></li>
                    <li><a href="#tab-billable" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Billable Items (' . $billableCount . ')</a></li>
                    <li><a href="#tab-emails" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Emails (' . $emailCount . ')</a></li>
                </ul>
            </div>

            <div class="tab-content" style="padding: 20px;">
                <!-- CLIENTS TAB -->
                <div class="tab-pane active" id="tab-clients">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-users" style="margin-right: 8px;"></i> Clients
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="' . $modulelink . '&action=add_relation&brand_id=' . $brand->id . '" class="action-circle-btn" title="Add Relation"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox"></th>
                                <th width="60">#ID</th>
                                <th>First name</th>
                                <th>Last name</th>
                                <th>Company</th>
                                <th>Created At</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (count($clients) > 0) {
            foreach ($clients as $client) {
                $createdAt = isset($client->datecreated) ? date('Y-m-d', strtotime($client->datecreated)) : '-';
                $company = $client->companyname ?: '-';
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox"></td>
                    <td>' . $client->id . '</td>
                    <td>' . htmlspecialchars($client->firstname) . '</td>
                    <td>' . htmlspecialchars($client->lastname) . '</td>
                    <td>' . htmlspecialchars($company) . '</td>
                    <td>' . htmlspecialchars($createdAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=reassign_client&brand_id=' . $brand->id . '&client_id=' . $client->id . '" class="btn btn-sm btn-primary" style="margin-right: 5px;" title="Swap/Reassign Brands"><i class="fas fa-exchange-alt"></i></a>
                        <a href="' . $modulelink . '&action=unlink_client&brand_id=' . $brand->id . '&client_id=' . $client->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this client from this brand?\')" title="Unlink Brand"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No clients assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                        </tbody>
                    </table>
                </div>

                <!-- SERVICES TAB -->
                <div class="tab-pane" id="tab-services">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;">
                            <i class="fas fa-cubes" style="margin-right: 8px;"></i> Services
                        </h4>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="60">#ID</th>
                                <th>Client</th>
                                <th>Product/Service</th>
                                <th>Domain</th>
                                <th>Amount</th>
                                <th>Billing Cycle</th>
                                <th>Next Due Date</th>
                                <th width="120" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (count($services) > 0) {
            foreach ($services as $service) {
                $statusColor = '#777';
                if (strtolower($service->domainstatus) == 'active') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($service->domainstatus) == 'suspended') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($service->domainstatus) == 'terminated') {
                    $statusColor = '#d9534f';
                }
                
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($service->domainstatus) . '</span>';
                $nextDue = ($service->nextduedate && $service->nextduedate != '0000-00-00') ? $service->nextduedate : '-';

                $output .= '<tr>
                    <td>' . $service->id . '</td>
                    <td>' . htmlspecialchars($service->firstname . ' ' . $service->lastname) . '</td>
                    <td>' . htmlspecialchars($service->product_name) . '</td>
                    <td>' . ($service->domain ? htmlspecialchars($service->domain) : '-') . '</td>
                    <td>' . htmlspecialchars($service->amount) . '</td>
                    <td>' . htmlspecialchars($service->billingcycle) . '</td>
                    <td>' . htmlspecialchars($nextDue) . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="8" class="text-center">No services found for clients under this brand.</td></tr>';
        }

        $output .= '
                        </tbody>
                    </table>
                </div>

                <!-- INVOICES TAB -->
                <div class="tab-pane" id="tab-invoices">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;">
                            <i class="fas fa-file-invoice-dollar" style="margin-right: 8px;"></i> Invoices
                        </h4>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="100">#Invoice ID</th>
                                <th>Client</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Total</th>
                                <th>Payment Method</th>
                                <th>Brand</th>
                                <th width="120" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (count($invoices) > 0) {
            foreach ($invoices as $invoice) {
                $statusColor = '#777';
                if (strtolower($invoice->status) == 'paid') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($invoice->status) == 'unpaid') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($invoice->status) == 'cancelled' || strtolower($invoice->status) == 'refunded') {
                    $statusColor = '#d9534f';
                }
                
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($invoice->status) . '</span>';
                $invDate = $invoice->date ? $invoice->date : '-';
                $dueDate = $invoice->duedate ? $invoice->duedate : '-';
                $brandDisplay = isset($invoice->brand_name) && $invoice->brand_name ? htmlspecialchars($invoice->brand_name) : '<span style="color: #999; font-style: italic;">-</span>';

                $output .= '<tr>
                    <td>' . $invoice->id . '</td>
                    <td>' . htmlspecialchars($invoice->firstname . ' ' . $invoice->lastname) . '</td>
                    <td>' . htmlspecialchars($invDate) . '</td>
                    <td>' . htmlspecialchars($dueDate) . '</td>
                    <td>' . htmlspecialchars($invoice->total) . '</td>
                    <td>' . htmlspecialchars($invoice->paymentmethod) . '</td>
                    <td>' . $brandDisplay . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="8" class="text-center">No invoices found for clients under this brand.</td></tr>';
        }

        $output .= '
                        </tbody>
                    </table>
                </div>

                <!-- QUOTES TAB -->
                <div class="tab-pane" id="tab-quotes">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;">
                            <i class="fas fa-file-signature" style="margin-right: 8px;"></i> Quotes
                        </h4>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="100">#Quote ID</th>
                                <th>Client</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Valid Until</th>
                                <th>Total</th>
                                <th width="120" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($quotes) > 0) {
            foreach ($quotes as $quote) {
                $statusColor = '#777';
                if (strtolower($quote->stage) == 'accepted' || strtolower($quote->stage) == 'delivered') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($quote->stage) == 'draft') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($quote->stage) == 'dead') {
                    $statusColor = '#d9534f';
                }
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($quote->stage) . '</span>';
                $qDate = $quote->date ? $quote->date : '-';
                $validUntil = $quote->validuntil ? $quote->validuntil : '-';

                $output .= '<tr>
                    <td>' . $quote->id . '</td>
                    <td>' . htmlspecialchars($quote->firstname . ' ' . $quote->lastname) . '</td>
                    <td>' . htmlspecialchars($quote->subject) . '</td>
                    <td>' . htmlspecialchars($qDate) . '</td>
                    <td>' . htmlspecialchars($validUntil) . '</td>
                    <td>' . htmlspecialchars($quote->total) . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No quotes found for clients under this brand.</td></tr>';
        }
        $output .= '
                        </tbody>
                    </table>
                </div>

                <!-- TICKETS TAB -->
                <div class="tab-pane" id="tab-tickets">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;">
                            <i class="fas fa-ticket-alt" style="margin-right: 8px;"></i> Support Tickets
                        </h4>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th width="100">#Ticket ID</th>
                                <th>Client</th>
                                <th>Subject</th>
                                <th>Department</th>
                                <th>Last Reply</th>
                                <th width="120" class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $statusColor = '#777';
                if (strtolower($ticket->status) == 'open' || strtolower($ticket->status) == 'active') {
                    $statusColor = '#d9534f';
                } elseif (strtolower($ticket->status) == 'answered') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($ticket->status) == 'closed') {
                    $statusColor = '#5cb85c';
                }
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($ticket->status) . '</span>';
                $lastReply = $ticket->lastreply ? $ticket->lastreply : '-';

                $deptName = isset($deptMap[$ticket->deptid]) ? $deptMap[$ticket->deptid] : 'Dept ID: ' . $ticket->deptid;
                $output .= '<tr>
                    <td>' . $ticket->ticketnum . '</td>
                    <td>' . htmlspecialchars($ticket->firstname . ' ' . $ticket->lastname) . '</td>
                    <td>' . htmlspecialchars($ticket->title) . '</td>
                    <td>' . htmlspecialchars($deptName) . '</td>
                    <td>' . htmlspecialchars($lastReply) . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="6" class="text-center">No support tickets found for clients under this brand.</td></tr>';
        }
        $output .= '
                        </tbody>
                    </table>
                </div>

';

        $output .= '
                <!-- KNOWLEDGEBASE TAB -->
                <div class="tab-pane" id="tab-knowledgebase">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-book" style="margin-right: 8px;"></i> Knowledgebase
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-kb" data-toggle="modal" class="action-circle-btn" title="Add Article"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_kb_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="kb-select-all"></th>
                                    <th>Article Title</th>
                                    <th width="100" class="text-center">Views</th>
                                    <th width="120" class="text-center">Useful Points</th>
                                    <th width="100" class="text-center">Votes</th>
                                    <th width="180">Created At</th>
                                    <th width="100" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (count($kbArticles) > 0) {
            foreach ($kbArticles as $kbArt) {
                $assignedAt = $kbArt->assigned_at ? date('Y-m-d H:i:s', strtotime($kbArt->assigned_at)) : '-';
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="kb_ids[]" value="' . $kbArt->id . '"></td>
                    <td><a href="supportkb.php?action=edit&id=' . $kbArt->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($kbArt->title) . '</a></td>
                    <td class="text-center">' . htmlspecialchars($kbArt->views) . '</td>
                    <td class="text-center">' . htmlspecialchars($kbArt->useful) . '</td>
                    <td class="text-center">' . htmlspecialchars($kbArt->votes) . '</td>
                    <td>' . htmlspecialchars($assignedAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_kb_from_edit&brand_id=' . $brand->id . '&kb_id=' . $kbArt->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this knowledgebase article from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No knowledgebase articles assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';

        $output .= '

                <!-- DOWNLOADS TAB -->
                <div class="tab-pane" id="tab-downloads">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-download" style="margin-right: 8px;"></i> Downloads
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-download" data-toggle="modal" class="action-circle-btn" title="Add Download"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_download_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="dl-select-all"></th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Downloads</th>
                                    <th>Created At</th>
                                    <th width="100" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (count($downloadArticles) > 0) {
            foreach ($downloadArticles as $dlArt) {
                $assignedAt = $dlArt->assigned_at ? date('Y-m-d H:i:s', strtotime($dlArt->assigned_at)) : '-';
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="dl_ids[]" value="' . $dlArt->id . '"></td>
                    <td><a href="supportdownloads.php?action=edit&id=' . $dlArt->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($dlArt->title) . '</a></td>
                    <td><span style="color: #0066cc;">' . htmlspecialchars($dlArt->category_name ?: 'Software') . '</span></td>
                    <td>' . htmlspecialchars($dlArt->type ?: '-') . '</td>
                    <td>' . htmlspecialchars($dlArt->downloads) . '</td>
                    <td>' . htmlspecialchars($assignedAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_download_from_edit&brand_id=' . $brand->id . '&download_id=' . $dlArt->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this download from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No downloads assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';

        $output .= '

                <!-- ANNOUNCEMENTS TAB -->
                <div class="tab-pane" id="tab-announcements">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-bullhorn" style="margin-right: 8px;"></i> Announcements
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-announcement" data-toggle="modal" class="action-circle-btn" title="Add Announcement"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_announcement_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="ann-select-all"></th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Published</th>
                                    <th>Created At</th>
                                    <th width="100" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (count($announcementArticles) > 0) {
            foreach ($announcementArticles as $annArt) {
                $assignedAt = $annArt->assigned_at ? date('Y-m-d H:i:s', strtotime($annArt->assigned_at)) : '-';
                $publishedText = $annArt->published ? 'Yes' : 'No';
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="ann_ids[]" value="' . $annArt->id . '"></td>
                    <td><a href="supportannouncements.php?action=manage&id=' . $annArt->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($annArt->title) . '</a></td>
                    <td>' . htmlspecialchars($annArt->date) . '</td>
                    <td>' . $publishedText . '</td>
                    <td>' . htmlspecialchars($assignedAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_announcement_from_edit&brand_id=' . $brand->id . '&announcement_id=' . $annArt->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this announcement from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="6" class="text-center">No announcements assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';

        $output .= '

                <!-- PROMOTIONS TAB -->
                <div class="tab-pane" id="tab-promotions">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-tags" style="margin-right: 8px;"></i> Promotions
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-promotion" data-toggle="modal" class="action-circle-btn" title="Add Promotion"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <!-- Promotions Sub-tabs -->
                    <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px; border-bottom: 1px solid #ddd; display: flex; gap: 5px;">
                        <li class="active"><a href="#promo-active" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">Active</a></li>
                        <li><a href="#promo-expired" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">Expired</a></li>
                        <li><a href="#promo-all" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">All</a></li>
                    </ul>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_promotion_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="tab-content" style="border: none; padding: 0; box-shadow: none; background: transparent;">';

                            // Filtering Logic
                            $activePromos = [];
                            $expiredPromos = [];
                            $today = date('Y-m-d');
                              
                            foreach ($promoArticles as $promo) {
                                $expire = ($promo->expirationdate == '0000-00-00' || $promo->expirationdate == '') ? '0000-00-00' : $promo->expirationdate;
                                $isExpired = false;
                                if ($expire !== '0000-00-00' && $expire < $today) {
                                    $isExpired = true;
                                }
                                if ($promo->maxuses > 0 && $promo->uses >= $promo->maxuses) {
                                    $isExpired = true;
                                }
                                if ($isExpired) {
                                    $expiredPromos[] = $promo;
                                } else {
                                    $activePromos[] = $promo;
                                }
                            }

                            // 1. ACTIVE SUB-TAB
                            $output .= '
                            <div class="tab-pane active" id="promo-active">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="promo-select-all"></th>
                                            <th width="50">#ID</th>
                                            <th>Code</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Recurring</th>
                                            <th>Uses</th>
                                            <th>Max Uses</th>
                                            <th>Start Date</th>
                                            <th>Expiration Date</th>
                                            <th>Created At</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($activePromos) > 0) {
                                        foreach ($activePromos as $promo) {
                                            $assignedAt = $promo->assigned_at ? date('Y-m-d H:i:s', strtotime($promo->assigned_at)) : '-';
                                            $maxUsesText = ($promo->maxuses > 0) ? $promo->maxuses : 'Unlimited';
                                            $recurringText = $promo->recurring ? 'Enabled' : 'Disabled';
                                            $startDate = ($promo->startdate == '0000-00-00' || !$promo->startdate) ? '0000-00-00' : $promo->startdate;
                                            $expireDate = ($promo->expirationdate == '0000-00-00' || !$promo->expirationdate) ? '0000-00-00' : $promo->expirationdate;
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="promo_ids[]" value="' . $promo->id . '" class="promo-item-checkbox"></td>
                                                <td>' . $promo->id . '</td>
                                                <td><a href="configpromotions.php?action=manage&id=' . $promo->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($promo->code) . '</a></td>
                                                <td>' . htmlspecialchars($promo->type) . '</td>
                                                <td>' . htmlspecialchars($promo->value) . '</td>
                                                <td>' . $recurringText . '</td>
                                                <td>' . htmlspecialchars($promo->uses) . '</td>
                                                <td>' . $maxUsesText . '</td>
                                                <td>' . htmlspecialchars($startDate) . '</td>
                                                <td>' . htmlspecialchars($expireDate) . '</td>
                                                <td>' . htmlspecialchars($assignedAt) . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_promotion_from_edit&brand_id=' . $brand->id . '&promotion_id=' . $promo->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this promotion from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="12" class="text-center">No active promotions found.</td></tr>';
                                    }
                            $output .= '
                                    </tbody>
                                </table>
                            </div>';

                            // 2. EXPIRED SUB-TAB
                            $output .= '
                            <div class="tab-pane" id="promo-expired">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="promo-select-all"></th>
                                            <th width="50">#ID</th>
                                            <th>Code</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Recurring</th>
                                            <th>Uses</th>
                                            <th>Max Uses</th>
                                            <th>Start Date</th>
                                            <th>Expiration Date</th>
                                            <th>Created At</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($expiredPromos) > 0) {
                                        foreach ($expiredPromos as $promo) {
                                            $assignedAt = $promo->assigned_at ? date('Y-m-d H:i:s', strtotime($promo->assigned_at)) : '-';
                                            $maxUsesText = ($promo->maxuses > 0) ? $promo->maxuses : 'Unlimited';
                                            $recurringText = $promo->recurring ? 'Enabled' : 'Disabled';
                                            $startDate = ($promo->startdate == '0000-00-00' || !$promo->startdate) ? '0000-00-00' : $promo->startdate;
                                            $expireDate = ($promo->expirationdate == '0000-00-00' || !$promo->expirationdate) ? '0000-00-00' : $promo->expirationdate;
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="promo_ids[]" value="' . $promo->id . '" class="promo-item-checkbox"></td>
                                                <td>' . $promo->id . '</td>
                                                <td><a href="configpromotions.php?action=manage&id=' . $promo->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($promo->code) . '</a></td>
                                                <td>' . htmlspecialchars($promo->type) . '</td>
                                                <td>' . htmlspecialchars($promo->value) . '</td>
                                                <td>' . $recurringText . '</td>
                                                <td>' . htmlspecialchars($promo->uses) . '</td>
                                                <td>' . $maxUsesText . '</td>
                                                <td>' . htmlspecialchars($startDate) . '</td>
                                                <td>' . htmlspecialchars($expireDate) . '</td>
                                                <td>' . htmlspecialchars($assignedAt) . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_promotion_from_edit&brand_id=' . $brand->id . '&promotion_id=' . $promo->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this promotion from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="12" class="text-center">No expired promotions found.</td></tr>';
                                    }
                            $output .= '
                                    </tbody>
                                </table>
                            </div>';

                            // 3. ALL SUB-TAB
                            $output .= '
                            <div class="tab-pane" id="promo-all">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="promo-select-all"></th>
                                            <th width="50">#ID</th>
                                            <th>Code</th>
                                            <th>Type</th>
                                            <th>Value</th>
                                            <th>Recurring</th>
                                            <th>Uses</th>
                                            <th>Max Uses</th>
                                            <th>Start Date</th>
                                            <th>Expiration Date</th>
                                            <th>Created At</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($promoArticles) > 0) {
                                        foreach ($promoArticles as $promo) {
                                            $assignedAt = $promo->assigned_at ? date('Y-m-d H:i:s', strtotime($promo->assigned_at)) : '-';
                                            $maxUsesText = ($promo->maxuses > 0) ? $promo->maxuses : 'Unlimited';
                                            $recurringText = $promo->recurring ? 'Enabled' : 'Disabled';
                                            $startDate = ($promo->startdate == '0000-00-00' || !$promo->startdate) ? '0000-00-00' : $promo->startdate;
                                            $expireDate = ($promo->expirationdate == '0000-00-00' || !$promo->expirationdate) ? '0000-00-00' : $promo->expirationdate;
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="promo_ids[]" value="' . $promo->id . '" class="promo-item-checkbox"></td>
                                                <td>' . $promo->id . '</td>
                                                <td><a href="configpromotions.php?action=manage&id=' . $promo->id . '" target="_blank" style="font-weight: bold;">' . htmlspecialchars($promo->code) . '</a></td>
                                                <td>' . htmlspecialchars($promo->type) . '</td>
                                                <td>' . htmlspecialchars($promo->value) . '</td>
                                                <td>' . $recurringText . '</td>
                                                <td>' . htmlspecialchars($promo->uses) . '</td>
                                                <td>' . $maxUsesText . '</td>
                                                <td>' . htmlspecialchars($startDate) . '</td>
                                                <td>' . htmlspecialchars($expireDate) . '</td>
                                                <td>' . htmlspecialchars($assignedAt) . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_promotion_from_edit&brand_id=' . $brand->id . '&promotion_id=' . $promo->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this promotion from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="12" class="text-center">No promotions assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
                                    }
                            $output .= '
                                    </tbody>
                                </table>
                            </div>';

        $output .= '
                        </div>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';

        $output .= '

                <!-- BILLABLE ITEMS TAB -->
                <div class="tab-pane" id="tab-billable">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-dollar-sign" style="margin-right: 8px;"></i> Billable Items
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-billable" data-toggle="modal" class="action-circle-btn" title="Add Billable Item"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_billable_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="billable-select-all"></th>
                                    <th width="50">#ID</th>
                                    <th>Client Name</th>
                                    <th>Description</th>
                                    <th>Hours</th>
                                    <th>Amount</th>
                                    <th>Invoice Action</th>
                                    <th>Invoice Count</th>
                                    <th>Created At</th>
                                    <th width="80" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';
        if (count($billableItems) > 0) {
            foreach ($billableItems as $item) {
                $assignedAt = $item->assigned_at ? date('Y-m-d H:i:s', strtotime($item->assigned_at)) : '-';
                
                // Mapped invoice action text
                $actionText = '-';
                if ($item->invoiceaction == 1) {
                    $actionText = "Add to User's Next Invoice";
                } elseif ($item->invoiceaction == 2) {
                    $actionText = "Don't Invoice for Now";
                } elseif ($item->invoiceaction == 3) {
                    $actionText = "Invoice as Normal for Due Date";
                }

                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="billable_ids[]" value="' . $item->id . '" class="billable-item-checkbox"></td>
                    <td>' . $item->id . '</td>
                    <td><a href="clientssummary.php?userid=' . $item->userid . '" target="_blank" style="font-weight: bold; color: #007bff; text-decoration: none;">' . htmlspecialchars($item->firstname . ' ' . $item->lastname) . '</a></td>
                    <td>' . htmlspecialchars($item->description) . '</td>
                    <td>' . number_format($item->hours, 1) . '</td>
                    <td>' . number_format($item->amount, 2) . '</td>
                    <td>' . htmlspecialchars($actionText) . '</td>
                    <td>' . htmlspecialchars($item->invoicecount) . '</td>
                    <td>' . htmlspecialchars($assignedAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_billable_from_edit&brand_id=' . $brand->id . '&billable_id=' . $item->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this billable item from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="10" class="text-center">No billable items assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }
        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';


        $output .= '
                <!-- EMAILS TAB -->
                <div class="tab-pane" id="tab-emails">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 15px; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-envelope" style="margin-right: 8px;"></i> Emails
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-email" data-toggle="modal" class="action-circle-btn" title="Add Email"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                        </div>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_email_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="email-select-all"></th>
                                    <th width="50">#ID</th>
                                    <th>Subject</th>
                                    <th>Client</th>
                                    <th>To</th>
                                    <th>Date</th>
                                    <th width="80" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';
        if (count($emails) > 0) {
            foreach ($emails as $email) {
                $assignedAt = $email->assigned_at ? date('Y-m-d H:i:s', strtotime($email->assigned_at)) : '-';
                
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="email_ids[]" value="' . $email->id . '" class="email-item-checkbox"></td>
                    <td>' . $email->id . '</td>
                    <td><a href="emails.php?action=view&id=' . $email->id . '" target="_blank" style="font-weight: bold; color: #007bff; text-decoration: none;">' . htmlspecialchars($email->subject) . '</a></td>
                    <td><a href="clientssummary.php?userid=' . $email->userid . '" target="_blank" style="font-weight: bold; color: #007bff; text-decoration: none;">' . htmlspecialchars($email->firstname . ' ' . $email->lastname) . '</a></td>
                    <td>' . htmlspecialchars($email->to) . '</td>
                    <td>' . htmlspecialchars($email->date) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_email_from_edit&brand_id=' . $brand->id . '&email_id=' . $email->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this email from this brand?\')" title="Delete Relation"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No email logs assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }
        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>';

        $output .= '
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function() {
            if (jQuery.fn.select2) {
                jQuery(\'select[name="ticket_departments[]"]\').select2({
                    placeholder: "Select ticket departments...",
                    allowClear: true
                });
            }
            jQuery("#mb-relations-box table.datatable").each(function() {
                if (!jQuery.fn.DataTable.isDataTable(this)) {
                    jQuery(this).DataTable({
                        "dom": \'<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-6"i><"col-sm-6"p>>\',
                        "pageLength": 10,
                        "responsive": true,
                        "order": [],
                        "stateSave": true,
                        "language": {
                            "paginate": {
                                "previous": "Previous",
                                "next": "Next"
                            },
                            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                            "infoEmpty": "Showing 0 to 0 of 0 entries",
                            "lengthMenu": "Show _MENU_ entries"
                        }
                    });
                }
            });
        });
        </script>';

        $output .= '
        <!-- Add Article Modal -->
        <div id="modal-add-kb" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Article</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_kb_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Article</label>
                                <select name="kb_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availableArticles) > 0) {
                                        foreach ($availableArticles as $art) {
                                            $output .= '<option value="' . $art->id . '">#' . $art->id . ' ' . htmlspecialchars($art->title) . '</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No articles available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">Choose an article that should be displayed in the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availableArticles) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle
            $("#kb-select-all").on("click", function() {
                var checked = this.checked;
                $("input[name=\"kb_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });
        });
        </script>';

        $output .= '
        <!-- Add Download Modal -->
        <div id="modal-add-download" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Download</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_download_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Download</label>
                                <select name="download_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availableDownloads) > 0) {
                                        foreach ($availableDownloads as $dl) {
                                            $catName = $dl->category_name ?: 'Software';
                                            $output .= '<option value="' . $dl->id . '">#' . $dl->id . ' ' . htmlspecialchars($dl->title) . ' (' . htmlspecialchars($dl->type) . ') - ' . htmlspecialchars($catName) . '</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No downloads available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">Select a download that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availableDownloads) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle for downloads
            $("#dl-select-all").on("click", function() {
                var checked = this.checked;
                $("input[name=\"dl_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });
        });
        </script>';

        $output .= '
        <!-- Add Announcement Modal -->
        <div id="modal-add-announcement" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Announcement</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_announcement_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Announcement</label>
                                <select name="announcement_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availableAnnouncements) > 0) {
                                        foreach ($availableAnnouncements as $ann) {
                                            $output .= '<option value="' . $ann->id . '">#' . $ann->id . ' ' . htmlspecialchars($ann->title) . ' - ' . htmlspecialchars($ann->date) . '</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No announcements available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">Select the announcements that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availableAnnouncements) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle for announcements
            $("#ann-select-all").on("click", function() {
                var checked = this.checked;
                $("input[name=\"ann_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });
        });
        </script>';

        $output .= '
        <!-- Add Promotion Modal -->
        <div id="modal-add-promotion" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Promotion</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_promotion_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Promotion</label>
                                <select name="promotion_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availablePromotions) > 0) {
                                        foreach ($availablePromotions as $promo) {
                                            $output .= '<option value="' . $promo->id . '">#' . $promo->id . ' - ' . htmlspecialchars($promo->code) . ' (' . htmlspecialchars($promo->type) . ')</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No promotions available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">Select a promotion that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availablePromotions) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle for promotions sub-tabs
            $(document).on("click", ".promo-select-all", function() {
                var checked = this.checked;
                $("input[name=\"promo_ids[]\"]").each(function() {
                    this.checked = checked;
                });
                $(".promo-select-all").prop("checked", checked);
            });
            
            // Sync select-all checkbox state when individual promo checkbox is changed
            $(document).on("change", ".promo-item-checkbox", function() {
                var allChecked = true;
                var totalCheckbox = $(".promo-item-checkbox").length;
                if (totalCheckbox === 0) allChecked = false;
                
                $(".promo-item-checkbox").each(function() {
                    if (!this.checked) allChecked = false;
                });
                
                $(".promo-select-all").prop("checked", allChecked);
            });
        });
        </script>';

        $output .= '
        <!-- Add Billable Item Modal -->
        <div id="modal-add-billable" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Billable Item</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_billable_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Billable Item</label>
                                <select name="billable_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availableBillables) > 0) {
                                        foreach ($availableBillables as $bill) {
                                            $output .= '<option value="' . $bill->id . '">#' . $bill->id . ' ' . htmlspecialchars($bill->firstname . ' ' . $bill->lastname) . ' - ' . htmlspecialchars($bill->description) . '</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No billable items available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">This action will delete the relation between the announcement and the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availableBillables) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle for billable items
            $(document).on("click", "#billable-select-all", function() {
                var checked = this.checked;
                $("input[name=\"billable_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });
            
            // Sync select-all checkbox state when individual billable checkbox is changed
            $(document).on("change", ".billable-item-checkbox", function() {
                var allChecked = true;
                var totalCheckbox = $(".billable-item-checkbox").length;
                if (totalCheckbox === 0) allChecked = false;
                
                $(".billable-item-checkbox").each(function() {
                    if (!this.checked) allChecked = false;
                });
                
                $("#billable-select-all").prop("checked", allChecked);
            });
        });
        </script>';

        $output .= '
        <!-- Add Email Modal -->
        <div id="modal-add-email" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold;"><i class="fas fa-plus"></i> Add Email</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_email_relation_from_edit">
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <div class="modal-body">
                            <div class="form-group">
                                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Email</label>
                                <select name="email_id" class="form-control" style="width: 100%; padding: 6px; font-weight: bold;">';
                                    if (count($availableEmails) > 0) {
                                        foreach ($availableEmails as $email) {
                                            $clientDisplay = htmlspecialchars($email->firstname . ' ' . $email->lastname);
                                            if ($email->companyname) {
                                                $clientDisplay .= ' (' . htmlspecialchars($email->companyname) . ')';
                                            }
                                            $clientDisplay .= ' &lt;' . htmlspecialchars($email->client_email) . '&gt;';
                                            $output .= '<option value="' . $email->id . '">#' . $email->id . ' ' . $clientDisplay . ' - ' . htmlspecialchars($email->subject) . '</option>';
                                        }
                                    } else {
                                        $output .= '<option value="0" disabled>No emails available</option>';
                                    }
        $output .= '            </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">Select an email that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success" ' . (count($availableEmails) == 0 ? 'disabled' : '') . '>Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            // Select All Checkboxes toggle for emails
            $(document).on("click", "#email-select-all", function() {
                var checked = this.checked;
                $("input[name=\"email_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });
            
            // Sync select-all checkbox state when individual email checkbox is changed
            $(document).on("change", ".email-item-checkbox", function() {
                var allChecked = true;
                var totalCheckbox = $(".email-item-checkbox").length;
                if (totalCheckbox === 0) allChecked = false;
                
                $(".email-item-checkbox").each(function() {
                    if (!this.checked) allChecked = false;
                });
                
                $("#email-select-all").prop("checked", allChecked);
            });
        });
        </script>';

        return print_r($output);
    }

    /**
     * Save brand logic
     */
    public function save($vars)
    {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        if ($isDefault) {
            Capsule::table('mod_multibrand_brands')->update(['is_default' => 0]);
        }

        $data = [
            'brand_name' => $_POST['brand_name'],
            'company_name' => $_POST['company_name'],
            'email_address' => $_POST['email_address'],
            'domain' => $_POST['domain'],
            'logo_url' => $_POST['logo_url'],
            'pay_to_text' => $_POST['pay_to_text'],
            'proforma_invoice' => isset($_POST['proforma_invoice']) ? 1 : 0,
            'invoice_number_branding' => isset($_POST['invoice_number_branding']) ? 1 : 0,
            'zero_invoices_number_branding' => isset($_POST['zero_invoices_number_branding']) ? 1 : 0,
            'sequential_invoice_number_format' => $_POST['sequential_invoice_number_format'],
            'next_sequential_number' => isset($_POST['next_sequential_number']) && $_POST['next_sequential_number'] !== '' ? (int) $_POST['next_sequential_number'] : null,
            'brand_currencies' => isset($_POST['brand_currencies']) && is_array($_POST['brand_currencies']) ? implode(',', $_POST['brand_currencies']) : '',
            'default_currency' => $_POST['default_currency'],
            'system_url' => $_POST['system_url'],
            'system_theme' => $_POST['system_theme'],
            'brand_color' => $_POST['brand_color'],
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'maintenance_mode_message' => $_POST['maintenance_mode_message'],
            'maintenance_mode_redirect_url' => $_POST['maintenance_mode_redirect_url'],
            'status' => isset($_POST['status']) ? 1 : (isset($_POST['status_submitted']) ? 0 : 1),
            'is_default' => $isDefault,
            'products_branding' => isset($_POST['products_branding']) ? 1 : 0,
            'price_override' => isset($_POST['price_override']) ? 1 : 0,
            'brand_switcher' => isset($_POST['brand_switcher']) ? 1 : 0,
            'ticket_departments' => isset($_POST['ticket_departments']) && is_array($_POST['ticket_departments']) ? implode(',', $_POST['ticket_departments']) : '',
            'order_template' => $_POST['order_template'],
            'default_language' => $_POST['default_language'],
            'auto_client_assignment' => isset($_POST['auto_client_assignment']) ? 1 : 0,
            'tos_url' => $_POST['tos_url'],
            'signature' => $_POST['signature'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Handle Logo Upload
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['name'] != '') {
            $logoError = '';
            if ($_FILES['logo_upload']['error'] != 0) {
                $logoError = 'Logo upload failed with error code ' . $_FILES['logo_upload']['error'];
            } else {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                $ext = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $logoError = 'Invalid file extension. Allowed: ' . implode(', ', $allowed);
                }
            }

            if ($logoError) {
                echo '<div class="alert alert-danger">Error: ' . $logoError . '</div>';
                if ($id > 0) {
                    $_REQUEST['id'] = $id;
                    return $this->edit($vars);
                }
                return $this->add($vars);
            }

            $uploadDir = ROOTDIR . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'multibrand' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logos' . DIRECTORY_SEPARATOR;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFilename = 'logo_' . time() . '_' . uniqid() . '.' . $ext;
            $targetFile = $uploadDir . $newFilename;

            if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $targetFile)) {
                // Remove previous logo if it exists locally
                if ($id > 0) {
                    $existingBrand = Capsule::table('mod_multibrand_brands')->where('id', $id)->first();
                    if ($existingBrand && $existingBrand->logo_url) {
                        $whmcsUrl = \App::getSystemUrl();
                        if (substr($whmcsUrl, -1) !== '/') {
                            $whmcsUrl .= '/';
                        }
                        $logoLocalPart = 'modules/addons/multibrand/uploads/logos/';
                        if (strpos($existingBrand->logo_url, $whmcsUrl . $logoLocalPart) !== false) {
                            $relativeLogoPath = str_replace($whmcsUrl, '', $existingBrand->logo_url);
                            $fullLogoPath = ROOTDIR . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeLogoPath);
                            if (file_exists($fullLogoPath) && is_file($fullLogoPath)) {
                                @unlink($fullLogoPath);
                            }
                        }
                    }
                }

                // Get base URL for WHMCS
                $whmcsUrl = \App::getSystemUrl();
                if (substr($whmcsUrl, -1) !== '/') {
                    $whmcsUrl .= '/';
                }
                $data['logo_url'] = $whmcsUrl . 'modules/addons/multibrand/uploads/logos/' . $newFilename;
            } else {
                echo '<div class="alert alert-danger">Error: Failed to move uploaded file.</div>';
                if ($id > 0) {
                    $_REQUEST['id'] = $id;
                    return $this->edit($vars);
                }
                return $this->add($vars);
            }
        }

        try {
            if ($id > 0) {
                Capsule::table('mod_multibrand_brands')->where('id', $id)->update($data);
                $message = "Brand updated successfully.";
                echo '<div class="alert alert-success">' . $message . '</div>';
                $_REQUEST['id'] = $id;
                return $this->edit($vars);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                Capsule::table('mod_multibrand_brands')->insert($data);
                $message = "Brand added successfully.";
                echo '<div class="alert alert-success">' . $message . '</div>';
                return $this->index($vars);
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
            if ($id > 0) {
                $_REQUEST['id'] = $id;
                return $this->edit($vars);
            }
            return $this->index($vars);
        }
    }

    /**
     * Delete brand logic
     */
    public function delete($vars)
    {
        $id = (int) $_REQUEST['id'];

        try {
            Capsule::table('mod_multibrand_brands')->where('id', $id)->delete();
            echo '<div class="alert alert-success">Brand deleted successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error deleting brand: ' . $e->getMessage() . '</div>';
        }

        return $this->index($vars);
    }

    /**
     * Helper to render the add/edit form
     */
    private function renderForm($vars, $brand = null)
    {
        $modulelink = $vars['modulelink'];
        $title = $brand ? 'Edit Brand: ' . htmlspecialchars($brand->brand_name) : 'Add New Brand';

        $output = '<h2>' . $title . '</h2>';
        $output .= '<form method="post" action="' . $modulelink . '&action=save" enctype="multipart/form-data">';
        if ($brand) {
            $output .= '<input type="hidden" name="id" value="' . $brand->id . '">';
        }

        $fields = [
            'brand_name' => ['label' => 'Internal Brand Name', 'type' => 'text', 'desc' => 'Internal identifier for this brand'],
            'brand_color' => ['label' => 'Brand Color', 'type' => 'color', 'desc' => 'Primary color for this brand'],
            'status' => ['label' => 'Status', 'type' => 'status_dropdown', 'desc' => 'Set this brand as Enabled or Disabled'],
            'company_name' => ['label' => 'Company Name', 'type' => 'text', 'desc' => 'Your Company Name as you want it to appear throughout the system'],
            'email_address' => ['label' => 'Email Address', 'type' => 'text', 'desc' => 'The default sender address used for emails sent by WHMCS'],
            'domain' => ['label' => 'Domain', 'type' => 'text', 'desc' => 'The URL to your website homepage'],
            'logo_url' => ['label' => 'Logo URL / Upload', 'type' => 'logo', 'desc' => 'Upload a logo image or enter a URL. If you upload a file, it will override the URL field.'],
            'system_url' => ['label' => 'WHMCS System URL', 'type' => 'text', 'desc' => 'The URL to your WHMCS installation'],
            'system_theme' => ['label' => 'System Theme', 'type' => 'dropdown', 'options' => $this->getAvailableThemes(), 'desc' => 'The theme you want WHMCS to use'],
            'maintenance_mode' => ['label' => 'Maintenance Mode', 'type' => 'checkbox', 'desc' => 'Check to enable - prevents client area access when enabled'],
            'maintenance_mode_message' => ['label' => 'Maintenance Mode Message', 'type' => 'textarea', 'desc' => 'We are currently performing maintenance and will be back shortly.'],
            'maintenance_mode_redirect_url' => ['label' => 'Maintenance Mode Redirect URL', 'type' => 'text', 'desc' => 'If specified, redirects client area visitors to this URL when Maintenance Mode is enabled'],
            'pay_to_text' => ['label' => 'Pay To Text', 'type' => 'textarea', 'desc' => 'This text is displayed on the invoice as the Pay To details'],
        ];

        $output .= '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3"><tbody>';

        foreach ($fields as $name => $info) {
            $value = $brand ? (isset($brand->$name) ? htmlspecialchars($brand->$name) : '') : '';
            if ($name === 'status' && !$brand) {
                $value = '1';
            }

            $output .= '<tr>
                <td class="fieldlabel" width="200">' . $info['label'] . '</td>
                <td class="fieldarea">';

            if ($info['type'] == 'textarea') {
                $output .= '<textarea name="' . $name . '" rows="5" class="form-control">' . $value . '</textarea>';
            } elseif ($info['type'] == 'status_dropdown') {
                $output .= '<select name="' . $name . '" class="form-control input-300">';
                $statusOptions = ['1' => 'Enabled', '0' => 'Disabled'];
                foreach ($statusOptions as $val => $label) {
                    $selected = ($value == $val) ? ' selected' : '';
                    $output .= '<option value="' . $val . '"' . $selected . '>' . $label . '</option>';
                }
                $output .= '</select>';
            } elseif ($info['type'] == 'dropdown') {
                $output .= '<select name="' . $name . '" class="form-control input-300">';
                foreach ($info['options'] as $option) {
                    $selected = ($value == $option) ? ' selected' : '';
                    $output .= '<option value="' . $option . '"' . $selected . '>' . ucfirst($option) . '</option>';
                }
                $output .= '</select>';
            } elseif ($info['type'] == 'checkbox') {
                $checked = $value ? ' checked' : '';
                $output .= '<label class="checkbox-inline"><input type="checkbox" name="' . $name . '" value="1"' . $checked . '> ' . $info['desc'] . '</label>';
                $info['desc'] = ''; // Clear desc since we put it in the label
            } elseif ($info['type'] == 'logo') {
                if ($value) {
                    $output .= '<div style="margin-bottom: 10px;"><img src="' . $value . '" style="max-height: 100px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9;" alt="Current Logo"></div>';
                }
                $output .= '<input type="file" name="logo_upload" class="form-control" style="margin-bottom: 10px;">';
                $output .= '<div class="input-group">
                    <span class="input-group-addon">OR URL</span>
                    <input type="text" name="' . $name . '" value="' . $value . '" class="form-control">
                </div>';
            } elseif ($info['type'] == 'color') {
                $output .= '<input type="color" name="' . $name . '" value="' . ($value ?: '#000000') . '" class="form-control" style="width: 100px; height: 40px; padding: 2px;">';
            } else {
                $output .= '<input type="text" name="' . $name . '" value="' . $value . '" class="form-control input-600">';
            }

            $output .= $info['desc'] . '</td>
            </tr>';
        }

        $output .= '</tbody></table>';

        $output .= '<div class="btn-container" style="margin-top: 20px;">
            <input type="submit" value="Save Changes" class="btn btn-primary">
            <a href="' . $modulelink . '" class="btn btn-default">Cancel</a>
        </div>';

        $output .= '</form>';

        return print_r($output);
    }

    /**
     * Get available themes from the templates directory
     */
    private function getAvailableThemes()
    {
        $themes = [];
        $path = ROOTDIR . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;

        if (is_dir($path)) {
            $dirs = scandir($path);
            foreach ($dirs as $dir) {
                if ($dir != '.' && $dir != '..' && is_dir($path . $dir)) {
                    // Ignore internal WHMCS directories if any
                    if (in_array($dir, ['orderforms', 'mail'])) {
                        continue;
                    }
                    $themes[] = $dir;
                }
            }
        }

        // If no themes found, return at least the default 'six' or 'twenty-one'
        if (empty($themes)) {
            $themes = ['six', 'twenty-one'];
        }

        return $themes;
    }

    /**
     * Get available order templates
     */
    private function getAvailableOrderTemplates()
    {
        $templates = [];
        $path = ROOTDIR . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'orderforms' . DIRECTORY_SEPARATOR;

        if (is_dir($path)) {
            $dirs = scandir($path);
            foreach ($dirs as $dir) {
                if ($dir != '.' && $dir != '..' && is_dir($path . $dir)) {
                    $templates[] = $dir;
                }
            }
        }

        if (empty($templates)) {
            $templates = ['standard_cart'];
        }

        return $templates;
    }

    /**
     * Get available languages
     */
    private function getAvailableLanguages()
    {
        $languages = [];
        $path = ROOTDIR . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR;

        if (is_dir($path)) {
            $files = scandir($path);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                    $languages[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }
        }

        if (empty($languages)) {
            $languages = ['english'];
        }

        return $languages;
    }

    /**
     * Brand-wise relations dashboard showing assigned Clients, Services, and Invoices
     */
    public function relations($vars)
    {
        $id = (int) $_REQUEST['id'];
        $modulelink = $vars['modulelink'];

        $brand = Capsule::table('mod_multibrand_brands')->find($id);
        if (!$brand) {
            return '<div class="alert alert-danger">Brand not found.</div>' . $this->index($vars);
        }

        // Get associated client IDs
        $clientIds = Capsule::table('mod_multibrand_client_brands')
            ->where('brand_id', $id)
            ->pluck('client_id')
            ->toArray();

        // 1. Fetch Clients
        $clients = [];
        if (!empty($clientIds)) {
            $clients = Capsule::table('tblclients')
                ->whereIn('id', $clientIds)
                ->get();
        }

        // 2. Fetch Services
        $services = [];
        if (!empty($clientIds)) {
            $services = Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                ->where('mod_multibrand_service_brands.brand_id', $id)
                ->whereIn('tblhosting.userid', $clientIds)
                ->select(
                    'tblhosting.*', 
                    'tblproducts.name as product_name', 
                    'tblclients.firstname', 
                    'tblclients.lastname'
                )
                ->get();
        }

        // 3. Fetch Invoices
        $invoices = [];
        if (!empty($clientIds)) {
            $invoices = Capsule::table('tblinvoices')
                ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->leftJoin('mod_multibrand_brands', 'mod_multibrand_invoice_brands.brand_id', '=', 'mod_multibrand_brands.id')
                ->where('mod_multibrand_invoice_brands.brand_id', $id)
                ->whereIn('tblinvoices.userid', $clientIds)
                ->select(
                    'tblinvoices.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname',
                    'mod_multibrand_invoice_brands.brand_id',
                    'mod_multibrand_brands.brand_name'
                )
                ->get();
        }

        // Counts
        $clientsCount = count($clients);
        $servicesCount = count($services);
        $invoicesCount = count($invoices);

        // Header CSS & Tabs matching the screenshot
        $output = '
        <style>
            .relations-header-container {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 25px;
                border-bottom: 2px solid #ef4444;
                padding-bottom: 10px;
            }
            .relations-title {
                color: #ef4444;
                margin: 0;
                font-weight: bold;
                text-transform: uppercase;
                display: flex;
                align-items: center;
                font-size: 1.6em;
            }
            .relations-tabs {
                border-bottom: 1px solid #ddd;
                margin-bottom: 25px;
                display: flex;
                list-style: none;
                padding: 0;
            }
            .relations-tabs li {
                margin-bottom: -1px;
            }
            .relations-tabs li a {
                display: block;
                padding: 12px 24px;
                font-size: 1.1em;
                color: #555;
                text-decoration: none;
                font-weight: 600;
                border-bottom: 3px solid transparent;
                transition: all 0.2s ease;
            }
            .relations-tabs li a:hover {
                color: #333;
                background: #f5f5f5;
                border-radius: 4px 4px 0 0;
            }
            .relations-tabs li.active a {
                color: #ef4444;
                border-bottom-color: #ef4444;
                font-weight: bold;
            }
            .action-bar-right {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .action-circle-btn {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: 1px solid #ccc;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #555;
                background: #fff;
                cursor: pointer;
                transition: all 0.15s ease;
                text-decoration: none;
            }
            .action-circle-btn:hover {
                background: #f0f0f0;
                color: #000;
                border-color: #999;
                text-decoration: none;
            }
        </style>

        <div class="relations-header-container">
            <h3 class="relations-title">
                <i class="fas fa-exchange-alt" style="margin-right: 12px;"></i> Relations
            </h3>
            <div>
                <a href="' . $modulelink . '" class="btn btn-default"><i class="fas fa-arrow-left"></i> Back to Brands</a>
            </div>
        </div>

        <ul class="relations-tabs" role="tablist">
            <li class="active"><a href="#tab-clients" role="tab" data-toggle="tab">Clients (' . $clientsCount . ')</a></li>
            <li><a href="#tab-services" role="tab" data-toggle="tab">Services (' . $servicesCount . ')</a></li>
            <li><a href="#tab-invoices" role="tab" data-toggle="tab">Invoices (' . $invoicesCount . ')</a></li>
        </ul>

        <div class="tab-content">
            <!-- CLIENTS TAB -->
            <div class="tab-pane active" id="tab-clients">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-weight: bold; color: #ef4444; text-transform: uppercase; display: flex; align-items: center;">
                        <i class="fas fa-users" style="margin-right: 8px;"></i> Clients
                    </h4>
                    <div class="action-bar-right">
                        <a class="action-circle-btn" title="Search"><i class="fas fa-search"></i></a>
                        <a href="' . $modulelink . '&action=add_relation&brand_id=' . $id . '" class="action-circle-btn" title="Add Relation"><i class="fas fa-plus"></i></a>
                        <a class="action-circle-btn" title="Help"><i class="fas fa-question"></i></a>
                    </div>
                </div>

                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                    <thead>
                        <tr>
                            <th width="30" class="text-center"><input type="checkbox"></th>
                            <th width="60">#ID</th>
                            <th>First name</th>
                            <th>Last name</th>
                            <th>Company</th>
                            <th>Created At</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (count($clients) > 0) {
            foreach ($clients as $client) {
                $createdAt = isset($client->datecreated) ? date('Y-m-d', strtotime($client->datecreated)) : '-';
                $company = $client->companyname ?: '-';
                $output .= '<tr>
                    <td class="text-center"><input type="checkbox"></td>
                    <td>' . $client->id . '</td>
                    <td>' . htmlspecialchars($client->firstname) . '</td>
                    <td>' . htmlspecialchars($client->lastname) . '</td>
                    <td>' . htmlspecialchars($company) . '</td>
                    <td>' . htmlspecialchars($createdAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=reassign_client&brand_id=' . $id . '&client_id=' . $client->id . '" class="btn btn-sm btn-primary" style="margin-right: 5px;" title="Swap/Reassign Brands"><i class="fas fa-exchange-alt"></i></a>
                        <a href="' . $modulelink . '&action=unlink_client&brand_id=' . $id . '&client_id=' . $client->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this client from this brand?\')" title="Unlink Brand"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No clients assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                    </tbody>
                </table>
            </div>

            <!-- SERVICES TAB -->
            <div class="tab-pane" id="tab-services">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-weight: bold; color: #ef4444; text-transform: uppercase;">
                        <i class="fas fa-cubes" style="margin-right: 8px;"></i> Services
                    </h4>
                </div>

                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                    <thead>
                        <tr>
                            <th width="60">#ID</th>
                            <th>Client</th>
                            <th>Product/Service</th>
                            <th>Domain</th>
                            <th>Amount</th>
                            <th>Billing Cycle</th>
                            <th>Next Due Date</th>
                            <th width="120" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (count($services) > 0) {
            foreach ($services as $service) {
                $statusColor = '#777';
                if (strtolower($service->domainstatus) == 'active') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($service->domainstatus) == 'suspended') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($service->domainstatus) == 'terminated') {
                    $statusColor = '#d9534f';
                }
                
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($service->domainstatus) . '</span>';
                $nextDue = ($service->nextduedate && $service->nextduedate != '0000-00-00') ? $service->nextduedate : '-';

                $output .= '<tr>
                    <td>' . $service->id . '</td>
                    <td>' . htmlspecialchars($service->firstname . ' ' . $service->lastname) . '</td>
                    <td>' . htmlspecialchars($service->product_name) . '</td>
                    <td>' . ($service->domain ? htmlspecialchars($service->domain) : '-') . '</td>
                    <td>' . htmlspecialchars($service->amount) . '</td>
                    <td>' . htmlspecialchars($service->billingcycle) . '</td>
                    <td>' . htmlspecialchars($nextDue) . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="8" class="text-center">No services found for clients under this brand.</td></tr>';
        }

        $output .= '
                    </tbody>
                </table>
            </div>

            <!-- INVOICES TAB -->
            <div class="tab-pane" id="tab-invoices">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-weight: bold; color: #ef4444; text-transform: uppercase;">
                        <i class="fas fa-file-invoice-dollar" style="margin-right: 8px;"></i> Invoices
                    </h4>
                </div>

                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                    <thead>
                        <tr>
                            <th width="100">#Invoice ID</th>
                            <th>Client</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Payment Method</th>
                            <th>Brand</th>
                            <th width="120" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>';

        if (count($invoices) > 0) {
            foreach ($invoices as $invoice) {
                $statusColor = '#777';
                if (strtolower($invoice->status) == 'paid') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($invoice->status) == 'unpaid') {
                    $statusColor = '#f0ad4e';
                } elseif (strtolower($invoice->status) == 'cancelled' || strtolower($invoice->status) == 'refunded') {
                    $statusColor = '#d9534f';
                }
                
                $statusBadge = '<span class="label" style="background-color: ' . $statusColor . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; font-size: 0.85em; text-transform: uppercase;">' . htmlspecialchars($invoice->status) . '</span>';
                $invDate = $invoice->date ? $invoice->date : '-';
                $dueDate = $invoice->duedate ? $invoice->duedate : '-';
                $brandDisplay = isset($invoice->brand_name) && $invoice->brand_name ? htmlspecialchars($invoice->brand_name) : '<span style="color: #999; font-style: italic;">-</span>';

                $output .= '<tr>
                    <td>' . $invoice->id . '</td>
                    <td>' . htmlspecialchars($invoice->firstname . ' ' . $invoice->lastname) . '</td>
                    <td>' . htmlspecialchars($invDate) . '</td>
                    <td>' . htmlspecialchars($dueDate) . '</td>
                    <td>' . htmlspecialchars($invoice->total) . '</td>
                    <td>' . htmlspecialchars($invoice->paymentmethod) . '</td>
                    <td>' . $brandDisplay . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="8" class="text-center">No invoices found for clients under this brand.</td></tr>';
        }

        $output .= '
                    </tbody>
                </table>
            </div>
        </div>';

        return print_r($output);
    }

    /**
     * Unlink a client from a brand
     */
    public function unlink_client($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $clientId = (int) $_REQUEST['client_id'];

        try {
            Capsule::table('mod_multibrand_client_brands')
                ->where('client_id', $clientId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Client unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking client: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->relations($vars);
    }

    /**
     * Reassign/swap brands for a client
     */
    public function reassign_client($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $clientId = (int) $_REQUEST['client_id'];
        $modulelink = $vars['modulelink'];

        $client = Capsule::table('tblclients')->find($clientId);
        if (!$client) {
            return '<div class="alert alert-danger">Client not found.</div>';
        }

        $brands = Capsule::table('mod_multibrand_brands')->get();
        $assignedBrandIds = Capsule::table('mod_multibrand_client_brands')
            ->where('client_id', $clientId)
            ->pluck('brand_id')
            ->toArray();

        $output = '<h3>Manage Brands for Client: ' . htmlspecialchars($client->firstname . ' ' . $client->lastname) . '</h3>';
        $output .= '<form method="post" action="' . $modulelink . '&action=save_client_brands">';
        $output .= '<input type="hidden" name="client_id" value="' . $clientId . '">';
        $output .= '<input type="hidden" name="brand_id" value="' . $brandId . '">';
        
        $output .= '<div class="panel panel-default" style="margin-top: 20px; max-width: 600px;">
            <div class="panel-body">';
        
        foreach ($brands as $b) {
            $checked = in_array($b->id, $assignedBrandIds) ? ' checked' : '';
            $output .= '<div class="checkbox" style="margin: 10px 0;">
                <label style="font-size: 1.1em; cursor: pointer;">
                    <input type="checkbox" name="brand_ids[]" value="' . $b->id . '"' . $checked . '> ' . htmlspecialchars($b->brand_name) . ' (' . htmlspecialchars($b->domain) . ')
                </label>
            </div>';
        }

        $output .= '<div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="' . $modulelink . '&action=relations&id=' . $brandId . '" class="btn btn-default" style="margin-left: 10px;">Cancel</a>
        </div>';

        $output .= '</div></div></form>';

        return print_r($output);
    }

    /**
     * Save updated brand assignments for a client
     */
    public function save_client_brands($vars)
    {
        $clientId = (int) $_POST['client_id'];
        $brandId = (int) $_POST['brand_id'];
        $submittedBrandIds = isset($_POST['brand_ids']) ? array_map('intval', $_POST['brand_ids']) : [];

        try {
            Capsule::table('mod_multibrand_client_brands')->where('client_id', $clientId)->delete();
            foreach ($submittedBrandIds as $bId) {
                if ($bId > 0) {
                    Capsule::table('mod_multibrand_client_brands')->insert([
                        'client_id' => $clientId,
                        'brand_id' => $bId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            echo '<div class="alert alert-success">Client brand relationships updated successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error updating brand relationships: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->relations($vars);
    }

    /**
     * Add brand relation to a client
     */
    public function add_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $modulelink = $vars['modulelink'];

        $brand = Capsule::table('mod_multibrand_brands')->find($brandId);
        if (!$brand) {
            return '<div class="alert alert-danger">Brand not found.</div>';
        }

        // Get all clients in WHMCS
        $clients = Capsule::table('tblclients')
            ->select('id', 'firstname', 'lastname', 'email', 'companyname')
            ->orderBy('firstname', 'asc')
            ->get();

        // Get already assigned client IDs for this brand
        $assignedClientIds = Capsule::table('mod_multibrand_client_brands')
            ->where('brand_id', $brandId)
            ->pluck('client_id')
            ->toArray();

        $output = '<h3>Assign Client to Brand: ' . htmlspecialchars($brand->brand_name) . '</h3>';
        $output .= '<form method="post" action="' . $modulelink . '&action=save_relation">';
        $output .= '<input type="hidden" name="brand_id" value="' . $brandId . '">';
        
        $output .= '<div class="panel panel-default" style="margin-top: 20px; max-width: 600px;">
            <div class="panel-body">
                <div class="form-group">
                    <label for="client_id">Select Client</label>
                    <select name="client_id" id="client_id" class="form-control select-inline" style="width: 100%; min-width: 300px;">';
        
        foreach ($clients as $client) {
            $disabled = in_array($client->id, $assignedClientIds) ? ' disabled style="color:#aaa;"' : '';
            $company = $client->companyname ? ' (' . $client->companyname . ')' : '';
            $output .= '<option value="' . $client->id . '"' . $disabled . '>' 
                . htmlspecialchars($client->firstname . ' ' . $client->lastname) 
                . ' - ' . htmlspecialchars($client->email) 
                . $company 
                . (in_array($client->id, $assignedClientIds) ? ' [Already Assigned]' : '') 
                . '</option>';
        }

        $output .= '      </select>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Assign Client</button>
                    <a href="' . $modulelink . '&action=relations&id=' . $brandId . '" class="btn btn-default" style="margin-left: 10px;">Cancel</a>
                </div>
            </div>
        </div></form>';

        return print_r($output);
    }

    /**
     * Save new brand relation for a client
     */
    public function save_relation($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $clientId = (int) $_POST['client_id'];

        try {
            $exists = Capsule::table('mod_multibrand_client_brands')
                ->where('client_id', $clientId)
                ->where('brand_id', $brandId)
                ->exists();

            if (!$exists) {
                Capsule::table('mod_multibrand_client_brands')->insert([
                    'client_id' => $clientId,
                    'brand_id' => $brandId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                echo '<div class="alert alert-success">Client assigned to brand successfully.</div>';
            } else {
                echo '<div class="alert alert-warning">Client is already assigned to this brand.</div>';
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error assigning client: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->relations($vars);
    }

    /**
     * Save new brand relation for a KB article from Brand Edit page
     */
    public function save_kb_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $kbId = (int) $_POST['kb_id'];

        if ($brandId > 0 && $kbId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_kb_brands')
                    ->where('article_id', $kbId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_kb_brands')->insert([
                        'article_id' => $kbId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Article relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Article is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning article: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink a KB article from a brand from Brand Edit page
     */
    public function unlink_kb_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $kbId = (int) $_REQUEST['kb_id'];

        try {
            Capsule::table('mod_multibrand_kb_brands')
                ->where('article_id', $kbId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Article unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking article: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple KB articles from a brand from Brand Edit page
     */
    public function bulk_unlink_kb_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $kbIds = isset($_POST['kb_ids']) ? array_map('intval', $_POST['kb_ids']) : [];

        if (!empty($kbIds)) {
            try {
                Capsule::table('mod_multibrand_kb_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('article_id', $kbIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected article relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected article relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No articles selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save new brand relation for a download file from Brand Edit page
     */
    public function save_download_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $downloadId = (int) $_POST['download_id'];

        if ($brandId > 0 && $downloadId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_download_brands')
                    ->where('download_id', $downloadId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_download_brands')->insert([
                        'download_id' => $downloadId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Download relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Download is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning download: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink a download file from a brand from Brand Edit page
     */
    public function unlink_download_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $downloadId = (int) $_REQUEST['download_id'];

        try {
            Capsule::table('mod_multibrand_download_brands')
                ->where('download_id', $downloadId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Download unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking download: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple download files from a brand from Brand Edit page
     */
    public function bulk_unlink_download_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $downloadIds = isset($_POST['dl_ids']) ? array_map('intval', $_POST['dl_ids']) : [];

        if (!empty($downloadIds)) {
            try {
                Capsule::table('mod_multibrand_download_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('download_id', $downloadIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected download relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected download relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No downloads selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save new brand relation for an announcement from Brand Edit page
     */
    public function save_announcement_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $announcementId = (int) $_POST['announcement_id'];

        if ($brandId > 0 && $announcementId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_announcement_brands')
                    ->where('announcement_id', $announcementId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_announcement_brands')->insert([
                        'announcement_id' => $announcementId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Announcement relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Announcement is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning announcement: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink an announcement from a brand from Brand Edit page
     */
    public function unlink_announcement_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $announcementId = (int) $_REQUEST['announcement_id'];

        try {
            Capsule::table('mod_multibrand_announcement_brands')
                ->where('announcement_id', $announcementId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Announcement unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking announcement: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple announcements from a brand from Brand Edit page
     */
    public function bulk_unlink_announcement_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $announcementIds = isset($_POST['ann_ids']) ? array_map('intval', $_POST['ann_ids']) : [];

        if (!empty($announcementIds)) {
            try {
                Capsule::table('mod_multibrand_announcement_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('announcement_id', $announcementIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected announcement relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected announcement relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No announcements selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save new brand relation for a promotion from Brand Edit page
     */
    public function save_promotion_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $promotionId = (int) $_POST['promotion_id'];

        if ($brandId > 0 && $promotionId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_promotion_brands')
                    ->where('promotion_id', $promotionId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_promotion_brands')->insert([
                        'promotion_id' => $promotionId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Promotion relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Promotion is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning promotion: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink a promotion from a brand from Brand Edit page
     */
    public function unlink_promotion_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $promotionId = (int) $_REQUEST['promotion_id'];

        try {
            Capsule::table('mod_multibrand_promotion_brands')
                ->where('promotion_id', $promotionId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Promotion unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking promotion: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple promotions from a brand from Brand Edit page
     */
    public function bulk_unlink_promotion_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $promotionIds = isset($_POST['promo_ids']) ? array_map('intval', $_POST['promo_ids']) : [];

        if (!empty($promotionIds)) {
            try {
                Capsule::table('mod_multibrand_promotion_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('promotion_id', $promotionIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected promotion relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected promotion relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No promotions selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save new brand relation for a billable item from Brand Edit page
     */
    public function save_billable_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $billableId = (int) $_POST['billable_id'];

        if ($brandId > 0 && $billableId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_billable_brands')
                    ->where('billable_id', $billableId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_billable_brands')->insert([
                        'billable_id' => $billableId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Billable item relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Billable item is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning billable item: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink a billable item from a brand from Brand Edit page
     */
    public function unlink_billable_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $billableId = (int) $_REQUEST['billable_id'];

        try {
            Capsule::table('mod_multibrand_billable_brands')
                ->where('billable_id', $billableId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Billable item unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking billable item: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple billable items from a brand from Brand Edit page
     */
    public function bulk_unlink_billable_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $billableIds = isset($_POST['billable_ids']) ? array_map('intval', $_POST['billable_ids']) : [];

        if (!empty($billableIds)) {
            try {
                Capsule::table('mod_multibrand_billable_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('billable_id', $billableIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected billable item relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected billable item relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No billable items selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save new brand relation for an email log entry from Brand Edit page
     */
    public function save_email_relation_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $emailId = (int) $_POST['email_id'];

        if ($brandId > 0 && $emailId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_email_brands')
                    ->where('email_id', $emailId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_email_brands')->insert([
                        'email_id' => $emailId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Email relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Email is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning email: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Unlink an email from a brand from Brand Edit page
     */
    public function unlink_email_from_edit($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $emailId = (int) $_REQUEST['email_id'];

        try {
            Capsule::table('mod_multibrand_email_brands')
                ->where('email_id', $emailId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Email unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking email: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Bulk unlink multiple emails from a brand from Brand Edit page
     */
    public function bulk_unlink_email_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $emailIds = isset($_POST['email_ids']) ? array_map('intval', $_POST['email_ids']) : [];

        if (!empty($emailIds)) {
            try {
                Capsule::table('mod_multibrand_email_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('email_id', $emailIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected email relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected email relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No emails selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }
}
