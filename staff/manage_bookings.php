<?php
require_once '../config/database.php';

if (!isLoggedIn() || !isStaff()) {
    redirect('../auth/login.php');
}

$message = '';
$error = '';

// Handle booking approval/rejection
if ($_POST && isset($_POST['update_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action']; // 'confirm' or 'reject'
    $reason = trim($_POST['reason'] ?? '');
    
    if (!$booking_id || !in_array($action, ['confirm', 'reject'])) {
        $error = 'Invalid booking or action';
    } elseif ($action === 'reject' && empty($reason)) {
        $error = 'Rejection reason is required';
    } else {
        try {
            $new_status = ($action === 'confirm') ? 'confirmed' : 'rejected';
            
            // Update booking status
            $stmt = $pdo->prepare("
                UPDATE table_bookings 
                SET status = ?, 
                    rejected_reason = ?,
                    processed_by = ?,
                    processed_at = NOW()
                WHERE id = ?
            ");
            
            $rejected_reason = ($action === 'reject') ? $reason : null;
            $result = $stmt->execute([$new_status, $rejected_reason, $_SESSION['user_id'], $booking_id]);
            
            if ($result) {
                $action_text = ($action === 'confirm') ? 'confirmed' : 'rejected';
                $message = "Booking successfully $action_text!";
            } else {
                $error = 'Failed to update booking status';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

// Build WHERE clause
$where_conditions = [];
$params = [];

switch ($filter) {
    case 'pending':
        $where_conditions[] = "tb.status = 'pending'";
        break;
    case 'confirmed':
        $where_conditions[] = "tb.status = 'confirmed'";
        break;
    case 'rejected':
        $where_conditions[] = "tb.status = 'rejected'";
        break;
    case 'today':
        $where_conditions[] = "tb.booking_date = CURDATE()";
        break;
    case 'upcoming':
        $where_conditions[] = "tb.booking_date >= CURDATE() AND tb.status = 'confirmed'";
        break;
}

if (!empty($search)) {
    if (is_numeric($search)) {
        $where_conditions[] = "(tb.id = ? OR tb.table_number = ?)";
        $params[] = (int)$search;
        $params[] = (int)$search;
    } else {
        $where_conditions[] = "(u.username LIKE ? OR tb.booking_id LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get booking statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_bookings,
        SUM(CASE WHEN booking_date = CURDATE() THEN 1 ELSE 0 END) as today_bookings,
        SUM(CASE WHEN booking_date >= CURDATE() AND status = 'confirmed' THEN 1 ELSE 0 END) as upcoming_bookings,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN package_price ELSE 0 END), 0) as total_package_revenue
    FROM table_bookings
";
$stmt = $pdo->query($stats_sql);
$stats = $stmt->fetch();

// Get bookings
$sql = "
    SELECT tb.*, 
           u.username, 
           u.email,
           staff.username as processed_by_name
    FROM table_bookings tb
    JOIN users u ON tb.user_id = u.id
    LEFT JOIN users staff ON tb.processed_by = staff.id
    $where_clause
    ORDER BY tb.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$page_title = 'Manage Table Bookings';
include '../includes/header.php';
?>

<h1>Manage Table Bookings</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
    <div style="background: #3498db; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['total_bookings']; ?></h3>
        <p>Total Bookings</p>
    </div>
    <div style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['pending_bookings']; ?></h3>
        <p>Pending</p>
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
        <h3><?php echo (int)$stats['today_bookings']; ?></h3>
        <p>Today</p>
    </div>
    <div style="background: #16a085; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3><?php echo (int)$stats['upcoming_bookings']; ?></h3>
        <p>Upcoming</p>
    </div>
    <div style="background: #8e44ad; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h3>RM <?php echo number_format($stats['total_package_revenue'], 2); ?></h3>
        <p>Package Revenue</p>
    </div>
</div>

<!-- Search and Filter -->
<div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin: 20px 0;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label for="search" style="display: block; margin-bottom: 5px; font-weight: bold;">Search:</label>
            <input type="text" name="search" id="search" placeholder="Booking ID, table number, or customer name" 
                   value="<?php echo htmlspecialchars($search); ?>" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="flex: 1; min-width: 150px;">
            <label for="filter" style="display: block; margin-bottom: 5px; font-weight: bold;">Filter:</label>
            <select name="filter" id="filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Bookings</option>
                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today's Bookings</option>
                <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn">Search</button>
        </div>
        
        <div>
            <a href="manage_bookings.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<!-- Quick Filter Buttons -->
<div style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
    <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? '' : 'btn-secondary'; ?>">
        Pending (<?php echo (int)$stats['pending_bookings']; ?>)
    </a>
    <a href="?filter=confirmed" class="btn <?php echo $filter === 'confirmed' ? '' : 'btn-secondary'; ?>">
        Confirmed (<?php echo (int)$stats['confirmed_bookings']; ?>)
    </a>
    <a href="?filter=rejected" class="btn <?php echo $filter === 'rejected' ? '' : 'btn-secondary'; ?>">
        Rejected (<?php echo (int)$stats['rejected_bookings']; ?>)
    </a>
    <a href="?filter=today" class="btn <?php echo $filter === 'today' ? '' : 'btn-secondary'; ?>">
        Today (<?php echo (int)$stats['today_bookings']; ?>)
    </a>
    <a href="?filter=upcoming" class="btn <?php echo $filter === 'upcoming' ? '' : 'btn-secondary'; ?>">
        Upcoming (<?php echo (int)$stats['upcoming_bookings']; ?>)
    </a>
</div>

<!-- Bookings List -->
<?php if (empty($bookings)): ?>
    <div style="text-align: center; padding: 50px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <h3>No bookings found</h3>
        <p>No bookings match the current filter criteria.</p>
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
            ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 25px; border-left: 5px solid <?php echo $color; ?>;">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 25px; align-items: start;">
                    
                    <!-- Booking Info -->
                    <div>
                        <h3 style="margin: 0 0 15px 0; color: #2c3e50;">
                            Booking #<?php echo htmlspecialchars($booking['booking_id']); ?>
                        </h3>
                        <div style="display: grid; gap: 8px; font-size: 14px;">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($booking['username']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                            <p><strong>Table:</strong> Table <?php echo (int)$booking['table_number']; ?></p>
                            <p><strong>Date & Time:</strong> <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?> at <?php echo $booking['booking_time']; ?></p>
                            <p><strong>Guests:</strong> <?php echo (int)$booking['guests']; ?> person(s)</p>
                            <p><strong>Booked:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <!-- Event & Package Details -->
                    <div>
                        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">Event Details</h4>
                        
                        <?php if (!empty($booking['event_type'])): ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <p><strong>Event Type:</strong> 
                                    <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                                        <?php echo ucwords(str_replace('_', ' ', $booking['event_type'])); ?>
                                    </span>
                                </p>
                                
                                <?php if (!empty($booking['package'])): ?>
                                    <p style="margin-top: 10px;"><strong>Package:</strong> 
                                        <?php
                                        $package_names = [
                                            'package_a' => 'Package A - Basic Decoration',
                                            'package_b' => 'Package B - Premium Decoration'
                                        ];
                                        echo $package_names[$booking['package']] ?? $booking['package'];
                                        ?>
                                    </p>
                                    <p><strong>Package Price:</strong> 
                                        <span style="color: #e67e22; font-weight: bold;">RM <?php echo number_format($booking['package_price'], 2); ?></span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; color: #666;">
                                <p><em>Regular Dining (No special event)</em></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking['special_requests'])): ?>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                                <p style="margin: 0;"><strong>Special Requests:</strong></p>
                                <p style="margin: 8px 0 0 0; font-style: italic;">
                                    "<?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>"
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Status & Actions -->
                    <div style="text-align: center; min-width: 220px;">
                        <div style="background: <?php echo $color; ?>; color: white; padding: 12px 15px; border-radius: 8px; font-weight: bold; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                            <?php echo ucfirst($booking['status']); ?>
                        </div>
                        
                        <?php if ($booking['status'] === 'pending'): ?>
                            <!-- ADMIN ACTIONS: Approve/Reject Booking -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 2px solid #e9ecef; margin-bottom: 15px;">
                                <h4 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 14px;">📋 ADMIN ACTION REQUIRED</h4>
                                
                                <!-- APPROVE BUTTON -->
                                <form method="POST" style="margin-bottom: 10px;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="action" value="confirm">
                                    <button type="submit" name="update_booking" 
                                            onclick="return confirm('Are you sure you want to APPROVE this booking?')"
                                            class="btn" style="width: 100%; padding: 12px; background: #27ae60; font-size: 14px; font-weight: bold; box-shadow: 0 2px 4px rgba(39, 174, 96, 0.3);">
                                        ✅ APPROVE BOOKING
                                    </button>
                                </form>
                                
                                <!-- REJECT BUTTON -->
                                <button onclick="showRejectForm(<?php echo $booking['id']; ?>)" 
                                        class="btn" style="width: 100%; padding: 12px; background: #e74c3c; font-size: 14px; font-weight: bold; box-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);">
                                    ❌ REJECT BOOKING
                                </button>
                                
                                <p style="margin: 10px 0 0 0; font-size: 11px; color: #666; font-style: italic;">
                                    Customer will be notified of your decision
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Already Processed -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; font-size: 12px; color: #666; text-align: center;">
                                <h4 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;">📋 BOOKING PROCESSED</h4>
                                
                                <?php if ($booking['processed_by_name']): ?>
                                    <p><strong>Processed by:</strong> <?php echo htmlspecialchars($booking['processed_by_name']); ?></p>
                                <?php endif; ?>
                                <?php if ($booking['processed_at']): ?>
                                    <p><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($booking['processed_at'])); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($booking['status'] === 'rejected' && !empty($booking['rejected_reason'])): ?>
                                    <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-top: 15px; text-align: left; border-left: 4px solid #e74c3c;">
                                        <strong>❌ Rejection Reason:</strong><br>
                                        <em style="font-size: 13px;">"<?php echo nl2br(htmlspecialchars($booking['rejected_reason'])); ?>"</em>
                                    </div>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                    <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-top: 15px; border-left: 4px solid #27ae60;">
                                        <strong>✅ Booking Confirmed</strong><br>
                                        <em style="font-size: 13px;">Customer has been notified</em>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- REJECTION MODAL - ENHANCED -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; backdrop-filter: blur(2px);">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 550px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        
        <!-- Modal Header -->
        <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f1f1f1;">
            <div style="font-size: 48px; margin-bottom: 10px;">❌</div>
            <h3 style="margin: 0; color: #e74c3c; font-size: 24px;">Reject Table Booking</h3>
            <p style="margin: 8px 0 0 0; color: #666; font-size: 14px;">Please provide a clear reason for rejecting this booking</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="booking_id" id="reject_booking_id">
            <input type="hidden" name="action" value="reject">
            
            <!-- Booking Info Reminder -->
            <div id="booking_info_reminder" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3498db;">
                <p style="margin: 0; font-size: 14px; color: #2c3e50;"><strong>You are rejecting:</strong></p>
                <p id="rejection_booking_details" style="margin: 5px 0 0 0; font-size: 13px; color: #666;"></p>
            </div>
            
            <!-- Rejection Reason -->
            <div style="margin-bottom: 25px;">
                <label for="reject_reason" style="display: block; margin-bottom: 10px; font-weight: bold; color: #2c3e50; font-size: 16px;">
                    <span style="color: #e74c3c;">*</span> Reason for rejection:
                </label>
                <textarea name="reason" id="reject_reason" rows="5" required
                          placeholder="Please provide a clear and professional reason for rejecting this booking. This message will be sent to the customer.

Examples:
• Table not available for selected date/time
• Event requirements cannot be accommodated  
• Fully booked for that period
• Special requests cannot be fulfilled"
                          style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; resize: vertical; font-family: inherit; font-size: 14px; line-height: 1.4;"></textarea>
                <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                    💡 Tip: Be polite and helpful. Suggest alternative dates if possible.
                </small>
            </div>
            
            <!-- Action Buttons -->
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" onclick="closeRejectModal()" class="btn btn-secondary" style="padding: 12px 24px; font-size: 14px;">
                    Cancel
                </button>
                <button type="submit" name="update_booking" class="btn" 
                        style="background: #e74c3c; padding: 12px 24px; font-size: 14px; font-weight: bold; box-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);"
                        onclick="return confirm('Are you sure you want to reject this booking? The customer will be notified with your reason.')">
                    ❌ Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    <button onclick="window.print()" class="btn">🖨️ Print Page</button>
</div>

<script>
function showRejectForm(bookingId) {
    // Find the booking data from the page
    const bookingCard = document.querySelector(`input[value="${bookingId}"]`).closest('div[style*="border-left: 5px solid"]');
    
    // Extract booking details from the card
    let bookingDetails = '';
    if (bookingCard) {
        const bookingIdText = bookingCard.querySelector('h3').textContent;
        const customerName = bookingCard.querySelector('p:has(strong)').textContent.replace('Customer: ', '');
        const tableInfo = Array.from(bookingCard.querySelectorAll('p')).find(p => p.textContent.includes('Table:')).textContent;
        const dateInfo = Array.from(bookingCard.querySelectorAll('p')).find(p => p.textContent.includes('Date & Time:')).textContent;
        const guestInfo = Array.from(bookingCard.querySelectorAll('p')).find(p => p.textContent.includes('Guests:')).textContent;
        
        bookingDetails = `${bookingIdText} - ${customerName}<br>${tableInfo} | ${dateInfo} | ${guestInfo}`;
    }
    
    // Set form data
    document.getElementById('reject_booking_id').value = bookingId;
    document.getElementById('reject_reason').value = '';
    document.getElementById('rejection_booking_details').innerHTML = bookingDetails;
    
    // Show modal and focus on textarea
    document.getElementById('rejectModal').style.display = 'block';
    setTimeout(() => {
        document.getElementById('reject_reason').focus();
    }, 100);
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    document.getElementById('reject_reason').value = '';
}

// Enhanced modal interactions
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('rejectModal');
    const textarea = document.getElementById('reject_reason');
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
    
    // Auto-resize textarea
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 200) + 'px';
    });
    
    // Add character counter
    const charCounter = document.createElement('div');
    charCounter.style.cssText = 'text-align: right; font-size: 11px; color: #999; margin-top: 5px;';
    textarea.parentNode.appendChild(charCounter);
    
    textarea.addEventListener('input', function() {
        const length = this.value.length;
        charCounter.textContent = `${length} characters`;
        
        if (length < 10) {
            charCounter.style.color = '#e74c3c';
            charCounter.textContent += ' (Please provide more detail)';
        } else if (length > 500) {
            charCounter.style.color = '#f39c12';
            charCounter.textContent += ' (Consider being more concise)';
        } else {
            charCounter.style.color = '#27ae60';
        }
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
    
    // Quick approve with Ctrl+A (when not in input field)
    if (e.ctrlKey && e.key === 'a' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        const approveBtn = document.querySelector('button[onclick*="confirm"]');
        if (approveBtn) {
            approveBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
            approveBtn.style.animation = 'pulse 1s ease-in-out';
        }
    }
});

// Add visual feedback for button clicks
document.addEventListener('click', function(e) {
    if (e.target.matches('button[type="submit"]')) {
        e.target.style.transform = 'scale(0.95)';
        setTimeout(() => {
            e.target.style.transform = 'scale(1)';
        }, 150);
    }
});

// Confirmation messages with better UX
function confirmApproval(bookingId) {
    const result = confirm(`✅ Are you sure you want to APPROVE this booking?\n\n• Customer will be notified immediately\n• This action cannot be undone\n• Booking will be confirmed in the system`);
    return result;
}

// Auto-refresh every 60 seconds for pending bookings
<?php if ($filter === 'pending'): ?>
let refreshTimer = setInterval(function() {
    // Only refresh if no modals are open and no forms are focused
    if (!document.querySelector('input:focus, textarea:focus, select:focus') && 
        document.getElementById('rejectModal').style.display === 'none') {
        
        // Show subtle refresh indicator
        const indicator = document.createElement('div');
        indicator.style.cssText = `
            position: fixed; top: 20px; right: 20px; 
            background: #3498db; color: white; 
            padding: 8px 16px; border-radius: 20px; 
            font-size: 12px; z-index: 2000;
            animation: fadeIn 0.3s ease-out;
        `;
        indicator.textContent = '🔄 Refreshing...';
        document.body.appendChild(indicator);
        
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}, 60000);
<?php endif; ?>

// Success/Error message auto-hide
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 3000);
        }
    });
});

// Add hover effects to booking cards
document.addEventListener('DOMContentLoaded', function() {
    const bookingCards = document.querySelectorAll('div[style*="border-left: 5px solid"]');
    bookingCards.forEach(card => {
        card.style.transition = 'transform 0.2s ease-out, box-shadow 0.2s ease-out';
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        });
    });
});

console.log('Enhanced booking management system loaded successfully');
</script>

<?php include '../includes/footer.php'; ?>