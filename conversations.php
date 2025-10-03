<?php
require_once __DIR__ . '/config/config.php';
require_login();

$me = (int)($_SESSION['user_id'] ?? 0);
$myRole = strtolower($_SESSION['role'] ?? 'resident');

// Build list of recent conversation partners from messages table
$sql = "SELECT 
	CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS partner_id,
	MAX(created_at) AS last_time
	FROM messages 
	WHERE sender_id = ? OR receiver_id = ?
	GROUP BY partner_id
	ORDER BY last_time DESC
	LIMIT 50";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $me, $me, $me);
$stmt->execute();
$rs = $stmt->get_result();
$partners = $rs->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch partner details
$conversations = [];
if ($partners) {
	$ids = array_map(function($r){ return (int)$r['partner_id']; }, $partners);
	$in = implode(',', array_fill(0, count($ids), '?'));
	$types = str_repeat('i', count($ids));
	$q = $conn->prepare("SELECT id, username, role FROM users WHERE id IN ($in)");
	$q->bind_param($types, ...$ids);
	$q->execute();
	$map = [];
	$res = $q->get_result();
	while ($u = $res->fetch_assoc()) { $map[(int)$u['id']] = $u; }
	$q->close();
	foreach ($partners as $p) {
		$pid = (int)$p['partner_id'];
		if (!isset($map[$pid])) continue;
		$conversations[] = [
			'partner_id' => $pid,
			'username' => $map[$pid]['username'] ?? ('User '.$pid),
			'role' => strtolower($map[$pid]['role'] ?? ''),
			'last_time' => $p['last_time'],
		];
	}
}

// Optional: if resident, only show authorities and a simple finder for authorities
$authorities = [];
if ($myRole === 'resident') {
	$aq = $conn->query("SELECT id, username FROM users WHERE LOWER(role) = 'authority' ORDER BY username LIMIT 50");
	$authorities = $aq ? $aq->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Messages - <?php echo APP_NAME; ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
	<nav class="navbar navbar-expand-lg fixed-top bg-white border-bottom">
		<div class="container">
			<a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php"><i class="fas fa-recycle text-success"></i> <?php echo APP_NAME; ?></a>
		</div>
	</nav>
	<div class="container" style="padding-top:90px;">
		<div class="row">
			<div class="col-lg-8 mx-auto">
				<h3 class="mb-4">Your Conversations</h3>
				<div class="list-group mb-4">
					<?php if (empty($conversations)): ?>
						<div class="text-muted">No conversations yet.</div>
					<?php else: ?>
						<?php foreach ($conversations as $c): 
							$cidA = min($me, (int)$c['partner_id']);
							$cidB = max($me, (int)$c['partner_id']);
							$convId = 'u_' . $cidA . '_u_' . $cidB;
						?>
							<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="<?php echo BASE_URL; ?>chat.php?to=<?php echo (int)$c['partner_id']; ?>">
								<span>
									<strong><?php echo e($c['username']); ?></strong>
									<small class="text-muted ms-2"><?php echo e(ucfirst($c['role'])); ?></small>
								</span>
								<small class="text-muted"><?php echo e(format_ph_date($c['last_time'])); ?></small>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<?php if ($myRole === 'resident'): ?>
					<h5 class="mb-3">Start a new chat with an Authority</h5>
					<div class="list-group">
						<?php foreach ($authorities as $a): ?>
							<a class="list-group-item list-group-item-action" href="<?php echo BASE_URL; ?>chat.php?to=<?php echo (int)$a['id']; ?>">
								<?php echo e($a['username']); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


