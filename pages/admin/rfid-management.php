<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../auth/login-form.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get all verified drivers with their RFID status
$query = "SELECT u.user_id, u.name, u.email, u.phone, u.status,
                 d.driver_id, d.license_number, d.tricycle_info, d.rfid_uid, 
                 d.verification_status, d.is_online, d.last_attendance, d.card_status
          FROM users u
          JOIN rfid_drivers d ON u.user_id = d.user_id
          WHERE u.user_type = 'driver'
          ORDER BY d.verification_status DESC, u.name ASC";

$result = $conn->query($query);
$drivers = [];
if ($result) {
    $drivers = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_drivers,
    SUM(CASE WHEN d.rfid_uid IS NOT NULL AND d.rfid_uid != '' THEN 1 ELSE 0 END) as drivers_with_rfid,
    SUM(CASE WHEN d.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_drivers,
    SUM(CASE WHEN d.is_online = 1 THEN 1 ELSE 0 END) as online_drivers
FROM users u
JOIN rfid_drivers d ON u.user_id = d.user_id
WHERE u.user_type = 'driver'";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

renderAdminHeader("RFID Card Management", "rfid");
?>
<link rel="stylesheet" href="../../public/css/rfid-management.css">
<style>
/* Ensure modals appear on top of everything */
.modal {
    z-index: 9999 !important;
}
.modal-backdrop {
    z-index: 9998 !important;
}
.modal-dialog {
    z-index: 10000 !important;
}
</style>

<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-primary">
        <i class="bi bi-people-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $stats['total_drivers'] ?></div>
        <div class="stat-label">Total Drivers</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-success">
        <i class="bi bi-credit-card-2-front-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $stats['drivers_with_rfid'] ?></div>
        <div class="stat-label">Cards Assigned</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-info">
        <i class="bi bi-shield-check"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $stats['verified_drivers'] ?></div>
        <div class="stat-label">Verified Drivers</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-warning">
        <i class="bi bi-broadcast"></i>
      </div>
      <div class="stat-content">
        <div class="stat-value"><?= $stats['online_drivers'] ?></div>
        <div class="stat-label">Online Now</div>
      </div>
    </div>
  </div>
</div>

<!-- Action Buttons -->
<div class="mb-4 d-flex gap-2 flex-wrap">
  <button class="btn btn-custom" onclick="showBulkAssignModal()">
    <i class="bi bi-plus-circle"></i> Assign New Card
  </button>
  <a href="rfid-learning.php" class="btn btn-custom-outline">
    <i class="bi bi-wifi"></i> RFID Learning Mode
  </a>
  <button class="btn btn-custom-outline" onclick="testRFIDCard()">
    <i class="bi bi-cpu"></i> Test Card UID
  </button>
  <button class="btn btn-custom-outline" onclick="refreshPage()">
    <i class="bi bi-arrow-clockwise"></i> Refresh
  </button>
</div>

<!-- Drivers Table -->
<div class="content-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5><i class="bi bi-table"></i> Driver RFID Cards</h5>
    <div class="search-box">
      <i class="bi bi-search"></i>
      <input type="text" id="searchInput" placeholder="Search drivers..." onkeyup="filterTable()">
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-hover" id="driversTable">
      <thead>
        <tr>
          <th>Driver ID</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Tricycle Info</th>
          <th>RFID Card UID</th>
          <th>Status</th>
          <th>Last Attendance</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($drivers) > 0): ?>
          <?php foreach ($drivers as $driver): ?>
            <tr class="<?= $driver['verification_status'] !== 'verified' ? 'table-warning' : '' ?>">
              <td><strong>#<?= $driver['driver_id'] ?></strong></td>
              <td>
                <div class="driver-info">
                  <strong><?= htmlspecialchars($driver['name']) ?></strong>
                  <small class="text-muted d-block"><?= htmlspecialchars($driver['email']) ?></small>
                </div>
              </td>
              <td><?= htmlspecialchars($driver['phone']) ?></td>
              <td><?= htmlspecialchars($driver['tricycle_info']) ?></td>
              <td>
                <?php if (!empty($driver['rfid_uid'])): ?>
                  <span class="rfid-badge rfid-assigned">
                    <i class="bi bi-credit-card-2-front"></i> <?= htmlspecialchars($driver['rfid_uid']) ?>
                  </span>
                <?php else: ?>
                  <span class="rfid-badge rfid-not-assigned">
                    <i class="bi bi-x-circle"></i> Not Assigned
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge status-<?= $driver['verification_status'] ?>">
                  <?= ucfirst($driver['verification_status']) ?>
                </span>
                <?php if ($driver['is_online']): ?>
                  <span class="status-badge bg-success ms-1">
                    <i class="bi bi-circle-fill"></i> Online
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($driver['last_attendance']): ?>
                  <small><?= date('M d, Y H:i', strtotime($driver['last_attendance'])) ?></small>
                <?php else: ?>
                  <small class="text-muted">Never</small>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-buttons">
                  <?php if ($driver['verification_status'] === 'verified'): ?>
                    <?php if (!empty($driver['rfid_uid'])): ?>
                      <?php $cardStatus = $driver['card_status'] ?? 'active'; ?>
                      
                      <?php if ($cardStatus === 'active'): ?>
                        <button class="btn btn-sm btn-warning" 
                                onclick="updateRFID(<?= $driver['user_id'] ?>, '<?= htmlspecialchars($driver['name']) ?>', '<?= htmlspecialchars($driver['rfid_uid']) ?>')"
                                title="Update RFID">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" 
                                onclick="removeRFID(<?= $driver['user_id'] ?>, '<?= htmlspecialchars($driver['name']) ?>')"
                                title="Remove RFID">
                          <i class="bi bi-trash"></i>
                        </button>
                        <button class="btn btn-sm btn-secondary" 
                                onclick="blockCard(<?= $driver['user_id'] ?>, '<?= htmlspecialchars($driver['name']) ?>')"
                                title="Block/Report Card">
                          <i class="bi bi-shield-x"></i>
                        </button>
                      <?php else: ?>
                        <span class="badge bg-danger me-2">
                          <?= strtoupper($cardStatus) ?>
                        </span>
                        <button class="btn btn-sm btn-success" 
                                onclick="unblockCard(<?= $driver['user_id'] ?>, '<?= htmlspecialchars($driver['name']) ?>')"
                                title="Unblock Card">
                          <i class="bi bi-shield-check"></i> Unblock
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      <button class="btn btn-sm btn-success" 
                              onclick="assignRFID(<?= $driver['user_id'] ?>, '<?= htmlspecialchars($driver['name']) ?>')"
                              title="Assign RFID">
                        <i class="bi bi-plus-circle"></i> Assign
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted small">Not Verified</span>
                  <?php endif; ?>
                  <a href="user-details.php?id=<?= $driver['user_id'] ?>" 
                     class="btn btn-sm btn-info" 
                     title="View Details">
                    <i class="bi bi-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="text-center py-4">
              <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
              <p class="text-muted mt-2">No drivers found in the system.</p>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Filter table by search input
function filterTable() {
  const input = document.getElementById('searchInput');
  const filter = input.value.toUpperCase();
  const table = document.getElementById('driversTable');
  const tr = table.getElementsByTagName('tr');
  
  for (let i = 1; i < tr.length; i++) {
    const td = tr[i].getElementsByTagName('td');
    let found = false;
    
    for (let j = 0; j < td.length; j++) {
      if (td[j]) {
        const txtValue = td[j].textContent || td[j].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
          found = true;
          break;
        }
      }
    }
    
    tr[i].style.display = found ? '' : 'none';
  }
}

// Assign RFID to driver
function assignRFID(userId, name) {
  document.getElementById('userId').value = userId;
  document.getElementById('driverName').value = name;
  document.getElementById('rfidUid').value = '';
  document.getElementById('action').value = 'assign';
  document.getElementById('modalTitle').textContent = 'Assign RFID Card';
  document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Assign Card';
  
  const modal = new bootstrap.Modal(document.getElementById('rfidModal'));
  modal.show();
}

// Update existing RFID
function updateRFID(userId, name, currentUid) {
  document.getElementById('userId').value = userId;
  document.getElementById('driverName').value = name;
  document.getElementById('rfidUid').value = currentUid;
  document.getElementById('action').value = 'update';
  document.getElementById('modalTitle').textContent = 'Update RFID Card';
  document.getElementById('submitBtn').innerHTML = '<i class="bi bi-pencil"></i> Update Card';
  
  const modal = new bootstrap.Modal(document.getElementById('rfidModal'));
  modal.show();
}

// Remove RFID from driver
function removeRFID(userId, name) {
  if (!confirm(`Remove RFID card from ${name}?`)) return;
  
  fetch('api-rfid-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'remove', user_id: userId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to remove RFID card');
  });
}

// Submit RFID form
function submitRFID(event) {
  event.preventDefault();
  
  const formData = new FormData(event.target);
  const data = Object.fromEntries(formData.entries());
  
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
  
  fetch('api-rfid-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert('Error: ' + data.message);
      document.getElementById('submitBtn').disabled = false;
      document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Save RFID Card';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to save RFID card');
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtn').innerHTML = '<i class="bi bi-check-circle"></i> Save RFID Card';
  });
}

// Test RFID card
function testRFIDCard() {
  const modal = new bootstrap.Modal(document.getElementById('testModal'));
  modal.show();
}

function performTest() {
  const uid = document.getElementById('testUid').value.trim().toUpperCase();
  if (!uid) {
    alert('Please enter a card UID');
    return;
  }
  
  fetch('api-rfid-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'test', rfid_uid: uid })
  })
  .then(response => response.json())
  .then(data => {
    const resultDiv = document.getElementById('testResult');
    resultDiv.style.display = 'block';
    
    if (data.success && data.driver) {
      resultDiv.innerHTML = `
        <div class="alert alert-success">
          <h6><i class="bi bi-check-circle-fill"></i> Card Found!</h6>
          <p class="mb-1"><strong>Driver:</strong> ${data.driver.name}</p>
          <p class="mb-1"><strong>Phone:</strong> ${data.driver.phone}</p>
          <p class="mb-1"><strong>Tricycle:</strong> ${data.driver.tricycle_info}</p>
          <p class="mb-0"><strong>Status:</strong> <span class="badge bg-${data.driver.verification_status === 'verified' ? 'success' : 'warning'}">${data.driver.verification_status}</span></p>
        </div>
      `;
    } else {
      resultDiv.innerHTML = `
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle-fill"></i> 
          <strong>Card Not Found</strong>
          <p class="mb-0">This RFID card is not assigned to any driver.</p>
        </div>
      `;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while testing the card');
  });
}

// Block card
function blockCard(userId, name) {
  document.getElementById('blockUserId').value = userId;
  document.getElementById('blockDriverName').value = name;
  
  const modalEl = document.getElementById('blockModal');
  const modal = new bootstrap.Modal(modalEl, {
    backdrop: true,
    keyboard: true,
    focus: true
  });
  
  // Ensure backdrop z-index
  modalEl.addEventListener('shown.bs.modal', function() {
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.style.zIndex = '9998';
    }
    modalEl.style.zIndex = '9999';
  });
  
  modal.show();
}

// Submit block
function submitBlock(e) {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = {
    action: 'block',
    user_id: formData.get('user_id'),
    status: formData.get('status'),
    reason: formData.get('reason')
  };
  
  fetch('api-rfid-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.text();
  })
  .then(text => {
    try {
      const data = JSON.parse(text);
      if (data.success) {
        alert(data.message);
        location.reload();
      } else {
        alert('Error: ' + data.message);
      }
    } catch (e) {
      console.error('Invalid JSON response:', text);
      alert('Error: Invalid server response. Check console for details.');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('An error occurred while blocking the card. Error: ' + error.message);
  });
}

// Unblock card
function unblockCard(userId, name) {
  if (!confirm(`Are you sure you want to unblock the card for ${name}?`)) {
    return;
  }
  
  fetch('api-rfid-actions.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'unblock', user_id: userId })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      location.reload();
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('Failed to test card');
  });
}

function showBulkAssignModal() {
  // Show the first unassigned verified driver
  const table = document.getElementById('driversTable');
  const rows = table.getElementsByTagName('tr');
  
  for (let i = 1; i < rows.length; i++) {
    const assignBtn = rows[i].querySelector('.btn-success');
    if (assignBtn) {
      assignBtn.click();
      return;
    }
  }
  
  alert('All verified drivers already have RFID cards assigned!');
}

function refreshPage() {
  location.reload();
}

// Auto uppercase RFID input
document.addEventListener('DOMContentLoaded', function() {
  const rfidInput = document.getElementById('rfidUid');
  const testInput = document.getElementById('testUid');
  
  if (rfidInput) {
    rfidInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }
  
  if (testInput) {
    testInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
    });
  }
});
</script>

<?php 
renderAdminFooter();
?>

<!-- Modals moved outside container for proper z-index stacking -->

<!-- Assign/Update RFID Modal -->
<div class="modal fade" id="rfidModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Assign RFID Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="rfidForm" onsubmit="submitRFID(event)">
        <div class="modal-body">
          <input type="hidden" id="userId" name="user_id">
          <input type="hidden" id="action" name="action" value="assign">
          
          <div class="mb-3">
            <label class="form-label fw-bold">Driver Name</label>
            <input type="text" class="form-control" id="driverName" readonly>
          </div>
          
          <div class="mb-3">
            <label for="rfidUid" class="form-label fw-bold">RFID Card UID</label>
            <input type="text" class="form-control" id="rfidUid" name="rfid_uid" 
                   placeholder="Enter card UID (e.g., E317A32A)" 
                   pattern="[A-Fa-f0-9]+" 
                   required
                   style="text-transform: uppercase;">
            <small class="text-muted">Enter the hexadecimal UID from the RFID card (letters and numbers only)</small>
          </div>
          
          <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <strong>Tip:</strong> Use the "RFID Learning Mode" to automatically detect card UIDs.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-custom" id="submitBtn">
            <i class="bi bi-check-circle"></i> Save RFID Card
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Test RFID Modal -->
<div class="modal fade" id="testModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Test RFID Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="testUid" class="form-label fw-bold">Enter Card UID to Test</label>
          <input type="text" class="form-control" id="testUid" 
                 placeholder="Enter card UID (e.g., E317A32A)"
                 style="text-transform: uppercase;">
        </div>
        <button class="btn btn-custom w-100" onclick="performTest()">
          <i class="bi bi-search"></i> Check Card
        </button>
        
        <div id="testResult" class="mt-3" style="display: none;"></div>
      </div>
    </div>
  </div>
</div>

<!-- Block Card Modal -->
<div class="modal fade" id="blockModal" tabindex="-1" aria-labelledby="blockModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="blockModalLabel">Block/Report Card</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="blockForm" onsubmit="submitBlock(event)">
        <div class="modal-body">
          <input type="hidden" id="blockUserId" name="user_id">
          
          <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Warning:</strong> Blocking a card will prevent it from being used for clock in/out.
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Driver</label>
            <input type="text" class="form-control" id="blockDriverName" readonly>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Card Status</label>
            <select class="form-select" name="status" required>
              <option value="">Select status...</option>
              <option value="blocked">Blocked (Temporary)</option>
              <option value="lost">Lost</option>
              <option value="stolen">Stolen</option>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-bold">Reason</label>
            <textarea class="form-control" name="reason" rows="3" 
                      placeholder="Enter reason for blocking this card..." 
                      required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-shield-x"></i> Block Card
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php 
$db->closeConnection();
?>
