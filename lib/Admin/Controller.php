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
            'pricing_overrides' => ['type' => 'text', 'default' => NULL],
            'brand_switcher' => ['type' => 'boolean', 'default' => 0],
            'ticket_departments' => ['type' => 'text', 'default' => NULL],
            'order_template' => ['type' => 'string', 'default' => NULL],
            'default_language' => ['type' => 'string', 'default' => NULL],
            'auto_client_assignment' => ['type' => 'boolean', 'default' => 0],
            'tos_url' => ['type' => 'string', 'default' => NULL],
            'signature' => ['type' => 'text', 'default' => NULL],
            'payment_gateways' => ['type' => 'text', 'default' => NULL],
            'smtp_settings' => ['type' => 'text', 'default' => NULL],
            'email_template_settings' => ['type' => 'text', 'default' => NULL],
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
                        <a href="' . $modulelink . '&action=edit&id=' . $brand->id . '" class="btn btn-sm btn-primary" style="margin-right: 5px;" title="Edit Brand"><i class="fas fa-edit"></i></a>
                        <a href="' . $modulelink . '&action=delete&id=' . $brand->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this brand?\')" title="Delete Brand"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="5" class="text-center">No brands found. Click "Add New Brand" to create one.</td></tr>';
        }

        $output .= '</tbody></table>';

        $output .= '
        <script type="text/javascript">
        jQuery("table.datatable").each(function() {
            if (jQuery(this).find("tbody td[colspan]").length > 0) {
                var tbl = jQuery(this);
                tbl.removeClass("datatable").addClass("datatable-placeholder");
                jQuery(document).ready(function() {
                    tbl.addClass("datatable").removeClass("datatable-placeholder");
                });
            }
        });
        </script>';

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
        $brand = null;
        if (isset($_REQUEST['validation_error'])) {
            $brand = new \stdClass();
            foreach ($_POST as $key => $val) {
                $brand->$key = $val;
            }
            $brand->id = 0;
        }
        return $this->renderForm($vars, $brand);
    }

    /**
     * Consolidated Brand Details & Edit Dashboard
     */
    public function edit($vars)
    {
        $id = (int) $_REQUEST['id'];
        $modulelink = $vars['modulelink'];
        $brand = Capsule::table('mod_multibrand_brands')->where('id', $id)->first();
        $activeTab = isset($_REQUEST['active_tab']) ? $_REQUEST['active_tab'] : '#set-general';
        if (!in_array($activeTab, ['#set-general', '#set-billing', '#set-gateways', '#set-smtp', '#set-emails', '#set-maintenance'])) {
            $activeTab = '#set-general';
        }

        if (!$brand) {
            return '<div class="alert alert-danger">Brand not found.</div>' . $this->index($vars);
        }

        if (isset($_REQUEST['validation_error']) && $brand) {
            foreach ($_POST as $key => $val) {
                if (property_exists($brand, $key)) {
                    $brand->$key = $val;
                }
            }
        }
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
                } catch (\Exception $e) {}
           

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

        // Fetch active payment gateways in WHMCS
        $whmcsGateways = [];
        try {
            $whmcsGateways = Capsule::table('tblpaymentgateways')
                ->where('setting', 'name')
                ->orderBy('value', 'asc')
                ->get();
        } catch (\Exception $e) {}

        // Parse SMTP and templates values
        $smtp = json_decode(htmlspecialchars_decode($brand->smtp_settings ?: '{}'), true);
        $email_templates = json_decode(htmlspecialchars_decode($brand->email_template_settings ?: '{}'), true);

        // Templates variables will be loaded below where brandColor is fully defined

        // Fetch configured brand-specific template entries
        $brandedMap = [];
        try {
            $brandedTemplates = Capsule::table('mod_multibrand_email_templates')->where('brand_id', $brand->id)->get();
            foreach ($brandedTemplates as $bt) {
                $translations = json_decode(htmlspecialchars_decode($bt->translations ?: '{}'), true);
                $isBranded = false;
                if (!empty($translations)) {
                    foreach ($translations as $lang => $data) {
                        if (!empty($data['subject']) || !empty($data['message'])) {
                            $isBranded = true;
                            break;
                        }
                    }
                }
                $brandedMap[$bt->template_name] = [
                    'status' => (int)$bt->status,
                    'branded' => $isBranded
                ];
            }
        } catch (\Exception $e) {}

        // Fetch client-facing templates and group them by category for Email Templates tab
        $allTplRows = [];
        try {
            $allTplRows = Capsule::table('tblemailtemplates')
                ->select('id', 'type', 'name', 'custom')
                ->orderBy('name', 'asc')
                ->get();
        } catch (\Exception $e) {}

        $groupedTemplates = [
            'general' => [],
            'product' => [],
            'domain' => [],
            'support' => [],
            'invoice' => []
        ];

        foreach ($allTplRows as $tpl) {
            $type = strtolower($tpl->type);
            if (in_array($type, ['general', 'user', 'invite', 'affiliate', 'notification', 'admin_invite'])) {
                $groupedTemplates['general'][] = $tpl;
            } elseif ($type === 'product') {
                $groupedTemplates['product'][] = $tpl;
            } elseif ($type === 'domain') {
                $groupedTemplates['domain'][] = $tpl;
            } elseif ($type === 'support') {
                $groupedTemplates['support'][] = $tpl;
            } elseif ($type === 'invoice') {
                $groupedTemplates['invoice'][] = $tpl;
            }
        }

        // Build category HTML
        $emailTemplatesHtml = '<h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-envelope" style="margin-right: 8px;"></i> Email Templates</h4>';
        $emailTemplatesHtml .= '<p style="color: #777; font-size: 0.9em; line-height: 1.5; margin-bottom: 20px;">Manage custom headers, footers, and templates for system emails dispatched by this brand.</p>';
        
        // Category sub-tabs headers
        $emailTemplatesHtml .= '
        <ul class="nav nav-tabs" id="emailTemplatesCatTabs" style="margin-bottom: 20px; border-bottom: 2px solid #eee; display: flex; gap: 4px;">
            <li class="active"><a href="#email-cat-general" data-toggle="tab" style="font-weight: 600; padding: 10px 20px; margin-right: 5px;">General</a></li>
            <li><a href="#email-cat-product" data-toggle="tab" style="font-weight: 600; padding: 10px 20px; margin-right: 5px;">Product</a></li>
            <li><a href="#email-cat-domain" data-toggle="tab" style="font-weight: 600; padding: 10px 20px; margin-right: 5px;">Domain</a></li>
            <li><a href="#email-cat-support" data-toggle="tab" style="font-weight: 600; padding: 10px 20px; margin-right: 5px;">Support</a></li>
            <li><a href="#email-cat-invoice" data-toggle="tab" style="font-weight: 600; padding: 10px 20px; margin-right: 5px;">Invoice</a></li>
        </ul>';

        $emailTemplatesHtml .= '<div class="tab-content" style="border: none; padding: 0; background: none; box-shadow: none; max-height: 500px; overflow-y: auto;">';

        $catKeys = ['general', 'product', 'domain', 'support', 'invoice'];
        foreach ($catKeys as $catKey) {
            $activeClass = ($catKey === 'general') ? 'active' : '';
            $emailTemplatesHtml .= '<div class="tab-pane ' . $activeClass . '" id="email-cat-' . $catKey . '">';
            $emailTemplatesHtml .= '<table class="table table-hover" style="width: 100%; border-collapse: collapse; margin-bottom: 0;">';
            $emailTemplatesHtml .= '<tbody>';
            
            if (empty($groupedTemplates[$catKey])) {
                $emailTemplatesHtml .= '<tr><td colspan="2" class="text-center" style="padding: 20px; color: #999;">No templates available in this category.</td></tr>';
            } else {
                foreach ($groupedTemplates[$catKey] as $t) {
                    $isBranded = false;
                    $isEnabled = false;
                    
                    if (isset($brandedMap[$t->name])) {
                        $isEnabled = $brandedMap[$t->name]['status'] === 1;
                        $isBranded = $brandedMap[$t->name]['branded'];
                    }
                    
                    $emailTemplatesHtml .= '<tr>';
                    $emailTemplatesHtml .= '<td style="padding: 12px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle;">';
                    $emailTemplatesHtml .= '<span style="font-size: 0.95em; font-weight: 600; color: #333;">' . htmlspecialchars($t->name) . '</span>';
                    if ($isBranded) {
                        $emailTemplatesHtml .= '<span class="label label-danger" style="background-color: #d9534f; font-size: 0.75em; padding: 2px 6px; border-radius: 3px; font-weight: bold; text-transform: uppercase; margin-left: 8px; color: #fff;">branded</span>';
                    }
                    if ($t->custom == 1) {
                        $emailTemplatesHtml .= '<span class="label label-default" style="background-color: #777; font-size: 0.75em; padding: 2px 6px; border-radius: 3px; font-weight: bold; text-transform: uppercase; margin-left: 8px; color: #fff;">custom</span>';
                    }
                    $emailTemplatesHtml .= '</td>';
                    
                    $emailTemplatesHtml .= '<td style="padding: 12px 10px; border-bottom: 1px solid #f0f0f0; text-align: right; vertical-align: middle; width: 250px;">';
                    $emailTemplatesHtml .= '<div style="display: flex; align-items: center; justify-content: flex-end; gap: 15px;">';
                    $emailTemplatesHtml .= '<button type="button" class="btn btn-primary btn-sm edit-email-template-btn" data-template-name="' . htmlspecialchars($t->name) . '" style="padding: 5px 10px; border-radius: 4px; background: #337ab7; color: #fff; border: 1px solid #2e6da4;" title="Edit Template">';
                    $emailTemplatesHtml .= '<i class="fas fa-edit"></i>';
                    $emailTemplatesHtml .= '</button>';
                    
                    $emailTemplatesHtml .= '<label class="mb-switch" style="margin-bottom: 0;">';
                    $emailTemplatesHtml .= '<input type="checkbox" class="email-template-toggle" name="email_template_status[' . htmlspecialchars($t->name) . ']" value="1" ' . ($isEnabled ? 'checked' : '') . ' onchange="var span = this.parentElement.nextElementSibling; span.textContent = this.checked ? \'Enabled\' : \'Disabled\'; span.className = this.checked ? \'label label-success\' : \'label label-default\';">';
                    $emailTemplatesHtml .= '<span class="mb-slider"></span>';
                    $emailTemplatesHtml .= '</label>';
                    $emailTemplatesHtml .= '<span class="label ' . ($isEnabled ? 'label-success' : 'label-default') . '" style="display: inline-block; width: 65px; text-align: center; font-size: 0.85em; padding: 4px 0; border-radius: 3px; font-weight: 600;">' . ($isEnabled ? 'Enabled' : 'Disabled') . '</span>';
                    $emailTemplatesHtml .= '</div>';
                    $emailTemplatesHtml .= '</td>';
                    $emailTemplatesHtml .= '</tr>';
                }
            }
            
            $emailTemplatesHtml .= '</tbody>';
            $emailTemplatesHtml .= '</table>';
            $emailTemplatesHtml .= '</div>';
        }
        
        $emailTemplatesHtml .= '</div>';
        
        // Add save configuration button at the bottom of the templates list
        // $emailTemplatesHtml .= '
        // <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; text-align: right;">
        //     <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; font-weight: bold; padding: 8px 20px; font-size: 0.95em;"><i class="fas fa-save" style="margin-right: 6px;"></i> Save Configuration</button>
        // </div>';

        // Fetch all WHMCS clients for the Assign Client modal dropdown
        $allClients = [];
        try {
            $allClients = Capsule::table('tblclients')->orderBy('firstname', 'asc')->get();
        } catch (\Exception $e) {}

        // Fetch all WHMCS client groups for the Assign Client modal dropdown
        $clientGroups = [];
        try {
            $clientGroups = Capsule::table('tblclientgroups')->orderBy('groupname', 'asc')->get();
        } catch (\Exception $e) {}

        // Fetch all brands for the Migrate Client modal dropdown
        $brandsList = [];
        try {
            $brandsList = Capsule::table('mod_multibrand_brands')->orderBy('brand_name', 'asc')->get();
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
        try {
            $thisMonthSale = Capsule::table('tblinvoices')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                ->where('tblinvoices.status', 'Paid')
                ->where('tblinvoices.date', '>=', date('Y-m-01'))
                ->sum('tblinvoices.total');
        } catch (\Exception $e) {}

        $allTimeSale = 0;
        try {
            $allTimeSale = Capsule::table('tblinvoices')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                ->where('tblinvoices.status', 'Paid')
                ->sum('tblinvoices.total');
        } catch (\Exception $e) {}

        $thisMonthSaleFormatted = '$' . number_format($thisMonthSale, 2) . ' USD';
        $allTimeSaleFormatted = '$' . number_format($allTimeSale, 2) . ' USD';
        $updatedAt = isset($brand->updated_at) && $brand->updated_at ? $brand->updated_at : '-';

        // 2. Fetch WHMCS Products, Addons, Domains, and Bundles for Branded Services Pricing
        $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
        if (!is_array($pricingOverrides)) {
            $pricingOverrides = [];
        }

        $brandedProducts = [];
        if (!empty($pricingOverrides['products'])) {
            try {
                $brandedProducts = Capsule::table('tblproducts')
                    ->whereIn('id', array_keys($pricingOverrides['products']))
                    ->orderBy('name', 'asc')
                    ->get();
            } catch (\Exception $e) {}
        }

        $brandedAddons = [];
        if (!empty($pricingOverrides['addons'])) {
            try {
                $brandedAddons = Capsule::table('tbladdons')
                    ->whereIn('id', array_keys($pricingOverrides['addons']))
                    ->orderBy('name', 'asc')
                    ->get();
            } catch (\Exception $e) {}
        }

        $brandedDomains = [];
        if (!empty($pricingOverrides['domains'])) {
            try {
                $brandedDomains = Capsule::table('tbldomainpricing')
                    ->whereIn('id', array_keys($pricingOverrides['domains']))
                    ->orderBy('extension', 'asc')
                    ->get();
            } catch (\Exception $e) {}
        }

        $brandedBundles = [];
        if (!empty($pricingOverrides['bundles'])) {
            try {
                $brandedBundles = Capsule::table('tblbundles')
                    ->whereIn('id', array_keys($pricingOverrides['bundles']))
                    ->orderBy('name', 'asc')
                    ->get();
            } catch (\Exception $e) {}
        }
        $currenciesList = [];
        try {
            $currenciesList = Capsule::table('tblcurrencies')->orderBy('code', 'asc')->get();
        } catch (\Exception $e) {}

        // 3. Fetch Relations (Clients, Services, Invoices)
        $clients = [];
        if (!empty($clientIds)) {
            $clients = Capsule::table('tblclients')
                ->whereIn('id', $clientIds)
                ->get();
        }

        $services = [];
        try {
            $services = Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_service_brands', 'tblhosting.id', '=', 'mod_multibrand_service_brands.service_id')
                ->where('mod_multibrand_service_brands.brand_id', $brand->id)
                ->select(
                    'tblhosting.*', 
                    'tblproducts.name as product_name', 
                    'tblclients.firstname', 
                    'tblclients.lastname'
                )
                ->get();
        } catch (\Exception $e) {}

        // Fetch assigned addons for this brand (explicitly assigned or inheriting parent service brand)
        $assignedAddons = [];
        try {
            $assignedAddons = Capsule::table('tblhostingaddons')
                ->leftJoin('tblhosting', 'tblhostingaddons.hostingid', '=', 'tblhosting.id')
                ->join('tblclients', 'tblhostingaddons.userid', '=', 'tblclients.id')
                ->leftJoin('tbladdons', 'tblhostingaddons.addonid', '=', 'tbladdons.id')
                ->join('mod_multibrand_addon_brands', 'tblhostingaddons.id', '=', 'mod_multibrand_addon_brands.addon_id')
                ->leftJoin('mod_multibrand_service_brands', 'tblhostingaddons.hostingid', '=', 'mod_multibrand_service_brands.service_id')
                ->where(function($q) use ($brand) {
                    $q->where('mod_multibrand_addon_brands.brand_id', $brand->id)
                      ->orWhere('mod_multibrand_service_brands.brand_id', $brand->id);
                })
                ->select(
                    'tblhostingaddons.*',
                    'tbladdons.name as addon_name',
                    'tblhosting.domain as service_domain',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.id as userid'
                )
                ->get();
        } catch (\Exception $e) {}

        // Fetch explicitly assigned domains for this brand
        $assignedDomains = [];
        try {
            $assignedDomains = Capsule::table('tbldomains')
                ->join('tblclients', 'tbldomains.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_domain_brands', 'tbldomains.id', '=', 'mod_multibrand_domain_brands.domain_id')
                ->where('mod_multibrand_domain_brands.brand_id', $brand->id)
                ->select(
                    'tbldomains.*',
                    'tblclients.firstname',
                    'tblclients.lastname',
                    'tblclients.id as userid'
                )
                ->get();
        } catch (\Exception $e) {}

        $invoices = [];
        try {
            $invoices = Capsule::table('tblinvoices')
                ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
                ->leftJoin('tblcurrencies', 'tblclients.currency', '=', 'tblcurrencies.id')
                ->join('mod_multibrand_invoice_brands', 'tblinvoices.id', '=', 'mod_multibrand_invoice_brands.invoice_id')
                ->leftJoin('mod_multibrand_brands', 'mod_multibrand_invoice_brands.brand_id', '=', 'mod_multibrand_brands.id')
                ->where('mod_multibrand_invoice_brands.brand_id', $brand->id)
                ->select(
                    'tblinvoices.*', 
                    'tblclients.firstname', 
                    'tblclients.lastname',
                    'tblcurrencies.code as currency_code',
                    'mod_multibrand_invoice_brands.brand_id',
                    'mod_multibrand_brands.brand_name'
                )
                ->get();
        } catch (\Exception $e) {}

        $quotes = [];
        try {
            // Dynamically ensure mod_multibrand_quote_brands exists
            if (!Capsule::schema()->hasTable('mod_multibrand_quote_brands')) {
                Capsule::schema()->create('mod_multibrand_quote_brands', function ($table) {
                    $table->increments('id');
                    $table->integer('quote_id')->unique();
                    $table->integer('brand_id');
                    $table->timestamps();
                });
            }

            $quotes = Capsule::table('tblquotes')
                ->leftJoin('tblclients', 'tblquotes.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_quote_brands', 'tblquotes.id', '=', 'mod_multibrand_quote_brands.quote_id')
                ->where('mod_multibrand_quote_brands.brand_id', $brand->id)
                ->select(
                    'tblquotes.*',
                    Capsule::raw('COALESCE(tblclients.firstname, tblquotes.firstname) as firstname'),
                    Capsule::raw('COALESCE(tblclients.lastname, tblquotes.lastname) as lastname')
                )
                ->get();
        } catch (\Exception $e) {}

        $tickets = [];
        try {
            $deptIds = array_filter(array_map('intval', explode(',', $brand->ticket_departments ?: '')));
            $query = Capsule::table('tbltickets')
                ->join('tblclients', 'tbltickets.userid', '=', 'tblclients.id')
                ->join('mod_multibrand_ticket_brands', 'tbltickets.id', '=', 'mod_multibrand_ticket_brands.ticket_id')
                ->where('mod_multibrand_ticket_brands.brand_id', $brand->id);
            
            $tickets = $query->select('tbltickets.*', 'tblclients.firstname', 'tblclients.lastname')
                ->orderBy('tbltickets.id', 'desc')
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
            /* Tab search filter row */
            .tab-search-row {
                display: none;
                margin-bottom: 12px;
                animation: slideDown 0.2s ease;
            }
            .tab-search-row.open {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .tab-search-row input {
                flex: 1;
                padding: 6px 12px;
                border: 1px solid #ccc;
                border-radius: 20px;
                font-size: 0.9em;
                outline: none;
                transition: border-color 0.2s;
            }
            .tab-search-row input:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 2px rgba(0,123,255,0.15);
            }
            .tab-search-row .clear-search-btn {
                background: none;
                border: none;
                color: #999;
                cursor: pointer;
                font-size: 1em;
                padding: 0 6px;
            }
            .tab-search-row .clear-search-btn:hover { color: #d9534f; }
            /* Action circle btn active state */
            .action-circle-btn.active {
                background: #007bff;
                color: #fff;
                border-color: #007bff;
            }
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            /* Help popover custom */
            .tab-help-popover {
                display: none;
                position: absolute;
                right: 0;
                top: 42px;
                z-index: 1050;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 14px 16px;
                min-width: 280px;
                max-width: 340px;
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
                font-size: 0.88em;
                color: #444;
                line-height: 1.5;
            }
            .tab-help-popover.open { display: block; animation: slideDown 0.18s ease; }
            .tab-help-popover h6 { margin: 0 0 8px; font-weight: 700; color: #333; font-size: 1em; }
            .action-bar-right { position: relative; }
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

        <form method="post" action="' . $modulelink . '&action=save" enctype="multipart/form-data" novalidate>' . generate_token() . '' . generate_token() . '
            <input type="hidden" name="id" value="' . $brand->id . '">
            <input type="hidden" name="status_submitted" value="1">
             <input type="hidden" name="active_tab" id="active_tab" value="' . htmlspecialchars($activeTab) . '">
            <!-- TOP ROW (BRAND INFORMATION & SETTINGS) -->
            <div style="display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 30px; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
                
                <!-- LEFT CARD: BRAND INFORMATION -->
                <div style="flex: 1; min-width: 320px; max-width: 380px;">
                    <div style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; height: 100%;">
                        <div style="padding: 15px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-info-circle" style="margin-right: 8px;"></i> Brand Information</span>
                           <!-- <a class="action-circle-btn" style="width: 24px; height: 24px; font-size: 0.8em;" href=""><i class="fas fa-sync-alt"></i></a> -->
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
                                <li class="' . ($activeTab == '#set-general' ? 'active' : '') . '"><a href="#set-general" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">General</a></li>
                                <li class="' . ($activeTab == '#set-billing' ? 'active' : '') . '"><a href="#set-billing" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Billing</a></li>
                                <li class="' . ($activeTab == '#set-gateways' ? 'active' : '') . '"><a href="#set-gateways" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Payment Gateways</a></li>
                                <li class="' . ($activeTab == '#set-smtp' ? 'active' : '') . '"><a href="#set-smtp" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">SMTP</a></li>
                                <li class="' . ($activeTab == '#set-emails' ? 'active' : '') . '"><a href="#set-emails" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Email Templates</a></li>
                                <li class="' . ($activeTab == '#set-maintenance' ? 'active' : '') . '"><a href="#set-maintenance" data-toggle="tab" style="padding: 8px 12px; border: none; font-weight: 600; font-size: 0.88em; background: transparent;">Maintenance</a></li>
                            </ul>
                        </div>
                        
                        <div class="tab-content" style="padding: 25px; flex-grow: 1;">
                            <!-- GENERAL TAB -->
                            <div class="tab-pane ' . ($activeTab == '#set-general' ? 'active' : '') . '" id="set-general" style="padding-top: 10px;">
                                
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
                           <div class="tab-pane ' . ($activeTab == '#set-billing' ? 'active' : '') . '" id="set-billing">
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
                                              <input type="number" name="next_sequential_number" value="' . ($brand->next_sequential_number !== null ? $brand->next_sequential_number : '') . '" placeholder="' . ($brand->next_sequential_number !== null ? $brand->next_sequential_number : '') . '" class="form-control" style="width: 100%;">
                                            <div style="font-size: 0.85em; color: #888; margin-top: 5px;">Change this option only if you want to reset the automatic sequential numbering. Otherwise, leave empty.</div>
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
                             <div class="tab-pane ' . ($activeTab == '#set-gateways' ? 'active' : '') . '" id="set-gateways">
                                <h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-credit-card" style="margin-right: 8px;"></i> Payment Gateways</h4>
                                <textarea name="payment_gateways" id="payment_gateways_json" style="display:none;">' . htmlspecialchars(htmlspecialchars_decode($brand->payment_gateways ?: '[]')) . '</textarea>
                                
                                <div style="border: 1px solid #e2e2e2; border-radius: 6px; background: #fff; overflow: hidden; font-family: \'Outfit\', sans-serif;">
                                    <div style="background: #f7f7f7; border-bottom: 1px solid #e2e2e2; padding: 10px 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                        <div id="brand_gateway_tabs" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;"></div>
                                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#modal-add-brand-gateway" style="border-radius: 50%; width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 1.1em; border: 1px solid #ccc; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); color: #555; transition: all 0.2s;" onmouseover="this.style.background=\'#eee\';" onmouseout="this.style.background=\'#fff\';">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div id="brand_gateway_config_panel" style="padding: 25px; min-height: 250px;"></div>
                                </div>
                                
                                <script>
                                (function() {
                                    var gatewaysTextarea = document.getElementById("payment_gateways_json");
                                    var activeTab = "";
                                    var gateways = [];
                                    var currencies = ' . json_encode($currencies) . ';
                                    var brandColor = "' . $brandColor . '";

                                    try {
                                        gateways = JSON.parse(gatewaysTextarea.value || "[]");
                                        if (Array.isArray(gateways)) {
                                            gateways.forEach(function(g) {
                                                if (g.gateway === "paypalrest" && g.friendly_name === "PayPal REST") {
                                                    g.friendly_name = "PayPal";
                                                }
                                            });
                                        }
                                    } catch(e) {
                                        gateways = [];
                                    }

                                    if (!Array.isArray(gateways)) {
                                        gateways = [];
                                    }

                                    if (gateways.length > 0) {
                                        activeTab = gateways[0].gateway;
                                    }

                                    function saveState() {
                                        gatewaysTextarea.value = JSON.stringify(gateways);
                                    }

                                    function renderTabs() {
                                        var tabsContainer = document.getElementById("brand_gateway_tabs");
                                        tabsContainer.innerHTML = "";

                                        if (gateways.length === 0) {
                                            tabsContainer.innerHTML = "<span style=\"color: #888; font-style: italic; font-size: 0.9em;\">No gateways added yet. Click the + button to add one.</span>";
                                            return;
                                        }

                                        gateways.forEach(function(gw, index) {
                                            var isActive = (gw.gateway === activeTab);
                                            var tabEl = document.createElement("div");
                                            tabEl.className = "gateway-tab" + (isActive ? " active" : "");
                                            
                                            tabEl.style.display = "inline-flex";
                                            tabEl.style.alignItems = "center";
                                            tabEl.style.gap = "6px";
                                            tabEl.style.padding = "6px 12px";
                                            tabEl.style.borderRadius = "4px";
                                            tabEl.style.background = isActive ? "#fff" : "#f0f0f0";
                                            tabEl.style.border = "1px solid " + (isActive ? "#dcdcdc" : "#e0e0e0");
                                            tabEl.style.borderBottom = isActive ? "2px solid " + brandColor : "1px solid #e0e0e0";
                                            tabEl.style.cursor = "pointer";
                                            tabEl.style.fontWeight = "600";
                                            tabEl.style.fontSize = "0.88em";
                                            tabEl.style.color = isActive ? "#333" : "#666";
                                            tabEl.style.transition = "all 0.15s";

                                            var dragHandle = document.createElement("span");
                                            dragHandle.innerHTML = "⠿";
                                            dragHandle.style.cursor = "move";
                                            dragHandle.style.color = "#aaa";
                                            dragHandle.style.fontSize = "1em";
                                            dragHandle.title = "Reorder";
                                            tabEl.appendChild(dragHandle);

                                            var nameSpan = document.createElement("span");
                                            var displayName = gw.friendly_name || gw.gateway;
                                            if (displayName === "PayPal REST") {
                                                displayName = "PayPal";
                                            }
                                            if (gw.is_whmcs) {
                                                displayName += " [WHMCS]";
                                            }
                                            nameSpan.innerText = displayName;
                                            tabEl.appendChild(nameSpan);

                                            var deleteBtn = document.createElement("span");
                                            deleteBtn.innerHTML = "&times;";
                                            deleteBtn.style.color = "#999";
                                            deleteBtn.style.fontSize = "1.2em";
                                            deleteBtn.style.fontWeight = "bold";
                                            deleteBtn.style.cursor = "pointer";
                                            deleteBtn.style.lineHeight = "1";
                                            deleteBtn.style.marginLeft = "4px";
                                            deleteBtn.style.display = "inline-block";
                                            deleteBtn.title = "Remove";
                                            deleteBtn.onmouseover = function() { this.style.color = "#d9534f"; };
                                            deleteBtn.onmouseout = function() { this.style.color = "#999"; };
                                            
                                            deleteBtn.onclick = function(e) {
                                                e.stopPropagation();
                                                if (confirm("Are you sure you want to remove this payment gateway config?")) {
                                                    removeGateway(gw.gateway);
                                                }
                                            };
                                            tabEl.appendChild(deleteBtn);

                                            tabEl.onclick = function() {
                                                activeTab = gw.gateway;
                                                render();
                                            };

                                            tabEl.draggable = true;
                                            tabEl.ondragstart = function(e) {
                                                e.dataTransfer.setData("text/plain", index);
                                            };
                                            tabEl.ondragover = function(e) {
                                                e.preventDefault();
                                            };
                                            tabEl.ondrop = function(e) {
                                                e.preventDefault();
                                                var fromIndex = parseInt(e.dataTransfer.getData("text/plain"), 10);
                                                var toIndex = index;
                                                if (fromIndex !== toIndex) {
                                                    var movedGw = gateways.splice(fromIndex, 1)[0];
                                                    gateways.splice(toIndex, 0, movedGw);
                                                    saveState();
                                                    render();
                                                }
                                            };

                                            tabsContainer.appendChild(tabEl);
                                        });
                                    }

                                    function removeGateway(gwName) {
                                        gateways = gateways.filter(function(gw) {
                                            return gw.gateway !== gwName;
                                        });
                                        if (activeTab === gwName) {
                                            activeTab = gateways.length > 0 ? gateways[0].gateway : "";
                                        }
                                        saveState();
                                        render();
                                    }

                                    function renderConfigPanel() {
                                        var panel = document.getElementById("brand_gateway_config_panel");
                                        panel.innerHTML = "";

                                        if (gateways.length === 0) {
                                            panel.innerHTML = "<div style=\"border: 1px solid #e2e2e2; border-radius: 6px; padding: 40px 25px; background: #fafafa; text-align: center; color: #888;\"><i class=\"fas fa-credit-card\" style=\"font-size: 3em; color: #ccc; margin-bottom: 15px;\"></i><div style=\"font-weight: 600; color: #555; font-size: 1.1em; margin-bottom: 5px;\">No Branded Payment Gateways</div><div style=\"font-size: 0.9em; max-width: 450px; margin: 0 auto 15px auto;\">Configure merchant credentials specific to this brand for PayPal, Stripe, and other gateways by clicking the add button in the top right.</div></div>";
                                            return;
                                        }

                                        var gw = gateways.find(function(g) { return g.gateway === activeTab; });
                                        if (!gw) {
                                            if (gateways.length > 0) {
                                                activeTab = gateways[0].gateway;
                                                gw = gateways[0];
                                            } else {
                                                return;
                                            }
                                        }

                                        var formContainer = document.createElement("div");
                                        formContainer.style.display = "flex";
                                        formContainer.style.flexDirection = "column";
                                        formContainer.style.gap = "20px";

                                        var statusRow = document.createElement("div");
                                        statusRow.style.display = "flex";
                                        statusRow.style.alignItems = "flex-start";
                                        statusRow.style.gap = "15px";
                                        
                                        var isChecked = gw.status ? "checked" : "";
                                        var badgeText = gw.status ? "Enabled" : "Disabled";
                                        var badgeColor = gw.status ? "#5cb85c" : "#777";

                                        statusRow.innerHTML = "<label class=\"mb-switch\" style=\"flex-shrink: 0; margin: 0;\"><input type=\"checkbox\" id=\"gw_status_chk\" " + isChecked + "><span class=\"mb-slider\"></span></label><div style=\"display: flex; flex-direction: column; gap: 4px;\"><div style=\"display: flex; align-items: center; gap: 8px;\"><span style=\"font-weight: 600; color: #444; font-size: 1em;\">Status</span><span id=\"gw_status_badge\" class=\"status-badge\" style=\"background-color: " + badgeColor + "; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase;\">" + badgeText + "</span></div><span style=\"font-size: 0.85em; color: #777;\">The status of the gateway.</span></div>";
                                        formContainer.appendChild(statusRow);

                                        var statusChk = statusRow.querySelector("#gw_status_chk");
                                        statusChk.onchange = function() {
                                            gw.status = this.checked ? 1 : 0;
                                            var badge = statusRow.querySelector("#gw_status_badge");
                                            badge.textContent = gw.status ? "Enabled" : "Disabled";
                                            badge.style.backgroundColor = gw.status ? "#5cb85c" : "#777";
                                            saveState();
                                        };

                                        if (gw.is_whmcs) {
                                            var alertsContainer = document.createElement("div");
                                            alertsContainer.style.display = "flex";
                                            alertsContainer.style.flexDirection = "column";
                                            alertsContainer.style.gap = "12px";
                                            alertsContainer.style.marginTop = "10px";

                                            alertsContainer.innerHTML = "<div class=\"alert alert-danger\" style=\"background-color: #f2dede; border-color: #ebccd1; color: #a94442; padding: 12px 15px; border-radius: 4px; font-size: 0.9em; margin: 0; font-weight: 600;\"><i class=\"fas fa-exclamation-triangle\" style=\"margin-right: 8px;\"></i> IMPORTANT: This gateway will not be branded in any way!</div><div class=\"alert alert-info\" style=\"background-color: #d9edf7; border-color: #bce8f1; color: #31708f; padding: 12px 15px; border-radius: 4px; font-size: 0.9em; margin: 0;\"><i class=\"fas fa-info-circle\" style=\"margin-right: 8px;\"></i> Please note: this is the WHMCS gateway and it can be configured only in Setup &rarr; Payments &rarr; Payment Gateways.</div>";
                                            formContainer.appendChild(alertsContainer);
                                        } else {
                                            var fieldsGrid = document.createElement("div");
                                            fieldsGrid.style.display = "flex";
                                            fieldsGrid.style.flexDirection = "column";
                                            fieldsGrid.style.gap = "15px";
                                            fieldsGrid.style.maxWidth = "600px";

                                            var friendlyNameGroup = document.createElement("div");
                                            friendlyNameGroup.className = "form-group-mb";
                                            friendlyNameGroup.innerHTML = "<label style=\"font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;\">Friendly Name</label><input type=\"text\" id=\"gw_friendly_name\" class=\"form-control\" value=\"" + (gw.friendly_name || "") + "\"><small class=\"text-muted\" style=\"color: #888; font-size: 0.82em; display: block; margin-top: 4px;\">The gateway name that will be displayed in the cart.</small>";
                                            fieldsGrid.appendChild(friendlyNameGroup);

                                            friendlyNameGroup.querySelector("#gw_friendly_name").oninput = function() {
                                                gw.friendly_name = this.value;
                                                saveState();
                                                renderTabs();
                                            };

                                            var convertToGroup = document.createElement("div");
                                            convertToGroup.className = "form-group-mb";
                                            
                                            var optionsHtml = "<option value=\"\">None</option>";
                                            currencies.forEach(function(curr) {
                                                var selected = (gw.convert_to === curr.code) ? "selected" : "";
                                                optionsHtml += "<option value=\"" + curr.code + "\" " + selected + ">" + curr.code + "</option>";
                                            });

                                            convertToGroup.innerHTML = "<label style=\"font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;\">Convert To</label><select id=\"gw_convert_to\" class=\"form-control\" style=\"height: 36px;\">" + optionsHtml + "</select><small class=\"text-muted\" style=\"color: #888; font-size: 0.82em; display: block; margin-top: 4px;\">This option is used for multiple currencies. This will use the rates set in Setup &rarr; Payments &rarr; Currencies to do the conversion.</small>";
                                            fieldsGrid.appendChild(convertToGroup);

                                            convertToGroup.querySelector("#gw_convert_to").onchange = function() {
                                                gw.convert_to = this.value;
                                                saveState();
                                            };

                                            var clientIdLabel = "Client ID";
                                            var clientIdDesc = "Provide the client ID from REST API Application.";
                                            var secretLabel = "Secret";
                                            var secretDesc = "Provide the secret from REST API Application.";
                                            var testModeLabel = "Test Mode";
                                            var testModeDesc = "Use this option if you want to use the PayPal sandbox API.";
                                            var showSecret = true;

                                            if (gw.gateway === "stripe") {
                                                clientIdLabel = "Publishable Key";
                                                clientIdDesc = "Provide the publishable key from Stripe Dashboard.";
                                                secretLabel = "Secret Key";
                                                secretDesc = "Provide the secret key from Stripe Dashboard.";
                                                testModeLabel = "Test Mode";
                                                testModeDesc = "Use this option if you want to use the Stripe test environment.";
                                            } else if (gw.gateway === "paypal") {
                                                clientIdLabel = "PayPal Email";
                                                clientIdDesc = "Provide your PayPal account email address.";
                                                showSecret = false;
                                                testModeLabel = "Sandbox Mode";
                                                testModeDesc = "Use this option if you want to use the PayPal sandbox environment.";
                                            }

                                            var clientIdGroup = document.createElement("div");
                                            clientIdGroup.className = "form-group-mb";
                                            clientIdGroup.innerHTML = "<label style=\"font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;\">" + clientIdLabel + "</label><input type=\"text\" id=\"gw_client_id\" class=\"form-control\" value=\"" + (gw.client_id || "") + "\"><small class=\"text-muted\" style=\"color: #888; font-size: 0.82em; display: block; margin-top: 4px;\">" + clientIdDesc + "</small>";
                                            fieldsGrid.appendChild(clientIdGroup);
                                            clientIdGroup.querySelector("#gw_client_id").oninput = function() {
                                                gw.client_id = this.value;
                                                saveState();
                                            };

                                            if (showSecret) {
                                                var secretGroup = document.createElement("div");
                                                secretGroup.className = "form-group-mb";
                                                secretGroup.innerHTML = "<label style=\"font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;\">" + secretLabel + "</label><input type=\"password\" id=\"gw_secret\" class=\"form-control\" value=\"" + (gw.secret || "") + "\" style=\"letter-spacing: 2px;\"><small class=\"text-muted\" style=\"color: #888; font-size: 0.82em; display: block; margin-top: 4px;\">" + secretDesc + "</small>";
                                                fieldsGrid.appendChild(secretGroup);
                                                secretGroup.querySelector("#gw_secret").oninput = function() {
                                                    gw.secret = this.value;
                                                    saveState();
                                                };
                                            }

                                            var testModeRow = document.createElement("div");
                                            testModeRow.style.display = "flex";
                                            testModeRow.style.alignItems = "flex-start";
                                            testModeRow.style.gap = "15px";
                                            testModeRow.style.marginTop = "10px";
                                            
                                            var isTestChecked = gw.test_mode ? "checked" : "";
                                            var testBadgeText = gw.test_mode ? "Enabled" : "Disabled";
                                            var testBadgeColor = gw.test_mode ? "#f0ad4e" : "#777";

                                            testModeRow.innerHTML = "<label class=\"mb-switch\" style=\"flex-shrink: 0; margin: 0;\"><input type=\"checkbox\" id=\"gw_test_mode_chk\" " + isTestChecked + "><span class=\"mb-slider\"></span></label><div style=\"display: flex; flex-direction: column; gap: 4px;\"><div style=\"display: flex; align-items: center; gap: 8px;\"><span style=\"font-weight: 600; color: #444; font-size: 1em;\">" + testModeLabel + "</span><span id=\"gw_test_badge\" class=\"status-badge\" style=\"background-color: " + testBadgeColor + "; color: #fff; font-size: 0.72em; padding: 2px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase;\">" + testBadgeText + "</span></div><span style=\"font-size: 0.85em; color: #777;\">" + testModeDesc + "</span></div>";
                                            fieldsGrid.appendChild(testModeRow);

                                            var testModeChk = testModeRow.querySelector("#gw_test_mode_chk");
                                            testModeChk.onchange = function() {
                                                gw.test_mode = this.checked ? 1 : 0;
                                                var badge = testModeRow.querySelector("#gw_test_badge");
                                                badge.textContent = gw.test_mode ? "Enabled" : "Disabled";
                                                badge.style.backgroundColor = gw.test_mode ? "#f0ad4e" : "#777";
                                                saveState();
                                            };

                                            // Test Credentials
                                            var testCredsRow = document.createElement("div");
                                            testCredsRow.style.display = "flex";
                                            testCredsRow.style.flexDirection = "column";
                                            testCredsRow.style.gap = "8px";
                                            testCredsRow.style.marginTop = "15px";

                                            testCredsRow.innerHTML = "<span style=\"font-weight: bold; color: #555; font-size: 0.92em;\">Test Gateway Credentials</span>" +
                                                "<button type=\"button\" id=\"btn_test_gateway\" class=\"btn btn-success btn-sm\" style=\"background-color: #5cb85c; border-color: #4cae4c; padding: 5px 15px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; align-self: flex-start; font-size: 0.88em;\">" +
                                                "<i class=\"fas fa-plug\"></i> Test Connection" +
                                                "</button>" +
                                                "<div id=\"gateway_test_result\" style=\"display: none; margin-top: 5px;\"></div>";

                                            fieldsGrid.appendChild(testCredsRow);

                                            var btnTestGw = testCredsRow.querySelector("#btn_test_gateway");
                                            btnTestGw.onclick = function() {
                                                var resultDiv = testCredsRow.querySelector("#gateway_test_result");
                                                resultDiv.style.display = "block";
                                                resultDiv.innerHTML = "<div class=\'alert alert-warning\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-spinner fa-spin\'></i> Verifying credentials...</div>";

                                                var clientIdVal = fieldsGrid.querySelector("#gw_client_id") ? fieldsGrid.querySelector("#gw_client_id").value : "";
                                                var secretVal = fieldsGrid.querySelector("#gw_secret") ? fieldsGrid.querySelector("#gw_secret").value : "";
                                                var testModeVal = gw.test_mode;

                                                jQuery.post("' . $modulelink . '&action=test_gateway_connection", {
                                                    gateway: gw.gateway,
                                                    client_id: clientIdVal,
                                                    secret: secretVal,
                                                    test_mode: testModeVal
                                                }, function(res) {
                                                    if (res.success) {
                                                        resultDiv.innerHTML = "<div class=\'alert alert-success\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-check-circle\'></i> " + res.message + "</div>";
                                                    } else {
                                                        resultDiv.innerHTML = "<div class=\'alert alert-danger\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-exclamation-circle\'></i> " + res.message + "</div>";
                                                    }
                                                }, "json").fail(function() {
                                                    resultDiv.innerHTML = "<div class=\'alert alert-danger\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-exclamation-circle\'></i> AJAX request failed. Make sure your server is reachable.</div>";
                                                });
                                            };

                                            formContainer.appendChild(fieldsGrid);
                                        }

                                        panel.appendChild(formContainer);
                                    }

                                    function render() {
                                        renderTabs();
                                        renderConfigPanel();
                                    }

                                    window.addEventListener("load", function() {
                                        var btnConfirm = document.getElementById("btn_confirm_add_gateway");
                                        if (btnConfirm) {
                                            btnConfirm.onclick = function() {
                                                var select = document.getElementById("add_brand_gateway_select");
                                                var val = select.value;
                                                if (!val) {
                                                    alert("Please select a gateway!");
                                                    return;
                                                }

                                                var exists = gateways.some(function(g) { return g.gateway === val; });
                                                if (exists) {
                                                    alert("This gateway is already configured on this brand!");
                                                    return;
                                                }

                                                var opt = select.options[select.selectedIndex];
                                                var isWhmcs = opt.getAttribute("data-whmcs") === "true";

                                                var newGw = {
                                                    gateway: val,
                                                    friendly_name: opt.text.replace(" (WHMCS)", ""),
                                                    status: 1,
                                                    is_whmcs: isWhmcs
                                                };

                                                if (!isWhmcs) {
                                                    newGw.convert_to = "";
                                                    newGw.client_id = "";
                                                    newGw.secret = "";
                                                    newGw.test_mode = 0;
                                                }

                                                gateways.push(newGw);
                                                activeTab = val;
                                                
                                                saveState();
                                                render();

                                                jQuery("#modal-add-brand-gateway").modal("hide");
                                                select.value = "";
                                            };
                                        }
                                    });

                                    render();
                                })();
                                </script>
                            </div>
                            
                            <!-- SMTP TAB -->
                            <div class="tab-pane ' . ($activeTab == '#set-smtp' ? 'active' : '') . '" id="set-smtp">
                                <h4 style="margin: 0 0 15px 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;"><i class="fas fa-paper-plane" style="margin-right: 8px;"></i> SMTP Configuration</h4>
                                <p style="color: #777; font-size: 0.9em; line-height: 1.5; margin-bottom: 25px;">
                                    If you would like your brand to use an outgoing email configuration which is different from the one in the main WHMCS settings, you can do it here.<br>
                                    In addition, you can set your custom CSS email styling as well as the header and footer content.
                                </p>

                                <div style="display: flex; flex-wrap: wrap; gap: 25px; margin-bottom: 25px; font-family: \'Outfit\', sans-serif;">
                                    <!-- Left Column -->
                                    <div style="flex: 1; min-width: 280px; display: flex; flex-direction: column; gap: 15px;">
                                        <!-- Override switch -->
                                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                                            <label class="mb-switch" style="flex-shrink: 0; margin: 0;">
                                                <input type="checkbox" name="smtp_override" value="1" ' . (!empty($smtp['override']) ? 'checked' : '') . '>
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span style="font-weight: 600; color: #444; font-size: 0.95em;">Override</span>
                                                <span style="font-size: 0.82em; color: #777; line-height: 1.4;">Enable this option to use the brand SMTP configuration. Otherwise the module will use the WHMCS settings.</span>
                                            </div>
                                        </div>

                                        <!-- Port input -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Port</label>
                                            <input type="text" name="smtp_port" class="form-control" value="' . htmlspecialchars($smtp['port'] ?? '') . '" placeholder="e.g. 465" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em;">
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Specify a port that your SMTP server operates on.</small>
                                        </div>

                                        <!-- Hostname input -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Hostname</label>
                                            <input type="text" name="smtp_hostname" class="form-control" value="' . htmlspecialchars($smtp['hostname'] ?? '') . '" placeholder="e.g. mail.mailbox.com" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em;">
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Provide the SMTP server hostname.</small>
                                        </div>

                                        <!-- Username input -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Username</label>
                                            <input type="text" name="smtp_username" class="form-control" value="' . htmlspecialchars($smtp['username'] ?? '') . '" placeholder="e.g. test@mailbox.com" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em;">
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Specify a username that should be used to connect with the SMTP server.</small>
                                        </div>

                                        <!-- Test Connection button -->
                                        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
                                            <span style="font-weight: bold; color: #555; font-size: 0.92em;">Test Connection</span>
                                            <button type="button" id="btn_test_smtp" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 7px 20px; font-weight: 600; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; align-self: flex-start; min-width: 155px;">
                                                <i class="fas fa-plug"></i> Test Connection
                                            </button>
                                            <span style="font-size: 0.82em; color: #777; line-height: 1.4;">An email is sent to the email address of the currently logged in administrator.</span>
                                            <div id="smtp_test_result" style="display: none; margin-top: 5px;"></div>
                                        </div>
                                    </div>

                                    <!-- Right Column -->
                                    <div style="flex: 1; min-width: 280px; display: flex; flex-direction: column; gap: 15px;">
                                        <!-- SMTP Debug switch -->
                                        <div style="display: flex; align-items: flex-start; gap: 15px;">
                                            <label class="mb-switch" style="flex-shrink: 0; margin: 0;">
                                                <input type="checkbox" name="smtp_debug" value="1" ' . (!empty($smtp['debug']) ? 'checked' : '') . '>
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span style="font-weight: 600; color: #444; font-size: 0.95em;">SMTP Debug</span>
                                                <span style="font-size: 0.82em; color: #777; line-height: 1.4;">Enable this option to log SMTP connection details.</span>
                                            </div>
                                        </div>

                                        <!-- Mail Type select -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Mail Type</label>
                                            <select name="smtp_mail_type" class="form-control" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em;">
                                                <option value="SMTP" ' . (($smtp['mail_type'] ?? 'SMTP') === 'SMTP' ? 'selected' : '') . '>SMTP</option>
                                                <option value="mail" ' . (($smtp['mail_type'] ?? '') === 'mail' ? 'selected' : '') . '>PHP Mail</option>
                                            </select>
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Choose how your system should send emails.</small>
                                        </div>

                                        <!-- SMTP SSL Type select -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">SMTP SSL Type</label>
                                            <select name="smtp_ssl_type" class="form-control" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em;">
                                                <option value="" ' . (empty($smtp['ssl_type']) ? 'selected' : '') . '>None</option>
                                                <option value="SSL" ' . (($smtp['ssl_type'] ?? '') === 'SSL' ? 'selected' : '') . '>SSL</option>
                                                <option value="TLS" ' . (($smtp['ssl_type'] ?? '') === 'TLS' ? 'selected' : '') . '>TLS</option>
                                            </select>
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Specify whether to use the secure connection when communicating with your mail server.</small>
                                        </div>

                                        <!-- Password input -->
                                        <div class="form-group-mb">
                                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Password</label>
                                            <input type="password" name="smtp_password" class="form-control" value="' . htmlspecialchars($smtp['password'] ?? '') . '" placeholder="••••••••" style="width: 100%; height: 36px; padding: 6px 12px; font-size: 0.95em; letter-spacing: 2px;">
                                            <small class="text-muted" style="color: #888; font-size: 0.82em; display: block; margin-top: 4px;">Provide a user password required to connect with the SMTP server.</small>
                                        </div>

                                        <!-- Disable Email switch -->
                                        <div style="display: flex; align-items: flex-start; gap: 15px; margin-top: 5px;">
                                            <label class="mb-switch" style="flex-shrink: 0; margin: 0;">
                                                <input type="checkbox" name="smtp_disable_email" value="1" ' . (!empty($smtp['disable_email']) ? 'checked' : '') . '>
                                                <span class="mb-slider"></span>
                                            </label>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span style="font-weight: 600; color: #444; font-size: 0.95em;">Disable Email</span>
                                                <span style="font-size: 0.82em; color: #777; line-height: 1.4;">Enable if you wish to switch off outgoing email messages.</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="border-top: 1px solid #eee; padding-top: 25px; display: flex; flex-direction: column; gap: 20px; font-family: \'Outfit\', sans-serif;">
                                    <!-- CSS Email Styling -->
                                    <div class="form-group-mb">
                                        <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">CSS Email Styling</label>
                                        <textarea name="css_email_styling" rows="4" class="form-control" style="width: 100%; font-family: monospace; font-size: 0.9em; line-height: 1.4; padding: 8px;">' . htmlspecialchars($email_templates['css'] ?? '') . '</textarea>
                                    </div>

                                    <!-- Email Header Content -->
                                    <div class="form-group-mb">
                                        <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Email Header Content</label>
                                        <textarea name="email_header_content" rows="6" class="form-control" style="width: 100%; font-family: monospace; font-size: 0.9em; line-height: 1.4; padding: 8px;">' . htmlspecialchars($email_templates['header'] ?? '') . '</textarea>
                                    </div>

                                    <!-- Email Footer Content -->
                                    <div class="form-group-mb">
                                        <label style="font-weight: bold; color: #555; display: block; margin-bottom: 5px; font-size: 0.92em;">Email Footer Content</label>
                                        <textarea name="email_footer_content" rows="6" class="form-control" style="width: 100%; font-family: monospace; font-size: 0.9em; line-height: 1.4; padding: 8px;">' . htmlspecialchars($email_templates['footer'] ?? '') . '</textarea>
                                    </div>
                                </div>

                                <div style="margin-top: 30px; border: 1px solid #e2e2e2; border-radius: 6px; background: #fafafa; padding: 20px; font-family: \'Outfit\', sans-serif;">
                                    <h5 style="margin: 0 0 12px 0; font-weight: bold; color: #333; font-size: 0.95em;"><i class="fas fa-info-circle" style="margin-right: 6px; color: #007bff;"></i> Merge Fields Information</h5>
                                    <p style="color: #666; font-size: 0.88em; line-height: 1.4; margin-bottom: 15px;">
                                        Please remember that the following merge fields can be used for <strong>\'Email Header Content\'</strong> as well as <strong>\'Email Footer Content\'</strong>:
                                    </p>
                                    
                                    <div style="overflow-x: auto;">
                                        <table class="table table-bordered table-condensed" style="background: #fff; font-size: 0.85em; margin: 0; min-width: 400px; border-color: #ddd;">
                                            <thead>
                                                <tr style="background: #f1f1f1;">
                                                    <th style="font-weight: bold; color: #444; width: 50%;">Description</th>
                                                    <th style="font-weight: bold; color: #444; width: 50%;">Merge Field Tag</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>Company Name</td><td><code>{$company_name}</code></td></tr>
                                                <tr><td>Domain</td><td><code>{$company_domain}</code></td></tr>
                                                <tr><td>Logo URL</td><td><code>{$company_logo_url}</code></td></tr>
                                                <tr><td>WHMCS URL</td><td><code>{$whmcs_url}</code></td></tr>
                                                <tr><td>WHMCS Link</td><td><code>{$whmcs_link}</code></td></tr>
                                                <tr><td>Marketing Unsubscribe URL</td><td><code>{$unsubscribe_url}</code></td></tr>
                                                <tr><td>Marketing Optout URL</td><td><code>{$email_marketing_optout_url}</code></td></tr>
                                                <tr><td>Marketing Optin URL</td><td><code>{$email_marketing_optin_url}</code></td></tr>
                                                <tr><td>Signature</td><td><code>{$signature}</code></td></tr>
                                                <tr><td>Full Sending Date</td><td><code>{$date}</code></td></tr>
                                                <tr><td>Full Sending Time</td><td><code>{$time}</code></td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <script>
                                (function() {
                                    var btn = document.getElementById("btn_test_smtp");
                                    if (btn) {
                                        btn.onclick = function() {
                                            var resultDiv = document.getElementById("smtp_test_result");
                                            resultDiv.style.display = "block";
                                            resultDiv.innerHTML = "<div class=\'alert alert-warning\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-spinner fa-spin\'></i> Testing SMTP connection...</div>";
                                            
                                            var hostname = document.getElementsByName("smtp_hostname")[0].value;
                                            var port = document.getElementsByName("smtp_port")[0].value;
                                            var username = document.getElementsByName("smtp_username")[0].value;
                                            var password = document.getElementsByName("smtp_password")[0].value;
                                            var sslType = document.getElementsByName("smtp_ssl_type")[0].value;
                                            var mailType = document.getElementsByName("smtp_mail_type")[0].value;

                                            jQuery.post("' . $modulelink . '&action=test_smtp_connection", {
                                                hostname: hostname,
                                                port: port,
                                                username: username,
                                                password: password,
                                                ssl_type: sslType,
                                                mail_type: mailType
                                            }, function(res) {
                                                if (res.success) {
                                                    resultDiv.innerHTML = "<div class=\'alert alert-success\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-check-circle\'></i> " + res.message + "</div>";
                                                } else {
                                                    resultDiv.innerHTML = "<div class=\'alert alert-danger\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-exclamation-circle\'></i> " + res.message + "</div>";
                                                }
                                            }, "json").fail(function() {
                                                resultDiv.innerHTML = "<div class=\'alert alert-danger\' style=\'margin: 0; padding: 8px 12px; font-size: 0.85em;\'><i class=\'fas fa-exclamation-circle\'></i> AJAX request failed. Make sure your server is reachable.</div>";
                                            });
                                        };
                                    }
                                })();
                                </script>
                            </div>
                            
                            <!-- EMAIL TEMPLATES TAB -->
                             <div class="tab-pane ' . ($activeTab == '#set-emails' ? 'active' : '') . '" id="set-emails">
                                ' . $emailTemplatesHtml . '
                            </div>
                            
                            <!-- MAINTENANCE TAB -->
                            <div class="tab-pane ' . ($activeTab == '#set-maintenance' ? 'active' : '') . '" id="set-maintenance">
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
                                        <button type="submit" class="btn btn-success btn-sm" style="padding: 5px 20px; font-weight: 600; border-radius: 4px; font-size: 0.88em;"><i class="fas fa-save" style="margin-right: 6px;"></i> Save Changes</button>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </form>

        <!-- EDIT EMAIL TEMPLATE MODAL -->
        <div class="modal fade" id="modal-edit-email-template" role="dialog" aria-hidden="true" style="display: none; z-index: 9999;">
            <div class="modal-dialog modal-lg" style="width: 80% !important; max-width: 1000px;">
                <div class="modal-content" style="border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); border: none;">
                    <div class="modal-body" id="email-template-modal-body" style="padding: 0;">
                        <div style="padding: 40px; text-align: center; color: #777;">
                            <i class="fas fa-spinner fa-spin fa-2x" style="margin-bottom: 15px; color: #337ab7;"></i>
                            <div>Loading template editor...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function() {
            // Handle Edit Template click
            jQuery(document).delegate(".edit-email-template-btn", "click", function(e) {
                e.preventDefault();
                var templateName = jQuery(this).data("template-name");
                var brandId = ' . $brand->id . ';
                
                jQuery("#email-template-modal-body").html(\'<div style="padding: 40px; text-align: center; color: #777;"><i class="fas fa-spinner fa-spin fa-2x" style="margin-bottom: 15px; color: #337ab7;"></i><div>Loading template editor...</div></div>\');
                jQuery("#modal-edit-email-template").modal("show");
                
                jQuery.post("' . $modulelink . '&action=get_email_template_editor_ajax", {
                    brand_id: brandId,
                    template_name: templateName
                }, function(res) {
                    if (res.success) {
                        jQuery("#email-template-modal-body").html(res.html);
                    } else {
                        jQuery("#email-template-modal-body").html(\'<div class="alert alert-danger" style="margin: 20px;">Error: \' + res.message + \'</div>\');
                    }
                }, "json").fail(function() {
                    jQuery("#email-template-modal-body").html(\'<div class="alert alert-danger" style="margin: 20px;">Error: Failed to request template details.</div>\');
                });
            });

            // Handle toolbar actions
            jQuery(document).delegate(".toolbar-btn", "click", function(e) {
                e.preventDefault();
                var cmd = jQuery(this).data("cmd");
                var textarea = document.getElementById("template-message-editor");
                if (!textarea) return;
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = textarea.value;
                var selectedText = text.substring(start, end);
                
                var replacement = "";
                switch (cmd) {
                    case "bold":
                        replacement = "<strong>" + selectedText + "</strong>";
                        break;
                    case "italic":
                        replacement = "<em>" + selectedText + "</em>";
                        break;
                    case "underline":
                        replacement = "<u>" + selectedText + "</u>";
                        break;
                    case "bullet":
                        replacement = "\n<ul>\n  <li>" + (selectedText || "List item") + "</li>\n</ul>\n";
                        break;
                    case "number":
                        replacement = "\n<ol>\n  <li>" + (selectedText || "List item") + "</li>\n</ol>\n";
                        break;
                    case "link":
                        var url = prompt("Enter URL:", "https://");
                        if (url) {
                            replacement = \'<a href="\' + url + \'">\' + (selectedText || "Link Text") + \'</a>\';
                        } else {
                            return;
                        }
                        break;
                    case "html":
                        alert("You are already in raw HTML code view mode.");
                        return;
                }
                
                textarea.value = text.substring(0, start) + replacement + text.substring(end);
                textarea.focus();
                textarea.selectionStart = start + replacement.length;
                textarea.selectionEnd = start + replacement.length;
            });

            // Handle format block selection
            jQuery(document).delegate("#editor-format-block", "change", function() {
                var tag = jQuery(this).val();
                if (!tag) return;
                jQuery(this).val(""); // Reset select
                
                var textarea = document.getElementById("template-message-editor");
                if (!textarea) return;
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = textarea.value;
                var selectedText = text.substring(start, end);
                
                var replacement = "<" + tag + ">" + (selectedText || "Text") + "</" + tag + ">";
                
                textarea.value = text.substring(0, start) + replacement + text.substring(end);
                textarea.focus();
                textarea.selectionStart = start + replacement.length;
                textarea.selectionEnd = start + replacement.length;
            });

            // Handle Merge Fields click
            jQuery(document).delegate(".merge-field-token", "click", function(e) {
                e.preventDefault();
                var token = jQuery(this).data("token");
                var textarea = document.getElementById("template-message-editor");
                if (!textarea) return;
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var text = textarea.value;
                
                textarea.value = text.substring(0, start) + token + text.substring(end);
                textarea.focus();
                textarea.selectionStart = start + token.length;
                textarea.selectionEnd = start + token.length;
            });

            // Handle Add Language click
            jQuery(document).delegate("#btn-add-lang", "click", function(e) {
                e.preventDefault();
                var selectedLang = jQuery("#select-new-lang").val();
                if (!selectedLang) return;
                
                if (templateTranslations[selectedLang]) {
                    alert("Language " + selectedLang + " is already added.");
                    return;
                }
                
                saveActiveLangState();
                
                templateTranslations[selectedLang] = {
                    subject: "",
                    message: ""
                };
                
                var label = selectedLang.charAt(0).toUpperCase() + selectedLang.slice(1);
                var tabLi = \'<li><a href="#" class="lang-tab-link" data-lang="\' + selectedLang + \'" style="font-weight: bold; border-radius: 4px; padding: 8px 16px; border: none; margin-right: 5px;">\' + label + \'</a></li>\';
                jQuery("#emailTemplateLangTabs").append(tabLi);
                
                switchLangTab(selectedLang);
            });

            // Handle Save template click
            jQuery(document).delegate("#btn-save-template", "click", function(e) {
                e.preventDefault();
                saveActiveLangState();
                
                var brandId = ' . $brand->id . ';
                var templateName = jQuery(this).closest(".modal-content").find(".modal-title").text().replace("Edit Template: ", "").trim();
                var copyTo = jQuery("#template-copy-to").val();
                var blindCopyTo = jQuery("#template-blind-copy-to").val();
                
                jQuery.post("' . $modulelink . '&action=save_email_template_editor_ajax", {
                    brand_id: brandId,
                    template_name: templateName,
                    copy_to: copyTo,
                    blind_copy_to: blindCopyTo,
                    translations: JSON.stringify(templateTranslations)
                }, function(res) {
                    if (res.success) {
                        jQuery("#modal-edit-email-template").modal("hide");
                        window.location.reload();
                    } else {
                        alert("Error: " + res.message);
                    }
                }, "json").fail(function() {
                    alert("Error: Save request failed.");
                });
            });

            // Handle Delete Branded template click
            jQuery(document).delegate("#btn-delete-branded", "click", function(e) {
                e.preventDefault();
                if (!confirm("Are you sure you want to restore the default template? This will delete all customized translations for this brand.")) {
                    return;
                }
                
                var brandId = ' . $brand->id . ';
                var templateName = jQuery(this).closest(".modal-content").find(".modal-title").text().replace("Edit Template: ", "").trim();
                
                jQuery.post("' . $modulelink . '&action=delete_branded_template_ajax", {
                    brand_id: brandId,
                    template_name: templateName
                }, function(res) {
                    if (res.success) {
                        jQuery("#modal-edit-email-template").modal("hide");
                        window.location.reload();
                    } else {
                        alert("Error: " + res.message);
                    }
                }, "json").fail(function() {
                    alert("Error: Delete request failed.");
                });
            });
        });
        </script>

        <!-- SERVICES PRICING BOX -->
        <div style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 30px; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
            <div style="padding: 20px 25px; border-bottom: 1px solid #f0f0f0;">
                <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1em;"><i class="fas fa-dollar-sign" style="margin-right: 8px;"></i> Services Pricing</span>
                <ul class="nav nav-tabs" style="border-bottom: none; margin: 15px 0 0 0; display: flex; gap: 5px;">
                    <li class="active"><a href="#price-products" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.88em; background: transparent; color: #555;">Products</a></li>
                    <li><a href="#price-addons" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.88em; background: transparent; color: #555;">Addons</a></li>
                    <li><a href="#price-domains" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.88em; background: transparent; color: #555;">Domains</a></li>
                    <li><a href="#price-bundles" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.88em; background: transparent; color: #555;">Bundles</a></li>
                </ul>
            </div>
            
            <div class="tab-content" style="padding: 25px;">
                <!-- PRODUCTS TAB -->
                <div class="tab-pane active" id="price-products">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.05em; display: flex; align-items: center;">
                            <i class="fas fa-cubes" style="margin-right: 8px;"></i> Branded Products (' . count($brandedProducts) . ')
                        </h4>
                        <div class="action-bar-right" style="display: flex; gap: 8px; align-items: center;">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-price-products"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-product" data-toggle="modal" data-target="#modal-assign-product" class="action-circle-btn" title="Assign Product"><i class="fas fa-plus"></i></a>
                            <a href="#modal-bulk-add-products" data-toggle="modal" data-target="#modal-bulk-add-products" class="action-circle-btn" title="Bulk Add Products"><i class="fas fa-cog"></i></a>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-price-products">
                        <input type="text" class="tab-search-input" placeholder="Filter products by name, payment type..." autocomplete="off">
                        <button class="clear-search-btn" title="Close"><i class="fas fa-times"></i></button>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Payment Type</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($brandedProducts) > 0) {
            foreach ($brandedProducts as $p) {
                $payType = ucfirst($p->paytype);
                if ($payType == 'Free') { $payType = 'Free Account'; }
                $output .= '<tr>
                    <td><a style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($p->name) . '</a></td>
                    <td>' . $payType . '</td>
                    <td class="text-center">
                        <span class="label btn-pricing-override" data-type="product" data-relid="' . $p->id . '" data-name="' . htmlspecialchars($p->name) . '" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px; font-family: sans-serif;" title="Pricing Override">$</span>
                        <a href="' . $modulelink . '&action=unlink_pricing_item&brand_id=' . $brand->id . '&type=product&item_id=' . $p->id . '" class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;" title="Delete Override" onclick="return confirm(\'Are you sure you want to unlink this product pricing from this brand?\')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="3" class="text-center" style="padding: 20px; color: #777;">No branded products configured. Click the "+" button in the top right to assign one, or "⚙" to bulk assign all.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
                
                <!-- ADDONS TAB -->
                <div class="tab-pane" id="price-addons">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.05em; display: flex; align-items: center;">
                            <i class="fas fa-puzzle-piece" style="margin-right: 8px;"></i> Branded Addons (' . count($brandedAddons) . ')
                        </h4>
                        <div class="action-bar-right" style="display: flex; gap: 8px; align-items: center;">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-price-addons"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-addon" data-toggle="modal" data-target="#modal-assign-addon" class="action-circle-btn" title="Assign Addon"><i class="fas fa-plus"></i></a>
                            <a href="#modal-bulk-add-addons" data-toggle="modal" data-target="#modal-bulk-add-addons" class="action-circle-btn" title="Bulk Add Addons"><i class="fas fa-cog"></i></a>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-price-addons">
                        <input type="text" class="tab-search-input" placeholder="Filter addons by name, billing cycle..." autocomplete="off">
                        <button class="clear-search-btn" title="Close"><i class="fas fa-times"></i></button>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th>Addon Name</th>
                                <th>Billing Cycle</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($brandedAddons) > 0) {
            foreach ($brandedAddons as $addon) {
                $output .= '<tr>
                    <td><a style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($addon->name) . '</a></td>
                    <td>' . htmlspecialchars($addon->billingcycle) . '</td>
                    <td class="text-center">
                        <span class="label btn-pricing-override" data-type="addon" data-relid="' . $addon->id . '" data-name="' . htmlspecialchars($addon->name) . '" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px; font-family: sans-serif;" title="Pricing Override">$</span>
                        <a href="' . $modulelink . '&action=unlink_pricing_item&brand_id=' . $brand->id . '&type=addon&item_id=' . $addon->id . '" class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;" title="Delete Override" onclick="return confirm(\'Are you sure you want to unlink this addon pricing from this brand?\')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="3" class="text-center" style="padding: 20px; color: #777;">No branded addons configured. Click the "+" button in the top right to assign one, or "⚙" to bulk assign all.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
                
                <!-- DOMAINS TAB -->
                <div class="tab-pane" id="price-domains">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.05em; display: flex; align-items: center;">
                            <i class="fas fa-globe" style="margin-right: 8px;"></i> Branded Domains (' . count($brandedDomains) . ')
                        </h4>
                        <div class="action-bar-right" style="display: flex; gap: 8px; align-items: center;">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-price-domains"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-domain" data-toggle="modal" data-target="#modal-assign-domain" class="action-circle-btn" title="Assign Domain"><i class="fas fa-plus"></i></a>
                            <a href="#modal-bulk-add-domains" data-toggle="modal" data-target="#modal-bulk-add-domains" class="action-circle-btn" title="Bulk Add Domains"><i class="fas fa-cog"></i></a>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-price-domains">
                        <input type="text" class="tab-search-input" placeholder="Filter domains by TLD/extension..." autocomplete="off">
                        <button class="clear-search-btn" title="Close"><i class="fas fa-times"></i></button>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th>TLD / Extension</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($brandedDomains) > 0) {
            foreach ($brandedDomains as $domain) {
                $output .= '<tr>
                    <td style="font-weight: 600; color: #333;">' . htmlspecialchars($domain->extension) . '</td>
                    <td class="text-center">
                        <span class="label btn-pricing-override" data-type="domain" data-relid="' . $domain->id . '" data-name="' . htmlspecialchars($domain->extension) . '" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px; font-family: sans-serif;" title="Pricing Override">$</span>
                        <a href="' . $modulelink . '&action=unlink_pricing_item&brand_id=' . $brand->id . '&type=domain&item_id=' . $domain->id . '" class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;" title="Delete Override" onclick="return confirm(\'Are you sure you want to unlink this domain pricing from this brand?\')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="2" class="text-center" style="padding: 20px; color: #777;">No branded domains configured. Click the "+" button in the top right to assign one, or "⚙" to bulk assign all.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>

                <!-- BUNDLES TAB -->
                <div class="tab-pane" id="price-bundles">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.05em; display: flex; align-items: center;">
                            <i class="fas fa-box-open" style="margin-right: 8px;"></i> Branded Bundles (' . count($brandedBundles) . ')
                        </h4>
                        <div class="action-bar-right" style="display: flex; gap: 8px; align-items: center;">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-price-bundles"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-bundle" data-toggle="modal" data-target="#modal-assign-bundle" class="action-circle-btn" title="Assign Bundle"><i class="fas fa-plus"></i></a>
                            <a href="#modal-bulk-add-bundles" data-toggle="modal" data-target="#modal-bulk-add-bundles" class="action-circle-btn" title="Bulk Add Bundles"><i class="fas fa-cog"></i></a>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-price-bundles">
                        <input type="text" class="tab-search-input" placeholder="Filter bundles by name..." autocomplete="off">
                        <button class="clear-search-btn" title="Close"><i class="fas fa-times"></i></button>
                    </div>

                    <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                        <thead>
                            <tr>
                                <th>Bundle Name</th>
                                <th width="120" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
        if (count($brandedBundles) > 0) {
            foreach ($brandedBundles as $bundle) {
                $output .= '<tr>
                    <td><a style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($bundle->name) . '</a></td>
                    <td class="text-center">
                        <span class="label btn-pricing-override" data-type="bundle" data-relid="' . $bundle->id . '" data-name="' . htmlspecialchars($bundle->name) . '" style="background-color: #e67e22; color: #fff; padding: 4px 10px; border-radius: 3px; font-weight: bold; cursor: pointer; margin-right: 5px; font-family: sans-serif;" title="Pricing Override">$</span>
                        <a href="' . $modulelink . '&action=unlink_pricing_item&brand_id=' . $brand->id . '&type=bundle&item_id=' . $bundle->id . '" class="label" style="background-color: #d9534f; color: #fff; padding: 4px 8px; border-radius: 3px; font-weight: bold; cursor: pointer;" title="Delete Override" onclick="return confirm(\'Are you sure you want to unlink this bundle pricing from this brand?\')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="2" class="text-center" style="padding: 20px; color: #777;">No branded bundles configured. Click the "+" button in the top right to assign one, or "⚙" to bulk assign all.</td></tr>';
        }
        $output .= '     </tbody>
                    </table>
                </div>
            </div>
        </div>';

        // Add Brand Gateway Modal
        $output .= '
        <div id="modal-add-brand-gateway" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15); font-family: \'Outfit\', sans-serif;">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; color: #333;"><i class="fas fa-credit-card" style="margin-right: 8px;"></i> Add a Payment Gateway</h4>
                    </div>
                    <div class="modal-body" style="padding: 25px;">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Gateway</label>
                            <select id="add_brand_gateway_select" class="form-control" style="width: 100%; height: 38px; font-size: 0.95em;">
                                <option value="">Please select ...</option>
                                <optgroup label="Branded Gateways">
                                    <option value="paypalrest" data-whmcs="false">PayPal</option>
                                    <option value="stripe" data-whmcs="false">Stripe</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" id="btn_confirm_add_gateway" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Confirm</button>
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                    </div>
                </div>
            </div>
        </div>
        ';

        $output .= '
        <!-- Pricing Modals for edit page -->
        <div id="modal-assign-product" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-cube" style="margin-right: 8px;"></i> Assign Product</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=assign_pricing_item">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="product">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Product Name</label>
                                <select name="item_id" class="form-control select2-ajax-unassigned-products" style="width: 100%;"></select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Please select a product that you wish to add to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-assign-addon" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-puzzle-piece" style="margin-right: 8px;"></i> Assign Addon</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=assign_pricing_item">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="addon">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Addon Name</label>
                                <select name="item_id" class="form-control select2-ajax-unassigned-addons" style="width: 100%;"></select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Please select an addon that you wish to add to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-assign-domain" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-globe" style="margin-right: 8px;"></i> Assign Domain</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=assign_pricing_item">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="domain">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Domain TLD Extension</label>
                                <select name="item_id" class="form-control select2-ajax-unassigned-domains" style="width: 100%;"></select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Please select a domain TLD that you wish to add to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-assign-bundle" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-box-open" style="margin-right: 8px;"></i> Assign Bundle</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=assign_pricing_item">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="bundle">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Bundle Name</label>
                                <select name="item_id" class="form-control select2-ajax-unassigned-bundles" style="width: 100%;"></select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Please select a bundle that you wish to add to the current brand.</small>
                            </div>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-bulk-add-products" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-cubes" style="margin-right: 8px;"></i> Bulk Add Products</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=bulk_add_pricing_items">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="product">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <p style="font-size: 0.95em; color: #555; line-height: 1.5; margin: 0;">This action will add all available products configured in WHMCS. Are you sure you want to proceed?</p>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 16px; font-weight: 600; border-radius: 4px;">Confirm</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-bulk-add-addons" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-cubes" style="margin-right: 8px;"></i> Bulk Add Addons</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=bulk_add_pricing_items">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="addon">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <p style="font-size: 0.95em; color: #555; line-height: 1.5; margin: 0;">This action will add all available addons configured in WHMCS. Are you sure you want to proceed?</p>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 16px; font-weight: 600; border-radius: 4px;">Confirm</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-bulk-add-domains" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-globe" style="margin-right: 8px;"></i> Bulk Add Domains</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=bulk_add_pricing_items">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="domain">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <p style="font-size: 0.95em; color: #555; line-height: 1.5; margin: 0;">This action will add all available domain extensions configured in WHMCS. Are you sure you want to proceed?</p>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 16px; font-weight: 600; border-radius: 4px;">Confirm</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-bulk-add-bundles" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-box-open" style="margin-right: 8px;"></i> Bulk Add Bundles</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=bulk_add_pricing_items">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="bundle">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <p style="font-size: 0.95em; color: #555; line-height: 1.5; margin: 0;">This action will add all available bundles configured in WHMCS. Are you sure you want to proceed?</p>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-primary" style="padding: 6px 16px; font-weight: 600; border-radius: 4px;">Confirm</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="modal-pricing-override" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-dollar-sign" style="margin-right: 8px;"></i> Pricing: <span class="override-item-name" style="color: ' . $brandColor . ';">-</span></h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_pricing_override">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="type" value="">
                        <input type="hidden" name="relid" value="">
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ddd; margin-bottom: 20px; padding-bottom: 5px;">
                                <ul class="nav nav-tabs currency-tabs-list" style="border-bottom: none; margin: 0; display: flex; gap: 5px;"></ul>
                                <div style="display: flex; gap: 5px;">
                                    <button type="button" class="btn btn-default btn-sm btn-copy-rates" title="Copy active tab rates to all other currency tabs" style="border-radius: 4px; padding: 5px 10px; font-weight: bold;"><i class="fas fa-equals"></i></button>
                                    <button type="button" class="btn btn-default btn-sm btn-reset-rates" title="Reset to standard WHMCS pricing template" style="border-radius: 4px; padding: 5px 10px; font-weight: bold;"><i class="fas fa-sync-alt"></i></button>
                                </div>
                            </div>
                            <div class="tab-content currency-tabs-content"></div>
                        </div>
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- RELATIONS BOX -->
        <div id="mb-relations-box" style="background: #fff; border: 1px solid #e1e1e1; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; font-family: \'Outfit\', \'Segoe UI\', sans-serif;">
            <div style="padding: 12px 20px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <span style="font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 0.95em;"><i class="fas fa-exchange-alt" style="margin-right: 8px;"></i> Relations</span>
                <ul class="relations-tabs" role="tablist" style="border-bottom: none; margin: 0; display: flex; flex-wrap: wrap;">
                    <li class="active"><a href="#tab-clients" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Clients (' . count($clients) . ')</a></li>
                    <li><a href="#tab-services" role="tab" data-toggle="tab" style="padding: 6px 12px; border: none; font-weight: 600; font-size: 0.85em; background: transparent;">Services (' . (count($services) + count($assignedAddons) + count($assignedDomains)) . ')</a></li>
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-clients"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-client" data-toggle="modal" data-target="#modal-assign-client" class="action-circle-btn" title="Add Relation"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Clients Tab" data-help-text="This tab shows all clients linked to this brand. Use the + button to assign a client to this brand. Use the trash icon to unlink a client. Use Search to filter the list."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-clients"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-clients">
                        <input type="text" class="tab-search-input" placeholder="Filter clients by name, company, ID..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_client_from_edit">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="client-select-all"></th>
                                    <th width="60">#ID</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Company</th>
                                    <th>Created At</th>
                                    <th width="120" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (count($clients) > 0) {
            foreach ($clients as $client) {
                $createdAt = isset($client->datecreated) ? date('Y-m-d H:i:s', strtotime($client->datecreated)) : '-';
                $company = $client->companyname ?: '-';
                
                $firstNameLink = '<a href="clientssummary.php?userid=' . $client->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($client->firstname) . '</a>';
                $lastNameLink = '<a href="clientssummary.php?userid=' . $client->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($client->lastname) . '</a>';
                $companyLink = $client->companyname ? '<a href="clientssummary.php?userid=' . $client->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($client->companyname) . '</a>' : '-';

                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="client_ids[]" value="' . $client->id . '"></td>
                    <td>' . $client->id . '</td>
                    <td>' . $firstNameLink . '</td>
                    <td>' . $lastNameLink . '</td>
                    <td>' . $companyLink . '</td>
                    <td>' . htmlspecialchars($createdAt) . '</td>
                    <td class="text-center">
                        <a href="#" class="btn btn-sm btn-primary btn-migrate-client" data-client-id="' . $client->id . '" data-client-name="' . htmlspecialchars($client->firstname . ' ' . $client->lastname) . '" style="margin-right: 5px; background-color: #337ab7; border-color: #2e6da4;" title="Migrate Client"><i class="fas fa-exchange-alt"></i></a>
                        <a href="' . $modulelink . '&action=unlink_client&brand_id=' . $brand->id . '&client_id=' . $client->id . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this client from this brand?\')" title="Unlink Brand" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="7" class="text-center">No clients assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>

                <!-- SERVICES TAB -->
                <div class="tab-pane" id="tab-services">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; font-size: 1.1em;">
                            <i class="fas fa-cubes" style="margin-right: 8px;"></i> Services
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-services"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-service" data-toggle="modal" data-target="#modal-add-service" class="action-circle-btn" title="Add Service Relation"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Services Tab" data-help-text="This tab shows all hosting services, addons, and domains linked to this brand. Use the + button to assign new service relations. Use the search to filter by product name or domain."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-services"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-services">
                        <input type="text" class="tab-search-input" placeholder="Filter services by name, domain, status..." autocomplete="off" data-table="services-table">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <ul class="relations-tabs" role="tablist" style="margin-top: 10px; margin-bottom: 20px; font-size: 0.95em; border-bottom: 1px solid #ddd; display: flex; list-style: none; padding: 0;">
                        <li class="active" style="margin-right: 5px;"><a href="#subtab-hosting" role="tab" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.9em; background: transparent; display: block; text-decoration: none; color: #555;">Services & Products</a></li>
                        <li style="margin-right: 5px;"><a href="#subtab-addons" role="tab" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.9em; background: transparent; display: block; text-decoration: none; color: #555;">Addons</a></li>
                        <li><a href="#subtab-domains" role="tab" data-toggle="tab" style="padding: 8px 16px; border: none; font-weight: 600; font-size: 0.9em; background: transparent; display: block; text-decoration: none; color: #555;">Domains</a></li>
                    </ul>

                    <div class="tab-content" style="border: none; padding: 0; box-shadow: none; background: transparent;">
                        <!-- Services & Products Sub-tab -->
                        <div class="tab-pane active" id="subtab-hosting">
                            <form method="post" action="' . $modulelink . '&action=bulk_unlink_services">' . generate_token() . '' . generate_token() . '
                                <input type="hidden" name="brand_id" value="' . $brand->id . '">
                                <input type="hidden" name="redirect" value="edit">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="select-all-services"></th>
                                            <th width="60">#ID</th>
                                            <th>Product</th>
                                            <th>Domain</th>
                                            <th>Client</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($services) > 0) {
                                        foreach ($services as $srv) {
                                            $clientLink = '<a href="clientssummary.php?userid=' . $srv->userid . '" style="font-weight: 600; color: ' . $brandColor . '; text-decoration: none;">' . htmlspecialchars($srv->firstname . ' ' . $srv->lastname) . '</a>';
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="service_ids[]" value="' . $srv->id . '" class="service-item-checkbox"></td>
                                                <td>' . $srv->id . '</td>
                                                <td style="font-weight: bold;">' . htmlspecialchars($srv->product_name) . '</td>
                                                <td>' . ($srv->domain ? '<a href="http://' . htmlspecialchars($srv->domain) . '" target="_blank" style="color: ' . $brandColor . '; font-weight: bold; text-decoration: none;">' . htmlspecialchars($srv->domain) . '</a>' : '-') . '</td>
                                                <td>' . $clientLink . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_service_relation&brand_id=' . $brand->id . '&service_id=' . $srv->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this service relation?\')" title="Delete Relation" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="6" class="text-center">No service relations found for this brand.</td></tr>';
                                    }
                                    $output .= '
                                    </tbody>
                                </table>
                                <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                                    <span>With Selected:</span>
                                    <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected service relations?\')">Delete</button>
                                </div>
                            </form>
                        </div>

                        <!-- Addons Sub-tab -->
                        <div class="tab-pane" id="subtab-addons">
                            <form method="post" action="' . $modulelink . '&action=bulk_unlink_addons">' . generate_token() . '' . generate_token() . '
                                <input type="hidden" name="brand_id" value="' . $brand->id . '">
                                <input type="hidden" name="redirect" value="edit">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="select-all-addons"></th>
                                            <th width="60">#ID</th>
                                            <th>Addon</th>
                                            <th>Service</th>
                                            <th>Client</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($assignedAddons) > 0) {
                                        foreach ($assignedAddons as $addn) {
                                            $addonDisplayName = $addn->custom_name ?: ($addn->addon_name ?: 'Addon');
                                            $clientLink = '<a href="clientssummary.php?userid=' . $addn->userid . '" style="font-weight: 600; color: ' . $brandColor . '; text-decoration: none;">' . htmlspecialchars($addn->firstname . ' ' . $addn->lastname) . '</a>';
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="addon_ids[]" value="' . $addn->id . '" class="addon-item-checkbox"></td>
                                                <td>' . $addn->id . '</td>
                                                <td style="font-weight: bold;">' . htmlspecialchars($addonDisplayName) . '</td>
                                                <td>' . ($addn->service_domain ? '<a href="http://' . htmlspecialchars($addn->service_domain) . '" target="_blank" style="color: ' . $brandColor . '; font-weight: bold; text-decoration: none;">' . htmlspecialchars($addn->service_domain) . '</a>' : '-') . '</td>
                                                <td>' . $clientLink . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_addon_relation&brand_id=' . $brand->id . '&addon_id=' . $addn->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this addon relation?\')" title="Delete Relation" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="6" class="text-center">No addon relations found for this brand.</td></tr>';
                                    }
                                    $output .= '
                                    </tbody>
                                </table>
                                <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                                    <span>With Selected:</span>
                                    <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected addon relations?\')">Delete</button>
                                </div>
                            </form>
                        </div>

                        <!-- Domains Sub-tab -->
                        <div class="tab-pane" id="subtab-domains">
                            <form method="post" action="' . $modulelink . '&action=bulk_unlink_domains">' . generate_token() . '' . generate_token() . '
                                <input type="hidden" name="brand_id" value="' . $brand->id . '">
                                <input type="hidden" name="redirect" value="edit">
                                <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                                    <thead>
                                        <tr>
                                            <th width="30" class="text-center"><input type="checkbox" class="select-all-domains"></th>
                                            <th width="60">#ID</th>
                                            <th>Domain</th>
                                            <th>Client</th>
                                            <th width="80" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    if (count($assignedDomains) > 0) {
                                        foreach ($assignedDomains as $dom) {
                                            $clientLink = '<a href="clientssummary.php?userid=' . $dom->userid . '" style="font-weight: 600; color: ' . $brandColor . '; text-decoration: none;">' . htmlspecialchars($dom->firstname . ' ' . $dom->lastname) . '</a>';
                                            $output .= '<tr>
                                                <td class="text-center"><input type="checkbox" name="domain_ids[]" value="' . $dom->id . '" class="domain-item-checkbox"></td>
                                                <td>' . $dom->id . '</td>
                                                <td>' . ($dom->domain ? '<a href="http://' . htmlspecialchars($dom->domain) . '" target="_blank" style="color: ' . $brandColor . '; font-weight: bold; text-decoration: none;">' . htmlspecialchars($dom->domain) . '</a>' : '-') . '</td>
                                                <td>' . $clientLink . '</td>
                                                <td class="text-center">
                                                    <a href="' . $modulelink . '&action=unlink_domain_relation&brand_id=' . $brand->id . '&domain_id=' . $dom->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this domain relation?\')" title="Delete Relation" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                                                </td>
                                            </tr>';
                                        }
                                    } else {
                                        $output .= '<tr><td colspan="5" class="text-center">No domain relations found for this brand.</td></tr>';
                                    }
                                    $output .= '
                                    </tbody>
                                </table>
                                <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                                    <span>With Selected:</span>
                                    <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected domain relations?\')">Delete</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- INVOICES TAB -->
                <div class="tab-pane" id="tab-invoices">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-file-invoice-dollar" style="margin-right: 8px;"></i> Invoices
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-invoices"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-invoice" data-toggle="modal" data-target="#modal-add-invoice" class="action-circle-btn" title="Add Invoice"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Invoices Tab" data-help-text="This tab shows all invoices linked to this brand. Use the + button to assign an existing invoice to this brand. Use the trash icon to unlink an invoice. Use Search to filter by invoice number, client name, or status."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-invoices"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-invoices">
                        <input type="text" class="tab-search-input" placeholder="Filter invoices by number, client, status..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_invoices">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="invoice-select-all"></th>
                                    <th width="60">#ID</th>
                                    <th>Invoice Number</th>
                                    <th>Client</th>
                                    <th>Invoice Date</th>
                                    <th>Due Date</th>
                                    <th>Total</th>
                                    <th>Currency</th>
                                    <th>Payment Method</th>
                                    <th width="100" class="text-center">Status</th>
                                    <th width="100" class="text-center">Actions</th>
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
                
                $numDisplay = $invoice->invoicenum ?: $invoice->id;
                $invoiceLink = '<a href="invoices.php?action=edit&id=' . $invoice->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($numDisplay) . '</a>';
                $clientLink = '<a href="clientssummary.php?userid=' . $invoice->userid . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($invoice->firstname . ' ' . $invoice->lastname) . '</a>';
                $currencyDisplay = isset($invoice->currency_code) && $invoice->currency_code ? htmlspecialchars($invoice->currency_code) : '-';

                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="invoice_ids[]" value="' . $invoice->id . '" class="invoice-item-checkbox"></td>
                    <td><a href="invoices.php?action=edit&id=' . $invoice->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . $invoice->id . '</a></td>
                    <td>' . $invoiceLink . '</td>
                    <td>' . $clientLink . '</td>
                    <td>' . htmlspecialchars($invDate) . '</td>
                    <td>' . htmlspecialchars($dueDate) . '</td>
                    <td>' . htmlspecialchars($invoice->total) . '</td>
                    <td>' . $currencyDisplay . '</td>
                    <td>' . htmlspecialchars($invoice->paymentmethod) . '</td>
                    <td class="text-center">' . $statusBadge . '</td>
                    <td class="text-center">
                        <a href="invoices.php?action=edit&id=' . $invoice->id . '" class="btn btn-sm btn-primary" style="margin-right: 5px; background-color: #337ab7; border-color: #2e6da4;" title="Edit Invoice"><i class="fas fa-pencil-alt"></i></a>
                        <a href="' . $modulelink . '&action=unlink_invoice_relation&brand_id=' . $brand->id . '&invoice_id=' . $invoice->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this invoice from this brand?\')" title="Unlink Brand" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="11" class="text-center">No invoices assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }

        $output .= '
                            </tbody>
                        </table>
                        
                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                    
                </div>

                <!-- QUOTES TAB -->
                <div class="tab-pane" id="tab-quotes">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-file-signature" style="margin-right: 8px;"></i> Quotes
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-quotes"><i class="fas fa-search"></i></a>
                            <a href="#modal-assign-quote" data-toggle="modal" data-target="#modal-assign-quote" class="action-circle-btn" title="Add Quote"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Quotes Tab" data-help-text="This tab shows all quotes linked to this brand. Use the + button to assign a quote. Use Search to filter by ID, subject, or client."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-quotes"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-quotes">
                        <input type="text" class="tab-search-input" placeholder="Filter quotes by ID, subject, client..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_quotes">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="quote-select-all"></th>
                                    <th width="60">#ID</th>
                                    <th>Subject</th>
                                    <th>Client</th>
                                    <th>Stage</th>
                                    <th>Total</th>
                                    <th>Valid Until</th>
                                    <th>Last Modified</th>
                                    <th width="100" class="text-center">Actions</th>
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
                
                $validUntil = ($quote->validuntil && $quote->validuntil != '0000-00-00') ? $quote->validuntil : '-';
                $lastModified = ($quote->lastmodified && $quote->lastmodified != '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($quote->lastmodified)) : '-';

                $idLink = '<a href="quotes.php?action=manage&id=' . $quote->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . $quote->id . '</a>';
                $subjectLink = '<a href="quotes.php?action=manage&id=' . $quote->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($quote->subject) . '</a>';
                $clientLink = '<a href="clientssummary.php?userid=' . $quote->userid . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($quote->firstname . ' ' . $quote->lastname) . '</a>';

                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="quote_ids[]" value="' . $quote->id . '" class="quote-item-checkbox"></td>
                    <td>' . $idLink . '</td>
                    <td>' . $subjectLink . '</td>
                    <td>' . $clientLink . '</td>
                    <td>' . $statusBadge . '</td>
                    <td>' . htmlspecialchars($quote->total) . '</td>
                    <td>' . htmlspecialchars($validUntil) . '</td>
                    <td>' . htmlspecialchars($lastModified) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_quote_relation&brand_id=' . $brand->id . '&quote_id=' . $quote->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this quote from this brand?\')" title="Unlink Brand" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="9" class="text-center">No quotes assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }
        $output .= '
                            </tbody>
                        </table>

                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>
                </div>

                <!-- TICKETS TAB -->
                <div class="tab-pane" id="tab-tickets">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-weight: bold; color: ' . $brandColor . '; text-transform: uppercase; display: flex; align-items: center; font-size: 1.1em;">
                            <i class="fas fa-ticket-alt" style="margin-right: 8px;"></i> Tickets
                        </h4>
                        <div class="action-bar-right">
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-tickets"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-ticket" data-toggle="modal" data-target="#modal-add-ticket" class="action-circle-btn" title="Add Ticket"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Tickets Tab" data-help-text="This tab shows all support tickets linked to this brand. Use the + button to assign a ticket. Use Search to filter by ID, subject, or ticket number."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-tickets"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-tickets">
                        <input type="text" class="tab-search-input" placeholder="Filter tickets by ID, subject, number..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_tickets">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        <table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">
                            <thead>
                                <tr>
                                    <th width="30" class="text-center"><input type="checkbox" id="ticket-select-all"></th>
                                    <th width="60">Ticket ID</th>
                                    <th>Ticket #</th>
                                    <th>Title</th>
                                    <th>Department</th>
                                    <th>Submitter</th>
                                    <th>Status</th>
                                    <th>Last Reply</th>
                                    <th>Created At</th>
                                    <th width="100" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>';
        if (count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                $statusColor = '#333';
                if (strtolower($ticket->status) == 'open' || strtolower($ticket->status) == 'active') {
                    $statusColor = '#5cb85c';
                } elseif (strtolower($ticket->status) == 'answered') {
                    $statusColor = '#333';
                } elseif (strtolower($ticket->status) == 'closed') {
                    $statusColor = '#777';
                } elseif (strtolower($ticket->status) == 'in progress') {
                    $statusColor = '#d9534f';
                } elseif (strtolower($ticket->status) == 'customer-reply') {
                    $statusColor = '#f0ad4e';
                }
                $statusDisplay = '<span style="color: ' . $statusColor . '; font-weight: bold;">' . htmlspecialchars($ticket->status) . '</span>';
                
                $createdAt = ($ticket->date && $ticket->date != '0000-00-00 00:00:00') ? date('Y-m-d H:i:s', strtotime($ticket->date)) : '-';
                $lastReply = ($ticket->lastreply && $ticket->lastreply != '0000-00-00 00:00:00') ? date('Y-m-d H:i:s', strtotime($ticket->lastreply)) : '-';

                $deptName = isset($deptMap[$ticket->did]) ? $deptMap[$ticket->did] : 'Dept ID: ' . $ticket->did;

                $idLink = '<a href="supporttickets.php?action=view&id=' . $ticket->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . $ticket->id . '</a>';
                $titleLink = '<a href="supporttickets.php?action=view&id=' . $ticket->id . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($ticket->title) . '</a>';
                $submitterLink = '<a href="clientssummary.php?userid=' . $ticket->userid . '" style="font-weight: 600; color: #007bff; text-decoration: none;">' . htmlspecialchars($ticket->firstname . ' ' . $ticket->lastname) . '</a>';

                $output .= '<tr>
                    <td class="text-center"><input type="checkbox" name="ticket_ids[]" value="' . $ticket->id . '" class="ticket-item-checkbox"></td>
                    <td>' . $idLink . '</td>
                    <td>' . htmlspecialchars($ticket->tid) . '</td>
                    <td>' . $titleLink . '</td>
                    <td>' . htmlspecialchars($deptName) . '</td>
                    <td>' . $submitterLink . '</td>
                    <td>' . $statusDisplay . '</td>
                    <td>' . htmlspecialchars($lastReply) . '</td>
                    <td>' . htmlspecialchars($createdAt) . '</td>
                    <td class="text-center">
                        <a href="' . $modulelink . '&action=unlink_ticket_relation&brand_id=' . $brand->id . '&ticket_id=' . $ticket->id . '&redirect=edit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to unlink this ticket from this brand?\')" title="Unlink Brand" style="background-color: #d9534f; border-color: #d43f3a;"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>';
            }
        } else {
            $output .= '<tr><td colspan="10" class="text-center">No tickets assigned to this brand. Click the "+" button in the top right to assign one.</td></tr>';
        }
        $output .= '
                            </tbody>
                        </table>

                        <div style="margin-top: 15px; display: flex; align-items: center; gap: 8px;">
                            <span>With Selected:</span>
                            <button type="submit" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a;" onclick="return confirm(\'Are you sure you want to delete selected brand relations?\')">Delete</button>
                        </div>
                    </form>

                
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-kb"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-kb" data-toggle="modal" data-target="#modal-add-kb" class="action-circle-btn" title="Add Article"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Knowledge Base Tab" data-help-text="This tab shows all knowledge base articles linked to this brand. Use the + button to assign an article. Use Search to filter by title or ID."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-kb"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-kb">
                        <input type="text" class="tab-search-input" placeholder="Filter articles by title, ID..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_kb_from_edit">' . generate_token() . '' . generate_token() . '
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-downloads"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-download" data-toggle="modal" data-target="#modal-add-download" class="action-circle-btn" title="Add Download"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Downloads Tab" data-help-text="This tab shows all downloads linked to this brand. Use the + button to assign a download file. Use Search to filter by name or ID."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-downloads"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-downloads">
                        <input type="text" class="tab-search-input" placeholder="Filter downloads by name, ID..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_download_from_edit">' . generate_token() . '' . generate_token() . '
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-announcements"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-announcement" data-toggle="modal" data-target="#modal-add-announcement" class="action-circle-btn" title="Add Announcement"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Announcements Tab" data-help-text="This tab shows all announcements linked to this brand. Use the + button to assign an announcement. Use Search to filter by title or ID."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-announcements"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-announcements">
                        <input type="text" class="tab-search-input" placeholder="Filter announcements by title, ID..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_announcement_from_edit">' . generate_token() . '' . generate_token() . '
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-promotions"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-promotion" data-toggle="modal" data-target="#modal-add-promotion" class="action-circle-btn" title="Add Promotion"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Promotions Tab" data-help-text="This tab shows all promotions linked to this brand. Use the + button to assign a promotion. Use Search to filter by code or name."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-promotions"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-promotions">
                        <input type="text" class="tab-search-input" placeholder="Filter promotions by code, name..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <!-- Promotions Sub-tabs -->
                    <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px; border-bottom: 1px solid #ddd; display: flex; gap: 5px;">
                        <li class="active"><a href="#promo-active" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">Active</a></li>
                        <li><a href="#promo-expired" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">Expired</a></li>
                        <li><a href="#promo-all" role="tab" data-toggle="tab" style="padding: 6px 12px; font-weight: 600;">All</a></li>
                    </ul>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_promotion_from_edit">' . generate_token() . '' . generate_token() . '
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-billable"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-billable" data-toggle="modal" data-target="#modal-add-billable" class="action-circle-btn" title="Add Billable Item"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Billable Items Tab" data-help-text="This tab shows all billable items linked to this brand. Use the + button to assign a billable item. Use Search to filter by description or amount."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-billable"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-billable">
                        <input type="text" class="tab-search-input" placeholder="Filter billable items by description..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_billable_from_edit">' . generate_token() . '' . generate_token() . '
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
                            <a class="action-circle-btn tab-search-toggle" title="Search" data-search-target="search-row-emails"><i class="fas fa-search"></i></a>
                            <a href="#modal-add-email" data-toggle="modal" data-target="#modal-add-email" class="action-circle-btn" title="Add Email"><i class="fas fa-plus"></i></a>
                            <a class="action-circle-btn tab-help-btn" title="Help" data-help-title="Emails Tab" data-help-text="This tab shows all email messages linked to this brand. Use the + button to assign an email. Use Search to filter by subject or recipient."><i class="fas fa-question"></i></a>
                            <div class="tab-help-popover" id="help-popover-emails"></div>
                        </div>
                    </div>
                    <div class="tab-search-row" id="search-row-emails">
                        <input type="text" class="tab-search-input" placeholder="Filter emails by subject, recipient..." autocomplete="off">
                        <button class="clear-search-btn" title="Clear"><i class="fas fa-times"></i></button>
                    </div>

                    <form method="post" action="' . $modulelink . '&action=bulk_unlink_email_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <!-- <td><a href="emails.php?action=view&id=' . $email->id . '" target="_blank" style="font-weight: bold; color: #007bff; text-decoration: none;">' . htmlspecialchars($email->subject) . '</a></td> -->
                    <td>' . htmlspecialchars($email->subject) . '</td>
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
        // Strip datatable class from empty tables immediately to prevent DataTable crash
        jQuery("table.datatable").each(function() {
            if (jQuery(this).find("tbody td[colspan]").length > 0) {
                jQuery(this).removeClass("datatable").addClass("datatable-placeholder");
            }
        });

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

            // Restore datatable class to empty tables to bring back WHMCS styling
            jQuery("table.datatable-placeholder").addClass("datatable").removeClass("datatable-placeholder");
        });
        </script>';

        // Options for Assign Client Modal
        $clientOptions = '';
        $availableClientCount = 0;
        if (count($allClients) > 0) {
            foreach ($allClients as $c) {
                if (in_array($c->id, $clientIds)) {
                    continue; // Skip already assigned clients
                }
                $comp = $c->companyname ? ' (' . $c->companyname . ')' : '';
                $clientOptions .= '<option value="' . $c->id . '">#' . $c->id . ' ' . htmlspecialchars($c->firstname . ' ' . $c->lastname) . $comp . '</option>';
                $availableClientCount++;
            }
        }
        if ($availableClientCount == 0) {
            $clientOptions = '<option value="0" disabled>No clients available</option>';
        }

        $groupOptions = '';
        if (count($clientGroups) > 0) {
            foreach ($clientGroups as $g) {
                $groupOptions .= '<option value="' . $g->id . '">' . htmlspecialchars($g->groupname) . '</option>';
            }
        } else {
            $groupOptions .= '<option value="0" disabled>No client groups available</option>';
        }

        $brandOptions = '';
        if (count($brandsList) > 0) {
            foreach ($brandsList as $b) {
                if ($b->id != $brand->id) {
                    $brandOptions .= '<option value="' . $b->id . '">#' . $b->id . ' ' . htmlspecialchars($b->brand_name) . ' (' . htmlspecialchars($b->domain) . ')</option>';
                }
            }
        } else {
            $brandOptions .= '<option value="0" disabled>No other brands available</option>';
        }

        $output .= '
        <!-- Assign Client Modal -->
        <div id="modal-assign-client" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-user-plus" style="margin-right: 8px;"></i> Assign Client</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_client_relation_from_modal">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Type</label>
                                <select name="type" class="form-control" style="width: 100%; border-radius: 4px; padding: 8px; font-weight: 500; height: auto;">
                                    <option value="single">Single Client</option>
                                    <option value="group">Group of Clients</option>
                                    <option value="all">All Clients</option>
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Choose the preferred type from the dropdown menu. You can assign one client, a group of clients or all of them.</small>
                            </div>

                            <div class="form-group" id="client-select-container" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Client</label>
                                <select name="client_id" class="form-control select2-ajax-clients" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select a client that you would like to assign to the current brand.</small>
                            </div>

                            <div class="form-group" id="group-select-container" style="margin-bottom: 20px; display: none;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Client Group</label>
                                <select name="group_id" class="form-control" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                    ' . $groupOptions . '
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select a client group whose members you would like to assign to the current brand.</small>
                            </div>

                            <div class="form-group" style="margin-bottom: 10px;">
                                <div style="display: flex; align-items: flex-start; gap: 15px;">
                                    <div style="width: 120px; font-weight: bold; color: #555; font-size: 0.95em; padding-top: 4px;">Add All Items</div>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <label class="mb-switch" style="margin-bottom: 0;">
                                            <input type="checkbox" name="add_all_items" value="1">
                                            <span class="mb-slider"></span>
                                        </label>
                                        <span style="font-size: 0.82em; color: #777; line-height: 1.4;">If enabled, information such as services, invoices, quotes, tickets etc. will be assigned along with a particular client or group of clients.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <!-- Migrate Client Modal -->
        <div id="modal-migrate-client" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-exchange-alt" style="margin-right: 8px;"></i> Migrate Client</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=migrate_client_from_modal">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="current_brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="client_id" value="">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <p style="font-size: 1.05em; color: #444; margin-bottom: 20px; font-weight: 500;">
                                Migrating client: <span class="client-name-display" style="font-weight: bold; color: ' . $brandColor . ';">-</span>
                            </p>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Target Brand</label>
                                <select name="target_brand_id" class="form-control" style="width: 100%; border-radius: 4px; padding: 8px; font-weight: 500; height: auto;">
                                    ' . $brandOptions . '
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select the brand which you would like to migrate the selected client to.</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <!-- Add Service Modal -->
        <div id="modal-add-service" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-plus" style="margin-right: 8px;"></i> Add Service</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=add_service_relation_from_modal">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Service Type</label>
                                <select name="type" class="form-control" style="width: 100%; border-radius: 4px; padding: 8px; font-weight: 500; height: auto;">
                                    <option value="hosting">Hosting</option>
                                    <option value="addon">Addon</option>
                                    <option value="domain">Domain</option>
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select a type of service that you wish to assign to the current brand. Please note that available options in the \'Service\' field depend on this selection.</small>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Service</label>
                                <select name="service_id" class="form-control select2-ajax-unassigned-services" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select a service that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <!-- Add Invoice Modal -->
        <div id="modal-add-invoice" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-file-invoice-dollar" style="margin-right: 8px;"></i> Add Invoice</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_invoice_relation">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Invoice</label>
                                <select name="invoice_id" class="form-control select2-ajax-unassigned-invoices" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select an invoice that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <!-- Assign Quote Modal -->
        <div id="modal-assign-quote" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-toggle="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-file-signature" style="margin-right: 8px;"></i> Assign Quote</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_quote_relation">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Quote</label>
                                <select name="quote_id" class="form-control select2-ajax-unassigned-quotes" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Select a quote that you would like to assign to the current brand.</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <!-- Add Ticket Modal -->
        <div id="modal-add-ticket" class="modal fade" role="dialog" style="display: none;">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius: 6px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #fafafa; border-bottom: 1px solid #e5e5e5; padding: 15px 20px;">
                        <button type="button" class="close" data-dismiss="modal" style="font-size: 22px; line-height: 1; opacity: 0.5;">&times;</button>
                        <h4 class="modal-title" style="font-weight: bold; font-family: \'Outfit\', sans-serif; color: #333;"><i class="fas fa-ticket-alt" style="margin-right: 8px;"></i> Add Ticket</h4>
                    </div>
                    <form method="post" action="' . $modulelink . '&action=save_ticket_relation">' . generate_token() . '' . generate_token() . '
                        <input type="hidden" name="brand_id" value="' . $brand->id . '">
                        <input type="hidden" name="redirect" value="edit">
                        
                        <div class="modal-body" style="padding: 25px; font-family: \'Outfit\', sans-serif;">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #555; display: block; margin-bottom: 8px; font-size: 0.95em;">Ticket</label>
                                <select name="ticket_id" class="form-control select2-ajax-unassigned-tickets" style="width: 100%; border-radius: 4px; padding: 8px; height: auto;">
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px; font-size: 0.82em; color: #777;">Please select a ticket that you wish to assign to the current brand.</small>
                            </div>
                        </div>
                        
                        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e5e5e5; padding: 15px 20px;">
                            <button type="submit" class="btn btn-success" style="background-color: #5cb85c; border-color: #4cae4c; padding: 6px 16px; font-weight: 600; border-radius: 4px;">Save</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal" style="padding: 6px 16px; border-radius: 4px;">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        $output .= '
        <script>
        $(document).ready(function() {
            // Assign Client Type toggle
            $(document).on("change", "select[name=\"type\"]", function() {
                var val = $(this).val();
                if (val === "single") {
                    $("#group-select-container").hide();
                    $("#client-select-container").show();
                } else if (val === "group") {
                    $("#client-select-container").hide();
                    $("#group-select-container").show();
                } else {
                    $("#client-select-container").hide();
                    $("#group-select-container").hide();
                }
            });

            // Select All Checkboxes toggle for clients
            $(document).on("click", "#client-select-all", function() {
                var checked = this.checked;
                $("input[name=\"client_ids[]\"]").each(function() {
                    this.checked = checked;
                });
            });

            // Populate Migrate Modal details on click
            $(document).on("click", ".btn-migrate-client", function(e) {
                e.preventDefault();
                var clientId = $(this).data("client-id");
                var clientName = $(this).data("client-name");
                $("#modal-migrate-client input[name=\"client_id\"]").val(clientId);
                $("#modal-migrate-client .client-name-display").text(clientName);
                $("#modal-migrate-client").modal("show");
            });

            // Initialize Select2 with AJAX for clients in the Assign modal
            var initSelect2 = function() {
                var selectEl = $(".select2-ajax-clients");
                if (selectEl.length && $.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) {
                        selectEl.select2("destroy");
                    }
                    selectEl.select2({
                        placeholder: "Search for a client by ID, Name, Email, or Company...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: $("#modal-assign-client"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_clients&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term // search query
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.results
                                };
                            },
                            cache: true
                        }
                    });
                }
            };

            // Run on shown.bs.modal to ensure correct width
            $("#modal-assign-client").on("shown.bs.modal", function() {
                initSelect2();
            });

            // Initialize Select2 with AJAX for unassigned services in Add Service Modal
            var initUnassignedServicesSelect2 = function() {
                var selectEl = $(".select2-ajax-unassigned-services");
                var type = $("#modal-add-service select[name=\"type\"]").val();
                if (selectEl.length && $.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) {
                        selectEl.select2("destroy");
                    }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a " + type + " by ID, Name, Domain, or Client...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: $("#modal-add-service"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_services&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term, // search query
                                    type: type
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.results
                                };
                            },
                            cache: true
                        }
                    });
                }
            };

            // Run on shown.bs.modal for service modal
            $("#modal-add-service").on("shown.bs.modal", function() {
                initUnassignedServicesSelect2();
            });

            // Re-initialize when Service Type changes
            $(document).on("change", "#modal-add-service select[name=\"type\"]", function() {
                initUnassignedServicesSelect2();
            });

            // Select All Checkboxes toggle for services sub-tab
            $(document).on("click", ".select-all-services", function() {
                var checked = this.checked;
                $(".service-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // Select All Checkboxes toggle for addons sub-tab
            $(document).on("click", ".select-all-addons", function() {
                var checked = this.checked;
                $(".addon-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // Select All Checkboxes toggle for domains sub-tab
            $(document).on("click", ".select-all-domains", function() {
                var checked = this.checked;
                $(".domain-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // Initialize Select2 with AJAX for unassigned invoices in Add Invoice Modal
            var initUnassignedInvoicesSelect2 = function() {
                var selectEl = $(".select2-ajax-unassigned-invoices");
                if (selectEl.length && $.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) {
                        selectEl.select2("destroy");
                    }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for an invoice by ID, Number, Client, or Company...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: $("#modal-add-invoice"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_invoices",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term // search query
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.results
                                };
                            },
                            cache: true
                        }
                    });
                }
            };

            // Run on shown.bs.modal for invoice modal
            $("#modal-add-invoice").on("shown.bs.modal", function() {
                initUnassignedInvoicesSelect2();
            });

            // Select All Checkboxes toggle for invoices
            $(document).on("click", "#invoice-select-all", function() {
                var checked = this.checked;
                $(".invoice-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // Initialize Select2 with AJAX for unassigned quotes in Assign Quote Modal
            var initUnassignedQuotesSelect2 = function() {
                var selectEl = $(".select2-ajax-unassigned-quotes");
                if (selectEl.length && $.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) {
                        selectEl.select2("destroy");
                    }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a quote by ID, Subject, Client...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: $("#modal-assign-quote"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_quotes&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term // search query
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.results
                                };
                            },
                            cache: true
                        }
                    });
                }
            };

            // Run on shown.bs.modal for quote modal
            $("#modal-assign-quote").on("shown.bs.modal", function() {
                initUnassignedQuotesSelect2();
            });

            // Select All Checkboxes toggle for quotes
            $(document).on("click", "#quote-select-all", function() {
                var checked = this.checked;
                $(".quote-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // Initialize Select2 with AJAX for unassigned tickets in Add Ticket Modal
            var initUnassignedTicketsSelect2 = function() {
                var selectEl = $(".select2-ajax-unassigned-tickets");
                if (selectEl.length && $.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) {
                        selectEl.select2("destroy");
                    }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a ticket by ID, Subject, Ticket Number...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: $("#modal-add-ticket"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_tickets&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term // search query
                                };
                            },
                            processResults: function (data) {
                                return {
                                    results: data.results
                                };
                            },
                            cache: true
                        }
                    });
                }
            };

            // Run on shown.bs.modal for ticket modal
            $("#modal-add-ticket").on("shown.bs.modal", function() {
                initUnassignedTicketsSelect2();
            });

            // Select All Checkboxes toggle for tickets
            $(document).on("click", "#ticket-select-all", function() {
                var checked = this.checked;
                $(".ticket-item-checkbox").each(function() {
                    this.checked = checked;
                });
            });

            // =============================================
            // TAB SEARCH TOGGLE & LIVE FILTER
            // =============================================
            $(document).on("click", ".tab-search-toggle", function(e) {
                e.preventDefault();
                var targetId = $(this).data("search-target");
                var $row = $("#" + targetId);
                var $btn = $(this);

                if ($row.hasClass("open")) {
                    $row.removeClass("open");
                    $btn.removeClass("active");
                    $row.find(".tab-search-input").val("").trigger("input");
                } else {
                    // Close any other open search rows
                    $(".tab-search-row.open").each(function() {
                        $(this).removeClass("open");
                        var otherId = $(this).attr("id");
                        $(".tab-search-toggle[data-search-target=\'" + otherId + "\']").removeClass("active");
                        $(this).find(".tab-search-input").val("").trigger("input");
                    });
                    $row.addClass("open");
                    $btn.addClass("active");
                    setTimeout(function() { $row.find(".tab-search-input").focus(); }, 50);
                }
            });

            // Clear/close search button - clears filter AND closes the search row
            $(document).on("click", ".clear-search-btn", function(e) {
                e.preventDefault();
                var $row = $(this).closest(".tab-search-row");
                var rowId = $row.attr("id");
                // Clear and hide all rows
                $row.find(".tab-search-input").val("").trigger("input");
                $row.removeClass("open");
                // Deactivate the matching search toggle button
                $(".tab-search-toggle[data-search-target=\'" + rowId + "\']").removeClass("active");
            });

            // Live filter: filter table rows based on search input
            $(document).on("input", ".tab-search-input", function() {
                var query = $(this).val().toLowerCase().trim();
                // Find the nearest table inside the same tab-pane
                var $tabPane = $(this).closest(".tab-pane");
                var $rows = $tabPane.find("table tbody tr");

                $rows.each(function() {
                    var text = $(this).text().toLowerCase();
                    if (query === "" || text.indexOf(query) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });

                // Show/hide no-results message
                var $tbody = $tabPane.find("table tbody");
                var visibleRows = $tbody.find("tr:visible").length;
                $tbody.find(".no-filter-results").remove();
                if (visibleRows === 0 && query !== "") {
                    $tbody.append(\'<tr class="no-filter-results"><td colspan="20" class="text-center" style="padding: 20px; color: #999; font-style: italic;">No results matching &ldquo;\' + $("<div>").text(query).html() + \'&rdquo;</td></tr>\');
                }
            });

            // =============================================
            // HELP POPOVER
            // =============================================
            $(document).on("click", ".tab-help-btn", function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var $bar = $btn.closest(".action-bar-right");
                var $popover = $bar.find(".tab-help-popover");
                var helpTitle = $btn.data("help-title") || "Help";
                var helpText  = $btn.data("help-text") || "";

                if ($popover.hasClass("open")) {
                    $popover.removeClass("open");
                    $btn.removeClass("active");
                } else {
                    // Close any other open popovers
                    $(".tab-help-popover.open").removeClass("open");
                    $(".tab-help-btn.active").removeClass("active");
                    // Set content
                    $popover.html("<h6><i class=\'fas fa-info-circle\' style=\'margin-right:6px;color:#007bff\'></i>" + helpTitle + "</h6><p style=\'margin:0;\'>" + helpText + "</p>");
                    $popover.addClass("open");
                    $btn.addClass("active");
                }
            });

            // Close help popover when clicking outside
            $(document).on("click", function(e) {
                if (!$(e.target).closest(".action-bar-right").length) {
                    $(".tab-help-popover.open").removeClass("open");
                    $(".tab-help-btn.active").removeClass("active");
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
                    <form method="post" action="' . $modulelink . '&action=save_kb_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <form method="post" action="' . $modulelink . '&action=save_download_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <form method="post" action="' . $modulelink . '&action=save_announcement_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <form method="post" action="' . $modulelink . '&action=save_promotion_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <form method="post" action="' . $modulelink . '&action=save_billable_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
                    <form method="post" action="' . $modulelink . '&action=save_email_relation_from_edit">' . generate_token() . '' . generate_token() . '
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
            // Fix action-circle-btn modals not opening in some themes due to anchor click overrides
            $(document).on(\'click\', \'.action-circle-btn\', function(e) {
                var target = $(this).attr(\'data-target\') || $(this).attr(\'href\');
                if (target && target.indexOf(\'#\') === 0 && target.indexOf(\'#modal-\') === 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $modal = $(target);
                    if ($modal.length && typeof $modal.modal === \'function\') {
                        $modal.modal(\'show\');
                    }
                }
            });

            // Keep active tab state on form submit
            jQuery(\'a[data-toggle="tab"]\').on(\'shown.bs.tab\', function (e) {
                var target = jQuery(e.target).attr("href");
                 if (target && target.indexOf("#set-") === 0) {
                    jQuery(\'#active_tab\').val(target);
                }
            });
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

            // Services Pricing - search table local filtering
            // pricing-search-btn is now replaced by tab-search-toggle - no action needed here
            jQuery(document).on("click", ".pricing-search-btn-legacy", function() {
                jQuery(this).closest(".tab-pane").find(".pricing-search-wrapper").slideToggle(200);
            });
            jQuery(document).on("keyup", ".search-pricing-table", function() {
                var val = jQuery(this).val().toLowerCase();
                jQuery(this).closest(".tab-pane").find("table tbody tr").filter(function() {
                    jQuery(this).toggle(jQuery(this).text().toLowerCase().indexOf(val) > -1);
                });
            });

            // Select2 AJAX Autocomplete loaders for Assign Pricing modals
            var initUnassignedProductsSelect2 = function() {
                var selectEl = jQuery(".select2-ajax-unassigned-products");
                if (selectEl.length && jQuery.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) { selectEl.select2("destroy"); }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a product...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: jQuery("#modal-assign-product"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_pricing_items&type=product&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || ""
                                };
                            },
                            processResults: function (data) { return { results: data.results }; },
                            cache: true
                        }
                    });
                }
            };
            jQuery("#modal-assign-product").on("shown.bs.modal", function() { initUnassignedProductsSelect2(); });

            var initUnassignedAddonsSelect2 = function() {
                var selectEl = jQuery(".select2-ajax-unassigned-addons");
                if (selectEl.length && jQuery.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) { selectEl.select2("destroy"); }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for an addon...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: jQuery("#modal-assign-addon"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_pricing_items&type=addon&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || ""
                                };
                            },
                            processResults: function (data) { return { results: data.results }; },
                            cache: true
                        }
                    });
                }
            };
            jQuery("#modal-assign-addon").on("shown.bs.modal", function() { initUnassignedAddonsSelect2(); });

            var initUnassignedDomainsSelect2 = function() {
                var selectEl = jQuery(".select2-ajax-unassigned-domains");
                if (selectEl.length && jQuery.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) { selectEl.select2("destroy"); }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a domain TLD...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: jQuery("#modal-assign-domain"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_pricing_items&type=domain&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || ""
                                };
                            },
                            processResults: function (data) { return { results: data.results }; },
                            cache: true
                        }
                    });
                }
            };
            jQuery("#modal-assign-domain").on("shown.bs.modal", function() { initUnassignedDomainsSelect2(); });

            var initUnassignedBundlesSelect2 = function() {
                var selectEl = jQuery(".select2-ajax-unassigned-bundles");
                if (selectEl.length && jQuery.fn.select2) {
                    if (selectEl.hasClass("select2-hidden-accessible")) { selectEl.select2("destroy"); }
                    selectEl.empty();
                    selectEl.select2({
                        placeholder: "Search for a bundle...",
                        minimumInputLength: 0,
                        width: "100%",
                        dropdownParent: jQuery("#modal-assign-bundle"),
                        ajax: {
                            url: "' . $modulelink . '&action=search_unassigned_pricing_items&type=bundle&brand_id=' . $brand->id . '",
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || ""
                                };
                            },
                            processResults: function (data) { return { results: data.results }; },
                            cache: true
                        }
                    });
                }
            };
            jQuery("#modal-assign-bundle").on("shown.bs.modal", function() { initUnassignedBundlesSelect2(); });


            // Dynamic Pricing Override Modal Renderer & Event handlers
            var currencies = ' . json_encode($currenciesList) . ';
            
            jQuery(document).on("click", ".btn-pricing-override", function(e) {
                e.preventDefault();
                var type = jQuery(this).data("type");
                var relid = jQuery(this).data("relid");
                var name = jQuery(this).data("name");
                
                var modal = jQuery("#modal-pricing-override");
                modal.find("input[name=\"type\"]").val(type);
                modal.find("input[name=\"relid\"]").val(relid);
                modal.find(".override-item-name").text(name);
                
                // Show loading state
                modal.find(".currency-tabs-list").html("<li><a>Loading...</a></li>");
                modal.find(".currency-tabs-content").html("<div class=\"text-center\" style=\"padding: 40px;\"><i class=\"fas fa-spinner fa-spin\" style=\"font-size: 2em; color: ' . $brandColor . ';\"></i><p style=\"margin-top: 10px;\">Fetching pricing templates...</p></div>");
                modal.modal("show");
                
                // Fetch dynamic WHMCS templates + Brand overrides
                jQuery.ajax({
                    url: "' . $modulelink . '&action=get_pricing_override_ajax&brand_id=' . $brand->id . '",
                    data: { type: type, relid: relid },
                    dataType: "json",
                    success: function(resp) {
                        renderPricingModal(resp);
                    },
                    error: function() {
                        modal.find(".currency-tabs-content").html("<div class=\"alert alert-danger\">Error loading pricing templates. Please try again.</div>");
                    }
                });
            });

            // Collapsible Cycle Panel toggle
            jQuery(document).on("click", ".cycle-panel-header", function() {
                var body = jQuery(this).next(".cycle-panel-body");
                body.slideToggle(200);
                jQuery(this).find(".cycle-chevron").toggleClass("fa-chevron-down fa-chevron-up");
            });

            // Copy first/default currency rates to other tabs
            jQuery(document).on("click", ".btn-copy-rates", function(e) {
                e.preventDefault();
                if (!confirm("Are you sure you want to copy the active tab rates to all other currency tabs?")) return;
                
                var activeTab = jQuery("#modal-pricing-override .currency-tab-pane.active");
                if (!activeTab.length) return;
                
                var data = {};
                activeTab.find("input").each(function() {
                    var name = jQuery(this).attr("name");
                    // Extract field name part e.g. pricing[1][monthly] -> monthly
                    var parts = name.match(/pricing\[\d+\]\[([^\]]+)\]/);
                    if (parts && parts[1]) {
                        data[parts[1]] = jQuery(this).val();
                    }
                });
                
                jQuery("#modal-pricing-override .currency-tab-pane").not(activeTab).each(function() {
                    var pane = jQuery(this);
                    jQuery.each(data, function(key, val) {
                        pane.find("input[name*=\"[" + key + "]\"]").val(val);
                    });
                });
            });

            // Reset current rates to standard WHMCS defaults
            jQuery(document).on("click", ".btn-reset-rates", function(e) {
                e.preventDefault();
                if (!confirm("Are you sure you want to reset all overrides to standard WHMCS template pricing?")) return;
                
                jQuery("#modal-pricing-override .currency-tab-pane").each(function() {
                    var pane = jQuery(this);
                    pane.find("input").each(function() {
                        var defaultVal = jQuery(this).data("default") || "0.00";
                        jQuery(this).val(defaultVal);
                    });
                });
            });

            function renderPricingModal(data) {
                var modal = jQuery("#modal-pricing-override");
                var tabList = modal.find(".currency-tabs-list");
                var tabContent = modal.find(".currency-tabs-content");
                
                tabList.empty();
                tabContent.empty();
                
                if (!currencies || currencies.length === 0) {
                    tabContent.html("<div class=\"alert alert-warning\">No currencies configured in WHMCS.</div>");
                    return;
                }
                
                jQuery.each(currencies, function(idx, curr) {
                    var activeClass = (idx === 0) ? "active" : "";
                    
                    // Create Currency Tab Header
                    tabList.append("<li class=\"" + activeClass + "\"><a href=\"#curr-tab-" + curr.id + "\" data-toggle=\"tab\" style=\"padding: 8px 16px; border: none; font-weight: bold;\">" + curr.code + "</a></li>");
                    
                    // Create Currency Tab Content
                    var pane = jQuery("<div class=\"tab-pane currency-tab-pane " + activeClass + "\" id=\"curr-tab-" + curr.id + "\"></div>");
                    
                    var baseRates = data.base[curr.id] || {};
                    var overrideRates = data.overrides[curr.id] || {};
                    var cycleList = [];
                    
                    if (data.paymenttype === "free") {
                        pane.append("<div class=\"text-center text-muted\" style=\"padding: 20px;\">This product is configured as Free in WHMCS. No overrides needed.</div>");
                    } else if (data.paymenttype === "onetime") {
                        cycleList = [
                            { key: "onetime", label: "One Time", priceKey: "monthly", setupKey: "msetupfee" }
                        ];
                    } else if (data.paymenttype === "recurring" || data.paymenttype === "addon") {
                        cycleList = [
                            { key: "monthly", label: "Monthly", priceKey: "monthly", setupKey: "msetupfee" },
                            { key: "quarterly", label: "Quarterly", priceKey: "quarterly", setupKey: "qsetupfee" },
                            { key: "semiannually", label: "Semiannually", priceKey: "semiannually", setupKey: "ssetupfee" },
                            { key: "annually", label: "Annually", priceKey: "annually", setupKey: "asetupfee" },
                            { key: "biennially", label: "Biennially", priceKey: "biennially", setupKey: "bsetupfee" },
                            { key: "triennially", label: "Triennially", priceKey: "triennially", setupKey: "tsetupfee" }
                        ];
                    } else if (data.paymenttype === "domain") {
                        // Domains support 1 to 10 Years
                        cycleList = [
                            { key: "1year", label: "1 Year Register", priceKey: "register1", setupKey: "transfer1", renewKey: "renew1", isDomain: true },
                            { key: "2year", label: "2 Years Register", priceKey: "register2", setupKey: "transfer2", renewKey: "renew2", isDomain: true },
                            { key: "3year", label: "3 Years Register", priceKey: "register3", setupKey: "transfer3", renewKey: "renew3", isDomain: true },
                            { key: "5year", label: "5 Years Register", priceKey: "register5", setupKey: "transfer5", renewKey: "renew5", isDomain: true },
                            { key: "10year", label: "10 Years Register", priceKey: "register10", setupKey: "transfer10", renewKey: "renew10", isDomain: true }
                        ];
                    } else if (data.paymenttype === "bundle") {
                        cycleList = [
                            { key: "bundle", label: "Display Price", priceKey: "displayprice", isBundle: true }
                        ];
                    } else {
                        // Fallback generic pricing
                        cycleList = [
                            { key: "onetime", label: "Price", priceKey: "monthly", setupKey: "msetupfee" }
                        ];
                    }
                    
                    jQuery.each(cycleList, function(cidx, cyc) {
                        var isFirst = (cidx === 0);
                        var collapseIcon = isFirst ? "fa-chevron-up" : "fa-chevron-down";
                        var displayStyle = isFirst ? "" : "display: none;";
                        
                        var basePrice = baseRates[cyc.priceKey] !== undefined ? parseFloat(baseRates[cyc.priceKey]).toFixed(2) : "0.00";
                        var baseSetup = baseRates[cyc.setupKey] !== undefined ? parseFloat(baseRates[cyc.setupKey]).toFixed(2) : "0.00";
                        var baseRenew = cyc.isDomain && baseRates[cyc.renewKey] !== undefined ? parseFloat(baseRates[cyc.renewKey]).toFixed(2) : "0.00";
                        
                        // Overrides fallback to base WHMCS pricing if not set yet
                        var overPrice = overrideRates[cyc.priceKey] !== undefined ? parseFloat(overrideRates[cyc.priceKey]).toFixed(2) : basePrice;
                        var overSetup = overrideRates[cyc.setupKey] !== undefined ? parseFloat(overrideRates[cyc.setupKey]).toFixed(2) : baseSetup;
                        var overRenew = cyc.isDomain && overrideRates[cyc.renewKey] !== undefined ? parseFloat(overrideRates[cyc.renewKey]).toFixed(2) : baseRenew;
                        
                        var cycPanel = jQuery(
                            "<div class=\"cycle-panel\" style=\"margin-bottom: 12px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;\">" +
                            "  <div class=\"cycle-panel-header\" style=\"background: #fcfcfc; padding: 10px 15px; font-weight: bold; color: #444; cursor: pointer; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #ddd;\">" +
                            "    <span>" + cyc.label + "</span>" +
                            "    <i class=\"fas " + collapseIcon + " cycle-chevron\" style=\"font-size: 0.9em; color: #777;\"></i>" +
                            "  </div>" +
                            "  <div class=\"cycle-panel-body\" style=\"padding: 15px 20px; background: #fff; " + displayStyle + "\">" +
                            "    <div class=\"row\">" +
                            "    </div>" +
                            "  </div>" +
                            "</div>"
                        );
                        
                        var row = cycPanel.find(".row");
                        
                        if (cyc.isDomain) {
                            row.append(
                                "      <div class=\"col-sm-4\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Register Price</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.priceKey + "]\" value=\"" + overPrice + "\" data-default=\"" + basePrice + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + basePrice + "</small>" +
                                "      </div>" +
                                "      <div class=\"col-sm-4\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Transfer Price</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.setupKey + "]\" value=\"" + overSetup + "\" data-default=\"" + baseSetup + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + baseSetup + "</small>" +
                                "      </div>" +
                                "      <div class=\"col-sm-4\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Renew Price</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.renewKey + "]\" value=\"" + overRenew + "\" data-default=\"" + baseRenew + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + baseRenew + "</small>" +
                                "      </div>"
                            );
                        } else if (cyc.isBundle) {
                            row.append(
                                "      <div class=\"col-sm-12\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Display Price</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.priceKey + "]\" value=\"" + overPrice + "\" data-default=\"" + basePrice + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + basePrice + "</small>" +
                                "      </div>"
                            );
                        } else {
                            row.append(
                                "      <div class=\"col-sm-6\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Price</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.priceKey + "]\" value=\"" + overPrice + "\" data-default=\"" + basePrice + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + basePrice + "</small>" +
                                "      </div>" +
                                "      <div class=\"col-sm-6\">" +
                                "        <label style=\"font-weight: 500; font-size: 0.9em;\">Setup Fee</label>" +
                                "        <div class=\"input-group input-group-sm\">" +
                                "          <span class=\"input-group-addon\">" + curr.prefix + "</span>" +
                                "          <input type=\"text\" class=\"form-control\" name=\"pricing[" + curr.id + "][" + cyc.setupKey + "]\" value=\"" + overSetup + "\" data-default=\"" + baseSetup + "\">" +
                                "        </div>" +
                                "        <small class=\"text-muted\">Base: " + curr.prefix + baseSetup + "</small>" +
                                "      </div>"
                            );
                        }
                        
                        pane.append(cycPanel);
                    });
                    
                    tabContent.append(pane);
                });
            }
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

        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        $systemUrl = isset($_POST['system_url']) ? trim($_POST['system_url']) : '';
        $tosUrl = isset($_POST['tos_url']) ? trim($_POST['tos_url']) : '';

        // Validate Domain URL (Required, must be a valid URL starting with http:// or https://)
        if (empty($domain)) {
            $validationError = 'Domain is required.';
        } elseif (!filter_var($domain, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $domain)) {
            $validationError = 'Domain must be a valid URL starting with http:// or https://';
        }

        // Validate System URL (Required, must be a valid URL starting with http:// or https://)
        if (!isset($validationError)) {
            if (empty($systemUrl)) {
                $validationError = 'System URL is required.';
            } elseif (!filter_var($systemUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $systemUrl)) {
                $validationError = 'System URL must be a valid URL starting with http:// or https://';
            }
        }

        // Validate TOS URL (Optional, but if present must be a valid URL starting with http:// or https://)
        if (!isset($validationError) && !empty($tosUrl)) {
            if (!filter_var($tosUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $tosUrl)) {
                $validationError = 'TOS URL must be a valid URL starting with http:// or https://';
            }
        }

        $_POST['domain'] = $domain;

        // If validation fails, handle redirecting back with errors and input preservation
        if (isset($validationError)) {
            echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($validationError) . '</div>';
            $_REQUEST['validation_error'] = true;
            if ($id > 0) {
                $_REQUEST['id'] = $id;
                return $this->edit($vars);
            }
            return $this->add($vars);
        }

        // Apply trailing slash to System URL if it does not end with one
        if (substr($systemUrl, -1) !== '/') {
            $systemUrl .= '/';
        }
        $_POST['system_url'] = $systemUrl;

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
            // 'next_sequential_number' => isset($_POST['next_sequential_number']) && $_POST['next_sequential_number'] !== '' ? (int) $_POST['next_sequential_number'] : null,
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
            'payment_gateways' => htmlspecialchars_decode($_POST['payment_gateways']),
            'smtp_settings' => json_encode([
                'override' => isset($_POST['smtp_override']) ? 1 : 0,
                'debug' => isset($_POST['smtp_debug']) ? 1 : 0,
                'port' => htmlspecialchars_decode($_POST['smtp_port']),
                'mail_type' => htmlspecialchars_decode($_POST['smtp_mail_type']),
                'hostname' => htmlspecialchars_decode($_POST['smtp_hostname']),
                'ssl_type' => htmlspecialchars_decode($_POST['smtp_ssl_type']),
                'username' => htmlspecialchars_decode($_POST['smtp_username']),
                'password' => htmlspecialchars_decode($_POST['smtp_password']),
                'disable_email' => isset($_POST['smtp_disable_email']) ? 1 : 0,
            ]),
            'email_template_settings' => json_encode([
                'css' => htmlspecialchars_decode($_POST['css_email_styling']),
                'header' => htmlspecialchars_decode($_POST['email_header_content']),
                'footer' => htmlspecialchars_decode($_POST['email_footer_content']),
            ]),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($_POST['next_sequential_number']) && $_POST['next_sequential_number'] !== '') {
            $data['next_sequential_number'] = (int) $_POST['next_sequential_number'];
        } else {
            $data['next_sequential_number'] = null;
        }
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
                
                // Process email template status updates
                $postedStatuses = isset($_POST['email_template_status']) && is_array($_POST['email_template_status']) ? $_POST['email_template_status'] : [];
                
                // Get all client-facing template names
                $clientTemplates = Capsule::table('tblemailtemplates')
                    ->where('language', '')
                    ->whereIn('type', ['general', 'product', 'domain', 'support', 'invoice', 'user', 'invite', 'affiliate', 'notification'])
                    ->pluck('name')
                    ->toArray();
                    
                foreach ($clientTemplates as $templateName) {
                    $status = isset($postedStatuses[$templateName]) ? 1 : 0;
                    
                    $exists = Capsule::table('mod_multibrand_email_templates')
                        ->where('brand_id', $id)
                        ->where('template_name', $templateName)
                        ->exists();
                        
                    if ($exists) {
                        Capsule::table('mod_multibrand_email_templates')
                            ->where('brand_id', $id)
                            ->where('template_name', $templateName)
                            ->update([
                                'status' => $status,
                                'updated_at' => date('Y-m-d H:i:s'),
                            ]);
                    } else if ($status == 1) {
                        Capsule::table('mod_multibrand_email_templates')->insert([
                            'brand_id' => $id,
                            'template_name' => $templateName,
                            'status' => 1,
                            'copy_to' => '',
                            'blind_copy_to' => '',
                            'translations' => json_encode([]),
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
                
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
     * Test SMTP connection credentials via AJAX
     */
    public function test_smtp_connection($vars)
    {
        header('Content-Type: application/json');
        
        $hostname = $_POST['hostname'] ?? '';
        $port = (int) ($_POST['port'] ?? 25);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $sslType = $_POST['ssl_type'] ?? '';
        $mailType = $_POST['mail_type'] ?? 'SMTP';

        if (empty($hostname) || empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Hostname, Username, and Password are required.']);
            exit;
        }

        // Get currently logged in admin email
        $adminId = isset($_SESSION['adminid']) ? (int)$_SESSION['adminid'] : 0;
        $adminEmail = '';
        if ($adminId > 0) {
            $admin = Capsule::table('tbladmins')->where('id', $adminId)->first();
            $adminEmail = $admin ? $admin->email : '';
        }

        if (empty($adminEmail)) {
            echo json_encode(['success' => false, 'message' => 'Unable to determine currently logged in admin email.']);
            exit;
        }

        try {
            // Instantiate PHPMailer
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            if (strtolower($mailType) === 'smtp') {
                $mail->isSMTP();
                $mail->Host = $hostname;
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
                $mail->Port = $port;

                if (strtolower($sslType) === 'ssl') {
                    $mail->SMTPSecure = 'ssl';
                } elseif (strtolower($sslType) === 'tls') {
                    $mail->SMTPSecure = 'tls';
                } else {
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                $mail->Timeout = 10;
            } else {
                $mail->isMail();
            }

            $mail->setFrom($username, $vars['companyname'] ?? 'Multi Brand Outgoing Test');
            $mail->addAddress($adminEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Multi Brand - SMTP Connection Test';
            $mail->Body = '<h3>Multi Brand SMTP Connection Test</h3><p>This email confirms that your outgoing SMTP credentials for the brand are correct and working perfectly.</p>';

            $mail->send();

            echo json_encode(['success' => true, 'message' => 'Connection successful! A test email has been sent to ' . htmlspecialchars($adminEmail)]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
        exit;
    }

    /**
     * Test payment gateway credentials via AJAX
     */
    public function test_gateway_connection($vars)
    {
        header('Content-Type: application/json');

        $gateway = $_POST['gateway'] ?? '';
        $client_id = $_POST['client_id'] ?? '';
        $secret = $_POST['secret'] ?? '';
        $test_mode = isset($_POST['test_mode']) ? (int)$_POST['test_mode'] : 0;

        if (empty($gateway)) {
            echo json_encode(['success' => false, 'message' => 'Gateway type is required.']);
            exit;
        }

        if ($gateway === 'paypal') {
            if (empty($client_id)) {
                echo json_encode(['success' => false, 'message' => 'PayPal Email address is required.']);
                exit;
            }
            if (filter_var($client_id, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => true, 'message' => 'PayPal Email address is valid.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid PayPal Email address.']);
            }
            exit;
        }

        if ($gateway === 'stripe') {
            if (empty($client_id)) {
                echo json_encode(['success' => false, 'message' => 'Publishable Key is required.']);
                exit;
            }
            if (empty($secret)) {
                echo json_encode(['success' => false, 'message' => 'Secret Key is required.']);
                exit;
            }

            // 1. Validate mode matching for Publishable Key
            $isTestPub = (strpos($client_id, 'pk_test_') === 0);
            if ($test_mode && !$isTestPub) {
                echo json_encode(['success' => false, 'message' => 'Stripe is set to Test Mode, but a Live Publishable Key was provided.']);
                exit;
            }
            if (!$test_mode && $isTestPub) {
                echo json_encode(['success' => false, 'message' => 'Stripe is set to Live Mode, but a Test Publishable Key was provided.']);
                exit;
            }

            // 2. Verify Stripe Publishable Key via Stripe API tokens endpoint
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/tokens');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $client_id
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $pubResponse = curl_exec($ch);
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);
                echo json_encode(['success' => false, 'message' => 'Stripe Publishable Key connection failed: ' . $error_msg]);
                exit;
            }
            $pubHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $pubResData = json_decode($pubResponse, true);
            // If the key is invalid, Stripe returns 401 Unauthorized
            if ($pubHttpCode === 401) {
                $pubErrMsg = $pubResData['error']['message'] ?? 'Invalid Publishable Key.';
                echo json_encode(['success' => false, 'message' => 'Stripe Publishable Key verification failed: ' . $pubErrMsg]);
                exit;
            }

            // 3. Validate mode matching for Secret Key
            $isTestKey = (strpos($secret, 'sk_test_') === 0 || strpos($secret, 'rk_test_') === 0);
            if ($test_mode && !$isTestKey) {
                echo json_encode(['success' => false, 'message' => 'Stripe is set to Test Mode, but a Live Secret Key was provided.']);
                exit;
            }
            if (!$test_mode && $isTestKey) {
                echo json_encode(['success' => false, 'message' => 'Stripe is set to Live Mode, but a Test Secret Key was provided.']);
                exit;
            }

            // 4. Verify Stripe Secret Key via Stripe API balance endpoint
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/balance');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $secret
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);
                echo json_encode(['success' => false, 'message' => 'Stripe Secret Key connection failed: ' . $error_msg]);
                exit;
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resData = json_decode($response, true);
            if ($httpCode === 200) {
                echo json_encode(['success' => true, 'message' => 'Stripe credentials (both Publishable and Secret Keys) are valid and connection was successful!']);
            } else {
                $errMsg = $resData['error']['message'] ?? 'Invalid Secret Key or API request failed.';
                echo json_encode(['success' => false, 'message' => 'Stripe Secret Key verification failed: ' . $errMsg]);
            }
            exit;
        }

        if ($gateway === 'paypalrest') {
            if (empty($client_id) || empty($secret)) {
                echo json_encode(['success' => false, 'message' => 'Client ID and Secret are required.']);
                exit;
            }

            $url = $test_mode ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token' : 'https://api-m.paypal.com/v1/oauth2/token';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Accept-Language: en_US',
                'Authorization: Basic ' . base64_encode($client_id . ':' . $secret)
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);
                echo json_encode(['success' => false, 'message' => 'PayPal REST connection failed: ' . $error_msg]);
                exit;
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resData = json_decode($response, true);
            if ($httpCode === 200 && !empty($resData['access_token'])) {
                echo json_encode(['success' => true, 'message' => 'PayPal REST credentials are valid and connection was successful!']);
            } else {
                $errMsg = $resData['error_description'] ?? $resData['message'] ?? 'Invalid Client ID or Secret.';
                echo json_encode(['success' => false, 'message' => 'PayPal REST verification failed: ' . $errMsg]);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Credential verification is not supported for ' . htmlspecialchars($gateway) . ' gateway.']);
        exit;
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
        $output .= '<form method="post" action="' . $modulelink . '&action=save" enctype="multipart/form-data" novalidate>' . generate_token() . '' . generate_token() . '';
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
     * Search unassigned products, addons, domains, or bundles for Services Pricing Select2 dropdowns
     */
    public function search_unassigned_pricing_items($vars)
    {
        $type = $_REQUEST['type'] ?? 'product';
        $brandId = (int)($_REQUEST['brand_id'] ?? 0);
        $q = $_REQUEST['q'] ?? '';

        $results = [];

        try {
            $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
            $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
            $assignedIds = [];
            if (isset($pricingOverrides[$type . 's']) && is_array($pricingOverrides[$type . 's'])) {
                $assignedIds = array_keys($pricingOverrides[$type . 's']);
            }

            if ($type == 'product') {
                $query = Capsule::table('tblproducts');
                if (!empty($assignedIds)) {
                    $query->whereNotIn('id', $assignedIds);
                }
                if ($q !== '') {
                    $query->where(function($sub) use ($q) {
                        $sub->where('id', '=', $q)
                            ->orWhere('name', 'like', '%' . $q . '%');
                    });
                }
                $products = $query->orderBy('name', 'asc')->limit(50)->get(['id', 'name']);
                foreach ($products as $p) {
                    $results[] = ['id' => $p->id, 'text' => '#' . $p->id . ' ' . $p->name];
                }
            } elseif ($type == 'addon') {
                $query = Capsule::table('tbladdons');
                if (!empty($assignedIds)) {
                    $query->whereNotIn('id', $assignedIds);
                }
                if ($q !== '') {
                    $query->where(function($sub) use ($q) {
                        $sub->where('id', '=', $q)
                            ->orWhere('name', 'like', '%' . $q . '%');
                    });
                }
                $addons = $query->orderBy('name', 'asc')->limit(50)->get(['id', 'name']);
                foreach ($addons as $addon) {
                    $results[] = ['id' => $addon->id, 'text' => '#' . $addon->id . ' ' . $addon->name];
                }
            } elseif ($type == 'domain') {
                $query = Capsule::table('tbldomainpricing');
                if (!empty($assignedIds)) {
                    $query->whereNotIn('id', $assignedIds);
                }
                if ($q !== '') {
                    $query->where('extension', 'like', '%' . $q . '%');
                }
                $domains = $query->orderBy('extension', 'asc')->limit(50)->get(['id', 'extension']);
                foreach ($domains as $domain) {
                    $results[] = ['id' => $domain->id, 'text' => $domain->extension];
                }
            } elseif ($type == 'bundle') {
                $query = Capsule::table('tblbundles');
                if (!empty($assignedIds)) {
                    $query->whereNotIn('id', $assignedIds);
                }
                if ($q !== '') {
                    $query->where(function($sub) use ($q) {
                        $sub->where('id', '=', $q)
                            ->orWhere('name', 'like', '%' . $q . '%');
                    });
                }
                $bundles = $query->orderBy('name', 'asc')->limit(50)->get(['id', 'name']);
                foreach ($bundles as $bundle) {
                    $results[] = ['id' => $bundle->id, 'text' => '#' . $bundle->id . ' ' . $bundle->name];
                }
            }
        } catch (\Exception $e) {}

        echo json_encode(['results' => $results]);
        exit;
    }

    /**
     * Assign a single product, addon, domain or bundle to the brand's pricing overrides
     */
    public function assign_pricing_item($vars)
    {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $type = $_POST['type'] ?? 'product';
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($brandId > 0 && $itemId > 0) {
            try {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
                if ($brand) {
                    $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
                    $key = $type . 's';
                    if (!isset($pricingOverrides[$key])) {
                        $pricingOverrides[$key] = [];
                    }
                    $pricingOverrides[$key][$itemId] = [
                        'enabled' => true,
                        'pricing' => []
                    ];

                    Capsule::table('mod_multibrand_brands')
                        ->where('id', $brandId)
                        ->update(['pricing_overrides' => json_encode($pricingOverrides)]);
                }
            } catch (\Exception $e) {}
        }

        header("Location: " . $vars['modulelink'] . "&action=edit&id=" . $brandId . "#price-" . $type . "s");
        exit;
    }

    /**
     * Unlink/remove a single product, addon, domain or bundle override relation
     */
    public function unlink_pricing_item($vars)
    {
        $brandId = (int)($_GET['brand_id'] ?? 0);
        $type = $_GET['type'] ?? 'product';
        $itemId = (int)($_GET['item_id'] ?? 0);

        if ($brandId > 0 && $itemId > 0) {
            try {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
                if ($brand) {
                    $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
                    $key = $type . 's';
                    if (isset($pricingOverrides[$key][$itemId])) {
                        unset($pricingOverrides[$key][$itemId]);
                    }

                    Capsule::table('mod_multibrand_brands')
                        ->where('id', $brandId)
                        ->update(['pricing_overrides' => json_encode($pricingOverrides)]);
                }
            } catch (\Exception $e) {}
        }

        header("Location: " . $vars['modulelink'] . "&action=edit&id=" . $brandId . "#price-" . $type . "s");
        exit;
    }

    /**
     * Bulk-assign all configured WHMCS templates of a type to this brand's pricing overrides
     */
    public function bulk_add_pricing_items($vars)
    {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $type = $_POST['type'] ?? 'product';

        if ($brandId > 0) {
            try {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
                if ($brand) {
                    $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
                    $key = $type . 's';
                    if (!isset($pricingOverrides[$key])) {
                        $pricingOverrides[$key] = [];
                    }

                    if ($type == 'product') {
                        $items = Capsule::table('tblproducts')->pluck('id')->toArray();
                    } elseif ($type == 'addon') {
                        $items = Capsule::table('tbladdons')->pluck('id')->toArray();
                    } elseif ($type == 'domain') {
                        $items = Capsule::table('tbldomainpricing')->pluck('id')->toArray();
                    } elseif ($type == 'bundle') {
                        $items = Capsule::table('tblbundles')->pluck('id')->toArray();
                    } else {
                        $items = [];
                    }

                    foreach ($items as $id) {
                        if (!isset($pricingOverrides[$key][$id])) {
                            $pricingOverrides[$key][$id] = [
                                'enabled' => true,
                                'pricing' => []
                            ];
                        }
                    }

                    Capsule::table('mod_multibrand_brands')
                        ->where('id', $brandId)
                        ->update(['pricing_overrides' => json_encode($pricingOverrides)]);
                }
            } catch (\Exception $e) {}
        }

        header("Location: " . $vars['modulelink'] . "&action=edit&id=" . $brandId . "#price-" . $type . "s");
        exit;
    }

    /**
     * Save customized pricing overrides for products, addons, domains or bundles
     */
    public function save_pricing_override($vars)
    {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        $relid = (int)($_POST['relid'] ?? 0);
        $pricing = $_POST['pricing'] ?? [];

        if ($brandId > 0 && !empty($type) && $relid > 0) {
            try {
                $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
                if ($brand) {
                    $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
                    $key = $type . 's';
                    if (!isset($pricingOverrides[$key])) {
                        $pricingOverrides[$key] = [];
                    }
                    if (!isset($pricingOverrides[$key][$relid])) {
                        $pricingOverrides[$key][$relid] = ['enabled' => true];
                    }

                    // Format rates cleanly to decimal
                    $formattedPricing = [];
                    foreach ($pricing as $currId => $cycleRates) {
                        $formattedPricing[$currId] = [];
                        foreach ($cycleRates as $cycleName => $rateValue) {
                            $formattedPricing[$currId][$cycleName] = number_format(floatval($rateValue), 2, '.', '');
                        }
                    }

                    $pricingOverrides[$key][$relid]['pricing'] = $formattedPricing;

                    Capsule::table('mod_multibrand_brands')
                        ->where('id', $brandId)
                        ->update(['pricing_overrides' => json_encode($pricingOverrides)]);
                }
            } catch (\Exception $e) {}
        }

        header("Location: " . $vars['modulelink'] . "&action=edit&id=" . $brandId . "#price-" . $type . "s");
        exit;
    }

    /**
     * AJAX endpoint to return standard WHMCS base prices + any active brand overrides
     */
    public function get_pricing_override_ajax($vars)
    {
        $brandId = (int)($_REQUEST['brand_id'] ?? 0);
        $type = $_REQUEST['type'] ?? 'product';
        $relid = (int)($_REQUEST['relid'] ?? 0);

        $response = [
            'paymenttype' => 'recurring',
            'base' => [],
            'overrides' => []
        ];

        try {
            // Determine payment type
            if ($type == 'product') {
                $prod = Capsule::table('tblproducts')->where('id', $relid)->first();
                if ($prod) {
                    $response['paymenttype'] = strtolower($prod->paytype); // 'free', 'onetime', 'recurring'
                }
            } elseif ($type == 'addon') {
                $addon = Capsule::table('tbladdons')->where('id', $relid)->first();
                if ($addon) {
                    $response['paymenttype'] = 'addon';
                }
            } elseif ($type == 'domain') {
                $response['paymenttype'] = 'domain';
            } elseif ($type == 'bundle') {
                $response['paymenttype'] = 'bundle';
            }

            // Fetch base standard WHMCS pricing from tblpricing
            if ($type == 'domain') {
                $domainPricingTypes = ['domainregister', 'domaintransfer', 'domainrenew'];
                $basePrices = Capsule::table('tblpricing')
                    ->whereIn('type', $domainPricingTypes)
                    ->where('relid', $relid)
                    ->get();
                
                // Group by currency
                foreach ($basePrices as $bp) {
                    $currId = $bp->currency;
                    if (!isset($response['base'][$currId])) {
                        $response['base'][$currId] = [];
                    }
                    
                    // Map registration/transfer/renewal prices by year
                    $cycles = [
                        'msetupfee' => 1,
                        'qsetupfee' => 2,
                        'ssetupfee' => 3,
                        'asetupfee' => 4,
                        'bsetupfee' => 5,
                        'tsetupfee' => 10
                    ];
                    foreach ($cycles as $col => $year) {
                        if ($bp->type == 'domainregister') {
                            $response['base'][$currId]['register' . $year] = $bp->$col;
                        } elseif ($bp->type == 'domaintransfer') {
                            $response['base'][$currId]['transfer' . $year] = $bp->$col;
                        } elseif ($bp->type == 'domainrenew') {
                            $response['base'][$currId]['renew' . $year] = $bp->$col;
                        }
                    }
                }
            } else {
                if ($type == 'bundle') {
                    $bundle = Capsule::table('tblbundles')->where('id', $relid)->first();
                    $baseDisplayPrice = $bundle ? (float)$bundle->displayprice : 0.00;
                    
                    $currencies = Capsule::table('tblcurrencies')->get();
                    foreach ($currencies as $curr) {
                        $response['base'][$curr->id] = [
                            'displayprice' => $baseDisplayPrice
                        ];
                    }
                } else {
                    $pricingType = $type == 'product' ? 'product' : 'addon';
                    $basePrices = Capsule::table('tblpricing')
                        ->where('type', $pricingType)
                        ->where('relid', $relid)
                        ->get();
                    
                    foreach ($basePrices as $bp) {
                        $currId = $bp->currency;
                        $response['base'][$currId] = (array)$bp;
                    }
                }
            }

            // Fetch brand overrides
            $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
            if ($brand) {
                $pricingOverrides = json_decode($brand->pricing_overrides ?? '', true) ?: [];
                $key = $type . 's';
                if (isset($pricingOverrides[$key][$relid]['pricing'])) {
                    $response['overrides'] = $pricingOverrides[$key][$relid]['pricing'];
                }
            }
        } catch (\Exception $e) {}

        echo json_encode($response);
        exit;
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
        return $this->edit($vars);
    }

    

    

    

    

    

    /**
     * Bulk unlink multiple clients from a brand from Brand Edit page
     */
    public function bulk_unlink_client_from_edit($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $clientIds = isset($_POST['client_ids']) ? array_map('intval', $_POST['client_ids']) : [];

        if (!empty($clientIds)) {
            try {
                Capsule::table('mod_multibrand_client_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('client_id', $clientIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected client brand relationships deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected client relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No clients selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        return $this->edit($vars);
    }

    /**
     * Save client relation from Assign Client Modal
     */
    public function save_client_relation_from_modal($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $type = $_POST['type']; // 'single', 'group', 'all'
        $addAllItems = isset($_POST['add_all_items']) ? 1 : 0;
        $clientId = isset($_POST['client_id']) ? (int) $_POST['client_id'] : 0;
        $groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit'; // 'edit' or 'relations'

        $targetClientIds = [];

        try {
            if ($type == 'single') {
                if ($clientId > 0) {
                    $targetClientIds[] = $clientId;
                }
            } elseif ($type == 'group') {
                if ($groupId > 0) {
                    $targetClientIds = Capsule::table('tblclients')
                        ->where('groupid', $groupId)
                        ->pluck('id')
                        ->toArray();
                }
            } elseif ($type == 'all') {
                $targetClientIds = Capsule::table('tblclients')
                    ->pluck('id')
                    ->toArray();
            }

            if (!empty($targetClientIds)) {
                $assignedCount = 0;
                foreach ($targetClientIds as $cId) {
                    $exists = Capsule::table('mod_multibrand_client_brands')
                        ->where('client_id', $cId)
                        ->where('brand_id', $brandId)
                        ->exists();

                    if (!$exists) {
                        Capsule::table('mod_multibrand_client_brands')->insert([
                            'client_id' => $cId,
                            'brand_id' => $brandId,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $assignedCount++;
                    }

                    // If add all items is enabled, assign services, invoices, tickets, orders
                    if ($addAllItems) {
                        // Services
                        $services = Capsule::table('tblhosting')->where('userid', $cId)->get();
                        foreach ($services as $srv) {
                            try {
                                Capsule::table('mod_multibrand_service_brands')->updateOrInsert(
                                    ['service_id' => $srv->id],
                                    ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                                );
                            } catch (\Exception $ex) {}
                        }

                        // Invoices
                        $invoices = Capsule::table('tblinvoices')->where('userid', $cId)->get();
                        foreach ($invoices as $inv) {
                            try {
                                Capsule::table('mod_multibrand_invoice_brands')->updateOrInsert(
                                    ['invoice_id' => $inv->id],
                                    ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                                );
                            } catch (\Exception $ex) {}
                        }

                        // Tickets
                        $tickets = Capsule::table('tbltickets')->where('userid', $cId)->get();
                        foreach ($tickets as $tkt) {
                            try {
                                Capsule::table('mod_multibrand_ticket_brands')->updateOrInsert(
                                    ['ticket_id' => $tkt->id],
                                    ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                                );
                            } catch (\Exception $ex) {}
                        }

                        // Orders
                        $orders = Capsule::table('tblorders')->where('userid', $cId)->get();
                        foreach ($orders as $ord) {
                            try {
                                Capsule::table('mod_multibrand_order_brands')->updateOrInsert(
                                    ['order_id' => $ord->id],
                                    ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                                );
                            } catch (\Exception $ex) {}
                        }
                    }
                }

                echo '<div class="alert alert-success">Assigned ' . $assignedCount . ' client(s) to brand successfully.</div>';
            } else {
                echo '<div class="alert alert-warning">No clients were found to assign.</div>';
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error assigning client(s): ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect == 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Migrate client assignment to target brand
     */
    public function migrate_client_from_modal($vars)
    {
        $currentBrandId = (int) $_POST['current_brand_id'];
        $targetBrandId = (int) $_POST['target_brand_id'];
        $clientId = (int) $_POST['client_id'];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit'; // 'edit' or 'relations'

        if ($clientId > 0 && $targetBrandId > 0 && $currentBrandId > 0) {
            try {
                // Delete current relationship
                Capsule::table('mod_multibrand_client_brands')
                    ->where('client_id', $clientId)
                    ->where('brand_id', $currentBrandId)
                    ->delete();

                // Insert target relationship
                Capsule::table('mod_multibrand_client_brands')->updateOrInsert(
                    ['client_id' => $clientId, 'brand_id' => $targetBrandId],
                    ['created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                );

                // Migrate associated services, invoices, tickets, and orders to target brand
                // Services
                $services = Capsule::table('tblhosting')->where('userid', $clientId)->pluck('id')->toArray();
                if (!empty($services)) {
                    Capsule::table('mod_multibrand_service_brands')
                        ->whereIn('service_id', $services)
                        ->where('brand_id', $currentBrandId)
                        ->update(['brand_id' => $targetBrandId]);
                }

                // Invoices
                $invoices = Capsule::table('tblinvoices')->where('userid', $clientId)->pluck('id')->toArray();
                if (!empty($invoices)) {
                    Capsule::table('mod_multibrand_invoice_brands')
                        ->whereIn('invoice_id', $invoices)
                        ->where('brand_id', $currentBrandId)
                        ->update(['brand_id' => $targetBrandId]);
                }

                // Tickets
                $tickets = Capsule::table('tbltickets')->where('userid', $clientId)->pluck('id')->toArray();
                if (!empty($tickets)) {
                    Capsule::table('mod_multibrand_ticket_brands')
                        ->whereIn('ticket_id', $tickets)
                        ->where('brand_id', $currentBrandId)
                        ->update(['brand_id' => $targetBrandId]);
                }

                // Orders
                $orders = Capsule::table('tblorders')->where('userid', $clientId)->pluck('id')->toArray();
                if (!empty($orders)) {
                    Capsule::table('mod_multibrand_order_brands')
                        ->whereIn('order_id', $orders)
                        ->where('brand_id', $currentBrandId)
                        ->update(['brand_id' => $targetBrandId]);
                }

                echo '<div class="alert alert-success">Client migrated to target brand successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error migrating client: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Invalid migration request parameters.</div>';
        }

        $_REQUEST['id'] = $currentBrandId;
        if ($redirect == 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Search clients dynamically via AJAX for Select2
     */
    public function search_clients($vars)
    {
        $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
        $brandId = isset($_REQUEST['brand_id']) ? (int) $_REQUEST['brand_id'] : 0;

        $assignedClientIds = [];
        if ($brandId > 0) {
            $assignedClientIds = Capsule::table('mod_multibrand_client_brands')
                ->where('brand_id', $brandId)
                ->pluck('client_id')
                ->toArray();
        }

        $query = Capsule::table('tblclients');
        
        if ($q !== '') {
            $query->where(function($queryPart) use ($q) {
                $queryPart->where('firstname', 'like', '%' . $q . '%')
                          ->orWhere('lastname', 'like', '%' . $q . '%')
                          ->orWhere('email', 'like', '%' . $q . '%')
                          ->orWhere('companyname', 'like', '%' . $q . '%')
                          ->orWhere('id', '=', $q);
            });
            $query->orderBy('firstname', 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        if (!empty($assignedClientIds)) {
            $query->whereNotIn('id', $assignedClientIds);
        }

        $clients = $query->select('id', 'firstname', 'lastname', 'companyname', 'email')
            ->limit(50)
            ->get();

        $results = [];
        foreach ($clients as $client) {
            $text = '#' . $client->id . ' ' . $client->firstname . ' ' . $client->lastname;
            if ($client->companyname) {
                $text .= ' (' . $client->companyname . ')';
            }
            $text .= ' - ' . $client->email;

            $results[] = [
                'id' => $client->id,
                'text' => $text
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
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

    /**
     * Search unassigned services, addons, or domains for Select2
     */
    public function search_unassigned_services($vars)
    {
        $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'hosting';
        $brandId = isset($_REQUEST['brand_id']) ? (int) $_REQUEST['brand_id'] : 0;

        $results = [];

        if ($type == 'hosting') {
            $assignedServiceIds = Capsule::table('mod_multibrand_service_brands')->pluck('service_id')->toArray();
            $query = Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->join('tblclients', 'tblhosting.userid', '=', 'tblclients.id');
            
            if (!empty($assignedServiceIds)) {
                $query->whereNotIn('tblhosting.id', $assignedServiceIds);
            }
            if ($q !== '') {
                $query->where(function($qp) use ($q) {
                    $qp->where('tblhosting.id', '=', $q)
                       ->orWhere('tblproducts.name', 'like', '%' . $q . '%')
                       ->orWhere('tblhosting.domain', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.lastname', 'like', '%' . $q . '%');
                });
            }
            $services = $query->select('tblhosting.id', 'tblproducts.name as product_name', 'tblhosting.domain', 'tblclients.firstname', 'tblclients.lastname')
                ->limit(50)
                ->get();
                
            foreach ($services as $svc) {
                $results[] = [
                    'id' => $svc->id,
                    'text' => '#' . $svc->id . ' ' . $svc->product_name . ' (' . ($svc->domain ?: 'no domain') . ') - ' . $svc->firstname . ' ' . $svc->lastname
                ];
            }
        } elseif ($type == 'addon') {
            $assignedAddonIds = Capsule::table('mod_multibrand_addon_brands')->pluck('addon_id')->toArray();
            $query = Capsule::table('tblhostingaddons')
                ->leftJoin('tblhosting', 'tblhostingaddons.hostingid', '=', 'tblhosting.id')
                ->join('tblclients', 'tblhostingaddons.userid', '=', 'tblclients.id')
                ->leftJoin('tbladdons', 'tblhostingaddons.addonid', '=', 'tbladdons.id');

            if (!empty($assignedAddonIds)) {
                $query->whereNotIn('tblhostingaddons.id', $assignedAddonIds);
            }
            if ($q !== '') {
                $query->where(function($qp) use ($q) {
                    $qp->where('tblhostingaddons.id', '=', $q)
                       ->orWhere('tblhostingaddons.name', 'like', '%' . $q . '%')
                       ->orWhere('tbladdons.name', 'like', '%' . $q . '%')
                       ->orWhere('tblhosting.domain', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.lastname', 'like', '%' . $q . '%');
                });
            }
            $addons = $query->select(
                'tblhostingaddons.id',
                'tblhostingaddons.name as custom_name',
                'tbladdons.name as addon_name',
                'tblhosting.domain as service_domain',
                'tblclients.firstname',
                'tblclients.lastname'
            )->limit(50)->get();

            foreach ($addons as $addon) {
                $name = $addon->custom_name ?: ($addon->addon_name ?: 'Addon');
                $results[] = [
                    'id' => $addon->id,
                    'text' => '#' . $addon->id . ' ' . $name . ' (' . ($addon->service_domain ?: 'no domain') . ') - ' . $addon->firstname . ' ' . $addon->lastname
                ];
            }
        } elseif ($type == 'domain') {
            $assignedDomainIds = Capsule::table('mod_multibrand_domain_brands')->pluck('domain_id')->toArray();
            $query = Capsule::table('tbldomains')
                ->join('tblclients', 'tbldomains.userid', '=', 'tblclients.id');

            if (!empty($assignedDomainIds)) {
                $query->whereNotIn('tbldomains.id', $assignedDomainIds);
            }
            if ($q !== '') {
                $query->where(function($qp) use ($q) {
                    $qp->where('tbldomains.id', '=', $q)
                       ->orWhere('tbldomains.domain', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                       ->orWhere('tblclients.lastname', 'like', '%' . $q . '%');
                });
            }
            $domains = $query->select('tbldomains.id', 'tbldomains.domain', 'tblclients.firstname', 'tblclients.lastname')
                ->limit(50)->get();

            foreach ($domains as $dom) {
                $results[] = [
                    'id' => $dom->id,
                    'text' => '#' . $dom->id . ' ' . $dom->domain . ' - ' . $dom->firstname . ' ' . $dom->lastname
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }

    /**
     * Add service relation from the Add Service Modal
     */
    public function add_service_relation_from_modal($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $type = $_POST['type'];
        $serviceId = (int) $_POST['service_id'];

        if ($brandId > 0 && $serviceId > 0) {
            try {
                if ($type == 'hosting') {
                    Capsule::table('mod_multibrand_service_brands')->updateOrInsert(
                        ['service_id' => $serviceId],
                        ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                    );
                } elseif ($type == 'addon') {
                    Capsule::table('mod_multibrand_addon_brands')->updateOrInsert(
                        ['addon_id' => $serviceId],
                        ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                    );
                } elseif ($type == 'domain') {
                    Capsule::table('mod_multibrand_domain_brands')->updateOrInsert(
                        ['domain_id' => $serviceId],
                        ['brand_id' => $brandId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
                    );
                }
                echo '<div class="alert alert-success">Service relation added successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error adding service relation: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single service relation
     */
    public function unlink_service_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $serviceId = (int) $_REQUEST['service_id'];

        try {
            Capsule::table('mod_multibrand_service_brands')
                ->where('service_id', $serviceId)
                ->where('brand_id', $brandId)
                ->delete();
            echo '<div class="alert alert-success">Service relation deleted successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error deleting relation: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single addon relation
     */
    public function unlink_addon_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $addonId = (int) $_REQUEST['addon_id'];

        try {
            Capsule::table('mod_multibrand_addon_brands')
                ->where('addon_id', $addonId)
                ->where('brand_id', $brandId)
                ->delete();
            echo '<div class="alert alert-success">Addon relation deleted successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error deleting relation: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single domain relation
     */
    public function unlink_domain_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $domainId = (int) $_REQUEST['domain_id'];

        try {
            Capsule::table('mod_multibrand_domain_brands')
                ->where('domain_id', $domainId)
                ->where('brand_id', $brandId)
                ->delete();
            echo '<div class="alert alert-success">Domain relation deleted successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error deleting relation: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete service relations
     */
    public function bulk_unlink_services($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $serviceIds = isset($_POST['service_ids']) ? array_map('intval', $_POST['service_ids']) : [];

        if (!empty($serviceIds)) {
            try {
                Capsule::table('mod_multibrand_service_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('service_id', $serviceIds)
                    ->delete();
                echo '<div class="alert alert-success">Selected service relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No items selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete addon relations
     */
    public function bulk_unlink_addons($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $addonIds = isset($_POST['addon_ids']) ? array_map('intval', $_POST['addon_ids']) : [];

        if (!empty($addonIds)) {
            try {
                Capsule::table('mod_multibrand_addon_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('addon_id', $addonIds)
                    ->delete();
                echo '<div class="alert alert-success">Selected addon relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No items selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete domain relations
     */
    public function bulk_unlink_domains($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $domainIds = isset($_POST['domain_ids']) ? array_map('intval', $_POST['domain_ids']) : [];

        if (!empty($domainIds)) {
            try {
                Capsule::table('mod_multibrand_domain_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('domain_id', $domainIds)
                    ->delete();
                echo '<div class="alert alert-success">Selected domain relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No items selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : '';
        if ($redirect === 'edit') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Search unassigned invoices dynamically via AJAX for Select2
     */
    public function search_unassigned_invoices($vars)
    {
        $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

        // Get already assigned invoice IDs
        $assignedInvoiceIds = Capsule::table('mod_multibrand_invoice_brands')->pluck('invoice_id')->toArray();

        $query = Capsule::table('tblinvoices')
            ->join('tblclients', 'tblinvoices.userid', '=', 'tblclients.id')
            ->leftJoin('tblcurrencies', 'tblclients.currency', '=', 'tblcurrencies.id');

        if (!empty($assignedInvoiceIds)) {
            $query->whereNotIn('tblinvoices.id', $assignedInvoiceIds);
        }

        if ($q !== '') {
            $query->where(function($qp) use ($q) {
                $qp->where('tblinvoices.id', '=', $q)
                   ->orWhere('tblinvoices.invoicenum', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.lastname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.companyname', 'like', '%' . $q . '%');
            });
        }

        $invoices = $query->select(
            'tblinvoices.id',
            'tblinvoices.invoicenum',
            'tblinvoices.total',
            'tblclients.firstname',
            'tblclients.lastname',
            'tblcurrencies.code as currency_code'
        )->orderBy('tblinvoices.id', 'desc')->limit(50)->get();

        $results = [];
        foreach ($invoices as $inv) {
            $num = $inv->invoicenum ?: $inv->id;
            $text = '#' . $inv->id . ' ' . $num . ' - ' . $inv->firstname . ' ' . $inv->lastname . ' - Total:' . $inv->total . ' ' . $inv->currency_code;
            $results[] = [
                'id' => $inv->id,
                'text' => $text
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }

    /**
     * Save new brand relation for an invoice
     */
    public function save_invoice_relation($vars)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->index($vars);
        }

        $brandId = (int) $_POST['brand_id'];
        $invoiceId = (int) $_POST['invoice_id'];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if ($brandId > 0 && $invoiceId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_invoice_brands')
                    ->where('invoice_id', $invoiceId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_invoice_brands')->insert([
                        'invoice_id' => $invoiceId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Invoice relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Invoice is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning invoice: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single invoice relation
     */
    public function unlink_invoice_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $invoiceId = (int) $_REQUEST['invoice_id'];
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : 'edit';

        try {
            Capsule::table('mod_multibrand_invoice_brands')
                ->where('invoice_id', $invoiceId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Invoice unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking invoice: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete invoice relations
     */
    public function bulk_unlink_invoices($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $invoiceIds = isset($_POST['invoice_ids']) ? array_map('intval', $_POST['invoice_ids']) : [];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if (!empty($invoiceIds)) {
            try {
                Capsule::table('mod_multibrand_invoice_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('invoice_id', $invoiceIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected invoice relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected invoice relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No invoices selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Search unassigned quotes dynamically via AJAX for Select2
     */
    public function search_unassigned_quotes($vars)
    {
        $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

        // Get already assigned quote IDs
        $assignedQuoteIds = Capsule::table('mod_multibrand_quote_brands')->pluck('quote_id')->toArray();

        $query = Capsule::table('tblquotes')
            ->leftJoin('tblclients', 'tblquotes.userid', '=', 'tblclients.id');

        if (!empty($assignedQuoteIds)) {
            $query->whereNotIn('tblquotes.id', $assignedQuoteIds);
        }

        if ($q !== '') {
            $query->where(function($qp) use ($q) {
                $qp->where('tblquotes.id', '=', $q)
                   ->orWhere('tblquotes.subject', 'like', '%' . $q . '%')
                   ->orWhere('tblquotes.stage', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.lastname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.companyname', 'like', '%' . $q . '%')
                   ->orWhere('tblquotes.firstname', 'like', '%' . $q . '%')
                   ->orWhere('tblquotes.lastname', 'like', '%' . $q . '%')
                   ->orWhere('tblquotes.companyname', 'like', '%' . $q . '%');
            });
        }

        $quotes = $query->select(
            'tblquotes.id',
            'tblquotes.subject',
            'tblquotes.stage',
            'tblquotes.total',
            Capsule::raw('COALESCE(tblclients.firstname, tblquotes.firstname) as firstname'),
            Capsule::raw('COALESCE(tblclients.lastname, tblquotes.lastname) as lastname')
        )->orderBy('tblquotes.id', 'desc')->limit(50)->get();

        $results = [];
        foreach ($quotes as $quote) {
            $clientName = trim($quote->firstname . ' ' . $quote->lastname);
            $clientText = $clientName ? ' - ' . $clientName : '';
            $text = '#' . $quote->id . ' [' . ($quote->stage ?: 'Draft') . ']' . $clientText . ' - ' . ($quote->subject ?: '(No Subject)') . ' - Total: ' . $quote->total;
            $results[] = [
                'id' => $quote->id,
                'text' => $text
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }

    /**
     * Save new brand relation for a quote
     */
    public function save_quote_relation($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $quoteId = (int) $_POST['quote_id'];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if ($brandId > 0 && $quoteId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_quote_brands')
                    ->where('quote_id', $quoteId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_quote_brands')->insert([
                        'quote_id' => $quoteId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Quote relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Quote is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning quote: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single quote relation
     */
    public function unlink_quote_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $quoteId = (int) $_REQUEST['quote_id'];
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : 'edit';

        try {
            Capsule::table('mod_multibrand_quote_brands')
                ->where('quote_id', $quoteId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Quote unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking quote: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete quote relations
     */
    public function bulk_unlink_quotes($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $quoteIds = isset($_POST['quote_ids']) ? array_map('intval', $_POST['quote_ids']) : [];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if (!empty($quoteIds)) {
            try {
                Capsule::table('mod_multibrand_quote_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('quote_id', $quoteIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected quote relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected quote relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No quotes selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Search unassigned tickets dynamically via AJAX for Select2
     */
    public function search_unassigned_tickets($vars)
    {
        $q = isset($_REQUEST['q']) ? $_REQUEST['q'] : '';

        // Get already assigned ticket IDs
        $assignedTicketIds = Capsule::table('mod_multibrand_ticket_brands')->pluck('ticket_id')->toArray();

        $query = Capsule::table('tbltickets')
            ->join('tblclients', 'tbltickets.userid', '=', 'tblclients.id');

        if (!empty($assignedTicketIds)) {
            $query->whereNotIn('tbltickets.id', $assignedTicketIds);
        }

        if ($q !== '') {
            $query->where(function($qp) use ($q) {
                $qp->where('tbltickets.id', '=', $q)
                   ->orWhere('tbltickets.tid', 'like', '%' . $q . '%')
                   ->orWhere('tbltickets.title', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.firstname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.lastname', 'like', '%' . $q . '%')
                   ->orWhere('tblclients.companyname', 'like', '%' . $q . '%');
            });
        }

        $tickets = $query->select(
            'tbltickets.id',
            'tbltickets.tid',
            'tbltickets.title',
            'tblclients.firstname',
            'tblclients.lastname'
        )->orderBy('tbltickets.id', 'desc')->limit(50)->get();

        $results = [];
        foreach ($tickets as $t) {
            $ticketNum = $t->tid ?: $t->id;
            $text = '#' . $ticketNum . ' - ' . $t->title . ' (' . $t->firstname . ' ' . $t->lastname . ')';
            $results[] = [
                'id' => $t->id,
                'text' => $text
            ];
        }

        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }

    /**
     * Save new brand relation for a ticket
     */
    public function save_ticket_relation($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $ticketId = (int) $_POST['ticket_id'];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if ($brandId > 0 && $ticketId > 0) {
            try {
                $exists = Capsule::table('mod_multibrand_ticket_brands')
                    ->where('ticket_id', $ticketId)
                    ->where('brand_id', $brandId)
                    ->exists();

                if (!$exists) {
                    Capsule::table('mod_multibrand_ticket_brands')->insert([
                        'ticket_id' => $ticketId,
                        'brand_id' => $brandId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    echo '<div class="alert alert-success">Ticket relation added successfully.</div>';
                } else {
                    echo '<div class="alert alert-warning">Ticket is already assigned to this brand.</div>';
                }
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error assigning ticket: ' . $e->getMessage() . '</div>';
            }
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Delete a single ticket relation
     */
    public function unlink_ticket_relation($vars)
    {
        $brandId = (int) $_REQUEST['brand_id'];
        $ticketId = (int) $_REQUEST['ticket_id'];
        $redirect = isset($_REQUEST['redirect']) ? $_REQUEST['redirect'] : 'edit';

        try {
            Capsule::table('mod_multibrand_ticket_brands')
                ->where('ticket_id', $ticketId)
                ->where('brand_id', $brandId)
                ->delete();

            echo '<div class="alert alert-success">Ticket unlinked from brand successfully.</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Error unlinking ticket: ' . $e->getMessage() . '</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * Bulk delete ticket relations
     */
    public function bulk_unlink_tickets($vars)
    {
        $brandId = (int) $_POST['brand_id'];
        $ticketIds = isset($_POST['ticket_ids']) ? array_map('intval', $_POST['ticket_ids']) : [];
        $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'edit';

        if (!empty($ticketIds)) {
            try {
                Capsule::table('mod_multibrand_ticket_brands')
                    ->where('brand_id', $brandId)
                    ->whereIn('ticket_id', $ticketIds)
                    ->delete();

                echo '<div class="alert alert-success">Selected ticket relations deleted successfully.</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Error deleting selected ticket relations: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">No tickets selected.</div>';
        }

        $_REQUEST['id'] = $brandId;
        if ($redirect === 'relations') {
            return $this->edit($vars);
        }
        return $this->edit($vars);
    }

    /**
     * AJAX endpoint to render the dynamic multi-lingual template editor modal
     */
    public function get_email_template_editor_ajax($vars)
    {
        $brandId = (int)$_POST['brand_id'];
        $templateName = $_POST['template_name'];
        
        $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
        if (!$brand) {
            echo json_encode(['success' => false, 'message' => 'Brand not found.']);
            exit;
        }
        
        $defaultTemplate = Capsule::table('tblemailtemplates')
            ->where('language', '')
            ->where('name', $templateName)
            ->first();
            
        if (!$defaultTemplate) {
            echo json_encode(['success' => false, 'message' => 'Template not found.']);
            exit;
        }
        
        $branded = Capsule::table('mod_multibrand_email_templates')
            ->where('brand_id', $brandId)
            ->where('template_name', $templateName)
            ->first();
            
        $copyTo = $branded ? $branded->copy_to : '';
        $blindCopyTo = $branded ? $branded->blind_copy_to : '';
        
        $translationsData = [];
        if ($branded && !empty($branded->translations)) {
            $translationsData = json_decode(htmlspecialchars_decode($branded->translations), true);
        }
        
        // Ensure default is prefilled
        if (empty($translationsData['default'])) {
            $translationsData['default'] = [
                'subject' => $defaultTemplate->subject,
                'message' => $defaultTemplate->message
            ];
        }
        
        // Get all languages list
        $standardLangs = ['arabic', 'azerbaijani', 'catalan', 'chinese', 'croatian', 'czech', 'danish', 'dutch', 'english', 'estonian', 'farsi', 'french', 'german', 'hebrew', 'hungarian', 'italian', 'norwegian', 'persian', 'portuguese-br', 'portuguese-pt', 'romanian', 'russian', 'spanish', 'swedish', 'turkish', 'ukrainian'];
        $dbLanguages = Capsule::table('tblemailtemplates')
            ->where('language', '!=', '')
            ->distinct()
            ->pluck('language')
            ->toArray();
        $allLanguages = array_unique(array_merge($standardLangs, $dbLanguages));
        sort($allLanguages);
        
        // Merge fields
        $mergeFields = [
            'Client ID' => '{$client_id}',
            'First Name' => '{$client_first_name}',
            'Last Name' => '{$client_last_name}',
            'Company Name' => '{$client_company_name}',
            'Email Address' => '{$client_email}',
            'Address 1' => '{$client_address1}',
            'Address 2' => '{$client_address2}',
            'City' => '{$client_city}',
            'State/Region' => '{$client_state}',
            'Postcode' => '{$client_postcode}',
            'Country' => '{$client_country}',
            'Phone Number' => '{$client_phonenumber}',
            'Credit Balance' => '{$client_credit}'
        ];
        
        $type = strtolower($defaultTemplate->type);
        if ($type == 'product') {
            $mergeFields += [
                'Service ID' => '{$service_id}',
                'Product Name' => '{$service_product_name}',
                'Domain' => '{$service_domain}',
                'Username' => '{$service_username}',
                'Password' => '{$service_password}',
                'Billing Cycle' => '{$service_billing_cycle}'
            ];
        } else if ($type == 'domain') {
            $mergeFields += [
                'Domain Name' => '{$domain_name}',
                'Expiry Date' => '{$domain_expiry_date}',
                'Registrar' => '{$domain_registrar}',
                'Status' => '{$domain_status}'
            ];
        } else if ($type == 'support') {
            $mergeFields += [
                'Ticket ID' => '{$ticket_id}',
                'Subject' => '{$ticket_subject}',
                'Message' => '{$ticket_message}',
                'Status' => '{$ticket_status}'
            ];
        } else if ($type == 'invoice') {
            $mergeFields += [
                'Invoice ID' => '{$invoice_id}',
                'Invoice Number' => '{$invoice_num}',
                'Date Created' => '{$invoice_date_created}',
                'Date Due' => '{$invoice_date_due}',
                'Total' => '{$invoice_total}'
            ];
        }
        
        // Render tabs
        $tabsHtml = '';
        foreach ($translationsData as $lang => $tData) {
            $isActive = ($lang === 'default');
            $label = ucfirst($lang);
            $tabsHtml .= '<li class="' . ($isActive ? 'active' : '') . '"><a href="#" class="lang-tab-link" data-lang="' . htmlspecialchars($lang) . '" style="font-weight: bold; border-radius: 4px; padding: 8px 16px; border: none; margin-right: 5px;">' . htmlspecialchars($label) . '</a></li>';
        }
        
        // Language options
        $langOptions = '';
        foreach ($allLanguages as $lang) {
            if ($lang === 'english' || isset($translationsData[$lang])) continue;
            $langOptions .= '<option value="' . htmlspecialchars($lang) . '">' . htmlspecialchars(ucfirst($lang)) . '</option>';
        }
        
        // Merge fields helper list
        $mergeFieldsHtml = '';
        foreach ($mergeFields as $label => $token) {
            $mergeFieldsHtml .= '<div style="display: flex; justify-content: space-between; padding: 6px 10px; border-bottom: 1px solid #f0f0f0; font-size: 0.85em;">
                <span style="color: #666; font-weight: 500;">' . htmlspecialchars($label) . '</span>
                <a href="#" class="merge-field-token" data-token="' . htmlspecialchars($token) . '" style="font-family: Courier, monospace; font-weight: bold; color: #337ab7; text-decoration: none;">' . htmlspecialchars($token) . '</a>
            </div>';
        }
        
        $html = '
        <div class="modal-header" style="border-bottom: 1px solid #eee; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h4 class="modal-title" style="margin: 0; font-weight: bold; color: #333;"><i class="fas fa-envelope-open-text" style="margin-right: 8px; color: #337ab7;"></i> Edit Template: ' . htmlspecialchars($templateName) . '</h4>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="font-size: 1.5em; background: none; border: none; opacity: 0.5; outline: none;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px; font-family: inherit;">
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label style="font-weight: bold; color: #555; margin-bottom: 5px; font-size: 0.9em; display: block;">Copy To</label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="text" id="template-copy-to" class="form-control" value="' . htmlspecialchars($copyTo) . '" placeholder="e.g. billing@brand.com" style="flex: 1;">
                        <span style="font-size: 0.82em; color: #777; width: 220px; line-height: 1.3;">Enter email addresses separated by a comma.</span>
                    </div>
                </div>
                <div style="flex: 1;">
                    <label style="font-weight: bold; color: #555; margin-bottom: 5px; font-size: 0.9em; display: block;">Blind Copy To</label>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <input type="text" id="template-blind-copy-to" class="form-control" value="' . htmlspecialchars($blindCopyTo) . '" placeholder="e.g. archive@brand.com" style="flex: 1;">
                        <span style="font-size: 0.82em; color: #777; width: 220px; line-height: 1.3;">Enter email addresses separated by a comma.</span>
                    </div>
                </div>
            </div>
            
            <ul class="nav nav-tabs" id="emailTemplateLangTabs" style="margin-bottom: 15px; border-bottom: 2px solid #eee;">
                ' . $tabsHtml . '
            </ul>
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #555; margin-bottom: 5px; font-size: 0.9em; display: block;">Subject</label>
                <input type="text" id="template-subject" class="form-control" value="' . htmlspecialchars($translationsData['default']['subject']) . '" style="font-weight: 500;">
            </div>
            
            <div style="display: flex; gap: 20px; align-items: stretch; margin-bottom: 20px;">
                <div style="flex: 3; display: flex; flex-direction: column;">
                    <label style="font-weight: bold; color: #555; margin-bottom: 5px; font-size: 0.9em;">Message</label>
                    <div class="editor-toolbar" style="background: #f5f5f5; border: 1px solid #ccc; border-bottom: none; border-top-left-radius: 4px; border-top-right-radius: 4px; padding: 6px 12px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <select id="editor-format-block" class="form-control input-sm" style="width: 120px; display: inline-block; height: 28px; padding: 2px 6px;">
                            <option value="">Normal Text</option>
                            <option value="h1">Heading 1</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                            <option value="p">Paragraph</option>
                        </select>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="bold" title="Bold" style="font-weight: bold; width: 28px; height: 28px; line-height: 24px; padding: 0;">B</button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="italic" title="Italic" style="font-style: italic; width: 28px; height: 28px; line-height: 24px; padding: 0;">I</button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="underline" title="Underline" style="text-decoration: underline; width: 28px; height: 28px; line-height: 24px; padding: 0;">U</button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="bullet" title="Bullet List" style="width: 28px; height: 28px; line-height: 24px; padding: 0;"><i class="fas fa-list-ul"></i></button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="number" title="Numbered List" style="width: 28px; height: 28px; line-height: 24px; padding: 0;"><i class="fas fa-list-ol"></i></button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="link" title="Insert Link" style="width: 28px; height: 28px; line-height: 24px; padding: 0;"><i class="fas fa-link"></i></button>
                        <button type="button" class="btn btn-default btn-xs toolbar-btn" data-cmd="html" title="HTML Code View" style="width: 28px; height: 28px; line-height: 24px; padding: 0;"><i class="fas fa-code"></i></button>
                    </div>
                    <textarea id="template-message-editor" class="form-control" style="border-top-left-radius: 0; border-top-right-radius: 0; font-family: Courier, monospace; font-size: 13px; height: 350px; line-height: 1.5; padding: 12px; resize: vertical;">' . htmlspecialchars($translationsData['default']['message']) . '</textarea>
                </div>
                
                <div style="flex: 1; border: 1px solid #ddd; border-radius: 4px; display: flex; flex-direction: column; background: #fafafa; max-height: 405px;">
                    <div style="background: #eee; padding: 8px 12px; font-weight: bold; border-bottom: 1px solid #ddd; font-size: 0.9em; color: #444;"><i class="fas fa-database" style="margin-right: 6px;"></i> Merge Fields</div>
                    <div style="overflow-y: auto; flex: 1;" id="merge-fields-scroller">
                        ' . $mergeFieldsHtml . '
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #eee; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <select id="select-new-lang" class="form-control input-sm" style="width: 130px; display: inline-block;">
                    <option value="">Choose...</option>
                    ' . $langOptions . '
                </select>
                <button type="button" id="btn-add-lang" class="btn btn-default btn-sm" style="background: #f0f0f0; border-color: #ccc; font-weight: 600;"><i class="fas fa-plus" style="margin-right: 4px; color: #d9534f;"></i> Add Language</button>
            </div>
            
            <div style="display: flex; gap: 8px;">
                ' . ($branded ? '<button type="button" id="btn-delete-branded" class="btn btn-danger btn-sm" style="background-color: #d9534f; border-color: #d43f3a; font-weight: bold;"><i class="fas fa-trash-alt" style="margin-right: 6px;"></i> Delete Branded Template</button>' : '') . '
                <button type="button" id="btn-save-template" class="btn btn-success btn-sm" style="background-color: #5cb85c; border-color: #4cae4c; font-weight: bold;"><i class="fas fa-save" style="margin-right: 6px;"></i> Save</button>
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal" style="font-weight: bold;">Close</button>
            </div>
        </div>
        
        <script>
        templateTranslations = ' . json_encode($translationsData) . ';
        activeLangTab = "default";
        
        function saveActiveLangState() {
            if (activeLangTab) {
                templateTranslations[activeLangTab] = {
                    subject: jQuery("#template-subject").val(),
                    message: jQuery("#template-message-editor").val()
                };
            }
        }
        
        function switchLangTab(newLang) {
            saveActiveLangState();
            activeLangTab = newLang;
            
            jQuery("#template-subject").val(templateTranslations[newLang].subject || "");
            jQuery("#template-message-editor").val(templateTranslations[newLang].message || "");
            
            jQuery("#emailTemplateLangTabs li").removeClass("active");
            jQuery("#emailTemplateLangTabs li a").each(function() {
                if (jQuery(this).data("lang") === newLang) {
                    jQuery(this).parent().addClass("active");
                }
            });
            
            var langSelect = jQuery("#select-new-lang");
            langSelect.find("option").each(function() {
                var val = jQuery(this).val();
                if (val) {
                    if (templateTranslations[val]) {
                        jQuery(this).hide();
                    } else {
                        jQuery(this).show();
                    }
                }
            });
            langSelect.val("");
        }
        
        jQuery(document).off("click", ".lang-tab-link").on("click", ".lang-tab-link", function(e) {
            e.preventDefault();
            var targetLang = jQuery(this).data("lang");
            switchLangTab(targetLang);
        });
        </script>
        ';
        
        echo json_encode(['success' => true, 'html' => $html]);
        exit;
    }

    /**
     * AJAX endpoint to save custom branded template overrides
     */
    public function save_email_template_editor_ajax($vars)
    {
        $brandId = (int)$_POST['brand_id'];
        $templateName = $_POST['template_name'];
        $copyTo = $_POST['copy_to'];
        $blindCopyTo = $_POST['blind_copy_to'];
        $translations = $_POST['translations'];
        
        $brand = Capsule::table('mod_multibrand_brands')->where('id', $brandId)->first();
        if (!$brand) {
            echo json_encode(['success' => false, 'message' => 'Brand not found.']);
            exit;
        }
        
                $translationsDecoded = json_decode(htmlspecialchars_decode($translations), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
             $translationsDecoded = json_decode(stripslashes(htmlspecialchars_decode($translations)), true);
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid translation data format.']);
            exit;
        }
        
        $exists = Capsule::table('mod_multibrand_email_templates')
            ->where('brand_id', $brandId)
            ->where('template_name', $templateName)
            ->exists();
            
        if ($exists) {
            Capsule::table('mod_multibrand_email_templates')
                ->where('brand_id', $brandId)
                ->where('template_name', $templateName)
                ->update([
                    'copy_to' => $copyTo,
                    'blind_copy_to' => $blindCopyTo,
                    'translations' => json_encode($translationsDecoded),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            Capsule::table('mod_multibrand_email_templates')->insert([
                'brand_id' => $brandId,
                'template_name' => $templateName,
                'status' => 1,
                'copy_to' => $copyTo,
                'blind_copy_to' => $blindCopyTo,
                'translations' => json_encode($translationsDecoded),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * AJAX endpoint to delete customized brand templates
     */
    public function delete_branded_template_ajax($vars)
    {
        $brandId = (int)$_POST['brand_id'];
        $templateName = $_POST['template_name'];
        
        Capsule::table('mod_multibrand_email_templates')
            ->where('brand_id', $brandId)
            ->where('template_name', $templateName)
            ->delete();
            
        echo json_encode(['success' => true]);
        exit;
    }
}
