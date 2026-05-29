<?php

namespace WHMCS\Module\Addon\Multibrand\Admin;

/**
 * Admin Area Dispatch Handler for Multi Brand Module
 */
class AdminDispatcher
{
    /**
     * Dispatch request to appropriate controller action.
     *
     * @param string $action
     * @param array $parameters
     *
     * @return string|void
     */
    public function dispatch($action, $parameters)
    {
        if (!$action) {
            // Default to index if no action specified
            $action = 'index';
        }

        $controller = new Controller();

        // Check if method exists and is callable
        if (is_callable(array($controller, $action))) {
            return $controller->$action($parameters);
        }

        return '<p>Invalid action requested. Please go back and try again.</p>';
    }
}
