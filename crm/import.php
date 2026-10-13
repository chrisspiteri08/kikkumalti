<?php
require __DIR__ . '/layout.php';

if (isset($_GET['reset'])) {
    unset($_SESSION['crm_import']);
    redirect('import.php');
}

$targets = [
    'prospect' => 'Prospect / organisation name', 'category' => 'Category', 'opportunity_type' => 'Opportunity type',
    'website' => 'Website', 'social_links' => 'Website / social links', 'why_kikku_fits' => 'Why Kikku Fits',
    'partnership_idea' => 'Partnership Idea', 'commercial_potential' => 'Commercial Potential', 'strategic_value' => 'Strategic Value',
    'score' => 'Score /10', 'contact_person' => 'Contact person', 'contact_role' => 'Role', 'email' => 'Email',
    'social_contact' => 'Social contact', 'best_channel' => 'Best channel', 'status' => 'Status', 'last_contact' => 'Last contact',
    'follow_up_date' => 'Follow-up date', 'reply_summary' => 'Reply summary', 'recommended_next_step' => 'Recommended next step', 'notes' => 'Notes',
];

function normalise_header(string $value): string { return strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value)); }
function import_date_or_null(string $value): ?string {
    $value = trim($value); if ($value === '') return null;
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) { $date = DateTime::createFromFormat('!' . $format, $value); if ($date && $date->format($format) === $value) return $date->format('Y-m-d'); }
    return null;
}
function suggested_source(array $headers, string $target): string {
    $wanted = normalise_header($target); foreach ($headers as $header) if (normalise_header($header) === $wanted) return $header;
    $aliases = ['prospect'=>['prospect','company','organisation','organization','name'], 'contact_person'=>['contactperson','contact','name'], 'opportunity_type'=>['opportunitytype','type'], 'why_kikku_fits'=>['whykikkufits','whykikku'], 'follow_up_date'=>['followup','followupdate','nextfollowup'], 'recommended_next_step'=>['nextstep','recommendednextstep']];
    foreach ($aliases[$target] ?? [] as $alias) foreach ($headers as $header) if (normalise_header($header) === $alias) return $header;
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $action = (string) ($_POST['action'] ?? '');
    if ($action === 'preview') {
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) { flash('Upload a CSV file smaller than 2 MB.', 'error'); redirect('import.php'); }
        $handle = fopen($file['tmp_name'], 'rb'); $headers = fgetcsv($handle);
        if (!$headers || count($headers) < 1) { flash('The CSV does not have a readable header row.', 'error'); redirect('import.php'); }
        $headers = array_map(fn($header) => trim((string) $header), $headers); $rows = [];
        while (($row = fgetcsv($handle)) !== false && count($rows) < 2000) { $values = array_pad($row, count($headers), ''); $rows[] = array_combine($headers, array_slice($values, 0, count($headers))); }
        fclose($handle);
        $_SESSION['crm_import'] = ['headers' => $headers, 'rows' => $rows];
        if (!$rows) { flash('The CSV has no data rows to import.', 'error'); redirect('import.php'); }
    }
    if ($action === 'import') {
        $upload = $_SESSION['crm_import'] ?? null; if (!$upload) { flash('Upload a CSV first.', 'error'); redirect('import.php'); }
        $map = $_POST['map'] ?? []; $sourceFor = [];
        foreach ($targets as $target => $_) { $source = (string)($map[$target] ?? ''); if (in_array($source, $upload['headers'], true)) $sourceFor[$target] = $source; }
        if (!isset($sourceFor['prospect'])) { flash('Map the Prospect / organisation name column before importing.', 'error'); redirect('import.php'); }
        $pdo = db(); $imported = 0; $skipped = 0; $pdo->beginTransaction();
        try {
            $duplicate = $pdo->prepare('SELECT id FROM prospects WHERE prospect = ? AND email = ? LIMIT 1');
            foreach ($upload['rows'] as $row) {
                $data = [];
                foreach ($targets as $target => $_) $data[$target] = trim((string)($row[$sourceFor[$target] ?? ''] ?? ''));
                if ($data['prospect'] === '') { $skipped++; continue; }
                $data['prospect'] = mb_substr($data['prospect'], 0, 190); $data['category'] = mb_substr($data['category'], 0, 120);
                $data['opportunity_type'] = valid_choice($data['opportunity_type'], CRM_OPPORTUNITY_TYPES, 'Strategic Collab');
                $data['status'] = valid_choice($data['status'], CRM_STATUSES, 'Researching');
                $data['score'] = filter_var($data['score'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>10]]); if ($data['score'] === false) $data['score'] = null;
                $data['last_contact'] = import_date_or_null($data['last_contact']); $data['follow_up_date'] = import_date_or_null($data['follow_up_date']);
                $duplicate->execute([$data['prospect'], $data['email']]); if ($duplicate->fetchColumn()) { $skipped++; continue; }
                $columns = array_keys($data); $sql = 'INSERT INTO prospects (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
                $pdo->prepare($sql)->execute(array_values($data)); $newId = (int)$pdo->lastInsertId(); add_activity($newId, 'Research added', 'Imported from the original CRM CSV.'); $imported++;
            }
            $pdo->commit(); unset($_SESSION['crm_import']); flash("Imported $imported prospect(s)" . ($skipped ? "; skipped $skipped blank row(s)." : '.')); redirect('prospects.php');
        } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
    }
}

$upload = $_SESSION['crm_import'] ?? null;
page_start('Import CSV', 'import'); ?>
<div class="page-head"><div><h1>Import prospects from CSV</h1><p>Export the existing Google Sheet as a CSV, review the mapping, then import once.</p></div><a class="button secondary" href="export.php">Download backup CSV</a></div>
<?php if (!$upload): ?><section class="panel"><h2>1. Upload your export</h2><p class="notice">This importer creates prospects only. It does not overwrite existing records. Check the preview before you import.</p><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="preview"><div class="field"><label>CSV file (up to 2 MB)</label><input type="file" name="csv" accept=".csv,text/csv" required></div><div class="actions"><button class="button" type="submit">Preview columns</button></div></form></section><?php else: ?><section class="panel"><h2>2. Map your columns</h2><p class="muted">We found <?= count($upload['rows']) ?> row<?= count($upload['rows'])===1?'':'s' ?>. “Prospect / organisation name” is required.</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="import"><div class="form-grid two-col"><?php foreach($targets as $target=>$label): $suggested=suggested_source($upload['headers'],$target); ?><div class="field"><label><?= e($label) ?><?= $target==='prospect'?' *':'' ?></label><select name="map[<?= e($target) ?>]"><option value="">Do not import</option><?php foreach($upload['headers'] as $header): ?><option value="<?= e($header) ?>" <?= $header===$suggested?'selected':'' ?>><?= e($header) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div><div class="actions"><button class="button" type="submit" onclick="return confirm('Import these prospects into the CRM? You can edit or delete individual records afterwards.')">Import <?= count($upload['rows']) ?> rows</button><a class="button secondary" href="import.php?reset=1">Start again</a></div></form></section><section class="panel table-wrap"><h2>Preview: first 5 rows</h2><table><thead><tr><?php foreach($upload['headers'] as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach(array_slice($upload['rows'],0,5) as $row): ?><tr><?php foreach($upload['headers'] as $header): ?><td><?= e(mb_strimwidth((string)$row[$header],0,100,'…')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
<?php page_end();
