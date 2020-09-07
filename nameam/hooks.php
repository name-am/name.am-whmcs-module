<?php
/**
 * WHMCS SDK Name.am Registrar Module Hooks File
 *
 * Hooks allow you to tie into events that occur within the WHMCS application.
 *
 * This allows you to execute your own code in addition to, or sometimes even
 * instead of that which WHMCS executes by default.
 *
 * WHMCS recommends as good practice that all named hook functions are prefixed
 * with the keyword "hook", followed by your module name, followed by the action
 * of the hook function. This helps prevent naming conflicts with other addons
 * and modules.
 *
 * For every hook function you create, you must also register it with WHMCS.
 * There are two ways of registering hooks, both are demonstrated below.
 *
 * @see https://developers.whmcs.com/hooks/
 *
 * @copyright Copyright (c) WHMCS Limited 2016
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */

// Require any libraries needed for the module to function.
// require_once __DIR__ . '/path/to/library/loader.php';
//
// Also, perform any initialization required by the service's library.
use WHMCS\Domain\TopLevel\ImportItem;
// use WHMCS\Results\ResultsList;
use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\NameamApi\ApiClient;

require_once __DIR__ . '/nameam.php';
require_once __DIR__ . '/lib/ApiClient.php';

/**
 * Register a hook with WHMCS.
 *
 * add_hook(string $hookPointName, int $priority, string|array|Closure $function)
 */
add_hook('AdminHomeWidgets', 1, function($vars) {


    // Run code to create remote forum account here...
    return new NameamRegistrarModuleWidget();
});

/**
 * Name.am Registrar Module Admin Dashboard Widget.
 *
 * @see https://developers.whmcs.com/addon-modules/admin-dashboard-widgets/
 */
class NameamRegistrarModuleWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'Name.am Registrar';
    protected $description = '';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = false;
    protected $cacheExpiry = 120;
    protected $requiredPermission = '';

    public function getData()
    {
        if (empty($_COOKIE['nameam_data'])) {
            $data = [];
        } else {
            $data = json_decode(htmlspecialchars_decode(urldecode($_COOKIE['nameam_data']))); 
        }

        return $data;
    }

    public function generateOutput($data)
    {
        $balance = (($data->balance) ?? 'Info not found');
        return <<<EOF
<div class="widget-content-padded">
    <div class="row">
        <div class="col-sm-12">
            <span class="btn">Your Balance is: $balance</span>
        </div>
        <div class="col-sm-12">
            <a href="//name.am/login" target="_blank" class="btn btn-primary" style="margin:2px">Login</a>
            <a href="//name.am/register" target="_blank" class="btn btn-info" style="margin:2px">Signup</a>
            <a href="//name.am/pricing" target="_blank" class="btn btn-success" style="margin:2px">Pricing</a>
            <a href="//name.am/domain-ideas" target="_blank" class="btn btn-warning" style="margin:2px">Domain Ideas</a>
            <a href="//name.am/pricing" target="_blank" class="btn btn-primary" style="margin:2px">Pricing</a>
            <a href="//name.am/domain-transfer" target="_blank" class="btn btn-primary" style="margin:2px">Domain Transfer</a>
            <a href="//name.am/marketplace" target="_blank" class="btn btn-primary" style="margin:2px">Marketplace</a>
        </div>
    </div>
</div>
EOF;
    }
}
