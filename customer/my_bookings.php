<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get customer's bookings
$stmt = $pdo->prepare("
    SELECT tb.*, 
           u.username as processed_by_name
    FROM table_bookings tb
    LEFT JOIN users u ON tb.processed_by = u.id
    WHERE tb.user_id = ?
    ORDER BY tb.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

// Get booking statistics for this customer
$stats_sql = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
        SUM(CASE WHEN booking_date >= CURDATE() AND status = 'confirmed' THEN 1 ELSE 0 END) as upcoming_bookings
    FROM table_bookings 
    WHERE user_id = ?
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

$page_title = 'My Table Bookings';
include '../includes/header.php';
?>

<h1>My Table Bookings</h1>

<!-- Customer Booking Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['total_bookings']; ?></h3>
        <p>Total Bookings</p>
    </div>
    <div style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center; position: relative;">
        <h3><?php echo (int)$stats['pending_bookings']; ?></h3>
        <p>Pending Approval</p>
        <?php if ($stats['pending_bookings'] > 0): ?>
            <div style="position: absolute; top: 10px; right: 10px; background: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; animation: pulse 2s infinite;">
                !
            </div>
        <?php endif; ?>
    </div>
    <div style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['confirmed_bookings']; ?></h3>
        <p>Confirmed</p>
    </div>
    <div style="background: #e74c3c; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['rejected_bookings']; ?></h3>
        <p>Rejected</p>
    </div>
    <div style="background: #9b59b6; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['upcoming_bookings']; ?></h3>
        <p>Upcoming</p>
    </div>
</div>

<!-- Quick Actions -->
<div style="text-align: center; margin: 30px 0;">
    <a href="book_table_enhanced.php" class="btn">📅 Make New Booking</a>
    <button onclick="window.location.reload()" class="btn btn-secondary">🔄 Refresh Status</button>
</div>

<!-- Bookings List -->
<?php if (empty($bookings)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;">📅</div>
        <h3>No bookings yet</h3>
        <p>You haven't made any table bookings yet.</p>
        <a href="book_table_enhanced.php" class="btn">Make Your First Booking</a>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($bookings as $booking): ?>
            <?php
            $status_colors = [
                'pending' => '#f39c12',
                'confirmed' => '#27ae60', 
                'rejected' => '#e74c3c'
            ];
            $color = $status_colors[$booking['status']] ?? '#95a5a6';
            
            $status_icons = [
                'pending' => '⏳',
                'confirmed' => '✅', 
                'rejected' => '❌'
            ];
            $icon = $status_icons[$booking['status']] ?? '📋';
            ?>
            <div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 25px; border-left: 5px solid <?php echo $color; ?>; position: relative;">
                
                <!-- Status Badge -->
                <div style="position: absolute; top: 20px; right: 20px;">
                    <div style="background: <?php echo $color; ?>; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <?php echo $icon; ?> <?php echo strtoupper($booking['status']); ?>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 10px;">
                    
                    <!-- Booking Details -->
                    <div>
                        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">
                            Booking #<?php echo htmlspecialchars($booking['booking_id']); ?>
                        </h3>
                        
                        <div style="display: grid; gap: 12px; font-size: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: bold; color: #2c3e50; min-width: 80px;">📅 Date:</span>
                                <span><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: bold; color: #2c3e50; min-width: 80px;">🕐 Time:</span>
                                <span><?php echo $booking['booking_time']; ?></span>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: bold; color: #2c3e50; min-width: 80px;">🪑 Table:</span>
                                <span>Table <?php echo $booking['table_number']; ?></span>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: bold; color: #2c3e50; min-width: 80px;">👥 Guests:</span>
                                <span><?php echo $booking['guests']; ?> person(s)</span>
                            </div>
                            
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-weight: bold; color: #2c3e50; min-width: 80px;">📝 Booked:</span>
                                <span><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event & Status Details -->
                    <div>
                        <?php if (!empty($booking['event_type'])): ?>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">🎉 Event Details</h4>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <p style="margin: 0 0 10px 0;"><strong>Event Type:</strong> 
                                    <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px; margin-left: 5px;">
                                        <?php echo ucwords(str_replace('_', ' ', $booking['event_type'])); ?>
                                    </span>
                                </p>
                                
                                <?php if (!empty($booking['package'])): ?>
                                    <p style="margin: 0 0 10px 0;"><strong>Package:</strong> 
                                        <?php
                                        $package_names = [
                                            'package_a' => 'Package A - Basic Decoration',
                                            'package_b' => 'Package B - Premium Decoration'
                                        ];
                                        echo $package_names[$booking['package']] ?? $booking['package'];
                                        ?>
                                    </p>
                                    <p style="margin: 0;"><strong>Package Price:</strong> 
                                        <span style="color: #e67e22; font-weight: bold;">RM <?php echo number_format($booking['package_price'], 2); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <h4 style="margin: 0 0 15px 0; color: #2c3e50;">🍽️ Regular Dining</h4>
                            <p style="color: #666; font-style: italic;">No special event arrangements</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking['special_requests'])): ?>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                                <p style="margin: 0 0 8px 0;"><strong>Special Requests:</strong></p>
                                <p style="margin: 0; font-style: italic; font-size: 14px;">
                                    "<?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>"
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status-specific Information -->
                        <?php if ($booking['status'] === 'pending'): ?>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #f39c12;">
                                <h5 style="margin: 0 0 10px 0; color: #856404;">⏳ Awaiting Staff Approval</h5>
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    Your booking is being reviewed by our staff. You will be notified once confirmed or if any issues arise. Expected response within 24 hours.
                                </p>
                            </div>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #27ae60;">
                                <h5 style="margin: 0 0 10px 0; color: #155724;">✅ Booking Confirmed!</h5>
                                <p style="margin: 0 0 10px 0; font-size: 14px; color: #155724;">
                                    Your booking has been confirmed. Please arrive on time and show this confirmation.
                                </p>
                                <?php if ($booking['processed_by_name']): ?>
                                    <p style="margin: 0; font-size: 12px; color: #155724;">
                                        Confirmed by: <?php echo htmlspecialchars($booking['processed_by_name']); ?>
                                        <?php if ($booking['processed_at']): ?>
                                            on <?php echo date('M j, Y g:i A', strtotime($booking['processed_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($booking['status'] === 'rejected'): ?>
                            <div style="background: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #e74c3c;">
                                <h5 style="margin: 0 0 10px 0; color: #721c24;">❌ Booking Rejected</h5>
                                <?php if (!empty($booking['rejected_reason'])): ?>
                                    <div style="background: white; padding: 12px; border-radius: 4px; margin: 10px 0;">
                                        <p style="margin: 0; font-size: 14px; color: #721c24;"><strong>Reason:</strong></p>
                                        <p style="margin: 8px 0 0 0; font-style: italic; color: #721c24;">
                                            "<?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?>"
                                        </p>
                                    </div>
                                <?php endif; ?>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: #721c24;">
                                    Please try booking a different date/time or contact us directly for assistance.
                                </p>
                                <?php if ($booking['processed_by_name']): ?>
                                    <p style="margin: 10px 0 0 0; font-size: 12px; color: #721c24;">
                                        Processed by: <?php echo htmlspecialchars($booking['processed_by_name']); ?>
                                        <?php if ($booking['processed_at']): ?>
                                            on <?php echo date('M j, Y g:i A', strtotime($booking['processed_at'])); ?>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #ecf0f1; text-align: right;">
                    <?php if ($booking['status'] === 'confirmed' && strtotime($booking['booking_date']) >= strtotime('today')): ?>
                        <button onclick="printBooking('<?php echo $booking['booking_id']; ?>')" class="btn btn-secondary" style="margin-right: 10px;">
                            🖨️ Print Confirmation
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($booking['status'] === 'rejected'): ?>
                        <a href="book_table_enhanced.php" class="btn" style="background: #3498db;">
                            📅 Book Again
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($booking['status'] === 'pending'): ?>
                        <span style="color: #f39c12; font-size: 14px; font-style: italic;">
                            ⏳ Waiting for approval...
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="text-align: center; margin: 30px 0;">
    <a href="../index.php" class="btn btn-secondary">🏠 Back to Home</a>
    <a href="book_table_enhanced.php" class="btn">📅 Make New Booking</a>
</div>

<script>
function printBooking(bookingId) {
    // Find the booking details
    const bookingCard = document.querySelector(`h3:contains('${bookingId}')`);
    if (!bookingCard) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Booking Confirmation - ${bookingId}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .booking-details { border: 2px solid #27ae60; padding: 20px; border-radius: 8px; }
                .status { background: #27ae60; color: white; padding: 10px; text-align: center; font-weight: bold; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>🍽️ QR Food Ordering</h1>
                <h2>Table Booking Confirmation</h2>
            </div>
            <div class="booking-details">
                <div class="status">✅ CONFIRMED BOOKING</div>
                <h3>Booking ID: ${bookingId}</h3>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                <p><strong>Print Date:</strong> ${new Date().toLocaleString()}</p>
                <br>
                <p style="text-align: center; font-style: italic;">Please show this confirmation upon arrival</p>
            </div>
            <div class="no-print" style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Auto-refresh every 30 seconds if there are pending bookings
<?php if ($stats['pending_bookings'] > 0): ?>
setInterval(function() {
    // Only refresh if no modals or focused elements
    if (!document.querySelector('input:focus, textarea:focus, select:focus')) {
        window.location.reload();
    }
}, 30000);
<?php endif; ?>

// Add animation for status changes
document.addEventListener('DOMContentLoaded', function() {
    const statusBadges = document.querySelectorAll('div[style*="border-left: 5px solid"]');
    statusBadges.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});

// Add notification sound for confirmed bookings (if browser supports it)
document.addEventListener('DOMContentLoaded', function() {
    const confirmedBookings = document.querySelectorAll('div:contains("✅ CONFIRMED")');
    if (confirmedBookings.length > 0 && 'Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('🎉 You have confirmed bookings!', {
                body: 'Check your booking details below.',
                icon: '/favicon.ico'
            });
        }
    }
});

console.log('Customer booking status page loaded successfully');
</script>

<style>
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

/* Responsive design for mobile */
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    div[style*="position: absolute; top: 20px; right: 20px"] {
        position: static !important;
        margin-bottom: 15px !important;
        text-align: center !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>