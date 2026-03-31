<?php
function listTemplates(): void {
    $client    = requireClient();
    $templates = DB::fetchAll(
        'SELECT * FROM message_templates WHERE client_id=? ORDER BY name ASC',
        [$client['id']]
    );
    require ROOT . '/templates/templates.php';
}

function saveTemplate(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();

    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $cat  = trim($_POST['category'] ?? '');

    if (!$name || !$body) {
        jsonResponse(['error' => 'Numele si continutul sunt obligatorii'], 422); return;
    }

    if ($id) {
        // Update
        $existing = DB::fetchOne('SELECT id FROM message_templates WHERE id=? AND client_id=?', [$id, $client['id']]);
        if (!$existing) { jsonResponse(['error' => 'Template negasit'], 404); return; }
        DB::execute(
            'UPDATE message_templates SET name=?, body=?, category=? WHERE id=? AND client_id=?',
            [$name, $body, $cat ?: null, $id, $client['id']]
        );
        audit('update_template', 'template', (string)$id);
        jsonResponse(['success' => true, 'id' => $id, 'action' => 'updated']);
    } else {
        // Insert
        $newId = DB::insert(
            'INSERT INTO message_templates (client_id, name, body, category, created_by) VALUES (?,?,?,?,?)',
            [$client['id'], $name, $body, $cat ?: null, Auth::userId()]
        );
        audit('create_template', 'template', (string)$newId);
        jsonResponse(['success' => true, 'id' => $newId, 'action' => 'created']);
    }
}

function deleteTemplate(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    DB::execute('DELETE FROM message_templates WHERE id=? AND client_id=?', [$id, $client['id']]);
    audit('delete_template', 'template', (string)$id);
    jsonResponse(['success' => true]);
}
