<?php
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// Get dates with orders for this customer
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as order_date, 
           COUNT(*) as order_count,
           SUM(total_amount) as day_total,
           MIN(created_at) as first_order,
           MAX(created_at) as last_order
    FROM orders 
    WHERE user_id = ? 
    GROUP BY DATE(created_at) 
    ORDER BY order_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$order_dates = $stmt->fetchAll();

// Get selected date or default to most recent
$selected_date = isset($_GET['date']) ? $_GET['date'] : null;

// Get orders for selected date
$orders = [];
if ($selected_date) {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? AND DATE(o.created_at) = ? 
        GROUP BY o.id 
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $selected_date]);
    $orders = $stmt->fetchAll();
}

$page_title = 'Order History';
include '../includes/header.php';
?>

<h1>📜 Order History</h1>

<?php if (empty($order_dates)): ?>
    <!-- No Orders State -->
    <div style="text-align: center; padding: 80px 20px; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 80px; margin-bottom: 20px; opacity: 0.3;">📜</div>
        <h3 style="color: #666; margin-bottom: 15px;">No orders yet</h3>
        <p style="color: #999; margin-bottom: 30px;">Start ordering to see your order history here!</p>
        <a href="menu.php" class="btn" style="padding: 15px 30px; font-size: 16px;">🍽️ Browse Menu</a>
    </div>
<?php else: ?>
    
    <?php if (!$selected_date): ?>
        <!-- DATE SELECTION VIEW -->
        <div style="max-width: 800px; margin: 0 auto;">
            <!-- Header Section -->
            <div style="text-align: center; margin-bottom: 40px;">
                <h2 style="color: #2c3e50; margin-bottom: 10px;">Select a date to view your orders</h2>
                <p style="color: #666; font-size: 16px;">You have orders on the following dates:</p>
            </div>
            
            <!-- Date Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <?php foreach ($order_dates as $date): ?>
                    <a href="?date=<?php echo $date['order_date']; ?>" 
                       style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 25px; text-decoration: none; color: inherit; transition: transform 0.3s ease, box-shadow 0.3s ease; border: 2px solid transparent; position: relative;"
                       onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'; this.style.borderColor='#e67e22';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'; this.style.borderColor='transparent';">
                        
                        <!-- Date Header -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="margin: 0; color: #2c3e50; font-size: 20px;">
                                    📅 <?php echo date('F j, Y', strtotime($date['order_date'])); ?>
                                </h3>
                                <p style="margin: 5px 0 0 0; color: #666; font-size: 14px;">
                                    <?php echo date('l', strtotime($date['order_date'])); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <div style="background: #e67e22; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; margin-bottom: 5px;">
                                    <?php echo $date['order_count']; ?> order<?php echo $date['order_count'] > 1 ? 's' : ''; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 14px;">
                                <div>
                                    <span style="color: #666;">Total Spent:</span><br>
                                    <strong style="color: #e67e22; font-size: 18px;">RM <?php echo number_format($date['day_total'], 2); ?></strong>
                                </div>
                                <div>
                                    <span style="color: #666;">Time Range:</span><br>
                                    <strong style="color: #2c3e50;">
                                        <?php echo date('g:i A', strtotime($date['first_order'])); ?> - <?php echo date('g:i A', strtotime($date['last_order'])); ?>
                                    </strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- View Details Button -->
                        <div style="text-align: center;">
                            <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; display: inline-block;">
                                👀 View Orders →
                            </div>
                        </div>
                        
                        <!-- Days Ago Indicator -->
                        <?php 
                        $days_ago = (strtotime('today') - strtotime($date['order_date'])) / (60 * 60 * 24);
                        if ($days_ago == 0): ?>
                            <div style="position: absolute; top: 15px; right: 15px; background: #27ae60; color: white; padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">
                                TODAY
                            </div>
                        <?php elseif ($days_ago == 1): ?>
                            <div style="position: absolute; top: 15px; right: 15px; background: #f39c12; color: white; padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">
                                YESTERDAY
                            </div>
                        <?php elseif ($days_ago <= 7): ?>
                            <div style="position: absolute; top: 15px; right: 15px; background: #9b59b6; color: white; padding: 4px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">
                                <?php echo (int)$days_ago; ?> DAYS AGO
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Quick Stats -->
            <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0 0 20px 0; color: #2c3e50;">📊 Your Ordering Summary</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #3498db; margin-bottom: 5px;">
                            <?php echo count($order_dates); ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Days with Orders</div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #e67e22; margin-bottom: 5px;">
                            <?php echo array_sum(array_column($order_dates, 'order_count')); ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Total Orders</div>
                    </div>
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #27ae60; margin-bottom: 5px;">
                            RM <?php echo number_format(array_sum(array_column($order_dates, 'day_total')), 2); ?>
                        </div>
                        <div style="color: #666; font-size: 14px;">Total Spent</div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- SELECTED DATE ORDERS VIEW -->
        <div style="max-width: 1000px; margin: 0 auto;">
            
            <!-- Back to Date Selection -->
            <div style="margin-bottom: 30px;">
                <a href="order_history.php" 
                   style="display: inline-flex; align-items: center; gap: 8px; background: #f8f9fa; color: #2c3e50; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-weight: 500; transition: background 0.3s ease;"
                   onmouseover="this.style.background='#e9ecef'"
                   onmouseout="this.style.background='#f8f9fa'">
                    ← Back to Date Selection
                </a>
            </div>
            
            <!-- Selected Date Header -->
            <div style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; text-align: center;">
                <h2 style="margin: 0 0 10px 0; font-size: 28px;">
                    📅 <?php echo date('F j, Y', strtotime($selected_date)); ?>
                </h2>
                <p style="margin: 0; opacity: 0.9; font-size: 16px;">
                    <?php echo date('l', strtotime($selected_date)); ?> • 
                    <?php echo count($orders); ?> order<?php echo count($orders) != 1 ? 's' : ''; ?>
                </p>
            </div>
            
            <!-- Orders for Selected Date -->
            <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 8px;">
                    <h3>No orders found for this date</h3>
                    <p>This shouldn't happen. Please try again.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 20px;">
                    <?php foreach ($orders as $order): ?>
                        <div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); padding: 25px; border-left: 5px solid #e67e22;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 25px; align-items: start;">
                                
                                <!-- Order Info -->
                                <div>
                                    <h3 style="margin: 0 0 15px 0; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
                                        🍽️ Order #<?php echo $order['id']; ?>
                                        <?php if ($order['table_number']): ?>
                                            <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                                                🔳 Table <?php echo $order['table_number']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    
                                    <div style="display: grid; gap: 8px; font-size: 14px;">
                                        <p style="margin: 0;"><strong>Time:</strong> <?php echo date('g:i A', strtotime($order['created_at'])); ?></p>
                                        <p style="margin: 0;"><strong>Type:</strong> 
                                            <span style="background: #f8f9fa; padding: 2px 8px; border-radius: 10px; font-size: 12px;">
                                                <?php echo ucfirst($order['order_type']); ?>
                                            </span>
                                        </p>
                                        <p style="margin: 0;"><strong>Items:</strong> <?php echo $order['item_count']; ?> item(s)</p>
                                        <p style="margin: 0;"><strong>Status:</strong> 
                                            <?php
                                            $status_colors = [
                                                'pending' => '#f39c12',
                                                'confirmed' => '#3498db',
                                                'preparing' => '#e67e22',
                                                'ready' => '#27ae60',
                                                'completed' => '#95a5a6'
                                            ];
                                            $status_icons = [
                                                'pending' => '⏳',
                                                'confirmed' => '✅',
                                                'preparing' => '👨‍🍳',
                                                'ready' => '🔔',
                                                'completed' => '✅'
                                            ];
                                            $color = $status_colors[$order['status']] ?? '#95a5a6';
                                            $icon = $status_icons[$order['status']] ?? '📋';
                                            ?>
                                            <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold;">
                                                <?php echo $icon; ?> <?php echo strtoupper($order['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Order Items -->
                                <div>
                                    <h4 style="margin: 0 0 15px 0; color: #2c3e50;">📋 Order Items</h4>
                                    <?php
                                    // Get order items
                                    $stmt = $pdo->prepare("
                                        SELECT oi.quantity, m.name, oi.price, (oi.quantity * oi.price) as subtotal
                                        FROM order_items oi
                                        JOIN menu_items m ON oi.menu_item_id = m.id
                                        WHERE oi.order_id = ?
                                        ORDER BY m.name
                                    ");
                                    $stmt->execute([$order['id']]);
                                    $order_items = $stmt->fetchAll();
                                    ?>
                                    
                                    <div style="max-height: 150px; overflow-y: auto; background: #f8f9fa; border-radius: 8px; padding: 12px;">
                                        <?php foreach ($order_items as $item): ?>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; padding-bottom: 8px; border-bottom: 1px solid #ecf0f1;">
                                                <div style="flex: 1;">
                                                    <span style="font-weight: bold;"><?php echo $item['quantity']; ?>× <?php echo htmlspecialchars($item['name']); ?></span><br>
                                                    <small style="color: #666;">@ RM <?php echo number_format($item['price'], 2); ?> each</small>
                                                </div>
                                                <span style="font-weight: bold; color: #e67e22;">RM <?php echo number_format($item['subtotal'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Total & Status Info -->
                                <div style="text-align: center; min-width: 150px;">
                                    <!-- Total Amount -->
                                    <div style="font-size: 24px; font-weight: bold; color: #e67e22; margin-bottom: 15px;">
                                        RM <?php echo number_format($order['total_amount'], 2); ?>
                                    </div>
                                    
                                    <!-- Status Description -->
                                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; font-size: 12px; color: #666; text-align: center;">
                                        <?php
                                        $status_descriptions = [
                                            'pending' => '⏳ Waiting for confirmation',
                                            'confirmed' => '✅ Order confirmed',
                                            'preparing' => '👨‍🍳 Being prepared',
                                            'ready' => '🔔 Ready for pickup/delivery',
                                            'completed' => '✅ Order completed'
                                        ];
                                        echo $status_descriptions[$order['status']] ?? 'Order processed';
                                        ?>
                                    </div>
                                    
                                    <!-- Order Time Info -->
                                    <div style="margin-top: 10px; font-size: 11px; color: #999; text-align: center;">
                                        <div>Ordered at</div>
                                        <div style="font-weight: bold; color: #666;"><?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Day Summary -->
                <?php
                $day_total = array_sum(array_column($orders, 'total_amount'));
                $total_items = array_sum(array_column($orders, 'item_count'));
                ?>
                <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 12px; margin-top: 30px; text-align: center;">
                    <h3 style="margin: 0 0 15px 0; color: #2c3e50;">📊 Day Summary</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 20px;">
                        <div>
                            <div style="font-size: 20px; font-weight: bold; color: #3498db;"><?php echo count($orders); ?></div>
                            <div style="color: #666; font-size: 14px;">Orders</div>
                        </div>
                        <div>
                            <div style="font-size: 20px; font-weight: bold; color: #e67e22;"><?php echo $total_items; ?></div>
                            <div style="color: #666; font-size: 14px;">Items</div>
                        </div>
                        <div>
                            <div style="font-size: 20px; font-weight: bold; color: #27ae60;">RM <?php echo number_format($day_total, 2); ?></div>
                            <div style="color: #666; font-size: 14px;">Total Spent</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div style="text-align: center; margin: 40px 0;">
    <a href="../index.php" class="btn btn-secondary">🏠 Back to Home</a>
    <a href="menu.php" class="btn" style="margin-left: 10px;">🍽️ Order Again</a>
    <?php if ($selected_date): ?>
        <a href="order_history.php" class="btn btn-secondary" style="margin-left: 10px;">📅 Other Dates</a>
    <?php endif; ?>
</div>

<script>
// Add loading animation for date cards
document.addEventListener('DOMContentLoaded', function() {
    const dateCards = document.querySelectorAll('a[href*="date="]');
    dateCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Auto-refresh order status every 30 seconds if viewing today's orders
    const currentDate = new Date().toISOString().split('T')[0];
    const selectedDate = new URLSearchParams(window.location.search).get('date');
    
    if (selectedDate === currentDate) {
        setInterval(() => {
            // Only refresh if no forms are focused and there are active orders
            if (!document.querySelector('input:focus, textarea:focus, select:focus')) {
                const activeOrders = document.querySelectorAll('[style*="PENDING"], [style*="CONFIRMED"], [style*="PREPARING"]');
                if (activeOrders.length > 0) {
                    window.location.reload();
                }
            }
        }, 30000); // Refresh every 30 seconds
    }
});

// Smooth scrolling for back button
document.querySelector('a[href="order_history.php"]')?.addEventListener('click', function(e) {
    e.preventDefault();
    window.history.back();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape to go back to date selection
    if (e.key === 'Escape' && window.location.search.includes('date=')) {
        window.location.href = 'order_history.php';
    }
    
    // 'M' to go to menu
    if (e.key.toLowerCase() === 'm' && !e.ctrlKey && !e.target.matches('input, textarea')) {
        e.preventDefault();
        window.location.href = 'menu.php';
    }
    
    // 'H' to go to home
    if (e.key.toLowerCase() === 'h' && !e.ctrlKey && !e.target.matches('input, textarea')) {
        e.preventDefault();
        window.location.href = '../index.php';
    }
});

console.log('Simple order history with date selection loaded successfully');
</script>

<style>
/* Add smooth animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.date-card {
    animation: fadeInUp 0.5s ease-out;
}

/* Status badge animations */
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

span[style*="PENDING"] {
    animation: pulse 2s infinite;
}

span[style*="PREPARING"] {
    animation: pulse 1.5s infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    div[style*="grid-template-columns: 1fr 1fr auto"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    div[style*="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))"] {
        grid-template-columns: 1fr !important;
    }
    
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
    
    /* Better spacing on mobile */
    div[style*="padding: 25px"] {
        padding: 15px !important;
    }
}

/* Hover effects */
a[href*="date="]:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    border-color: #e67e22 !important;
}

/* Print styles */
@media print {
    .no-print,
    a[href*="order_history.php"],
    div[style*="margin: 40px 0"] {
        display: none !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>