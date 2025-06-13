<?php
// ==============================================
// FILE: staff/dashboard.php (Updated with booking management)
// ==============================================
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

// Get statistics with improved queries and error handling
try {
    // Today's orders - using DATE() function to extract date part from timestamp
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_orders = $stmt->fetchColumn() ?: 0;

    // Pending orders - including all active statuses
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending', 'confirmed', 'preparing')");
    $pending_orders = $stmt->fetchColumn() ?: 0;

    // Today's revenue with proper NULL handling
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE()");
    $today_revenue = $stmt->fetchColumn() ?: 0;

    // Menu items count
    $stmt = $pdo->query("SELECT COUNT(*) FROM menu_items");
    $total_items = $stmt->fetchColumn() ?: 0;

    // QR Code statistics - tables used today
    $stmt = $pdo->query("SELECT COUNT(DISTINCT table_number) FROM orders WHERE table_number IS NOT NULL AND DATE(created_at) = CURDATE()");
    $qr_orders_today = $stmt->fetchColumn() ?: 0;

    // Total QR orders today
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE table_number IS NOT NULL AND DATE(created_at) = CURDATE()");
    $total_qr_orders = $stmt->fetchColumn() ?: 0;

    // Booking statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM table_bookings WHERE status = 'pending'");
    $pending_bookings = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query("SELECT COUNT(*) FROM table_bookings WHERE booking_date = CURDATE()");
    $today_bookings = $stmt->fetchColumn() ?: 0;

} catch (PDOException $e) {
    // Log error and set default values
    error_log('Dashboard statistics error: ' . $e->getMessage());
    $today_orders = $pending_orders = $total_items = $qr_orders_today = $total_qr_orders = 0;
    $pending_bookings = $today_bookings = 0;
    $today_revenue = 0.00;
}

$page_title = 'Staff Dashboard';
include '../includes/header.php';
?>

<h1>Staff Dashboard</h1>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
    <div style="background: #3498db; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $today_orders; ?></h2>
        <p>Today's Orders</p>
    </div>
    
    <div style="background: #e67e22; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $pending_orders; ?></h2>
        <p>Pending Orders</p>
    </div>
    
    <div style="background: #27ae60; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2>RM <?php echo number_format($today_revenue, 2); ?></h2>
        <p>Today's Revenue</p>
    </div>
    
    <div style="background: #8e44ad; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $total_items; ?></h2>
        <p>Menu Items</p>
    </div>
    
    <div style="background: #f39c12; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $total_qr_orders; ?></h2>
        <p>QR Orders Today</p>
    </div>
    
    <div style="background: #16a085; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $qr_orders_today; ?></h2>
        <p>Tables Used Today</p>
    </div>
    
    <div style="background: #9b59b6; color: white; padding: 30px; border-radius: 8px; text-align: center; position: relative;">
        <h2><?php echo $pending_bookings; ?></h2>
        <p>Pending Bookings</p>
        <?php if ($pending_bookings > 0): ?>
            <div style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; animation: pulse 2s infinite;">
                !
            </div>
        <?php endif; ?>
    </div>
    
    <div style="background: #95a5a6; color: white; padding: 30px; border-radius: 8px; text-align: center;">
        <h2><?php echo $today_bookings; ?></h2>
        <p>Today's Bookings</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0;">
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>Quick Actions</h3>
        <div style="margin: 20px 0;">
            <a href="manage_items.php" class="btn" style="display: block; margin: 10px 0;">🍽️ Manage Menu Items</a>
            <a href="manage_orders.php" class="btn" style="display: block; margin: 10px 0;">📋 Manage Orders</a>
            <a href="manage_bookings.php" class="btn" style="display: block; margin: 10px 0; background: #9b59b6;">
                📅 Manage Table Bookings
                <?php if ($pending_bookings > 0): ?>
                    <span style="background: #e74c3c; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 8px;">
                        <?php echo $pending_bookings; ?> pending
                    </span>
                <?php endif; ?>
            </a>
            <a href="generate.php" class="btn" style="display: block; margin: 10px 0; background: #f39c12;">🔳 Generate Restaurant QR Code</a>
         </div>
    </div>
    
    <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>Recent Orders</h3>
        <?php
        try {
            $stmt = $pdo->query("
                SELECT o.id, o.order_type, o.table_number, o.total_amount, o.status, o.created_at, 
                       COALESCE(u.username, o.customer_name, 'Guest') as username
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $recent_orders = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Recent orders error: ' . $e->getMessage());
            $recent_orders = [];
        }
        ?>
        
        <?php if (empty($recent_orders)): ?>
            <p style="color: #666; margin: 20px 0;">No recent orders</p>
        <?php else: ?>
            <?php foreach ($recent_orders as $order): ?>
                <div style="padding: 10px 0; border-bottom: 1px solid #eee;">
                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <strong>Order #<?php echo $order['id']; ?></strong>
                            <?php if ($order['table_number']): ?>
                                <span style="background: #3498db; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">
                                    🔳 Table <?php echo $order['table_number']; ?>
                                </span>
                            <?php endif; ?>
                            <br>
                            <small><?php echo $order['username']; ?> • <?php echo ucfirst($order['order_type']); ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong>RM <?php echo number_format($order['total_amount'], 2); ?></strong><br>
                            <small style="color: #666;"><?php echo ucfirst($order['status']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 15px;">
            <a href="manage_orders.php" class="btn btn-secondary">View All Orders</a>
        </div>
    </div>
</div>

<!-- Recent Bookings Section -->
<div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 30px 0;">
    <h3>Recent Table Bookings</h3>
    <?php
    try {
        $stmt = $pdo->query("
            SELECT tb.booking_id, tb.table_number, tb.booking_date, tb.booking_time, 
                   tb.guests, tb.status, tb.created_at, u.username, tb.event_type
            FROM table_bookings tb
            JOIN users u ON tb.user_id = u.id
            ORDER BY tb.created_at DESC
            LIMIT 5
        ");
        $recent_bookings = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Recent bookings error: ' . $e->getMessage());
        $recent_bookings = [];
    }
    ?>
    
    <?php if (empty($recent_bookings)): ?>
        <p style="color: #666; margin: 20px 0;">No recent bookings</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Booking ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Customer</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Table</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Date & Time</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Guests</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Event</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;"><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                            <td style="padding: 12px;"><?php echo htmlspecialchars($booking['username']); ?></td>
                            <td style="padding: 12px;">Table <?php echo $booking['table_number']; ?></td>
                            <td style="padding: 12px;">
                                <?php echo date('M j', strtotime($booking['booking_date'])); ?> at <?php echo $booking['booking_time']; ?>
                            </td>
                            <td style="padding: 12px;"><?php echo $booking['guests']; ?></td>
                            <td style="padding: 12px;">
                                <?php if ($booking['event_type']): ?>
                                    <span style="background: #e8f4f8; color: #2c3e50; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo ucwords(str_replace('_', ' ', $booking['event_type'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">Regular</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                $status_colors = ['pending' => '#f39c12', 'confirmed' => '#27ae60', 'rejected' => '#e74c3c'];
                                $color = $status_colors[$booking['status']] ?? '#95a5a6';
                                ?>
                                <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px;">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 15px;">
        <a href="manage_bookings.php" class="btn btn-secondary">View All Bookings</a>
        <?php if ($pending_bookings > 0): ?>
            <a href="manage_bookings.php?filter=pending" class="btn" style="background: #9b59b6; margin-left: 10px;">
                Review Pending (<?php echo $pending_bookings; ?>)
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- QR Code Quick Actions -->
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px 0;">
    <h3>QR Code Management</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
        <a href="generate.php" class="btn" style="text-decoration: none; padding: 15px; text-align: center;">
            🔳 Generate QR Codes<br>
            <small>Create QR codes for tables</small>
        </a>
        <a href="manage_orders.php?filter=dine-in" class="btn btn-secondary" style="text-decoration: none; padding: 15px; text-align: center;">
            🍽️ Table Orders<br>
            <small>View dine-in orders</small>
        </a>
        <a href="manage_bookings.php?filter=upcoming" class="btn btn-secondary" style="text-decoration: none; padding: 15px; text-align: center;">
            📅 Upcoming Reservations<br>
            <small>View confirmed bookings</small>
        </a>
    </div>
</div>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<?php include '../includes/footer.php'; ?>