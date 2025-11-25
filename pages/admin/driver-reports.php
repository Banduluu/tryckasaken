<?php
session_start();
require_once '../../config/Database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../../pages/auth/login-form.php");
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Update report status
if (isset($_POST['update_status'])) {
    $id = intval($_POST['id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE driver_reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: driver-reports.php?updated=1");
    exit;
}

// Delete report
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM driver_reports WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    header("Location: driver-reports.php?deleted=1");
    exit;
}

// Fetch all reports
$stmt = $conn->prepare("
    SELECT 
        id,
        user_id,
        name,
        email,
        report_type,
        subject,
        message,
        status,
        created_at,
        is_read,
        read_at
    FROM driver_reports
    ORDER BY created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();

$reports = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-primary: #10b981;
            --color-secondary: #059669;
            --color-success: #22c55e;
            --color-danger: #ef4444;
            --color-info: #14b8a6;
            --color-warning: #f59e0b;
            
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.3);
            
            --blur-sm: blur(8px);
            --blur-md: blur(12px);
            --blur-lg: blur(16px);
            
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--glass-bg);
            backdrop-filter: var(--blur-lg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 40px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 1;
        }

        .page-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .alert {
            background: var(--glass-bg);
            backdrop-filter: var(--blur-md);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 15px 20px;
            margin-bottom: 25px;
            color: white;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.25);
            border-color: rgba(34, 197, 94, 0.4);
        }

        .alert-info {
            background: rgba(20, 184, 166, 0.25);
            border-color: rgba(20, 184, 166, 0.4);
        }

        .table-wrapper {
            background: var(--glass-bg);
            backdrop-filter: var(--blur-lg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: 0;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: transparent;
        }

        table th {
            background: var(--color-primary);
            color: white;
            font-weight: 600;
            border: none;
            padding: 18px 15px;
            text-align: left;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            vertical-align: middle;
            background: transparent;
        }

        table tr {
            transition: all 0.3s ease;
        }

        table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-open {
            background: rgba(239, 68, 68, 0.8);
            color: white;
        }

        .badge-in-progress {
            background: rgba(245, 158, 11, 0.8);
            color: white;
        }

        .badge-closed {
            background: rgba(34, 197, 94, 0.8);
            color: white;
        }

        .badge-technical {
            background: rgba(59, 130, 246, 0.8);
            color: white;
        }

        .badge-payment {
            background: rgba(168, 85, 247, 0.8);
            color: white;
        }

        .badge-passenger {
            background: rgba(236, 72, 153, 0.8);
            color: white;
        }

        .badge-app_bug {
            background: rgba(249, 115, 22, 0.8);
            color: white;
        }

        .badge-other {
            background: rgba(107, 114, 128, 0.8);
            color: white;
        }

        .btn {
            border-radius: var(--radius-sm);
            padding: 8px 16px;
            font-size: 15px;
            font-weight: 500;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            backdrop-filter: var(--blur-sm);
            color: white;
        }

        .btn-primary {
            background: rgba(16, 185, 129, 0.8);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-right: 5px;
        }

        .btn-primary:hover {
            background: rgba(16, 185, 129, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.5);
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.8);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: rgba(5, 150, 105, 0.8);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-secondary:hover {
            background: rgba(5, 150, 105, 1);
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: var(--blur-lg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 20px 25px;
            border: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.3rem;
        }

        .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 25px;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #333;
            background: white;
        }

        .modal-body strong {
            color: var(--color-primary);
            font-weight: 600;
        }

        .modal-footer {
            border: none;
            padding: 15px 25px;
            background: rgba(240, 253, 244, 0.95);
            border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        }

        .modal-dialog {
            max-width: 800px;
        }

        .modal-body p {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }

        .status-select {
            background: rgba(16, 185, 129, 0.9) !important;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: var(--radius-sm);
            padding: 6px 10px !important;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-select option {
            background: white;
            color: #333;
        }

        @media (max-width: 768px) {
            .reports-container {
                padding: 20px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            table th,
            table td {
                padding: 10px;
                font-size: 0.85rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
    </style>

</head>
<body>

<div class="reports-container">
    <a href="dashboard.php"  class="btn btn-sm btn-outline-light mb-3">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
    <h1 class="page-title">📋 Driver Reports</h1>

    <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">✓ Report status updated successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✓ Report deleted successfully.</div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="alert alert-info">ℹ️ No driver reports found.</div>
    <?php else: ?>
    
    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Driver Name</th>
                <th>Type</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td>#<?= htmlspecialchars($report['id']) ?></td>
                    <td><?= htmlspecialchars($report['name']) ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($report['report_type']) ?>">
                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($report['report_type']))) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars(mb_substr($report['subject'], 0, 40)) ?><?= mb_strlen($report['subject']) > 40 ? '...' : '' ?></td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($report['status']) ?>">
                            <?= ucfirst(htmlspecialchars($report['status'])) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($report['created_at'])) ?></td>

                    <td>
                        <button class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#viewModal<?= htmlspecialchars($report['id']) ?>">
                            View
                        </button>

                        <a href="driver-reports.php?delete=<?= htmlspecialchars($report['id']) ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Delete this report?');">
                            Delete
                        </a>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>
    </div>
    
    <?php endif; ?>
</div>

<!-- Modals Container -->
<?php foreach ($reports as $report): ?>
    <!-- View Modal -->
    <div class="modal fade" id="viewModal<?= htmlspecialchars($report['id']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Report from <?= htmlspecialchars($report['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p><strong>Driver:</strong> <?= htmlspecialchars($report['name']) ?> (<?= htmlspecialchars($report['email']) ?>)</p>
                    <p><strong>Report Type:</strong> 
                        <span class="badge badge-<?= htmlspecialchars($report['report_type']) ?>">
                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($report['report_type']))) ?>
                        </span>
                    </p>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($report['subject']) ?></p>
                    <p><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($report['created_at'])) ?></p>
                    <p><strong>Message:</strong></p>
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; border-left: 4px solid var(--color-primary);">
                        <?= nl2br(htmlspecialchars($report['message'])) ?>
                    </div>

                    <form method="POST" style="margin-top: 20px;">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($report['id']) ?>">
                        <div class="mb-3">
                            <label for="status<?= htmlspecialchars($report['id']) ?>" class="form-label"><strong>Update Status:</strong></label>
                            <select name="status" id="status<?= htmlspecialchars($report['id']) ?>" class="status-select">
                                <option value="open" <?= $report['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="in-progress" <?= $report['status'] === 'in-progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
