<?php
require __DIR__ . '/bootstrap.php';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="kikku-partnerships-backup-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'wb');
$stmt = db()->query('SELECT prospect, category, opportunity_type, website, social_links, why_kikku_fits, partnership_idea, commercial_potential, strategic_value, score, contact_person, contact_role, email, social_contact, best_channel, status, last_contact, follow_up_date, reply_summary, recommended_next_step, notes, created_at, updated_at FROM prospects ORDER BY id');
$first = $stmt->fetch();
if ($first) { fputcsv($out, array_keys($first)); fputcsv($out, $first); while ($row = $stmt->fetch()) fputcsv($out, $row); }
fclose($out);
