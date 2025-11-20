<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Debug: Check if we have drivers in the database
$debugQuery = "SELECT COUNT(*) as total_drivers FROM drivers";
$debugResult = $conn->query($debugQuery);
$totalDrivers = $debugResult ? $debugResult->fetch_assoc()['total'] : 0;

// Debug: Check pending drivers
$debugPendingQuery = "SELECT COUNT(*) as pending_drivers FROM drivers WHERE verification_status = 'pending' OR verification_status IS NULL";
$debugPendingResult = $conn->query($debugPendingQuery);
$pendingDrivers = $debugPendingResult ? $debugPendingResult->fetch_assoc()['pending_drivers'] : 0;

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $driverId = intval($_POST['driver_id']);
    $action = $_POST['action'];
    
    // Debug: Log the action being performed
    error_log("Driver verification action: $action for driver ID: $driverId");
    
    if ($action === 'verify') {
        // First check if the driver exists in drivers table
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM drivers WHERE user_id = ?");
        $checkStmt->bind_param("i", $driverId);
        $checkStmt->execute();
        $driverExists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
        $checkStmt->close();
        
        if (!$driverExists) {
            $error = "Driver record not found in drivers table. Please ensure the driver has submitted their documents.";
        } else {
            // Update both users and drivers tables for verification
            $conn->begin_transaction();
            
            try {
                // Update users table
                $stmt1 = $conn->prepare("UPDATE users SET is_verified = 1, verification_status = 'verified' WHERE user_id = ? AND user_type = 'driver'");
                $stmt1->bind_param("i", $driverId);
                $result1 = $stmt1->execute();
                $affected1 = $stmt1->affected_rows;
                
                // Update drivers table
                $stmt2 = $conn->prepare("UPDATE drivers SET verification_status = 'verified' WHERE user_id = ?");
                $stmt2->bind_param("i", $driverId);
                $result2 = $stmt2->execute();
                $affected2 = $stmt2->affected_rows;
                
                if ($result1 && $result2 && ($affected1 > 0 || $affected2 > 0)) {
                    $conn->commit();
                    $success = "Driver verified successfully! Updated " . ($affected1 + $affected2) . " record(s).";
                    
                    // Redirect to refresh the page and show updated data - always go to pending tab
                    header("Location: drivers-verification.php?filter=pending&success=" . urlencode($success));
                    exit();
                } else {
                    $conn->rollback();
                    $error = "No records were updated. Driver may already be verified or user ID not found.";
                }
                
                $stmt1->close();
                $stmt2->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to verify driver: " . $e->getMessage();
            }
        }
    } elseif ($action === 'reject') {
        // First check if the driver exists in drivers table
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM drivers WHERE user_id = ?");
        $checkStmt->bind_param("i", $driverId);
        $checkStmt->execute();
        $driverExists = $checkStmt->get_result()->fetch_assoc()['count'] > 0;
        $checkStmt->close();
        
        if (!$driverExists) {
            $error = "Driver record not found in drivers table.";
        } else {
            // Update both users and drivers tables for rejection
            $conn->begin_transaction();
            
            try {
                // Update users table
                $stmt1 = $conn->prepare("UPDATE users SET verification_status = 'rejected' WHERE user_id = ? AND user_type = 'driver'");
                $stmt1->bind_param("i", $driverId);
                $result1 = $stmt1->execute();
                $affected1 = $stmt1->affected_rows;
                
                // Update drivers table
                $stmt2 = $conn->prepare("UPDATE drivers SET verification_status = 'rejected' WHERE user_id = ?");
                $stmt2->bind_param("i", $driverId);
                $result2 = $stmt2->execute();
                $affected2 = $stmt2->affected_rows;
                
                if ($result1 && $result2 && ($affected1 > 0 || $affected2 > 0)) {
                    $conn->commit();
                    $success = "Driver application rejected successfully!";
                    
                    // Redirect to refresh the page and show updated data - always go to pending tab
                    header("Location: drivers-verification.php?filter=pending&success=" . urlencode($success));
                    exit();
                } else {
                    $conn->rollback();
                    $error = "No records were updated. Driver may already be processed or user ID not found.";
                }
                
                $stmt1->close();
                $stmt2->close();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to reject driver: " . $e->getMessage();
            }
        }
    }
}

// Check for success message from URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Check for error message from URL
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// Get verification filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'pending';

// Build query based on filter - use JOIN to get data from both tables
$whereClause = "u.user_type = 'driver'";
switch($filter) {
    case 'pending':
        $whereClause .= " AND (d.verification_status = 'pending' OR d.verification_status IS NULL)";
        break;
    case 'verified':
        $whereClause .= " AND d.verification_status = 'verified'";
        break;
    case 'rejected':
        $whereClause .= " AND d.verification_status = 'rejected'";
        break;
}

$query = "SELECT u.user_id, u.name, u.email, u.phone, u.license_number, u.tricycle_info, u.created_at, 
                 u.is_verified, d.verification_status, u.is_active,
                 d.or_cr_path, d.license_path, d.picture_path
          FROM users u 
          LEFT JOIN drivers d ON u.user_id = d.user_id
          WHERE $whereClause 
          ORDER BY u.created_at DESC";

$result = $conn->query($query);
$drivers = $result->fetch_all(MYSQLI_ASSOC);

renderAdminHeader("Driver Verification", "driver_verification");
?>
<link rel="stylesheet" href="../../public/css/drivers-verification.css">

<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle"></i> <?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter Tabs -->
<div class="content-card">
    <div class="verification-tabs">
        <a href="?filter=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">
            <i class="bi bi-clock"></i> Pending Verification
        </a>
        <a href="?filter=verified" class="tab-btn <?= $filter === 'verified' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Verified Drivers
        </a>
        <a href="?filter=rejected" class="tab-btn <?= $filter === 'rejected' ? 'active' : '' ?>">
            <i class="bi bi-x-circle"></i> Rejected Applications
        </a>
        <a href="?filter=all" class="tab-btn <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="bi bi-list"></i> All Drivers
        </a>
    </div>
</div>

<!-- Drivers List -->
<div class="content-card">
    <h3>
        <i class="bi bi-shield-check"></i>
        <?php 
        switch($filter) {
            case 'pending': echo 'Pending Verification'; break;
            case 'verified': echo 'Verified Drivers'; break;
            case 'rejected': echo 'Rejected Applications'; break;
            default: echo 'All Drivers'; break;
        }
        ?> (<?= count($drivers) ?>)
    </h3>
    
    <?php if (count($drivers) > 0): ?>
        <div class="row g-4">
            <?php foreach ($drivers as $driver): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="driver-card">
                        <div class="driver-header">
                            <h5><?= htmlspecialchars($driver['name']) ?></h5>
                            <span class="verification-badge verification-<?= $driver['verification_status'] ?: 'pending' ?>">
                                <?= ucfirst($driver['verification_status'] ?: 'pending') ?>
                            </span>
                        </div>
                        
                        <div class="driver-info">
                            <div class="info-item">
                                <i class="bi bi-envelope"></i>
                                <span><?= htmlspecialchars($driver['email']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-phone"></i>
                                <span><?= htmlspecialchars($driver['phone']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-card-text"></i>
                                <span>License: <?= htmlspecialchars($driver['license_number']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-car-front"></i>
                                <span><?= htmlspecialchars($driver['tricycle_info']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="bi bi-calendar"></i>
                                <span>Applied: <?= date('M d, Y', strtotime($driver['created_at'])) ?></span>
                            </div>
                        </div>
                        
                        <!-- Document Preview -->
                        <?php if ($driver['license_path'] || $driver['or_cr_path'] || $driver['picture_path']): ?>
                            <div class="documents-preview">
                                <h6><i class="bi bi-file-earmark-text"></i> Uploaded Documents</h6>
                                <div class="documents-grid">
                                    <?php if ($driver['picture_path']): ?>
                                        <div class="document-item">
                                            <img src="../../<?= htmlspecialchars($driver['picture_path']) ?>" 
                                                 alt="Driver Photo" 
                                                 class="document-thumbnail"
                                                 onclick="viewDocument('../../<?= htmlspecialchars($driver['picture_path']) ?>', 'Driver Photo')">
                                            <small>Photo</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($driver['license_path']): ?>
                                        <div class="document-item">
                                            <?php if (str_ends_with(strtolower($driver['license_path']), '.pdf')): ?>
                                                <div class="document-thumbnail pdf-thumb"
                                                     onclick="viewDocument('../../<?= htmlspecialchars($driver['license_path']) ?>', 'Driver License')">
                                                    <i class="bi bi-file-pdf"></i>
                                                </div>
                                            <?php else: ?>
                                                <img src="../../<?= htmlspecialchars($driver['license_path']) ?>" 
                                                     alt="License" 
                                                     class="document-thumbnail"
                                                     onclick="viewDocument('../../<?= htmlspecialchars($driver['license_path']) ?>', 'Driver License')">
                                            <?php endif; ?>
                                            <small>License</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($driver['or_cr_path']): ?>
                                        <div class="document-item">
                                            <?php if (str_ends_with(strtolower($driver['or_cr_path']), '.pdf')): ?>
                                                <div class="document-thumbnail pdf-thumb"
                                                     onclick="viewDocument('../../<?= htmlspecialchars($driver['or_cr_path']) ?>', 'OR/CR Document')">
                                                    <i class="bi bi-file-pdf"></i>
                                                </div>
                                            <?php else: ?>
                                                <img src="../../<?= htmlspecialchars($driver['or_cr_path']) ?>" 
                                                     alt="OR/CR" 
                                                     class="document-thumbnail"
                                                     onclick="viewDocument('../../<?= htmlspecialchars($driver['or_cr_path']) ?>', 'OR/CR Document')">
                                            <?php endif; ?>
                                            <small>OR/CR</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (($driver['verification_status'] ?: 'pending') === 'pending'): ?>
                            <div class="driver-actions" id="driver-actions-<?= $driver['user_id'] ?>">
                                <button type="button" class="btn btn-success btn-sm" 
                                        onclick="verifyDriver(<?= $driver['user_id'] ?>, this)">
                                    <i class="bi bi-check-circle"></i> Verify
                                </button>
                                
                                <button type="button" class="btn btn-danger btn-sm" 
                                        onclick="rejectDriver(<?= $driver['user_id'] ?>, this)">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="driver-actions">
                            <a href="user-details.php?id=<?= $driver['user_id'] ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                            <a href="user-edit.php?id=<?= $driver['user_id'] ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-shield-x"></i>
            <h5>No Drivers Found</h5>
            <p>
                <?php 
                switch($filter) {
                    case 'pending': echo 'No drivers are currently pending verification.'; break;
                    case 'verified': echo 'No verified drivers found.'; break;
                    case 'rejected': echo 'No rejected applications found.'; break;
                    default: echo 'No drivers registered yet.'; break;
                }
                ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Document Viewer Modal -->
<div class="modal fade" id="documentModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="documentModalTitle">
                    <i class="bi bi-file-earmark-text me-2"></i>Document Viewer
                </h5>
                <div class="d-flex gap-2">
                    <!-- Download/Open button -->
                    <button type="button" class="btn btn-light btn-sm" id="downloadBtn" onclick="downloadDocument()">
                        <i class="bi bi-download"></i> Download
                    </button>
                    <!-- Close button -->
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i> Close
                    </button>
                </div>
            </div>
            <div class="modal-body text-center p-0" onclick="event.stopPropagation()">
                <div id="documentContent" style="min-height: 600px; background: #f8f9fa;"></div>
            </div>
            <div class="modal-footer bg-light">
                <div class="d-flex justify-content-between w-100">
                    <small class="text-muted" id="documentInfo">Document loaded</small>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Close">
                            <i class="bi bi-x-circle"></i> Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentDocumentPath = '';

function viewDocument(path, title) {
    currentDocumentPath = path;
    document.getElementById('documentModalTitle').innerHTML = `<i class="bi bi-file-earmark-text me-2"></i>${title}`;
    const content = document.getElementById('documentContent');
    const info = document.getElementById('documentInfo');
    
    // Show loading state
    content.innerHTML = `
        <div class="d-flex justify-content-center align-items-center" style="height: 600px;">
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted">Loading document...</p>
            </div>
        </div>
    `;
    
    // Show modal immediately
    const modal = new bootstrap.Modal(document.getElementById('documentModal'));
    modal.show();
    
    // Load document content
    setTimeout(() => {
        if (path.toLowerCase().endsWith('.pdf')) {
            content.innerHTML = `
                <embed src="${path}" type="application/pdf" width="100%" height="600px" style="border: none;" />
                <div class="p-3 text-center bg-white border-top">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        If the PDF doesn't load, <a href="${path}" target="_blank" class="text-decoration-none">click here to open in new tab</a>
                    </small>
                </div>
            `;
            info.innerHTML = `<i class="bi bi-file-pdf"></i> PDF Document - ${title}`;
        } else {
            content.innerHTML = `
                <div class="p-3">
                    <img src="${path}" alt="${title}" class="img-fluid" style="max-height: 600px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" 
                         onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtc2l6ZT0iMThweCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkltYWdlIG5vdCBmb3VuZDwvdGV4dD48L3N2Zz4='; this.parentNode.innerHTML += '<p class=&quot;text-danger mt-2&quot;><i class=&quot;bi bi-exclamation-triangle&quot;></i> Image could not be loaded</p>';">
                </div>
            `;
            info.innerHTML = `<i class="bi bi-image"></i> Image Document - ${title}`;
        }
    }, 500);
}

function downloadDocument() {
    if (currentDocumentPath) {
        // Create a temporary link to download the file
        const link = document.createElement('a');
        link.href = currentDocumentPath;
        link.download = currentDocumentPath.split('/').pop(); // Get filename from path
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Enhanced modal close functionality
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('documentModal');
    
    if (modalElement) {
        // Allow backdrop click to close modal
        modalElement.addEventListener('click', function(event) {
            if (event.target === modalElement) {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        });
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('documentModal'));
        if (modal) {
            modal.hide();
        }
    }
});

// Reset modal content when closed
document.getElementById('documentModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('documentContent').innerHTML = '';
    currentDocumentPath = '';
});

// Toast notification function
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
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
    
    setTimeout(() => {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999;';
    document.body.appendChild(container);
    return container;
}

// AJAX: Verify driver
function verifyDriver(driverId, button) {
    if (!confirm('Verify this driver?')) return;
    
    // Add loading state
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';
    
    const formData = new FormData();
    formData.append('action', 'verify_driver');
    formData.append('driver_id', driverId);
    
    fetch('api-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Remove the driver card from pending list
            setTimeout(() => {
                const driverCard = button.closest('.driver-card');
                if (driverCard) {
                    driverCard.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => driverCard.remove(), 300);
                }
                // Reload if no more pending drivers
                checkEmptyState();
            }, 1000);
        } else {
            showToast(data.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-check-circle"></i> Verify';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-check-circle"></i> Verify';
        console.error('Error:', error);
    });
}

// AJAX: Reject driver
function rejectDriver(driverId, button) {
    if (!confirm('Reject this driver application?')) return;
    
    // Add loading state
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Rejecting...';
    
    const formData = new FormData();
    formData.append('action', 'reject_driver');
    formData.append('driver_id', driverId);
    
    fetch('api-admin-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Remove the driver card from pending list
            setTimeout(() => {
                const driverCard = button.closest('.driver-card');
                if (driverCard) {
                    driverCard.style.animation = 'fadeOut 0.3s ease-out';
                    setTimeout(() => driverCard.remove(), 300);
                }
                // Reload if no more pending drivers
                checkEmptyState();
            }, 1000);
        } else {
            showToast(data.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-x-circle"></i> Reject';
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-x-circle"></i> Reject';
        console.error('Error:', error);
    });
}

function checkEmptyState() {
    const remainingDrivers = document.querySelectorAll('.driver-card').length;
    if (remainingDrivers === 0) {
        setTimeout(() => location.reload(), 1500);
    }
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
    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: scale(1);
        }
        to {
            opacity: 0;
            transform: scale(0.9);
        }
    }
`;
document.head.appendChild(style);
</script>

<?php renderAdminFooter(); ?>