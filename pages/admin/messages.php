<?php
session_start();
require_once '../../config/Database.php';

$db = new Database();
$conn = $db->getConnection();

// Delete message
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $deleteId);
    $stmt->execute();
    $stmt->close();
    
    header("Location: messages.php?deleted=1");
    exit;
}

// Fetch messages using prepared statement
$stmt = $conn->prepare("SELECT * FROM messages ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --color-primary: #10b981;
            --color-secondary: #059669;
            --color-success: #22c55e;
            --color-danger: #ef4444;
            --color-info: #14b8a6;
            
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

        .messages-container {
            max-width: 1200px;
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
            transform: scale(1.01);
        }

        table tbody tr:last-child td {
            border-bottom: none;
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
            max-width: 700px;
        }

        .modal-body p {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }

        @media (max-width: 768px) {
            .messages-container {
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

<div class="messages-container">
    <a href="dashboard.php"  class="btn btn-sm btn-outline-primary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Dashboard
    </a>
    <h1 class="page-title">📩 User Messages</h1>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✓ Message deleted successfully.</div>
    <?php endif; ?>

    <?php if (empty($messages)): ?>
        <div class="alert alert-info">ℹ️ No messages found.</div>
    <?php else: ?>
    
    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Subject</th>
                <th>Message</th>
                <th>Actions</th>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?= htmlspecialchars($msg['id']) ?></td>
                    <td><?= htmlspecialchars($msg['name']) ?></td>
                    <td><?= htmlspecialchars($msg['email']) ?></td>
                    <td><?= htmlspecialchars($msg['subject']) ?></td>
                    <td><?= htmlspecialchars(mb_substr($msg['message'], 0, 40)) ?><?= mb_strlen($msg['message']) > 40 ? '...' : '' ?></td>

                    <td>
                        <button class="btn btn-primary btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#viewModal<?= htmlspecialchars($msg['id']) ?>">
                            View
                        </button>

                        <a href="messages.php?delete=<?= htmlspecialchars($msg['id']) ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Delete this message?');">
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

<!-- Modals Container - Outside main container for proper z-index stacking -->
<?php foreach ($messages as $msg): ?>
    <!-- Modal -->
    <div class="modal fade" id="viewModal<?= htmlspecialchars($msg['id']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Message from <?= htmlspecialchars($msg['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p><strong>Email:</strong> <?= htmlspecialchars($msg['email']) ?></p>
                    <p><strong>Subject:</strong> <?= htmlspecialchars($msg['subject']) ?></p>
                    <p><strong>Message:</strong></p>
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; border-left: 4px solid var(--color-primary);">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                    </div>
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