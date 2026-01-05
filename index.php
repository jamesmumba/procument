<?php
/**
 * Main Index Page
 * Landing page for the Procurement Platform
 */

require_once 'config/config.php';

// Check if user is logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Welcome</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }
        .feature-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-outline-primary {
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 25px;
            padding: 10px 28px;
            font-weight: 600;
        }
        .btn-outline-primary:hover {
            background: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-building me-2"></i><?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="login.php">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Streamline Your Procurement Process</h1>
                    <p class="lead mb-4">
                        A comprehensive procurement management system that automates purchase requests, 
                        approvals, vendor management, and inventory tracking for your organization.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Get Started
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="bi bi-graph-up-arrow display-1 opacity-75"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-3">Powerful Features</h2>
                    <p class="lead text-muted">
                        Everything you need to manage procurement efficiently and transparently
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h5 class="fw-bold">Purchase Requisitions</h5>
                        <p class="text-muted">Create and manage purchase requests with multi-item support and detailed specifications.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h5 class="fw-bold">Approval Workflow</h5>
                        <p class="text-muted">Configurable multi-level approval system based on amount thresholds and business rules.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h5 class="fw-bold">Vendor Management</h5>
                        <p class="text-muted">Complete vendor database with performance tracking and contract management.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-boxes"></i>
                        </div>
                        <h5 class="fw-bold">Inventory Control</h5>
                        <p class="text-muted">Real-time inventory tracking with automated reorder alerts and stock adjustments.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <h5 class="fw-bold">Purchase Orders</h5>
                        <p class="text-muted">Generate and manage purchase orders with vendor integration and delivery tracking.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                        <h5 class="fw-bold">Analytics & Reports</h5>
                        <p class="text-muted">Comprehensive spending analytics, vendor performance metrics, and budget tracking.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold">Security & Audit</h5>
                        <p class="text-muted">Role-based access control, complete audit trails, and secure data handling.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-gear"></i>
                        </div>
                        <h5 class="fw-bold">API Integration</h5>
                        <p class="text-muted">RESTful API endpoints for seamless integration with existing business systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Roles Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-3">User Roles</h2>
                    <p class="lead text-muted">
                        Designed for different organizational roles with appropriate access levels
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-person-gear"></i>
                        </div>
                        <h5 class="fw-bold text-primary">Admin</h5>
                        <p class="text-muted">Full system access, user management, and system configuration.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h5 class="fw-bold text-success">Procurement Officer</h5>
                        <p class="text-muted">Create requisitions, manage vendors, and process purchase orders.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <h5 class="fw-bold text-warning">Approver</h5>
                        <p class="text-muted">Review and approve purchase requests based on budget and policies.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-person-eye"></i>
                        </div>
                        <h5 class="fw-bold text-info">Viewer</h5>
                        <p class="text-muted">Read-only access to view reports, analytics, and transaction history.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-3">Why Choose Our Platform?</h2>
                    <p class="lead text-muted">
                        Transform your procurement process with these key benefits
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <h5 class="fw-bold">Save Time</h5>
                        <p class="text-muted">Reduce approval times by up to 70% with automated workflows and digital processes.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <h5 class="fw-bold">Reduce Costs</h5>
                        <p class="text-muted">Optimize spending with real-time analytics, vendor performance tracking, and budget controls.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h5 class="fw-bold">Ensure Compliance</h5>
                        <p class="text-muted">Maintain full audit trails, enforce approval policies, and meet regulatory requirements.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h5 class="fw-bold">Improve Visibility</h5>
                        <p class="text-muted">Get real-time insights into spending patterns, vendor performance, and inventory levels.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5 class="fw-bold">Enhance Collaboration</h5>
                        <p class="text-muted">Streamline communication between departments, approvers, and vendors.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-mobile"></i>
                        </div>
                        <h5 class="fw-bold">Mobile Ready</h5>
                        <p class="text-muted">Access and approve requests from anywhere with our responsive, mobile-friendly interface.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Flow Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-3">How It Works</h2>
                    <p class="lead text-muted">
                        Simple, streamlined procurement process in 5 easy steps
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-2">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">1</span>
                        </div>
                        <h6 class="fw-bold">Create Request</h6>
                        <p class="small text-muted">Submit purchase requisitions with detailed specifications</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-2">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">2</span>
                        </div>
                        <h6 class="fw-bold">Get Approval</h6>
                        <p class="small text-muted">Automated routing to appropriate approvers based on amount</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-2">
                    <div class="text-center">
                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">3</span>
                        </div>
                        <h6 class="fw-bold">Create Order</h6>
                        <p class="small text-muted">Generate purchase orders with selected vendors</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-2">
                    <div class="text-center">
                        <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">4</span>
                        </div>
                        <h6 class="fw-bold">Track Delivery</h6>
                        <p class="small text-muted">Monitor order status and delivery progress</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-2">
                    <div class="text-center">
                        <div class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <span class="fw-bold">5</span>
                        </div>
                        <h6 class="fw-bold">Receive & Pay</h6>
                        <p class="small text-muted">Update inventory and process vendor payments</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Industries Section -->
    <section class="py-5">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-3">Perfect for Every Industry</h2>
                    <p class="lead text-muted">
                        Trusted by organizations across various sectors
                    </p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <h5 class="fw-bold">Healthcare</h5>
                        <p class="text-muted">Manage medical supplies, equipment, and pharmaceutical purchases with compliance tracking.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-mortarboard"></i>
                        </div>
                        <h5 class="fw-bold">Education</h5>
                        <p class="text-muted">Streamline school supplies, technology, and facility management procurement.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <h5 class="fw-bold">Manufacturing</h5>
                        <p class="text-muted">Optimize raw materials, equipment, and supply chain management.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="feature-icon">
                            <i class="bi bi-bank"></i>
                        </div>
                        <h5 class="fw-bold">Financial Services</h5>
                        <p class="text-muted">Ensure compliance and control in IT, office supplies, and vendor management.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-8 mx-auto">
                    <h2 class="display-5 fw-bold mb-4">Ready to Transform Your Procurement?</h2>
                    <p class="lead mb-4">
                        Join thousands of organizations already using our platform to streamline their procurement processes
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="login.php" class="btn btn-light btn-lg">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Get Started Now
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-info-circle me-2"></i>Learn More
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="fw-bold"><?php echo APP_NAME; ?></h5>
                    <p class="text-muted">Streamline your procurement process with our comprehensive management system.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">
                        <i class="bi bi-shield-check me-1"></i>
                        Secure • Reliable • Efficient
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
