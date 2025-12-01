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

// Get all verified drivers without RFID cards
$query = "SELECT u.user_id, u.name, u.email, u.phone,
                 d.driver_id, d.license_number, d.tricycle_info, d.verification_status
          FROM users u
          JOIN rfid_drivers d ON u.user_id = d.user_id
          WHERE u.user_type = 'driver' 
          AND d.verification_status = 'verified'
          AND (d.rfid_uid IS NULL OR d.rfid_uid = '')
          ORDER BY u.name ASC";

$result = $conn->query($query);
$availableDrivers = [];
if ($result) {
    $availableDrivers = $result->fetch_all(MYSQLI_ASSOC);
}

renderAdminHeader("RFID Learning Mode", "rfid");
?>
<link rel="stylesheet" href="../../public/css/rfid-management.css">

<style>
.learning-container {
    max-width: 900px;
    margin: 0 auto;
}

.card-detected {
    background: linear-gradient(135deg, #16a34a, #22c55e);
    color: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 30px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.detected-card {
    background: rgba(255, 255, 255, 0.2);
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.detected-card h3 {
    font-size: 32px;
    font-family: monospace;
    margin: 10px 0;
}

.driver-select-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.3s;
}

.driver-select-card:hover {
    border-color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
    transform: translateY(-2px);
}

.driver-select-card.selected {
    border-color: #16a34a;
    background: #f0fdf4;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-waiting { background: #fbbf24; }
.status-detecting { background: #3b82f6; animation: blink 1s infinite; }
.status-success { background: #22c55e; }

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.instruction-box {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.polling-status {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    text-align: center;
}
</style>

<div class="learning-container">
  <!-- Back Button -->
  <div class="mb-4">
    <a href="rfid-management.php" class="btn btn-custom-outline">
      <i class="bi bi-arrow-left"></i> Back to RFID Management
    </a>
  </div>

  <!-- Instruction Box -->
  <div class="instruction-box">
    <h5><i class="bi bi-info-circle-fill"></i> How RFID Learning Mode Works</h5>
    <ol class="mb-0">
      <li>Click "Start Learning Mode" to begin scanning for new RFID cards</li>
      <li>Place an unregistered RFID card on the reader</li>
      <li>The system will automatically detect the card UID</li>
      <li>Select a driver from the list below</li>
      <li>Click "Assign to Selected Driver" to complete the registration</li>
    </ol>
  </div>

  <!-- Polling Status -->
  <div class="polling-status">
    <div id="statusIndicator">
      <span class="status-indicator status-waiting"></span>
      <span id="statusText">Ready to start learning mode</span>
    </div>
    <div class="mt-3">
      <button id="startBtn" class="btn btn-custom" onclick="startLearning()">
        <i class="bi bi-play-fill"></i> Start Learning Mode
      </button>
      <button id="stopBtn" class="btn btn-danger" onclick="stopLearning()" style="display: none;">
        <i class="bi bi-stop-fill"></i> Stop Learning
      </button>
    </div>
  </div>

  <!-- Detected Card Display -->
  <div id="detectedCard" style="display: none;">
    <div class="card-detected">
      <i class="bi bi-credit-card-2-front" style="font-size: 64px;"></i>
      <h4 class="mt-3">New RFID Card Detected!</h4>
      <div class="detected-card">
        <p class="mb-1">Card UID:</p>
        <h3 id="detectedUid">-</h3>
      </div>
      <p class="mb-0"><i class="bi bi-arrow-down"></i> Select a driver below to assign this card</p>
    </div>
  </div>

  <!-- Available Drivers -->
  <div class="content-card">
    <h5><i class="bi bi-people"></i> Available Drivers (Without RFID Cards)</h5>
    
    <?php if (count($availableDrivers) > 0): ?>
      <div class="mb-3">
        <input type="text" class="form-control" id="driverSearch" 
               placeholder="Search drivers..." onkeyup="filterDrivers()">
      </div>
      
      <div id="driversList" style="max-height: 400px; overflow-y: auto;">
        <?php foreach ($availableDrivers as $driver): ?>
          <div class="driver-select-card" onclick="selectDriver(<?= $driver['user_id'] ?>, this)">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-1">
                  <input type="radio" name="selectedDriver" value="<?= $driver['user_id'] ?>" 
                         style="margin-right: 8px;">
                  <?= htmlspecialchars($driver['name']) ?>
                </h6>
                <p class="mb-0 text-muted small">
                  <i class="bi bi-envelope"></i> <?= htmlspecialchars($driver['email']) ?> | 
                  <i class="bi bi-phone"></i> <?= htmlspecialchars($driver['phone']) ?>
                </p>
              </div>
              <div class="text-end">
                <span class="badge bg-info">#<?= $driver['driver_id'] ?></span>
                <p class="mb-0 text-muted small mt-1">
                  <i class="bi bi-car-front"></i> <?= htmlspecialchars($driver['tricycle_info']) ?>
                </p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <div class="mt-3 text-center">
        <button id="assignBtn" class="btn btn-custom btn-lg" onclick="assignCard()" disabled>
          <i class="bi bi-check-circle"></i> Assign to Selected Driver
        </button>
      </div>
    <?php else: ?>
      <div class="empty-state py-5">
        <i class="bi bi-check-circle" style="font-size: 64px; color: #22c55e;"></i>
        <h5 class="mt-3">All verified drivers have RFID cards!</h5>
        <p class="text-muted">There are no verified drivers without RFID assignments.</p>
        <a href="rfid-management.php" class="btn btn-custom mt-3">
          <i class="bi bi-arrow-left"></i> Back to RFID Management
        </a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent Detection Log -->
  <div class="content-card mt-4">
    <h5><i class="bi bi-clock-history"></i> Detection Log</h5>
    <div id="detectionLog" style="max-height: 200px; overflow-y: auto;">
      <p class="text-muted text-center py-3">No detections yet. Start learning mode to begin.</p>
    </div>
  </div>
</div>

<script>
let learningActive = false;
let pollingInterval = null;
let detectedUid = null;
let selectedDriverId = null;
let detectionLog = [];

// Start learning mode
function startLearning() {
    // Enable learning mode on server
    fetch('../driver/rfid-learning-handler.php?action=enable', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                learningActive = true;
                document.getElementById('startBtn').style.display = 'none';
                document.getElementById('stopBtn').style.display = 'inline-block';
                
                updateStatus('detecting', '🔓 ACTIVE: Scanning for RFID cards...');
                
                // Poll for new cards every 2 seconds
                pollingInterval = setInterval(pollForCards, 2000);
                addLog('✅ Learning mode ENABLED - Unknown cards will be detected');
            } else {
                alert('Failed to enable learning mode');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to start learning mode');
        });
}

// Stop learning mode
function stopLearning() {
    // Disable learning mode on server
    fetch('../driver/rfid-learning-handler.php?action=disable', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            learningActive = false;
            clearInterval(pollingInterval);
            
            document.getElementById('startBtn').style.display = 'inline-block';
            document.getElementById('stopBtn').style.display = 'none';
            
            updateStatus('waiting', '🔒 Learning mode DISABLED - Intruders blocked');
            addLog('🔒 Learning mode DISABLED - Unknown cards will NOT be recorded');
        });
}

// Update status indicator
function updateStatus(status, text) {
    const indicator = document.querySelector('.status-indicator');
    const statusText = document.getElementById('statusText');
    
    indicator.className = 'status-indicator status-' + status;
    statusText.textContent = text;
}

// Poll for new cards (simulated - in real implementation, this would check a server endpoint)
function pollForCards() {
    // Check the server endpoint for new card detections
    fetch('../driver/rfid-learning-handler.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_card && data.uid) {
                cardDetected(data.uid);
            } else {
                updateStatus('detecting', 'Scanning... Place a card on the reader');
            }
        })
        .catch(error => {
            console.error('Polling error:', error);
            updateStatus('detecting', 'Scanning... (Connection issue)');
        });
}

// Card detected handler
function cardDetected(uid) {
    detectedUid = uid.toUpperCase();
    
    // Check if card is already registered
    fetch('api-rfid-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'test', rfid_uid: detectedUid })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.driver) {
            // Card already registered
            updateStatus('waiting', 'Card already registered to ' + data.driver.name);
            addLog('❌ Card ' + detectedUid + ' already registered to ' + data.driver.name);
            
            setTimeout(() => {
                if (learningActive) {
                    updateStatus('detecting', 'Scanning for RFID cards...');
                }
            }, 3000);
        } else {
            // New card detected
            document.getElementById('detectedCard').style.display = 'block';
            document.getElementById('detectedUid').textContent = detectedUid;
            updateStatus('success', 'New card detected! Select a driver to assign');
            addLog('✅ New card detected: ' + detectedUid);
            
            // Stop learning until this card is assigned or cancelled
            stopLearning();
        }
    });
}

// Manual card entry (for testing without hardware)
function manualCardEntry() {
    const uid = prompt('Enter RFID card UID (for testing):');
    if (uid && uid.trim()) {
        cardDetected(uid.trim());
    }
}

// Add keyboard shortcut for manual entry (for development/testing)
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'm') {
        e.preventDefault();
        manualCardEntry();
    }
});

// Select driver
function selectDriver(userId, element) {
    selectedDriverId = userId;
    
    // Remove selected class from all cards
    document.querySelectorAll('.driver-select-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    element.classList.add('selected');
    
    // Check the radio button
    element.querySelector('input[type="radio"]').checked = true;
    
    // Enable assign button if card is detected
    if (detectedUid) {
        document.getElementById('assignBtn').disabled = false;
    }
}

// Assign card to selected driver
function assignCard() {
    if (!detectedUid || !selectedDriverId) {
        alert('Please detect a card and select a driver first');
        return;
    }
    
    if (!confirm('Assign card ' + detectedUid + ' to selected driver?')) {
        return;
    }
    
    const assignBtn = document.getElementById('assignBtn');
    assignBtn.disabled = true;
    assignBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Assigning...';
    
    fetch('api-rfid-actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'register',
            rfid_uid: detectedUid,
            user_id: selectedDriverId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            addLog('✅ Card ' + detectedUid + ' assigned to ' + data.driver_name);
            alert('RFID card assigned successfully to ' + data.driver_name);
            
            // Reload page to update available drivers list
            location.reload();
        } else {
            alert('Error: ' + data.message);
            assignBtn.disabled = false;
            assignBtn.innerHTML = '<i class="bi bi-check-circle"></i> Assign to Selected Driver';
            addLog('❌ Failed to assign card: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to assign card');
        assignBtn.disabled = false;
        assignBtn.innerHTML = '<i class="bi bi-check-circle"></i> Assign to Selected Driver';
    });
}

// Add log entry
function addLog(message) {
    const timestamp = new Date().toLocaleTimeString();
    detectionLog.unshift({ time: timestamp, message: message });
    
    // Keep only last 10 entries
    if (detectionLog.length > 10) {
        detectionLog = detectionLog.slice(0, 10);
    }
    
    updateLogDisplay();
}

// Update log display
function updateLogDisplay() {
    const logDiv = document.getElementById('detectionLog');
    
    if (detectionLog.length === 0) {
        logDiv.innerHTML = '<p class="text-muted text-center py-3">No detections yet. Start learning mode to begin.</p>';
        return;
    }
    
    let html = '<div class="list-group">';
    detectionLog.forEach(entry => {
        html += `<div class="list-group-item"><small class="text-muted">[${entry.time}]</small> ${entry.message}</div>`;
    });
    html += '</div>';
    
    logDiv.innerHTML = html;
}

// Filter drivers
function filterDrivers() {
    const input = document.getElementById('driverSearch');
    const filter = input.value.toUpperCase();
    const cards = document.querySelectorAll('.driver-select-card');
    
    cards.forEach(card => {
        const text = card.textContent || card.innerText;
        card.style.display = text.toUpperCase().indexOf(filter) > -1 ? '' : 'none';
    });
}

// Show instructions on load
window.addEventListener('load', function() {
    addLog('System ready. Click "Start Learning Mode" to begin.');
    
    // Show a tip about manual entry for testing
    console.log('💡 Tip: Press Ctrl+M to manually enter a card UID for testing without hardware');
});
</script>

<?php 
renderAdminFooter();
$db->closeConnection();
?>
