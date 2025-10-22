<?php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Only authority or admin should access
if (($_SESSION['role'] ?? '') !== 'authority' && ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$resident_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$resident_id) {
    http_response_code(400);
    echo 'Invalid resident ID';
    exit;
}

// Fetch resident basic info
 $stmt = $conn->prepare("SELECT id, first_name, last_name, email, phone, address, eco_points, created_at FROM users WHERE id = ? AND role = 'resident'");
 if (!$stmt) {
     http_response_code(500);
     echo 'DB prepare error: ' . $conn->error;
     exit;
 }
 $stmt->bind_param('i', $resident_id);
 $stmt->execute();
 $resident = $stmt->get_result()->fetch_assoc();

if (!$resident) {
    http_response_code(404);
    echo 'Resident not found';
    exit;
}

ob_start();
?>
<div class="row">
    <div class="col-md-4 text-center">
        <div style="width:120px;height:120px;border-radius:12px;background:#f5f7fa;display:inline-flex;align-items:center;justify-content:center;font-size:36px;color:#666;">
            <?php echo htmlspecialchars(substr($resident['first_name'],0,1) . substr($resident['last_name'],0,1)); ?>
        </div>
        <h5 class="mt-3"><?php echo htmlspecialchars($resident['first_name'] . ' ' . $resident['last_name']); ?></h5>
        <p class="text-muted mb-1"><?php echo htmlspecialchars($resident['email']); ?></p>
        <?php if ($resident['phone']): ?><p class="text-muted"><?php echo htmlspecialchars($resident['phone']); ?></p><?php endif; ?>
        <p class="text-muted small">Joined: <?php echo format_ph_date($resident['created_at'], 'M j, Y'); ?></p>
        <p class="badge bg-success mt-2"><?php echo (int)$resident['eco_points']; ?> Eco Points</p>
    </div>
    <div class="col-md-8">
        <h6>Reports</h6>
        <div>
            <?php
            // Fetch reports and images
            $stmt = $conn->prepare("SELECT wr.*, u.first_name AS reporter_first, u.last_name AS reporter_last FROM waste_reports wr JOIN users u ON wr.user_id = u.id WHERE wr.user_id = ? ORDER BY wr.created_at DESC LIMIT 10");
            if (!$stmt) {
                echo '<p class="text-danger">DB error: ' . htmlspecialchars($conn->error) . '</p>';
                $reports = false;
            } else {
                $stmt->bind_param('i', $resident_id);
                $stmt->execute();
                $reports = $stmt->get_result();
            }

            if ($reports && $reports->num_rows > 0):
                while ($r = $reports->fetch_assoc()):
                    // fetch report images
                    $imgs = [];
                    $stmt2 = $conn->prepare("SELECT filename FROM report_images WHERE report_id = ?");
                    if ($stmt2) {
                        $stmt2->bind_param('i', $r['id']);
                        $stmt2->execute();
                        $resImgs = $stmt2->get_result();
                        if ($resImgs) {
                            while ($ri = $resImgs->fetch_assoc()) {
                                $imgs[] = $ri['filename'];
                            }
                        }
                    }
            ?>
                <div class="mb-3 p-2" style="border-left:4px solid #007bff;">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                            <div class="text-muted small"><?php echo htmlspecialchars($r['location']); ?> · <?php echo format_ph_date($r['created_at']); ?></div>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $r['priority'] === 'high' ? 'danger' : ($r['priority'] === 'medium' ? 'warning' : 'success'); ?>"><?php echo htmlspecialchars(ucfirst($r['priority'])); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($imgs)): ?>
                        <div class="mt-2 d-flex flex-wrap">
                            <?php foreach ($imgs as $img):
                                $path = BASE_URL . 'uploads/reports/' . $img;
                                // ensure file exists on server
                                $localPath = __DIR__ . '/../../uploads/reports/' . $img;
                                if (file_exists($localPath)):
                            ?>
                                <a href="<?php echo $path; ?>" target="_blank" style="margin-right:8px;margin-bottom:8px;">
                                    <img src="<?php echo $path; ?>" alt="report image" style="width:96px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #e9eef0;" />
                                </a>
                            <?php else: ?>
                                <div style="width:96px;height:64px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;border-radius:6px;margin-right:8px;margin-bottom:8px;color:#999;">No image</div>
                            <?php endif; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <p class="text-muted">No reports found.</p>
            <?php endif; ?>
        </div>

        <h6 class="mt-3">Feedback</h6>
        <div>
            <?php
            $stmt = $conn->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            if ($stmt) {
                $stmt->bind_param('i', $resident_id);
                $stmt->execute();
                $feedbacks = $stmt->get_result();
            } else {
                $feedbacks = false;
                echo '<p class="text-danger">DB error: ' . htmlspecialchars($conn->error) . '</p>';
            }
            if ($feedbacks && $feedbacks->num_rows > 0):
                while ($f = $feedbacks->fetch_assoc()):
            ?>
                <div class="mb-2">
                    <strong><?php echo htmlspecialchars($f['subject']); ?></strong>
                    <div class="text-muted small"><?php echo format_ph_date($f['created_at']); ?></div>
                    <div class="text-muted small"><?php echo htmlspecialchars($f['message']); ?></div>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <p class="text-muted">No feedback yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
echo $html;
exit;

?>
