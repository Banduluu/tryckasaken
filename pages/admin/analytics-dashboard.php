<?php
session_start();
require_once '../../config/Database.php';
require_once 'layout-header.php';

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE user_type = 'passenger'");
$stats['total_passengers'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM rfid_drivers");
$stats['total_drivers'] = $result->fetch_assoc()['total'];

// Total bookings
$result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings");
$stats['total_bookings'] = $result->fetch_assoc()['total'];

// Bookings by status
$result = $conn->query("SELECT status, COUNT(*) as count FROM tricycle_bookings GROUP BY status");
$bookingStats = [];
while ($row = $result->fetch_assoc()) {
    $bookingStats[$row['status']] = $row['count'];
}

// Recent activity (last 30 days)
$result = $conn->query("SELECT COUNT(*) as total FROM tricycle_bookings WHERE booking_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['recent_bookings'] = $result->fetch_assoc()['total'];

// Monthly booking trends (last 6 months)
$monthlyStats = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $result = $conn->query("SELECT COUNT(*) as count FROM tricycle_bookings WHERE DATE_FORMAT(booking_time, '%Y-%m') = '$date'");
    $monthlyStats[$date] = $result->fetch_assoc()['count'];
}

renderAdminHeader("Analytics Dashboard", "analytics");
?>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-primary">
        <i class="bi bi-people"></i>
      </div>
      <div class="stat-info">
        <h3><?= number_format($stats['total_passengers']) ?></h3>
        <p>Total Passengers</p>
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-success">
        <i class="bi bi-car-front"></i>
      </div>
      <div class="stat-info">
        <h3><?= number_format($stats['total_drivers']) ?></h3>
        <p>Total Drivers</p>
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
      </div>
    </div>
  </div>
  
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon bg-warning">
        <i class="bi bi-clock-history"></i>
      </div>
      <div class="stat-info">
        <h3><?= number_format($stats['recent_bookings']) ?></h3>
        <p>Last 30 Days</p>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-4">
  <!-- Booking Status Chart -->
  <div class="col-md-6">
    <div class="content-card">
      <h5><i class="bi bi-pie-chart"></i> Booking Status Distribution</h5>
      <div class="chart-container">
        <canvas id="statusChart" width="400" height="300"></canvas>
      </div>
      <div class="chart-legend mt-3">
        <?php foreach ($bookingStats as $status => $count): ?>
          <div class="legend-item">
            <span class="legend-color status-<?= $status ?>"></span>
            <span><?= ucfirst($status) ?>: <?= $count ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  
  <!-- Monthly Trends -->
  <div class="col-md-6">
    <div class="content-card">
      <h5><i class="bi bi-graph-up"></i> Monthly Booking Trends</h5>
      <div class="chart-container">
        <canvas id="trendsChart" width="400" height="300"></canvas>
      </div>
      <div class="trends-summary mt-3">
        <?php 
        $months = array_keys($monthlyStats);
        $current = end($monthlyStats);
        $previous = prev($monthlyStats);
        $change = $previous > 0 ? (($current - $previous) / $previous) * 100 : 0;
        ?>
        <div class="trend-item">
          <span>Current Month: <strong><?= $current ?></strong></span>
          <span class="trend-change <?= $change >= 0 ? 'positive' : 'negative' ?>">
            <i class="bi bi-arrow-<?= $change >= 0 ? 'up' : 'down' ?>"></i>
            <?= abs(round($change, 1)) ?>%
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Activity -->
<div class="content-card mt-4">
  <h5><i class="bi bi-activity"></i> Recent Activity</h5>
  <?php
  $recentQuery = "SELECT b.*, p.name as passenger_name, d.name as driver_name 
                  FROM tricycle_bookings b 
                  LEFT JOIN users p ON b.user_id = p.user_id 
                  LEFT JOIN users d ON b.driver_id = d.user_id 
                  ORDER BY b.booking_time DESC LIMIT 5";
  $recentResult = $conn->query($recentQuery);
  
  if ($recentResult->num_rows > 0): ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Passenger</th>
            <th>Route</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($booking = $recentResult->fetch_assoc()): ?>
            <tr>
              <td><strong>#<?= $booking['id'] ?></strong></td>
              <td><?= htmlspecialchars($booking['passenger_name']) ?></td>
              <td>
                <small><?= htmlspecialchars($booking['location']) ?> → <?= htmlspecialchars($booking['destination']) ?></small>
              </td>
              <td>
                <span class="status-badge status-<?= $booking['status'] ?>">
                  <?= ucfirst($booking['status']) ?>
                </span>
              </td>
              <td><?= date('M d, H:i', strtotime($booking['booking_time'])) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <i class="bi bi-calendar-x"></i>
      <p>No recent activity found.</p>
    </div>
  <?php endif; ?>
</div>

<script>
// Simple chart implementation without external libraries
document.addEventListener('DOMContentLoaded', function() {
    // Status Chart (simple bar representation)
    const statusData = <?= json_encode($bookingStats) ?>;
    const statusCanvas = document.getElementById('statusChart');
    
    if (statusCanvas && Object.keys(statusData).length > 0) {
        const ctx = statusCanvas.getContext('2d');
        const total = Object.values(statusData).reduce((a, b) => a + b, 0);
        
        // Simple bar chart
        let x = 0;
        const colors = {
            'pending': '#ffc107',
            'accepted': '#17a2b8', 
            'completed': '#28a745',
            'cancelled': '#dc3545'
        };
        
        Object.entries(statusData).forEach(([status, count]) => {
            const width = (count / total) * statusCanvas.width;
            ctx.fillStyle = colors[status] || '#6c757d';
            ctx.fillRect(x, statusCanvas.height - 50, width, 40);
            x += width;
        });
    }
    
    // Monthly trends (simple line chart)
    const monthlyData = <?= json_encode($monthlyStats) ?>;
    const trendsCanvas = document.getElementById('trendsChart');
    
    if (trendsCanvas && Object.keys(monthlyData).length > 0) {
        const ctx = trendsCanvas.getContext('2d');
        const values = Object.values(monthlyData);
        const max = Math.max(...values);
        
        ctx.strokeStyle = '#28a745';
        ctx.lineWidth = 2;
        ctx.beginPath();
        
        values.forEach((value, index) => {
            const x = (index / (values.length - 1)) * trendsCanvas.width;
            const y = trendsCanvas.height - ((value / max) * (trendsCanvas.height - 40)) - 20;
            
            if (index === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });
        
        ctx.stroke();
    }
});
</script>

<?php renderAdminFooter(); ?>