<?php
/**
 * Enhanced Analytics Dashboard
 * Displays comprehensive procurement analytics, reports, and audit logs
 */

require_once 'config/config.php';
require_once 'auth/auth.php';
require_once 'includes/notifications.php';

// Check authentication and permissions
requireLogin();
requirePermission('view_analytics');

$db = new Database();
$conn = $db->getConnection();

// Unread notifications count for sidebar badge
$notificationSystem = new NotificationSystem();
$unread_notifications = $notificationSystem->getUnreadCount($_SESSION['user_id']);

// Get date range (default to last 90 days)
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days')); // 90 days ago
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'overview'; // overview, financial, performance, compliance, audit
$export = $_GET['export'] ?? false; // Export flag

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    $start_date = date('Y-m-d', strtotime('-90 days'));
    $end_date = date('Y-m-d');
}

// Get analytics data
$analytics = [];
    // Check optional tables existence
    $has_vendor_contracts = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'vendor_contracts'");
        $stmt->execute();
        $has_vendor_contracts = $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $has_vendor_contracts = false;
    }
    // Total spend in period
    try {
        $sql = "SELECT COALESCE(SUM(total_amount), 0) as total_spend 
                FROM purchase_orders 
                WHERE created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['total_spend'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Analytics total_spend error: " . $e->getMessage());
        $analytics['total_spend'] = 0;
    }

    // Spend by category
    try {
        $sql = "SELECT ii.category, COALESCE(SUM(poi.total_cost), 0) as category_spend
                FROM purchase_orders po
                JOIN po_items poi ON po.id = poi.po_id
                JOIN inventory_items ii ON poi.item_id = ii.id
                WHERE po.created_at BETWEEN ? AND ?
                GROUP BY ii.category
                ORDER BY category_spend DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['spend_by_category'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics spend_by_category error: " . $e->getMessage());
        $analytics['spend_by_category'] = [];
    }

    // Top vendors by spend
    try {
        $sql = "SELECT v.name, COALESCE(SUM(po.total_amount), 0) as vendor_spend, COUNT(po.id) as po_count,
                       v.vendor_score, v.delivery_time_avg, v.defect_rate, v.on_time_percentage
                FROM vendors v
                LEFT JOIN purchase_orders po ON v.id = po.vendor_id AND po.created_at BETWEEN ? AND ?
                WHERE v.is_active = 1
                GROUP BY v.id, v.name, v.vendor_score, v.delivery_time_avg, v.defect_rate, v.on_time_percentage
                ORDER BY vendor_spend DESC
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['top_vendors'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics top_vendors error: " . $e->getMessage());
        $analytics['top_vendors'] = [];
    }

    // Monthly spend trend (last 12 months)
    try {
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(total_amount), 0) as monthly_spend
                FROM purchase_orders
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $analytics['monthly_trend'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics monthly_trend error: " . $e->getMessage());
        $analytics['monthly_trend'] = [];
    }

    // Requisition status breakdown
    try {
        $sql = "SELECT status, COUNT(*) as count
                FROM purchase_requisitions
                WHERE created_at BETWEEN ? AND ?
                GROUP BY status";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['requisition_status'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics requisition_status error: " . $e->getMessage());
        $analytics['requisition_status'] = [];
    }

    // Average approval time
    try {
        $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, a.created_at, a.approved_at)) as avg_approval_hours
                FROM approvals a
                WHERE a.status = 'approved' 
                AND a.approved_at IS NOT NULL
                AND a.created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['avg_approval_time'] = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Analytics avg_approval_time error: " . $e->getMessage());
        $analytics['avg_approval_time'] = 0;
    }

    // Low stock items
    try {
        $sql = "SELECT ii.name, ii.item_code, ii.current_stock, ii.reorder_point, ii.reorder_quantity, 
                       ii.unit_cost, v.name as supplier_name
                FROM inventory_items ii
                LEFT JOIN vendors v ON ii.supplier_id = v.id
                WHERE ii.current_stock <= ii.reorder_point AND ii.is_active = 1
                ORDER BY (ii.current_stock - ii.reorder_point) ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $analytics['low_stock_items'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics low_stock_items error: " . $e->getMessage());
        $analytics['low_stock_items'] = [];
    }

    // Maverick spending (POs not linked to contracts) - only if vendor_contracts table exists
    try {
        if ($has_vendor_contracts) {
            $sql = "SELECT po.po_number, po.total_amount, v.name as vendor_name, po.created_at
                    FROM purchase_orders po
                    JOIN vendors v ON po.vendor_id = v.id
                    LEFT JOIN vendor_contracts vc ON v.id = vc.vendor_id 
                        AND vc.status = 'active' 
                        AND po.created_at BETWEEN vc.start_date AND vc.end_date
                    WHERE vc.id IS NULL
                    AND po.created_at BETWEEN ? AND ?
                    ORDER BY po.total_amount DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $analytics['maverick_spending'] = $stmt->fetchAll();
        } else {
            $analytics['maverick_spending'] = [];
        }
    } catch (Exception $e) {
        error_log("Analytics maverick_spending error: " . $e->getMessage());
        $analytics['maverick_spending'] = [];
    }

    // Department spend analysis
    try {
        $sql = "SELECT pr.department, COALESCE(SUM(pr.total_amount), 0) as dept_spend, COUNT(pr.id) as req_count
                FROM purchase_requisitions pr
                WHERE pr.created_at BETWEEN ? AND ?
                GROUP BY pr.department
                ORDER BY dept_spend DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['department_spend'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics department_spend error: " . $e->getMessage());
        $analytics['department_spend'] = [];
    }

    // Payment terms analysis
    try {
        $sql = "SELECT po.payment_terms, COUNT(*) as count, COALESCE(SUM(po.total_amount), 0) as total_amount
                FROM purchase_orders po
                WHERE po.created_at BETWEEN ? AND ?
                GROUP BY po.payment_terms
                ORDER BY total_amount DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['payment_terms_analysis'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics payment_terms_analysis error: " . $e->getMessage());
        $analytics['payment_terms_analysis'] = [];
    }

    // Audit logs for the period (with fallback to last 365 days if empty)
    try {
        $sql = "SELECT al.*, u.username, u.first_name, u.last_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.created_at BETWEEN ? AND ?
                ORDER BY al.created_at DESC
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['audit_logs'] = $stmt->fetchAll();
        if (empty($analytics['audit_logs'])) {
            $sql = "SELECT al.*, u.username, u.first_name, u.last_name
                    FROM audit_logs al
                    LEFT JOIN users u ON al.user_id = u.id
                    WHERE al.created_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                    ORDER BY al.created_at DESC
                    LIMIT 100";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $analytics['audit_logs'] = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Analytics audit_logs error: " . $e->getMessage());
        $analytics['audit_logs'] = [];
    }

    // Vendor performance metrics
    try {
        $sql = "SELECT v.name, v.vendor_score, v.delivery_time_avg, v.defect_rate, v.on_time_percentage,
                       COUNT(po.id) as total_pos, COALESCE(SUM(po.total_amount), 0) as total_spend
                FROM vendors v
                LEFT JOIN purchase_orders po ON v.id = po.vendor_id AND po.created_at BETWEEN ? AND ?
                WHERE v.is_active = 1
                GROUP BY v.id, v.name, v.vendor_score, v.delivery_time_avg, v.defect_rate, v.on_time_percentage
                ORDER BY v.vendor_score DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['vendor_performance'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics vendor_performance error: " . $e->getMessage());
        $analytics['vendor_performance'] = [];
    }

    // Cost center analysis
    try {
        $sql = "SELECT pr.cost_center, COALESCE(SUM(pr.total_amount), 0) as cost_center_spend, COUNT(pr.id) as req_count
                FROM purchase_requisitions pr
                WHERE pr.created_at BETWEEN ? AND ? AND pr.cost_center IS NOT NULL
                GROUP BY pr.cost_center
                ORDER BY cost_center_spend DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['cost_center_analysis'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics cost_center_analysis error: " . $e->getMessage());
        $analytics['cost_center_analysis'] = [];
    }

    // Approval efficiency metrics
    try {
        $sql = "SELECT 
                    COUNT(*) as total_approvals,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    AVG(CASE WHEN status = 'approved' AND approved_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(HOUR, created_at, approved_at) END) as avg_approval_hours
                FROM approvals
                WHERE created_at BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $analytics['approval_metrics'] = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Analytics approval_metrics error: " . $e->getMessage());
        $analytics['approval_metrics'] = [
            'total_approvals' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'pending_count' => 0,
            'avg_approval_hours' => 0,
        ];
    }

    // Dead stock reporting (items with no movement in last 180 days and high stock levels)
    try {
        $sql = "SELECT ii.id, ii.item_code, ii.name, ii.current_stock, ii.reorder_point, 
                       ii.unit_cost, ii.category, v.name as supplier_name,
                       (ii.current_stock * ii.unit_cost) as total_value,
                       DATEDIFF(CURDATE(), IFNULL(MAX(poi.created_at), '1900-01-01')) as days_since_last_order,
                       COUNT(DISTINCT poi.po_id) as total_orders
                FROM inventory_items ii
                LEFT JOIN vendors v ON ii.supplier_id = v.id
                LEFT JOIN po_items poi ON ii.id = poi.item_id 
                    AND poi.created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                WHERE ii.is_active = 1
                    AND ii.current_stock > (ii.reorder_point * 2)
                GROUP BY ii.id, ii.item_code, ii.name, ii.current_stock, ii.reorder_point, 
                         ii.unit_cost, ii.category, v.name
                HAVING days_since_last_order >= 90 OR (days_since_last_order IS NULL AND ii.created_at < DATE_SUB(CURDATE(), INTERVAL 180 DAY))
                ORDER BY total_value DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $analytics['dead_stock_items'] = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Analytics dead_stock_items error: " . $e->getMessage());
        $analytics['dead_stock_items'] = [];
    }

    // Contract renewal alerts (contracts expiring within 90 days)
    $has_contracts_alerts = false;
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'vendor_contracts'");
        $stmt->execute();
        $has_contracts_alerts = $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $has_contracts_alerts = false;
    }
    
    try {
        if ($has_contracts_alerts) {
            $sql = "SELECT vc.id, vc.contract_number, vc.contract_name, vc.end_date, vc.status,
                           v.name as vendor_name, vc.total_value,
                           DATEDIFF(vc.end_date, CURDATE()) as days_to_expiry
                    FROM vendor_contracts vc
                    JOIN vendors v ON vc.vendor_id = v.id
                    WHERE vc.status = 'active' 
                        AND vc.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                    ORDER BY vc.end_date ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $analytics['contracts_expiring'] = $stmt->fetchAll();
        } else {
            $analytics['contracts_expiring'] = [];
        }
    } catch (Exception $e) {
        error_log("Analytics contracts_expiring error: " . $e->getMessage());
        $analytics['contracts_expiring'] = [];
    }

// Handle export
if ($export) {
    $filename = 'procurement_report_' . $report_type . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($report_type) {
        case 'overview':
            fputcsv($output, ['Report Type', 'Overview Report']);
            fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Metric', 'Value']);
            fputcsv($output, ['Total Spend', formatCurrency($analytics['total_spend'])]);
            fputcsv($output, ['Average Approval Time', $analytics['avg_approval_time'] ? number_format($analytics['avg_approval_time'], 1) . ' hours' : 'N/A']);
            fputcsv($output, ['Low Stock Items', count($analytics['low_stock_items'])]);
            fputcsv($output, ['Maverick Spending', count($analytics['maverick_spending'])]);
            break;
            
        case 'financial':
            fputcsv($output, ['Report Type', 'Financial Report']);
            fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Category', 'Spend Amount']);
            foreach ($analytics['spend_by_category'] as $category) {
                fputcsv($output, [$category['category'], formatCurrency($category['category_spend'])]);
            }
            break;
            
        case 'performance':
            fputcsv($output, ['Report Type', 'Performance Report']);
            fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Vendor Name', 'Total Spend', 'PO Count', 'Average Order Value']);
            foreach ($analytics['top_vendors'] as $vendor) {
                $total = (float)($vendor['vendor_spend'] ?? 0);
                $count = (int)($vendor['po_count'] ?? 0);
                $avg = $count > 0 ? $total / $count : 0;
                fputcsv($output, [
                    $vendor['name'],
                    formatCurrency($total),
                    $count,
                    formatCurrency($avg)
                ]);
            }
            break;
            
        case 'compliance':
            fputcsv($output, ['Report Type', 'Compliance Report']);
            fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Compliance Metric', 'Value']);
            $statusCounts = ['approved' => 0, 'rejected' => 0, 'pending' => 0];
            foreach ($analytics['requisition_status'] as $row) {
                $status = strtolower($row['status']);
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status] = (int)$row['count'];
                }
            }
            fputcsv($output, ['Approved Requisitions', $statusCounts['approved']]);
            fputcsv($output, ['Rejected Requisitions', $statusCounts['rejected']]);
            fputcsv($output, ['Pending Requisitions', $statusCounts['pending']]);
            break;
            
        case 'audit':
            fputcsv($output, ['Report Type', 'Audit Log Report']);
            fputcsv($output, ['Date Range', $start_date . ' to ' . $end_date]);
            fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
            fputcsv($output, []);
            fputcsv($output, ['Date', 'User', 'Action', 'Table', 'Record ID', 'IP Address']);
            foreach ($analytics['audit_logs'] as $log) {
                fputcsv($output, [
                    $log['created_at'],
                    $log['username'] ?? 'System',
                    $log['action'],
                    $log['table_name'],
                    $log['record_id'],
                    $log['ip_address']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .sidebar {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .main-content {
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        .stat-card.danger {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .report-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }
        .report-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-weight: 500;
        }
        .report-tabs .nav-link.active {
            border-bottom-color: #667eea;
            color: #667eea;
            background: none;
        }
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }
        .metric-card.success { border-left-color: #28a745; }
        .metric-card.warning { border-left-color: #ffc107; }
        .metric-card.danger { border-left-color: #dc3545; }
        .metric-card.info { border-left-color: #17a2b8; }
        .log-entry {
            border-left: 3px solid #667eea;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 0 5px 5px 0;
        }
        .log-entry.create { border-left-color: #28a745; }
        .log-entry.update { border-left-color: #ffc107; }
        .log-entry.delete { border-left-color: #dc3545; }
        .log-entry.approve { border-left-color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h5 class="text-primary fw-bold"><?php echo APP_NAME; ?></h5>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                        <?php if (hasPermission('manage_vendors')): ?>
                        <a class="nav-link" href="vendor.php">
                            <i class="bi bi-building me-2"></i>Vendors
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('create_requisition')): ?>
                        <a class="nav-link" href="requisition.php">
                            <i class="bi bi-file-earmark-text me-2"></i>Requisitions
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('approve_requisition')): ?>
                        <a class="nav-link" href="approval.php">
                            <i class="bi bi-check-circle me-2"></i>Approvals
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('create_po')): ?>
                        <a class="nav-link" href="purchase_order.php">
                            <i class="bi bi-receipt me-2"></i>Purchase Orders
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_inventory')): ?>
                        <a class="nav-link" href="inventory.php">
                            <i class="bi bi-boxes me-2"></i>Inventory
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('view_analytics')): ?>
                        <a class="nav-link active" href="analytics.php">
                            <i class="bi bi-graph-up me-2"></i>Analytics
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('manage_users')): ?>
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="bi bi-shield-check me-2"></i>Admin Panel
                        </a>
                        <?php endif; ?>
                        <a class="nav-link" href="notification_center.php">
                            <i class="bi bi-bell me-2"></i>Notifications
                            <?php if ($unread_notifications > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $unread_notifications; ?></span>
                            <?php endif; ?>
                        </a>
                        <hr>
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-graph-up me-2"></i>Analytics & Reports</h2>
                    <div class="d-flex gap-2">
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search me-2"></i>Filter
                            </button>
                        </form>
                        <button class="btn btn-success" onclick="exportReport()">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                    </div>
                </div>

                <!-- Report Type Tabs -->
                <ul class="nav nav-tabs report-tabs" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'overview' ? 'active' : ''; ?>" 
                                onclick="switchReport('overview')" type="button">
                            <i class="bi bi-speedometer2 me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'financial' ? 'active' : ''; ?>" 
                                onclick="switchReport('financial')" type="button">
                            <i class="bi bi-currency-dollar me-2"></i>Financial
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'performance' ? 'active' : ''; ?>" 
                                onclick="switchReport('performance')" type="button">
                            <i class="bi bi-trophy me-2"></i>Performance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'compliance' ? 'active' : ''; ?>" 
                                onclick="switchReport('compliance')" type="button">
                            <i class="bi bi-shield-check me-2"></i>Compliance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $report_type == 'audit' ? 'active' : ''; ?>" 
                                onclick="switchReport('audit')" type="button">
                            <i class="bi bi-journal-text me-2"></i>Audit Logs
                        </button>
                    </li>
                </ul>

                <!-- Overview Report -->
                <?php if ($report_type == 'overview'): ?>
                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Total Spend</h6>
                                    <h3 class="fw-bold"><?php echo formatCurrency($analytics['total_spend']); ?></h3>
                                </div>
                                <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Avg Approval Time</h6>
                                    <h3 class="fw-bold"><?php echo $analytics['avg_approval_time'] ? number_format($analytics['avg_approval_time'], 1) . 'h' : 'N/A'; ?></h3>
                                </div>
                                <i class="bi bi-clock fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Low Stock Items</h6>
                                    <h3 class="fw-bold"><?php echo count($analytics['low_stock_items']); ?></h3>
                                </div>
                                <i class="bi bi-exclamation-triangle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card danger">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="text-white-50">Maverick Spending</h6>
                                    <h3 class="fw-bold"><?php echo count($analytics['maverick_spending']); ?></h3>
                                </div>
                                <i class="bi bi-shield-exclamation fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Spend by Category</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['spend_by_category'])): ?>
                                    <p class="text-muted mb-0">No spend data for the selected period.</p>
                                <?php else: ?>
                                    <div class="chart-container">
                                        <canvas id="categoryChart"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Monthly Spend Trend</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['monthly_trend'])): ?>
                                    <p class="text-muted mb-0">No monthly trend data available.</p>
                                <?php else: ?>
                                    <div class="chart-container">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Tables Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Top Vendors by Spend</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['top_vendors'])): ?>
                                    <p class="text-muted">No vendor data available</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Vendor</th>
                                                    <th>Spend</th>
                                                    <th>POs</th>
                                                    <th>Score</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($analytics['top_vendors'] as $vendor): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                                        <td><?php echo formatCurrency($vendor['vendor_spend']); ?></td>
                                                        <td><?php echo $vendor['po_count']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $vendor['vendor_score'] >= 8 ? 'success' : ($vendor['vendor_score'] >= 6 ? 'warning' : 'danger'); ?>">
                                                                <?php echo number_format($vendor['vendor_score'], 1); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Low Stock Items</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['low_stock_items'])): ?>
                                    <p class="text-success">All items are well stocked!</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Current</th>
                                                    <th>Reorder</th>
                                                    <th>Supplier</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($analytics['low_stock_items'] as $item): ?>
                                                    <tr class="<?php echo $item['current_stock'] == 0 ? 'table-danger' : 'table-warning'; ?>">
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['item_code']); ?></small>
                                                        </td>
                                                        <td><?php echo $item['current_stock']; ?></td>
                                                        <td><?php echo $item['reorder_point']; ?></td>
                                                        <td><?php echo htmlspecialchars($item['supplier_name'] ?: 'N/A'); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Financial Report -->
                <?php if ($report_type == 'financial'): ?>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="metric-card">
                            <h6 class="text-muted">Total Spend</h6>
                            <h3 class="fw-bold text-primary"><?php echo formatCurrency($analytics['total_spend']); ?></h3>
                            <small class="text-muted">Period: <?php echo formatDate($start_date); ?> - <?php echo formatDate($end_date); ?></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card success">
                            <h6 class="text-muted">Department Spend</h6>
                            <h3 class="fw-bold"><?php echo count($analytics['department_spend']); ?> Departments</h3>
                            <small class="text-muted">Active cost centers</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="metric-card info">
                            <h6 class="text-muted">Payment Terms</h6>
                            <h3 class="fw-bold"><?php echo count($analytics['payment_terms_analysis']); ?> Methods</h3>
                            <small class="text-muted">Payment options used</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Department Spend Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Spend</th>
                                                <th>Requests</th>
                                                <th>Avg/Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['department_spend'] as $dept): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                    <td><?php echo formatCurrency($dept['dept_spend']); ?></td>
                                                    <td><?php echo $dept['req_count']; ?></td>
                                                    <td><?php echo formatCurrency($dept['dept_spend'] / $dept['req_count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment Terms Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Payment Terms</th>
                                                <th>Count</th>
                                                <th>Total Amount</th>
                                                <th>Avg Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['payment_terms_analysis'] as $payment): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($payment['payment_terms']); ?></td>
                                                    <td><?php echo $payment['count']; ?></td>
                                                    <td><?php echo formatCurrency($payment['total_amount']); ?></td>
                                                    <td><?php echo formatCurrency($payment['total_amount'] / $payment['count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Performance Report -->
                <?php if ($report_type == 'performance'): ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <h6 class="text-muted">Total Approvals</h6>
                            <h3 class="fw-bold text-primary"><?php echo $analytics['approval_metrics']['total_approvals'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card success">
                            <h6 class="text-muted">Approved</h6>
                            <h3 class="fw-bold"><?php echo $analytics['approval_metrics']['approved_count'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card warning">
                            <h6 class="text-muted">Pending</h6>
                            <h3 class="fw-bold"><?php echo $analytics['approval_metrics']['pending_count'] ?? 0; ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card danger">
                            <h6 class="text-muted">Rejected</h6>
                            <h3 class="fw-bold"><?php echo $analytics['approval_metrics']['rejected_count'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Vendor Performance Metrics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Vendor</th>
                                                <th>Score</th>
                                                <th>Delivery Time</th>
                                                <th>Defect Rate</th>
                                                <th>On-Time %</th>
                                                <th>Total POs</th>
                                                <th>Total Spend</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['vendor_performance'] as $vendor): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($vendor['name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $vendor['vendor_score'] >= 8 ? 'success' : ($vendor['vendor_score'] >= 6 ? 'warning' : 'danger'); ?>">
                                                            <?php echo number_format($vendor['vendor_score'], 1); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $vendor['delivery_time_avg']; ?> days</td>
                                                    <td><?php echo number_format($vendor['defect_rate'], 1); ?>%</td>
                                                    <td><?php echo number_format($vendor['on_time_percentage'], 1); ?>%</td>
                                                    <td><?php echo $vendor['total_pos']; ?></td>
                                                    <td><?php echo formatCurrency($vendor['total_spend']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Compliance Report -->
                <?php if ($report_type == 'compliance'): ?>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Maverick Spending</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['maverick_spending'])): ?>
                                    <p class="text-success">No maverick spending detected!</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>PO Number</th>
                                                    <th>Vendor</th>
                                                    <th>Amount</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($analytics['maverick_spending'] as $po): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($po['vendor_name']); ?></td>
                                                        <td><?php echo formatCurrency($po['total_amount']); ?></td>
                                                        <td><?php echo formatDate($po['created_at']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Cost Center Analysis</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Cost Center</th>
                                                <th>Spend</th>
                                                <th>Requests</th>
                                                <th>Avg/Request</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['cost_center_analysis'] as $cc): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cc['cost_center']); ?></td>
                                                    <td><?php echo formatCurrency($cc['cost_center_spend']); ?></td>
                                                    <td><?php echo $cc['req_count']; ?></td>
                                                    <td><?php echo formatCurrency($cc['cost_center_spend'] / $cc['req_count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4 mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header" id="deadstock">
                                <h5 class="mb-0"><i class="bi bi-archive me-2"></i>Dead Stock Items</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['dead_stock_items'])): ?>
                                    <p class="text-success">No dead stock detected!</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Item Code</th>
                                                    <th>Item Name</th>
                                                    <th>Category</th>
                                                    <th>Current Stock</th>
                                                    <th>Reorder Point</th>
                                                    <th>Days Since Last Order</th>
                                                    <th>Total Value</th>
                                                    <th>Supplier</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($analytics['dead_stock_items'] as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                        <td><?php echo $item['current_stock']; ?></td>
                                                        <td><?php echo $item['reorder_point']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $item['days_since_last_order'] >= 180 ? 'danger' : 'warning'; ?>">
                                                                <?php echo $item['days_since_last_order'] ?? 'No orders'; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatCurrency($item['total_value']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($analytics['contracts_expiring'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Contracts Expiring Soon</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Contract Number</th>
                                                <th>Contract Name</th>
                                                <th>Vendor</th>
                                                <th>End Date</th>
                                                <th>Days to Expiry</th>
                                                <th>Total Value</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($analytics['contracts_expiring'] as $contract): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($contract['contract_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($contract['contract_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($contract['vendor_name']); ?></td>
                                                    <td><?php echo formatDate($contract['end_date']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $contract['days_to_expiry'] <= 30 ? 'danger' : 'warning'; ?>">
                                                            <?php echo $contract['days_to_expiry']; ?> days
                                                        </span>
                                                    </td>
                                                    <td><?php echo formatCurrency($contract['total_value']); ?></td>
                                                    <td>
                                                        <span class="badge bg-warning">
                                                            Expiring
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Audit Logs Report -->
                <?php if ($report_type == 'audit'): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Audit Logs</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($analytics['audit_logs'])): ?>
                                    <p class="text-muted">No audit logs found for the selected period.</p>
                                <?php else: ?>
                                    <div class="audit-logs">
                                        <?php foreach ($analytics['audit_logs'] as $log): ?>
                                            <div class="log-entry <?php echo strtolower($log['action']); ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                                        on <code><?php echo htmlspecialchars($log['table_name']); ?></code>
                                                        <?php if ($log['record_id']): ?>
                                                            (ID: <?php echo $log['record_id']; ?>)
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            By: <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name'] . ' (' . $log['username'] . ')'); ?>
                                                            | <?php echo formatDate($log['created_at']); ?>
                                                        </small>
                                                    </div>
                                                    <small class="text-muted"><?php echo $log['ip_address']; ?></small>
                                                </div>
                                                <?php if ($log['new_values']): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Details:</small>
                                                        <pre class="small bg-light p-2 rounded mt-1"><?php echo htmlspecialchars($log['new_values']); ?></pre>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh notification badge every 30 seconds
        setInterval(function() {
            fetch('notification_center.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=1&action=get_count'
            })
            .then(response => response.json())
            .then(data => {
                const navLink = document.querySelector('.nav-link[href="notification_center.php"]');
                if (!navLink) return;
                let badge = navLink.querySelector('.badge');
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        navLink.innerHTML += ` <span class="badge bg-danger ms-2">${data.count}</span>`;
                    }
                } else if (badge) {
                    badge.remove();
                }
            })
            .catch(() => {});
        }, 30000);

        function switchReport(type) {
            const url = new URL(window.location);
            url.searchParams.set('report_type', type);
            window.location.href = url.toString();
        }

        function exportReport() {
            const url = new URL(window.location);
            url.searchParams.set('export', '1');
            window.open(url.toString(), '_blank');
        }

        // Category Chart
        (function(){
            const categoryData = <?php echo json_encode($analytics['spend_by_category']); ?>;
            const categoryCtx = document.getElementById('categoryChart');
            if (!categoryCtx || !Array.isArray(categoryData) || categoryData.length === 0 || typeof Chart === 'undefined') return;
            const categoryLabels = categoryData.map(item => item.category || 'Uncategorized');
            const categoryValues = categoryData.map(item => parseFloat(item.category_spend));
            new Chart(categoryCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryValues,
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c',
                            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })();

        // Trend Chart
        (function(){
            const trendData = <?php echo json_encode($analytics['monthly_trend']); ?>;
            const trendCtx = document.getElementById('trendChart');
            if (!trendCtx || !Array.isArray(trendData) || trendData.length === 0 || typeof Chart === 'undefined') return;
            const trendLabels = trendData.map(item => item.month);
            const trendValues = trendData.map(item => parseFloat(item.monthly_spend));
            new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Monthly Spend',
                        data: trendValues,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'K' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
</body>
</html>