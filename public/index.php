<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/helpers/DB.php';
require ROOT . '/src/helpers/Auth.php';
require ROOT . '/src/helpers/Router.php';
require ROOT . '/src/helpers/SendSmsApi.php';
require ROOT . '/src/helpers/SmsApiRo.php';
require ROOT . '/src/helpers/SmsoRo.php';
require ROOT . '/src/helpers/ProviderFactory.php';
require ROOT . '/src/helpers/TOTP.php';

Auth::init();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
if (APP_ENV === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

$router = new Router();

// Auth
$router->get('/login',             function() { require ROOT . '/src/controllers/auth.php'; renderLogin(); });
$router->post('/login',            function() { require ROOT . '/src/controllers/auth.php'; handleLogin(); });
$router->post('/login/verify-2fa', function() { require ROOT . '/src/controllers/auth.php'; verify2fa(); });
$router->post('/logout', function() {
    if (!Auth::csrfVerify()) { redirect('/login'); return; }
    Auth::logout();
    redirect('/login');
});

// Client selector
$router->get('/select-client',  function() { Auth::require(); require ROOT . '/src/controllers/client.php'; renderSelectClient(); });
$router->post('/select-client', function() { Auth::require(); require ROOT . '/src/controllers/client.php'; handleSelectClient(); });

// Dashboard
$router->get('/',          function() { Auth::require(); require ROOT . '/src/controllers/dashboard.php'; renderDashboard(); });
$router->get('/dashboard', function() { Auth::require(); require ROOT . '/src/controllers/dashboard.php'; renderDashboard(); });

// Quick SMS
$router->get('/quick-sms',  function() { Auth::require(); require ROOT . '/src/controllers/quick_sms.php'; renderQuickSms(); });
$router->post('/quick-sms', function() { Auth::require(); require ROOT . '/src/controllers/quick_sms.php'; handleQuickSms(); });

// Campaigns
$router->get('/campaigns',               function()    { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; listCampaigns(); });
$router->get('/campaigns/new',           function()    { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; newCampaign(); });
$router->post('/campaigns/new',          function()    { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; createCampaign(); });
$router->get('/campaigns/{id}',          function($id) { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; viewCampaign((int)$id); });
$router->post('/campaigns/{id}/send',    function($id) { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; sendCampaign((int)$id); });
$router->post('/campaigns/{id}/pause',   function($id) { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; pauseCampaign((int)$id); });
$router->post('/campaigns/{id}/delete',  function($id) { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; deleteCampaign((int)$id); });
$router->get('/campaigns/{id}/export',   function($id) { Auth::require(); require ROOT . '/src/controllers/campaigns.php'; exportCampaignCsv((int)$id); });

// Contact lists
$router->get('/lists',              function()    { Auth::require(); require ROOT . '/src/controllers/lists.php'; listLists(); });
$router->get('/lists/new',          function()    { Auth::require(); require ROOT . '/src/controllers/lists.php'; newList(); });
$router->post('/lists/new',         function()    { Auth::require(); require ROOT . '/src/controllers/lists.php'; createList(); });
$router->get('/lists/{id}',         function($id) { Auth::require(); require ROOT . '/src/controllers/lists.php'; viewList((int)$id); });
$router->post('/lists/{id}/import', function($id) { Auth::require(); require ROOT . '/src/controllers/lists.php'; importContacts((int)$id); });
$router->post('/lists/{id}/delete', function($id) { Auth::require(); require ROOT . '/src/controllers/lists.php'; deleteList((int)$id); });

// Templates
$router->get('/templates',               function()    { Auth::require(); require ROOT . '/src/controllers/templates.php'; listTemplates(); });
$router->post('/templates/save',         function()    { Auth::require(); require ROOT . '/src/controllers/templates.php'; saveTemplate(); });
$router->post('/templates/{id}/delete',  function($id) { Auth::require(); require ROOT . '/src/controllers/templates.php'; deleteTemplate((int)$id); });

// Reports
$router->get('/reports',                function() { Auth::require(); require ROOT . '/src/controllers/reports.php'; renderReports(); });
$router->get('/reports/contact',        function() { Auth::require(); require ROOT . '/src/controllers/reports.php'; contactReport(); });
$router->post('/reports/delete-failed', function() { Auth::require(); require ROOT . '/src/controllers/reports.php'; deleteFailedMessages(); });
$router->post('/reports/sync-costs',    function() { Auth::require(); require ROOT . '/src/controllers/reports.php'; syncCostsNow(); });
$router->get('/reports/export',         function() { Auth::require(); require ROOT . '/src/controllers/reports.php'; exportReportCsv(); });

// Optout / Blacklist
$router->get('/optout',              function()    { Auth::require(); require ROOT . '/src/controllers/optout.php'; listOptout(); });
$router->post('/optout/add',         function()    { Auth::require(); require ROOT . '/src/controllers/optout.php'; addOptout(); });
$router->post('/optout/{id}/delete', function($id) { Auth::require(); require ROOT . '/src/controllers/optout.php'; deleteOptout((int)$id); });
$router->post('/optout/import',      function()    { Auth::require(); require ROOT . '/src/controllers/optout.php'; importOptout(); });
$router->get('/stop',                function()    { require ROOT . '/src/controllers/optout.php'; handleStopPage(); });
$router->post('/stop',               function()    { require ROOT . '/src/controllers/optout.php'; handleStopSubmit(); });

// Admin
$router->get('/admin/logs',               function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; listAuditLog(); });
$router->get('/admin/clients',            function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; listClients(); });
$router->get('/admin/clients/new',        function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; newClient(); });
$router->post('/admin/clients/new',       function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; createClient(); });
$router->get('/admin/clients/{id}/edit',  function($id) { Auth::require(); require ROOT . '/src/controllers/admin.php'; editClient((int)$id); });
$router->post('/admin/clients/{id}/edit', function($id) { Auth::require(); require ROOT . '/src/controllers/admin.php'; updateClient((int)$id); });
$router->get('/admin/users',              function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; listUsers(); });
$router->get('/admin/users/new',          function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; newUser(); });
$router->post('/admin/users/new',         function()    { Auth::require(); require ROOT . '/src/controllers/admin.php'; createUser(); });

// Settings
$router->get('/settings',  function() { Auth::require(); require ROOT . '/src/controllers/admin.php'; renderSettings(); });
$router->post('/settings', function() { Auth::require(); require ROOT . '/src/controllers/admin.php'; saveSettings(); });

// Delivery Report Webhook — public, fara auth
$router->get('/dlr',  function() { require ROOT . '/src/controllers/dlr.php'; handleDlr(); });
$router->post('/dlr', function() { require ROOT . '/src/controllers/dlr.php'; handleDlr(); });

// AJAX API
$router->post('/api/sms/send',             function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiSendSms(); });
$router->get('/api/campaign/{id}/status',  function($id) { Auth::require(); require ROOT . '/src/controllers/api.php'; apiCampaignStatus((int)$id); });
$router->get('/api/balance',               function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiBalance(); });
$router->get('/api/stats',                 function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiStats(); });
$router->get('/api/senders',               function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiGetSenders(); });
$router->get('/api/templates',             function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiGetTemplates(); });
$router->post('/api/lists/import-preview', function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiImportPreview(); });
$router->get('/api/debug/message',         function()    { Auth::require(); require ROOT . '/src/controllers/api.php'; apiDebugMessage(); });
$router->post('/api/switch-client',        function()    { Auth::require(); require ROOT . '/src/controllers/client.php'; handleSwitchClient(); });

$router->dispatch();
