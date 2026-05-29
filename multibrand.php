<?php
/**
 * Multi Brand Addon Module
 * 
 * Allows managing multiple brands within a single WHMCS installation.
 * 
 * @package WHMCS
 * @version 1.0.0
 * @author WHMCS Development
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Load internal library files
if (file_exists(__DIR__ . '/lib/Admin/AdminDispatcher.php')) {
    require_once __DIR__ . '/lib/Admin/AdminDispatcher.php';
}
if (file_exists(__DIR__ . '/lib/Admin/Controller.php')) {
    require_once __DIR__ . '/lib/Admin/Controller.php';
}

use WHMCS\Module\Addon\Multibrand\Admin\AdminDispatcher;

/**
 * Module configuration and metadata
 */
function multibrand_config()
{
    return array(
        "name" => "Multi Brand",
        "description" => "Manage multiple brands with unique identities (Company Name, Logo, Email, etc.) within a single WHMCS installation.",
        "version" => "1.0.0",
        "author" => "WHMCS Development",
        "fields" => array(),
    );
}

/**
 * Activate the addon module
 */
function multibrand_activate()
{
    try {
        // Create database table for brands if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_brands')) {
            Capsule::schema()->create('mod_multibrand_brands', function ($table) {
                $table->increments('id');
                $table->string('brand_name');
                $table->string('company_name');
                $table->string('email_address');
                $table->string('domain');
                $table->text('logo_url')->nullable();
                $table->text('pay_to_text')->nullable();
                $table->boolean('proforma_invoice')->default(0);
                $table->boolean('invoice_number_branding')->default(0);
                $table->boolean('zero_invoices_number_branding')->default(0);
                $table->string('sequential_invoice_number_format')->nullable();
                $table->integer('next_sequential_number')->nullable();
                $table->text('brand_currencies')->nullable();
                $table->string('default_currency')->nullable();
                $table->string('system_url');
                $table->string('system_theme')->nullable();
                $table->string('brand_color')->nullable();
                $table->boolean('maintenance_mode')->default(0);
                $table->text('maintenance_mode_message')->nullable();
                $table->string('maintenance_mode_redirect_url')->nullable();
                $table->boolean('is_default')->default(0);
                $table->boolean('status')->default(1);
                $table->boolean('products_branding')->default(0);
                $table->boolean('price_override')->default(0);
                $table->boolean('brand_switcher')->default(0);
                $table->text('ticket_departments')->nullable();
                $table->string('order_template')->nullable();
                $table->string('default_language')->nullable();
                $table->boolean('auto_client_assignment')->default(0);
                $table->string('tos_url')->nullable();
                $table->text('signature')->nullable();
                $table->timestamps();
            });
        } else {
            // Add missing columns if they don't exist
            Capsule::schema()->table('mod_multibrand_brands', function ($table) {
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'system_theme')) {
                    $table->string('system_theme')->nullable()->after('system_url');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'brand_color')) {
                    $table->string('brand_color')->nullable()->after('system_theme');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'maintenance_mode')) {
                    $table->boolean('maintenance_mode')->default(0)->after('brand_color');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'maintenance_mode_message')) {
                    $table->text('maintenance_mode_message')->nullable()->after('maintenance_mode');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'maintenance_mode_redirect_url')) {
                    $table->string('maintenance_mode_redirect_url')->nullable()->after('maintenance_mode_message');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'status')) {
                    $table->boolean('status')->default(1)->after('is_default');
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'proforma_invoice')) {
                    $table->boolean('proforma_invoice')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'invoice_number_branding')) {
                    $table->boolean('invoice_number_branding')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'zero_invoices_number_branding')) {
                    $table->boolean('zero_invoices_number_branding')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'sequential_invoice_number_format')) {
                    $table->string('sequential_invoice_number_format')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'next_sequential_number')) {
                    $table->integer('next_sequential_number')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'brand_currencies')) {
                    $table->text('brand_currencies')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'default_currency')) {
                    $table->string('default_currency')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'products_branding')) {
                    $table->boolean('products_branding')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'price_override')) {
                    $table->boolean('price_override')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'brand_switcher')) {
                    $table->boolean('brand_switcher')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'ticket_departments')) {
                    $table->text('ticket_departments')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'order_template')) {
                    $table->string('order_template')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'default_language')) {
                    $table->string('default_language')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'auto_client_assignment')) {
                    $table->boolean('auto_client_assignment')->default(0);
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'tos_url')) {
                    $table->string('tos_url')->nullable();
                }
                if (!Capsule::schema()->hasColumn('mod_multibrand_brands', 'signature')) {
                    $table->text('signature')->nullable();
                }
            });
        }

        // Create table for client-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_client_brands')) {
            Capsule::schema()->create('mod_multibrand_client_brands', function ($table) {
                $table->increments('id');
                $table->integer('client_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['client_id', 'brand_id']);
            });
        } else {
            // Migrate existing table to drop old unique key on client_id and add composite unique key
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

        // Create table for invoice-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_invoice_brands')) {
            Capsule::schema()->create('mod_multibrand_invoice_brands', function ($table) {
                $table->increments('id');
                $table->integer('invoice_id')->unique();
                $table->integer('brand_id');
                $table->timestamps();
            });
        }

        // Create table for announcement-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_announcement_brands')) {
            Capsule::schema()->create('mod_multibrand_announcement_brands', function ($table) {
                $table->increments('id');
                $table->integer('announcement_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['announcement_id', 'brand_id'], 'mod_ann_brand_unique');
            });
        }

        // Create table for download-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_download_brands')) {
            Capsule::schema()->create('mod_multibrand_download_brands', function ($table) {
                $table->increments('id');
                $table->integer('download_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['download_id', 'brand_id']);
            });
        }

        // Create table for promotion-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_promotion_brands')) {
            Capsule::schema()->create('mod_multibrand_promotion_brands', function ($table) {
                $table->increments('id');
                $table->integer('promotion_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['promotion_id', 'brand_id'], 'mod_promo_brand_unique');
            });
        }

        // Create table for billableitems-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_billable_brands')) {
            Capsule::schema()->create('mod_multibrand_billable_brands', function ($table) {
                $table->increments('id');
                $table->integer('billable_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['billable_id', 'brand_id'], 'mod_bill_brand_unique');
            });
        }

        // Create table for email-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_email_brands')) {
            Capsule::schema()->create('mod_multibrand_email_brands', function ($table) {
                $table->increments('id');
                $table->integer('email_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['email_id', 'brand_id'], 'mod_email_brand_unique');
            });
        }


        // Create table for kb-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_kb_brands')) {
            Capsule::schema()->create('mod_multibrand_kb_brands', function ($table) {
                $table->increments('id');
                $table->integer('article_id');
                $table->integer('brand_id');
                $table->timestamps();
                $table->unique(['article_id', 'brand_id']);
            });
        }

        // Create table for order-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_order_brands')) {
            Capsule::schema()->create('mod_multibrand_order_brands', function ($table) {
                $table->increments('id');
                $table->integer('order_id')->unique();
                $table->integer('brand_id');
                $table->timestamps();
            });
        }

        // Create table for service-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_service_brands')) {
            Capsule::schema()->create('mod_multibrand_service_brands', function ($table) {
                $table->increments('id');
                $table->integer('service_id')->unique();
                $table->integer('brand_id');
                $table->timestamps();
            });
        }

        // Create table for ticket-brand association if needed
        if (!Capsule::schema()->hasTable('mod_multibrand_ticket_brands')) {
            Capsule::schema()->create('mod_multibrand_ticket_brands', function ($table) {
                $table->increments('id');
                $table->integer('ticket_id')->unique();
                $table->integer('brand_id');
                $table->timestamps();
            });
        }

        return array("status" => "success", "description" => "Multi Brand module activated successfully");
    } catch (Exception $e) {
        return array("status" => "error", "description" => "Failed to activate: " . $e->getMessage());
    }
}

/**
 * Deactivate the addon module
 */
function multibrand_deactivate()
{
    try {
        // Capsule::schema()->dropIfExists('mod_multibrand_brands');
        return array("status" => "success", "description" => "Multi Brand module deactivated successfully");
    } catch (Exception $e) {
        return array("status" => "error", "description" => "Failed to deactivate: " . $e->getMessage());
    }
}

/**
 * Admin Area Output
 */
function multibrand_output($vars)
{
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
    
    $dispatcher = new AdminDispatcher();
    return $dispatcher->dispatch($action, $vars);
}

/**
 * Admin Area Sidebar Output
 */
function multibrand_sidebar($vars)
{
    $modulelink = $vars['modulelink'];
    
    $sidebar = '<p><strong>Multi Brand</strong></p>';
    $sidebar .= '<ul class="list-unstyled">';
    $sidebar .= '<li><a href="' . $modulelink . '"><i class="fas fa-list"></i> List Brands</a></li>';
    $sidebar .= '<li><a href="' . $modulelink . '&action=add"><i class="fas fa-plus"></i> Add New Brand</a></li>';
    $sidebar .= '</ul>';
    return $sidebar;
}
