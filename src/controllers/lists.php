<?php
function listLists(): void {
    $client = requireClient();
    $lists  = DB::fetchAll('SELECT * FROM contact_lists WHERE client_id = ? ORDER BY created_at DESC', [$client['id']]);
    require ROOT . '/templates/lists.php';
}

function newList(): void {
    $client = requireClient();
    require ROOT . '/templates/list_new.php';
}

function createList(): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    if (empty($name)) { jsonResponse(['error' => 'Numele este obligatoriu'], 422); return; }
    $id = DB::insert('INSERT INTO contact_lists (client_id, name, description) VALUES (?,?,?)', [$client['id'], $name, $desc]);
    audit('create_list', 'list', (string)$id);
    redirect('/lists/' . $id);
}

function viewList(int $id): void {
    $client = requireClient();
    $list   = DB::fetchOne('SELECT * FROM contact_lists WHERE id=? AND client_id=?', [$id, $client['id']]);
    if (!$list) { http_response_code(404); require ROOT . '/templates/404.php'; return; }
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = 50;
    $offset  = ($page - 1) * $limit;
    $search  = trim($_GET['q'] ?? '');
    $where   = 'WHERE list_id = ?';
    $params  = [$id];
    if ($search) { $where .= ' AND (phone LIKE ? OR first_name LIKE ? OR last_name LIKE ?)'; $s = "%$search%"; $params = array_merge($params, [$s,$s,$s]); }
    $total    = DB::fetchOne("SELECT COUNT(*) as n FROM contacts $where", $params)['n'];
    $contacts = DB::fetchAll("SELECT * FROM contacts $where ORDER BY id DESC LIMIT $limit OFFSET $offset", $params);
    require ROOT . '/templates/list_view.php';
}

function importContacts(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    $list   = DB::fetchOne('SELECT * FROM contact_lists WHERE id=? AND client_id=?', [$id, $client['id']]);
    if (!$list) { jsonResponse(['error' => 'Lista invalida'], 404); return; }

    if (empty($_FILES['csv_file']['tmp_name'])) {
        jsonResponse(['error' => 'Niciun fisier incarcat'], 422); return;
    }

    // Limita marime fisier: max 5 MB
    if (($_FILES['csv_file']['size'] ?? 0) > 5 * 1024 * 1024) {
        jsonResponse(['error' => 'Fisierul depaseste limita de 5 MB'], 413); return;
    }

    // Rate limit: max 5 importuri per ora per utilizator
    $recentImports = (int)(DB::fetchOne(
        "SELECT COUNT(*) as n FROM audit_log WHERE user_id=? AND action='import_contacts' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [Auth::userId()]
    )['n'] ?? 0);
    if ($recentImports >= 5) {
        jsonResponse(['error' => 'Limita atinsa: max 5 importuri pe ora. Reveniti mai tarziu.'], 429); return;
    }

    $file      = $_FILES['csv_file']['tmp_name'];
    $mapping   = $_POST['mapping'] ?? [];

    if (!is_readable($file)) {
        jsonResponse(['error' => 'Fisierul nu poate fi citit'], 422); return;
    }

    $handle    = fopen($file, 'r');
    $delimiter = detectDelimiter($file);
    $hasHeader = (int)($_POST['has_header'] ?? 1);
    $imported  = 0;
    $skipped   = 0;
    $lineNum   = 0;

    DB::beginTransaction();
    try {
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNum++;
            if ($hasHeader && $lineNum === 1) continue;
            if ($lineNum > UPLOAD_MAX_ROWS + ($hasHeader ? 1 : 0)) break;

            $phone     = '';
            $firstName = '';
            $lastName  = '';
            $extra     = [];

            foreach ($mapping as $colIdx => $fieldName) {
                $val = trim($row[(int)$colIdx] ?? '');
                switch ($fieldName) {
                    case 'phone':      $phone     = SendSmsApi::normalizePhone($val); break;
                    case 'first_name': $firstName = $val; break;
                    case 'last_name':  $lastName  = $val; break;
                    case 'ignore':     break;
                    default:           if ($val) $extra[$fieldName] = $val; break;
                }
            }

            if (empty($phone) || strlen($phone) < 10) { $skipped++; continue; }

            DB::execute(
                'INSERT IGNORE INTO contacts (list_id, client_id, phone, first_name, last_name, extra_data)
                 VALUES (?,?,?,?,?,?)',
                [$id, $client['id'], $phone, $firstName ?: null, $lastName ?: null,
                 $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null]
            );
            $imported++;
        }
        fclose($handle);

        DB::execute('UPDATE contact_lists SET total_contacts = (SELECT COUNT(*) FROM contacts WHERE list_id=?) WHERE id=?', [$id, $id]);
        DB::commit();
        audit('import_contacts', 'list', (string)$id);
        jsonResponse(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
    } catch (Exception $e) {
        DB::rollback();
        fclose($handle);
        error_log('Import error: ' . $e->getMessage());
        jsonResponse(['error' => 'Eroare la import'], 500);
    }
}

function deleteList(int $id): void {
    requirePost();
    if (!Auth::csrfVerify()) csrfFail();
    $client = requireClient();
    DB::execute('DELETE FROM contact_lists WHERE id=? AND client_id=?', [$id, $client['id']]);
    audit('delete_list', 'list', (string)$id);
    jsonResponse(['success' => true]);
}

function detectDelimiter(string $file): string {
    $handle = fopen($file, 'r');
    $line   = fgets($handle);
    fclose($handle);
    $counts = [
        ','  => substr_count($line, ','),
        ';'  => substr_count($line, ';'),
        "\t" => substr_count($line, "\t"),
        '|'  => substr_count($line, '|'),
    ];
    arsort($counts);
    return array_key_first($counts);
}
