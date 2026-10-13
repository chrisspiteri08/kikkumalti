<?php
require __DIR__ . '/layout.php';
require_login();

if (isset($_GET['reset'])) { unset($_SESSION['crm_import'], $_SESSION['crm_import_preview'], $_SESSION['crm_import_result']); redirect('import.php'); }

$targets = [
    'prospect' => 'Prospect', 'category' => 'Category', 'opportunity_type' => 'Opportunity Type', 'website' => 'Website / Social',
    'why_kikku_fits' => 'Why Kikku Fits', 'partnership_idea' => 'Partnership Idea', 'commercial_potential' => 'Commercial Potential',
    'strategic_value' => 'Strategic Value', 'score' => 'Score /10', 'contact_person' => 'Contact Person', 'contact_role' => 'Role',
    'email' => 'Email', 'social_contact' => 'Social Contact', 'best_channel' => 'Best Channel', 'status' => 'Status',
    'outreach_draft' => 'Outreach Draft', 'last_contact' => 'Last Contact', 'follow_up_date' => 'Follow-up Date',
    'reply_summary' => 'Reply Summary', 'recommended_next_step' => 'Recommended Next Step', 'notes' => 'Notes',
];

function norm(string $value): string { return mb_strtolower(trim($value)); }
function canonical_choice(string $value, array $choices): ?string {
    foreach ($choices as $choice) if (norm($value) === norm($choice)) return $choice;
    return null;
}
function import_date(string $value): array {
    $value = trim($value); if ($value === '') return [null, null];
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) { $date = DateTime::createFromFormat('!' . $format, $value); if ($date && $date->format($format) === $value) return [$date->format('Y-m-d'), null]; }
    return [null, 'Invalid date “' . $value . '”. Use YYYY-MM-DD or DD/MM/YYYY.'];
}
function header_key(string $value): string { return strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value)); }
function source_suggestion(array $headers, string $target): string {
    $wanted = header_key($target); foreach ($headers as $header) if (header_key($header) === $wanted) return $header;
    $aliases = [
        'prospect'=>['prospect','company','organisation','organization','name'], 'website'=>['website','websitesocial','sociallinks','websiteandsocial'],
        'contact_person'=>['contactperson','contact'], 'contact_role'=>['role','contactrole'], 'opportunity_type'=>['opportunitytype','type'],
        'why_kikku_fits'=>['whykikkufits','whykikku'], 'follow_up_date'=>['followupdate','followup','nextfollowup'],
        'recommended_next_step'=>['recommendednextstep','nextstep'], 'outreach_draft'=>['outreachdraft','draft'], 'score'=>['score','score10'],
    ];
    foreach ($aliases[$target] ?? [] as $alias) foreach ($headers as $header) if (header_key($header) === $alias) return $header;
    return '';
}
function mapped_sources(array $map, array $headers): array { $sources=[]; foreach ($map as $field=>$source) if (is_string($source) && in_array($source, $headers, true)) $sources[$field]=$source; return $sources; }
function imported_values(array $row, array $sources): array { $data=[]; foreach ($sources as $field=>$source) $data[$field]=trim((string)($row[$source] ?? '')); return $data; }
function build_import_preview(array $upload, array $sources): array {
    $all = db()->query('SELECT * FROM prospects')->fetchAll(); $matches=[];
    foreach ($all as $prospect) $matches[norm($prospect['prospect'])][]=$prospect;
    $seen=[]; $rows=[]; $counts=['update'=>0,'create'=>0,'possible_duplicate'=>0,'error'=>0];
    foreach ($upload['rows'] as $index=>$row) {
        $values=imported_values($row,$sources); $errors=[]; $warnings=[]; $name=$values['prospect'] ?? '';
        if ($name==='') $errors[]='Missing Prospect name.';
        $key=norm($name); if ($key!=='' && isset($seen[$key])) $errors[]='Duplicate Prospect name in this CSV.'; $seen[$key]=true;
        if (($values['score'] ?? '') !== '' && score_or_null($values['score'])===null) $errors[]='Score must be a number from 0 to 10.';
        foreach (['last_contact','follow_up_date'] as $dateField) if (($values[$dateField] ?? '') !== '') { [, $error]=import_date($values[$dateField]); if ($error) $errors[]=$error; }
        if (($values['status'] ?? '') !== '' && canonical_choice($values['status'], CRM_STATUSES) === null) $warnings[]='Status “'.$values['status'].'” will be left unchanged because it is not a CRM status.';
        if (($values['opportunity_type'] ?? '') !== '' && canonical_choice($values['opportunity_type'], CRM_OPPORTUNITY_TYPES) === null) $warnings[]='Opportunity Type “'.$values['opportunity_type'].'” will be left unchanged because it is not recognised.';
        $matched=$key!==''?($matches[$key]??[]):[];
        $action='create'; if (count($matched)===1) $action='update'; if (count($matched)>1) $action='possible_duplicate'; if ($errors) $action='error';
        $rows[]=['row'=>$index+2,'action'=>$action,'id'=>$matched[0]['id']??null,'prospect'=>$name,'values'=>$values,'errors'=>$errors,'warnings'=>$warnings]; $counts[$action]++;
    }
    return ['rows'=>$rows,'counts'=>$counts,'sources'=>$sources,'token'=>bin2hex(random_bytes(16))];
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $action=(string)($_POST['action']??'');
    if ($action==='upload') {
        $file=$_FILES['csv']??null;
        if (!$file || $file['error']!==UPLOAD_ERR_OK || $file['size']>2*1024*1024) { flash('Upload a CSV file smaller than 2 MB.','error'); redirect('import.php'); }
        $handle=fopen($file['tmp_name'],'rb'); $headers=fgetcsv($handle);
        if (!$headers || count($headers)<1) { flash('The CSV does not have a readable header row.','error'); redirect('import.php'); }
        $headers=array_map(fn($header)=>trim((string)$header),$headers); $rows=[];
        while (($row=fgetcsv($handle))!==false && count($rows)<2000) { $values=array_pad($row,count($headers),''); $rows[]=array_combine($headers,array_slice($values,0,count($headers))); }
        fclose($handle); if (!$rows) { flash('The CSV has no data rows to import.','error'); redirect('import.php'); }
        $_SESSION['crm_import']=['headers'=>$headers,'rows'=>$rows]; unset($_SESSION['crm_import_preview'],$_SESSION['crm_import_result']); redirect('import.php');
    }
    if ($action==='preview') {
        $upload=$_SESSION['crm_import']??null; if (!$upload) { flash('Upload a CSV first.','error'); redirect('import.php'); }
        $sources=mapped_sources((array)($_POST['map']??[]),$upload['headers']);
        if (!isset($sources['prospect'])) { flash('Map the Prospect column before previewing.','error'); redirect('import.php'); }
        $_SESSION['crm_import_preview']=build_import_preview($upload,$sources); unset($_SESSION['crm_import_result']); redirect('import.php');
    }
    if ($action==='apply') {
        $preview=$_SESSION['crm_import_preview']??null; if (!$preview || !hash_equals((string)$preview['token'],(string)($_POST['preview_token']??''))) { flash('The import preview expired. Preview the CSV again.','error'); redirect('import.php'); }
        $pdo=db(); $updated=0;$created=0;$drafts=0;$draftsUntouched=0;$skipped=0;$blankFields=0;$ignoredChoices=0;$errors=[];$warnings=[]; $pdo->beginTransaction();
        try {
            $hasDraft=$pdo->prepare('SELECT id FROM outreach_drafts WHERE prospect_id=? LIMIT 1');
            $insertDraft=$pdo->prepare('INSERT INTO outreach_drafts (prospect_id, recipient, subject, channel, body, status) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($preview['rows'] as $row) {
                if (!in_array($row['action'],['update','create'],true)) { $skipped++; $errors[]='Row '.$row['row'].': '.($row['errors'] ? implode(' ',$row['errors']) : 'Multiple existing CRM records match this Prospect name.'); continue; }
                $values=$row['values']; $data=[];
                foreach ($values as $field=>$value) {
                    if ($value==='' || $field==='outreach_draft' || $field==='prospect') { if ($row['action']==='update' && $value==='' && $field!=='outreach_draft') $blankFields++; continue; }
                    if ($field==='status') {
                        $canonical=canonical_choice($value, CRM_STATUSES);
                        if ($canonical===null) { $ignoredChoices++; $warnings[]='Row '.$row['row'].': Status “'.$value.'” was left unchanged.'; continue; }
                        $data[$field]=$canonical;
                    } elseif ($field==='opportunity_type') {
                        $canonical=canonical_choice($value, CRM_OPPORTUNITY_TYPES);
                        if ($canonical===null) { $ignoredChoices++; $warnings[]='Row '.$row['row'].': Opportunity Type “'.$value.'” was left unchanged.'; continue; }
                        $data[$field]=$canonical;
                    } elseif ($field==='score') $data[$field]=score_or_null($value);
                    elseif (in_array($field,['last_contact','follow_up_date'],true)) [$data[$field],] = import_date($value);
                    else $data[$field]=$value;
                }
                if ($row['action']==='update') {
                    $prospectId=(int)$row['id']; if ($data) { $set=implode(', ',array_map(fn($field)=>"$field = ?",array_keys($data))); $pdo->prepare("UPDATE prospects SET $set WHERE id=?")->execute([...array_values($data),$prospectId]); add_activity($prospectId,'Note added','Fields updated from CSV re-import.'); } $updated++;
                } else {
                    $data=['prospect'=>mb_substr($row['prospect'],0,190),...$data]; $columns=array_keys($data); $pdo->prepare('INSERT INTO prospects ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')')->execute(array_values($data)); $prospectId=(int)$pdo->lastInsertId(); add_activity($prospectId,'Research added','Created from CSV import.'); $created++;
                }
                $draft=trim((string)($values['outreach_draft']??''));
                if ($draft!=='') { $hasDraft->execute([$prospectId]); if ($hasDraft->fetchColumn()) { $draftsUntouched++; } else { $insertDraft->execute([$prospectId,$data['email']??'','',$data['best_channel']??'Email',$draft,'Draft']); add_activity($prospectId,'Draft prepared','Outreach draft imported from CSV.'); $drafts++; } }
            }
            $pdo->commit(); $_SESSION['crm_import_result']=['updated'=>$updated,'created'=>$created,'drafts'=>$drafts,'drafts_untouched'=>$draftsUntouched,'skipped'=>$skipped,'blank_fields'=>$blankFields,'ignored_choices'=>$ignoredChoices,'errors'=>$errors,'warnings'=>$warnings]; unset($_SESSION['crm_import_preview']); redirect('import.php');
        } catch (Throwable $exception) { $pdo->rollBack(); throw $exception; }
    }
}

$upload=$_SESSION['crm_import']??null; $preview=$_SESSION['crm_import_preview']??null; $result=$_SESSION['crm_import_result']??null; unset($_SESSION['crm_import_result']);
page_start('Import CSV','import'); ?>
<div class="page-head"><div><h1>Update prospects from CSV</h1><p>Match by trimmed, case-insensitive Prospect name. Blank CSV values never overwrite existing CRM values.</p></div><a class="button secondary" href="export.php">Download backup CSV</a></div>
<?php if ($result): ?><section class="panel"><h2>Import complete</h2><div class="three"><div><strong><?= (int)$result['updated'] ?></strong><br><span class="muted">updated</span></div><div><strong><?= (int)$result['created'] ?></strong><br><span class="muted">created</span></div><div><strong><?= (int)$result['skipped'] ?></strong><br><span class="muted">skipped / flagged</span></div></div><p class="muted">Left <?= (int)$result['blank_fields'] ?> blank mapped cell(s) unchanged. Imported <?= (int)$result['drafts'] ?> outreach draft(s) and left <?= (int)$result['drafts_untouched'] ?> existing draft(s) untouched.</p><?php if($result['ignored_choices']): ?><p class="notice">Left <?= (int)$result['ignored_choices'] ?> unrecognised Status or Opportunity Type value(s) unchanged (or at the CRM default for new prospects).</p><?php endif; ?><?php if($result['errors']): ?><details><summary>Rows not applied (<?= count($result['errors']) ?>)</summary><ul><?php foreach(array_slice($result['errors'],0,30) as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></details><?php endif; ?><?php if($result['warnings']): ?><details><summary>Fields left unchanged (<?= count($result['warnings']) ?>)</summary><ul><?php foreach(array_slice($result['warnings'],0,30) as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></details><?php endif; ?></section><?php endif; ?>
<?php if (!$upload): ?><section class="panel"><h2>1. Upload CSV</h2><p class="notice">Export the Google Sheet as CSV. Nothing changes until you review the mapping and confirm the resulting preview.</p><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload"><div class="field"><label>CSV file (up to 2 MB)</label><input type="file" name="csv" accept=".csv,text/csv" required></div><div class="actions"><button class="button" type="submit">Upload CSV</button></div></form></section><?php elseif (!$preview): ?><section class="panel"><h2>2. Map fields</h2><p class="muted"><?= count($upload['rows']) ?> rows found. Map only the fields you want to update. Prospect is required for matching.</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="preview"><div class="form-grid two-col"><?php foreach($targets as $target=>$label): $suggested=source_suggestion($upload['headers'],$target); ?><div class="field"><label><?= e($label) ?><?= $target==='prospect'?' *':'' ?></label><select name="map[<?= e($target) ?>]"><option value="">Do not update</option><?php foreach($upload['headers'] as $header): ?><option value="<?= e($header) ?>" <?= $header===$suggested?'selected':'' ?>><?= e($header) ?></option><?php endforeach; ?></select></div><?php endforeach; ?></div><div class="actions"><button class="button" type="submit">Preview updates</button><a class="button secondary" href="import.php?reset=1">Use a different file</a></div></form></section><?php else: ?><section class="panel"><h2>3. Review changes</h2><div class="three"><div><strong><?= (int)$preview['counts']['update'] ?></strong><br><span class="muted">will update</span></div><div><strong><?= (int)$preview['counts']['create'] ?></strong><br><span class="muted">will create</span></div><div><strong><?= (int)$preview['counts']['possible_duplicate'] ?></strong><br><span class="muted">possible duplicates</span></div><div><strong><?= (int)$preview['counts']['error'] ?></strong><br><span class="muted">validation errors</span></div></div><p class="notice">Existing activities and saved outreach drafts are preserved. A CSV outreach draft is saved only when the prospect has no draft already. Unrecognised Status and Opportunity Type labels are warnings, so the rest of that row can still import safely.</p><form method="post" class="actions"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>"><button class="button" type="submit" onclick="return confirm('Apply these updates and create the new prospects shown above?')">Confirm import</button><a class="button secondary" href="import.php?reset=1">Cancel</a></form></section><section class="panel table-wrap"><h2>Row-level preview</h2><table><thead><tr><th>CSV row</th><th>Prospect</th><th>Action</th><th>Notes</th></tr></thead><tbody><?php foreach(array_slice($preview['rows'],0,100) as $row): $notes=array_merge($row['errors'],$row['warnings']??[]); ?><tr><td><?= (int)$row['row'] ?></td><td><?= e($row['prospect']) ?></td><td><?= e(ucwords(str_replace('_',' ',$row['action']))) ?></td><td><?= e(implode(' ',$notes) ?: ($row['action']==='update'?'Matched existing prospect.':($row['action']==='create'?'New prospect.':'Multiple matching CRM names.'))) ?></td></tr><?php endforeach; ?></tbody></table><?php if(count($preview['rows'])>100): ?><p class="muted">Showing the first 100 rows.</p><?php endif; ?></section><?php endif; ?>
<?php page_end();
