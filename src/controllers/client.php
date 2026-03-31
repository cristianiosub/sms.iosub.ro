<?php
function renderSelectClient(): void {
    $userId     = Auth::userId();
    $superAdmin = Auth::isSuperAdmin();
    if ($superAdmin) {
        $clients = DB::fetchAll('SELECT * FROM clients WHERE is_active = 1 ORDER BY name');
    } else {
        $clients = DB::fetchAll(
            'SELECT c.* FROM clients c
             JOIN user_clients uc ON uc.client_id = c.id
             WHERE uc.user_id = ? AND c.is_active = 1 ORDER BY c.name',
            [$userId]
        );
    }
    require ROOT . '/templates/select_client.php';
}

function handleSelectClient(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();

    $clientId = (int)($_POST['client_id'] ?? 0);
    $userId   = Auth::userId();

    if (!Auth::isSuperAdmin()) {
        $access = DB::fetchOne(
            'SELECT 1 FROM user_clients WHERE user_id = ? AND client_id = ?',
            [$userId, $clientId]
        );
        if (!$access) {
            jsonResponse(['error' => 'Access denied'], 403);
            return;
        }
    }

    $client = DB::fetchOne('SELECT id FROM clients WHERE id = ? AND is_active = 1', [$clientId]);
    if (!$client) {
        $_SESSION['error'] = 'Client invalid.';
        redirect('/select-client');
        return;
    }

    Auth::setActiveClient($clientId);
    audit('select_client', 'client', (string)$clientId);
    redirect('/dashboard');
}

function handleSwitchClient(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();

    $clientId = (int)($_POST['client_id'] ?? 0);
    $userId   = Auth::userId();

    if (!Auth::isSuperAdmin()) {
        $access = DB::fetchOne(
            'SELECT 1 FROM user_clients WHERE user_id = ? AND client_id = ?',
            [$userId, $clientId]
        );
        if (!$access) {
            jsonResponse(['error' => 'Access denied'], 403);
            return;
        }
    }

    $client = DB::fetchOne('SELECT id, name FROM clients WHERE id = ? AND is_active = 1', [$clientId]);
    if (!$client) {
        jsonResponse(['error' => 'Client invalid'], 400);
        return;
    }

    Auth::setActiveClient($clientId);
    audit('select_client', 'client', (string)$clientId);
    jsonResponse(['success' => true, 'client_name' => $client['name']]);
}
