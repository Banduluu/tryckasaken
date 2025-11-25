<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Get quick statistics with error handling
$stats = [];

try {
    // User counts
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'passenger'");
    $stats['passengers'] = $result ? $result->fetch_assoc()['total'] : 0;

    $result = $conn->query("SELECT COUNT(*) as total FROM rfid_drivers");
    $stats['drivers'] = $result ? $result->fetch_assoc()['total'] : 0;

    // Booking counts
    $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings");
    $stats['total_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

    $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings WHERE status = 'pending'");
    $stats['pending_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

    $result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings WHERE status = 'completed'");
    $stats['completed_bookings'] = $result ? $result->fetch_assoc()['total'] : 0;

    // Get pending driver verifications - only count drivers with pending status
    $result = $conn->query("
        SELECT COUNT(*) as total 
        FROM rfid_drivers d
        INNER JOIN users u ON d.user_id = u.user_id 
        WHERE u.user_type = 'driver' 
        AND (d.verification_status = 'pending' OR d.verification_status IS NULL)
    ");
    $stats['pending_verifications'] = $result ? $result->fetch_assoc()['total'] : 0;

    // Recent activity
    $recentDrivers = [];
    $result = $conn->query("SELECT u.name, u.email, u.created_at 
                          FROM rfid_drivers d
                          INNER JOIN users u ON d.user_id = u.user_id
                          WHERE d.verification_status = 'pending' OR d.verification_status IS NULL
                          ORDER BY u.created_at DESC LIMIT 5");
    if ($result) {
        $recentDrivers = $result->fetch_all(MYSQLI_ASSOC);
    }

} catch (Exception $e) {
    // Set default values if there are database errors
    $stats = [
        'passengers' => 0,
        'drivers' => 0,
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'completed_bookings' => 0,
        'pending_verifications' => 0
    ];
    $recentDrivers = [];
}

renderAdminHeader("Dashboard", "admin");
?>
<link rel="stylesheet" href="../../public/css/dashboard.css?v=<?php echo time(); ?>">

<!-- Quick Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['passengers']) ?></h3>
                <p>Total Passengers</p>
                <a href="passengers-list.php?filter=passenger" class="stat-link">
                    <i class="bi bi-arrow-right"></i> View All
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="bi bi-car-front"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['drivers']) ?></h3>
                <p>Total Drivers</p>
                <a href="drivers-list.php" class="stat-link">
                    <i class="bi bi-arrow-right"></i> Manage
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-info">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['total_bookings']) ?></h3>
                <p>Total Bookings</p>
                <a href="bookings-list.php" class="stat-link">
                    <i class="bi bi-arrow-right"></i> View All
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['pending_bookings']) ?></h3>
                <p>Pending Bookings</p>
                <a href="bookings-list.php?status=pending" class="stat-link">
                    <i class="bi bi-arrow-right"></i> Review
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="action-card <?= $stats['pending_verifications'] > 0 ? 'pending-highlight' : '' ?>">
            <div class="action-icon">
                <i class="bi bi-shield-check"></i>
                <?php if ($stats['pending_verifications'] > 0): ?>
                    <span class="notification-badge"><?= $stats['pending_verifications'] ?></span>
                <?php endif; ?>
            </div>
            <h5>Driver Verifications</h5>
            <?php if ($stats['pending_verifications'] > 0): ?>
                <p class="pending-count">
                    <strong><?= $stats['pending_verifications'] ?></strong> pending verification<?= $stats['pending_verifications'] != 1 ? 's' : '' ?>
                    <br><small class="text-muted">Require your attention</small>
                </p>
            <?php else: ?>
                <p>All drivers verified<br><small class="text-muted">No pending applications</small></p>
            <?php endif; ?>
            <a href="drivers-verification.php" class="btn btn-custom">
                <i class="bi bi-<?= $stats['pending_verifications'] > 0 ? 'exclamation-circle' : 'check-circle' ?>"></i> 
                <?= $stats['pending_verifications'] > 0 ? 'Review Applications' : 'View Verifications' ?>
            </a>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="action-card">
            <div class="action-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <h5>Analytics & Reports</h5>
            <p>View platform performance and statistics</p>
            <a href="analytics-dashboard.php" class="btn btn-custom">
                <i class="bi bi-bar-chart"></i> View Analytics
            </a>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="action-card">
            <div class="action-icon">
                <i class="bi bi-gear"></i>
            </div>
            <h5>System Management</h5>
            <p>Manage users, settings, and system operations</p>
            <a href="passengers-list.php" class="btn btn-custom">
                <i class="bi bi-people"></i> Manage Passengers
            </a>
        </div>
    </div>
</div>

<!-- Driver Attendance Section -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="attendance-section-header">
            <h5><i class="bi bi-clock-history"></i> Driver Attendance</h5>
        </div>
        <div id="rfidAttendanceContainer">
            <div class="empty-state small">
                <i class="bi bi-hourglass"></i>
                <p>Waiting for RFID tap...</p>
            </div>
        </div>
    </div>
</div>

<!-- Driver Attendance Modal -->
<div class="modal fade" id="driverAttendanceModal" tabindex="-1" aria-labelledby="driverAttendanceLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="driverAttendanceLabel">Driver Check-in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="attendanceContent" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer" style="position: fixed; top: 80px; right: 20px; z-index: 9999;"></div>

<script>
// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    
    const iconMap = {
        success: 'check-circle-fill text-success',
        error: 'x-circle-fill text-danger',
        info: 'info-circle-fill text-info',
        warning: 'exclamation-triangle-fill text-warning'
    };
    
    const bgMap = {
        success: 'rgba(16, 185, 129, 0.95)',
        error: 'rgba(220, 38, 38, 0.95)',
        info: 'rgba(13, 202, 240, 0.95)',
        warning: 'rgba(245, 158, 11, 0.95)'
    };
    
    const toastHTML = `
        <div id="${toastId}" style="
            background: ${bgMap[type]};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        ">
            <i class="bi bi-${iconMap[type]}" style="font-size: 1.2rem;"></i>
            <span style="flex: 1; font-weight: 600;">${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                cursor: pointer;
                padding: 0;
                opacity: 0.8;
            ">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    
    setTimeout(() => {
        toastElement.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => toastElement.remove(), 300);
    }, 5000);
}

// AJAX: Refresh dashboard statistics
function refreshStats() {
    fetch('api-admin-actions.php?action=get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsDisplay(data.data);
            }
        })
        .catch(error => console.error('Stats refresh error:', error));
}

function updateStatsDisplay(stats) {
    // Update stat cards with animation
    const statElements = {
        passengers: document.querySelector('.stat-card:nth-child(1) h3'),
        drivers: document.querySelector('.stat-card:nth-child(2) h3'),
        total_bookings: document.querySelector('.stat-card:nth-child(3) h3'),
        pending_verifications: document.querySelector('.stat-card:nth-child(4) h3')
    };
    
    Object.keys(statElements).forEach(key => {
        if (statElements[key] && stats[key] !== undefined) {
            const currentValue = parseInt(statElements[key].textContent.replace(/,/g, ''));
            const newValue = parseInt(stats[key]);
            
            if (currentValue !== newValue) {
                statElements[key].style.transform = 'scale(1.1)';
                statElements[key].textContent = newValue.toLocaleString();
                setTimeout(() => {
                    statElements[key].style.transform = 'scale(1)';
                }, 200);
            }
        }
    });
}



// Auto-refresh every 30 seconds
setInterval(() => {
    refreshStats();
    loadDriverAttendance();
}, 30000);

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    loadDriverAttendance();
});

// Load driver attendance records
function loadDriverAttendance() {
    fetch('api-admin-actions.php?action=get_driver_attendance&limit=5')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAttendanceDisplay(data.data);
            }
        })
        .catch(error => console.error('Attendance fetch error:', error));
}

function updateAttendanceDisplay(records) {
    const container = document.getElementById('rfidAttendanceContainer');
    
    if (records.length === 0) {
        container.innerHTML = `
            <div class="empty-state small">
                <i class="bi bi-hourglass"></i>
                <p>Waiting for RFID tap...</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="attendance-grid">';
    
    records.forEach(record => {
        const picturePath = record.picture_path ? '../../' + record.picture_path : '../../public/images/default-avatar.png';
        const actionBadge = record.action === 'online' 
            ? '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Check-in</span>' 
            : '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Check-out</span>';
        const actionText = record.action === 'online' ? 'Checked In' : 'Checked Out';
        
        html += `
            <div class="attendance-card">
                <div class="attendance-card-body">
                    <div class="attendance-picture">
                        <img src="${picturePath}" alt="${record.name}" onerror="this.src='../../public/images/default-avatar.png'">
                    </div>
                    <div class="attendance-card-info">
                        <div class="attendance-card-header">
                            <h5>${htmlEscape(record.name)}</h5>
                        </div>
                        <div class="attendance-card-phone">
                            <i class="bi bi-telephone"></i>
                            <span>${htmlEscape(record.phone)}</span>
                        </div>
                        <div class="attendance-card-tricycle">
                            <i class="bi bi-car-front"></i>
                            <span>${htmlEscape(record.tricycle_info)}</span>
                        </div>
                        <div class="attendance-card-status">
                            ${actionBadge}
                        </div>
                        <div class="attendance-card-time">
                            <i class="bi bi-calendar-event"></i>
                            <small>${new Date(record.timestamp).toLocaleString()}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function htmlEscape(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    .stat-card h3 {
        transition: transform 0.2s ease;
    }
`;
document.head.appendChild(style);
</script>

<?php renderAdminFooter(); ?>