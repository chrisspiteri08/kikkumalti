<?php
require __DIR__ . '/layout.php';
require_login();
$sportslinkDraft = static function (array $prospect): string {
    $name = trim((string)$prospect['prospect']);
    return "Hello,\n\nI’m Chris Spiteri, founder of Sportslink, a local cooperative supporting Maltese sport and athletes.\n\nI’d like to invite {$name} to join the Sportslink Network: a free, no-obligation network that connects Maltese businesses with sportspeople looking for partnership opportunities.\n\nBy joining, {$name} can discover athletes and sporting opportunities relevant to the brand, and explore potential partnerships directly where there is a good fit. There is no fee and no obligation to sponsor anyone.\n\nYou can see how the Network works here:\nhttps://sportslink.mt/network/\n\nBest,\nChris Spiteri\nFounder, Sportslink\nMob/WhatsApp: +356 9904 9798\nhttps://sportslink.mt/";
};
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_sam_network_drafts') {
    verify_csrf();
    $pdo = db();
    $stmt = $pdo->prepare("SELECT d.id, d.subject, d.body, p.prospect, p.category FROM outreach_drafts d INNER JOIN prospects p ON p.id = d.prospect_id WHERE p.opportunity_type = ? AND p.status = 'Draft Saved'");
    $stmt->execute(['Sports sponsorship — Sam Cassar']);
    $update = $pdo->prepare('UPDATE outreach_drafts SET subject = ?, body = ? WHERE id = ?');
    $updated = 0; $unchanged = 0;
    foreach ($stmt->fetchAll() as $draft) {
        $body = $sportslinkDraft($draft);
        $subject = 'Invitation to join the Sportslink Network';
        if ($draft['subject'] !== $subject || $draft['body'] !== $body) { $update->execute([$subject, $body, $draft['id']]); $updated++; } else { $unchanged++; }
    }
    flash("Updated {$updated} Sportslink Network draft(s). {$unchanged} draft(s) were already up to date.");
    redirect('prospects.php?status=Draft+Saved&opportunity_type=' . rawurlencode('Sports sponsorship — Sam Cassar'));
}
$where = []; $params = [];
$search = trim((string) ($_GET['q'] ?? ''));
if ($search !== '') { $where[] = '(prospect LIKE ? OR category LIKE ? OR contact_person LIKE ? OR email LIKE ?)'; for ($i=0;$i<4;$i++) $params[] = '%' . $search . '%'; }
foreach (['status' => CRM_STATUSES, 'opportunity_type' => CRM_OPPORTUNITY_TYPES] as $field => $choices) { $value = (string) ($_GET[$field] ?? ''); if (in_array($value, $choices, true)) { $where[] = "$field = ?"; $params[] = $value; } }
$category = trim((string) ($_GET['category'] ?? '')); if ($category !== '') { $where[] = 'category LIKE ?'; $params[] = '%' . $category . '%'; }
$score = score_or_null((string) ($_GET['score'] ?? '')); if ($score !== null) { $where[] = 'score = ?'; $params[] = $score; }
$follow = (string) ($_GET['follow'] ?? ''); if ($follow === 'overdue') $where[] = "follow_up_date < CURDATE() AND status NOT IN ('Closed – Won','Closed – Not Pursuing')"; if ($follow === 'week') { $where[] = "follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"; }
$sorts = ['follow_up_date' => 'follow_up_date IS NULL, follow_up_date ASC', 'score' => 'score IS NULL, score DESC', 'updated' => 'updated_at DESC', 'prospect' => 'prospect ASC'];
$sort = (string) ($_GET['sort'] ?? 'follow_up_date'); $order = $sorts[$sort] ?? $sorts['follow_up_date'];
$sql = 'SELECT * FROM prospects' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $order;
$stmt = db()->prepare($sql); $stmt->execute($params); $prospects = $stmt->fetchAll();
page_start('Prospects', 'prospects'); ?>
<div class="page-head"><div><h1>Prospects</h1><p><?= count($prospects) ?> matching prospect<?= count($prospects) === 1 ? '' : 's' ?></p></div><a class="button" href="prospect.php">+ Add prospect</a></div>
<section class="panel"><form class="filters" method="get"><div class="field"><label>Search</label><input name="q" value="<?= e($search) ?>" placeholder="Prospect, person, email…"></div><div class="field"><label>Status</label><select name="status"><option value="">All statuses</option><?php foreach (CRM_STATUSES as $item): ?><option <?= ($_GET['status'] ?? '') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="field"><label>Opportunity</label><select name="opportunity_type"><option value="">All types</option><?php foreach (CRM_OPPORTUNITY_TYPES as $item): ?><option <?= ($_GET['opportunity_type'] ?? '') === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="field"><label>Category</label><input name="category" value="<?= e($category) ?>" placeholder="e.g. Family venue"></div><div class="field"><label>Score</label><input name="score" type="number" min="0" max="10" step="0.1" value="<?= e((string)($score ?? '')) ?>" placeholder="e.g. 8.5"></div><div class="field"><label>Follow-up</label><select name="follow"><option value="">Any date</option><option value="overdue" <?= $follow === 'overdue' ? 'selected' : '' ?>>Overdue</option><option value="week" <?= $follow === 'week' ? 'selected' : '' ?>>Due this week</option></select></div><div class="field"><label>Sort</label><select name="sort"><option value="follow_up_date" <?= $sort === 'follow_up_date' ? 'selected' : '' ?>>Follow-up date</option><option value="score" <?= $sort === 'score' ? 'selected' : '' ?>>Score</option><option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option><option value="prospect" <?= $sort === 'prospect' ? 'selected' : '' ?>>Name</option></select></div><div class="field"><label>&nbsp;</label><div class="actions"><button class="button" type="submit">Filter</button><a class="button secondary" href="prospects.php">Clear</a></div></div></form></section>
<section class="panel"><p class="muted">Create Sportslink Network invitations for the saved Sam Cassar prospects. This replaces each saved draft’s subject and body, personalising only the prospect name in the invitation and “By joining” paragraph. No email is sent.</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_sam_network_drafts"><button class="button" type="submit" onclick="return confirm('Replace the saved Sam Cassar drafts with Sportslink Network invitations? No emails will be sent.')">Create Sportslink Network drafts</button></form></section>
<section class="panel table-wrap"><table><thead><tr><th>Prospect</th><th>Type</th><th>Status</th><th>Score</th><th>Follow-up</th><th>Next step</th></tr></thead><tbody><?php foreach ($prospects as $item): ?><tr><td><a href="<?= e(prospect_url((int)$item['id'])) ?>"><strong><?= e($item['prospect']) ?></strong></a><br><span class="muted"><?= e($item['category']) ?></span></td><td><?= e($item['opportunity_type']) ?></td><td><?= status_badge($item['status']) ?></td><td><?= e(score_label($item['score'])) ?></td><td><?= e($item['follow_up_date'] ?: '—') ?></td><td><?= e(mb_strimwidth($item['recommended_next_step'] ?: '—', 0, 80, '…')) ?></td></tr><?php endforeach; ?><?php if (!$prospects): ?><tr><td colspan="6" class="empty">No prospects match these filters.</td></tr><?php endif; ?></tbody></table></section>
<?php page_end();
