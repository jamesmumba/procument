# Procurement Platform

A comprehensive, secure web-based procurement management system built with PHP and MySQL. This platform provides end-to-end procurement workflow management including vendor management, purchase requisitions, approval workflows, inventory management, and analytics.

## Features

### 🔐 Authentication & Authorization
- Session-based authentication with secure password hashing
- Role-based access control (Admin, Chief Procurement Officer, Inventory Manager)
- CSRF protection and input sanitization
- Audit trail for all user actions

### 🏢 Vendor Management
- Complete vendor CRUD operations
- Vendor performance scoring (delivery time, defect rate, on-time percentage)
- Contract and certification management
- Vendor search and filtering

### 📋 Purchase Requisition Workflow
- Create and manage purchase requisitions
- Multi-item requisition support with specifications
- Priority levels and justification tracking
- Department and cost center assignment

### ✅ Approval Engine
- Configurable approval rules based on amount thresholds
- Multi-stage approval workflows
- Mobile-friendly approval interface
- Approval history and comments

### 📦 Inventory Management
- Item catalog with categories and specifications
- Real-time stock level tracking
- Automated reorder point alerts
- Stock adjustment with audit trail
- Supplier integration

### 📊 Analytics & Reporting
- Spend analytics by category and vendor
- Monthly spend trends and forecasting
- Maverick spending detection
- Low stock alerts and reports
- Approval time analytics

### 🔌 API Endpoints
- RESTful JSON API for all major operations
- Authentication endpoints
- Vendor management API
- Requisition and approval APIs
- Inventory management API

## Technology Stack

- **Backend**: PHP 7.4+ (Procedural with OOP classes)
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, Bootstrap 5, Chart.js
- **Security**: PDO prepared statements, password hashing, CSRF protection
- **Server**: Apache/Nginx with PHP support

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- XAMPP/WAMP/MAMP (for local development)

### Setup Instructions

1. **Clone or Download**
   ```bash
   # If using git
   git clone <repository-url>
   # Or download and extract the ZIP file
   ```

2. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE procurement_platform;
   
   -- Import schema
   mysql -u root -p procurement_platform < schema.sql
   ```

3. **Configure Database Connection**
   Edit `config/database.php`:
   ```php
   private $host = 'localhost';
   private $db_name = 'procurement_platform';
   private $username = 'root';
   private $password = 'your_password';
   ```

4. **Set Permissions**
   ```bash
   # Make sure web server can write to uploads directory
   chmod 755 uploads/
   ```

5. **Access the Application**
   - Open your browser
   - Navigate to `http://localhost/procurement`
   - Use demo credentials to login

## Demo Credentials

| Role | Username | Password | Access Level |
|------|----------|----------|--------------|
| Admin | admin | password123 | Full system access |
| Chief Procurement Officer (CPO) | cpo1 | password123 | Approve requisitions, manage vendors, create POs |
| Inventory Manager | inventory1 | password123 | Manage inventory, issue stock, transfers |

## File Structure

```
procurement/
├── api/                    # API endpoints
│   ├── index.php          # API router
│   ├── auth.php           # Authentication API
│   └── vendors.php        # Vendors API
├── auth/                  # Authentication classes
│   └── auth.php           # Auth management
├── config/                # Configuration files
│   ├── config.php         # Main configuration
│   └── database.php       # Database connection
├── uploads/               # File uploads directory
├── schema.sql            # Database schema
├── index.php             # Main entry point
├── login.php             # Login page
├── dashboard.php         # Main dashboard
├── vendor.php            # Vendor management
├── requisition.php       # Purchase requisitions
├── approval.php          # Approval workflow
├── inventory.php         # Inventory management
├── analytics.php         # Analytics dashboard
└── README.md             # This file
```

## API Usage

### Authentication
```bash
# Login
POST /api/auth/login
{
    "username": "admin",
    "password": "password123"
}

# Get current user
GET /api/auth/user
```

### Vendors
```bash
# Get vendors list
GET /api/vendors/list?page=1&limit=20&search=supplier

# Get single vendor
GET /api/vendors/get?id=1

# Create vendor
POST /api/vendors/create
{
    "name": "New Supplier",
    "contact_person": "John Doe",
    "email": "john@supplier.com"
}

# Update vendor
PUT /api/vendors/update?id=1
{
    "name": "Updated Supplier"
}

# Delete vendor
DELETE /api/vendors/delete?id=1
```

## Configuration

### Database Settings
Update `config/database.php` with your database credentials:
```php
private $host = 'your_host';
private $db_name = 'your_database';
private $username = 'your_username';
private $password = 'your_password';
```

### Application Settings
Modify `config/config.php` for application-specific settings:
```php
define('APP_NAME', 'Your Company Procurement');
define('APP_URL', 'https://yourdomain.com/procurement');
define('SESSION_TIMEOUT', 3600); // 1 hour
```

## Security Features

- **Password Security**: Bcrypt hashing with salt
- **SQL Injection Protection**: PDO prepared statements
- **CSRF Protection**: Token-based validation
- **Input Sanitization**: HTML entity encoding
- **Session Security**: Secure session management
- **Audit Trail**: Complete action logging

## Customization

### Adding New Roles
1. Insert new role in `roles` table
2. Update permissions JSON
3. Modify role checks in PHP files

### Custom Approval Rules
1. Add rules to `approval_rules` table
2. Modify approval logic in `requisition.php`
3. Update approval workflow as needed

### Adding New Fields
1. Update database schema
2. Modify relevant PHP files
3. Update API endpoints
4. Test thoroughly

## Troubleshooting

### Common Issues

**Database Connection Error**
- Check database credentials in `config/database.php`
- Ensure MySQL service is running
- Verify database exists

**Permission Denied**
- Check file permissions on `uploads/` directory
- Ensure web server has read/write access

**Session Issues**
- Check PHP session configuration
- Ensure `session_start()` is called
- Verify session directory permissions

**API Errors**
- Check authentication headers
- Verify endpoint URLs
- Review server error logs

### Debug Mode
Enable debug mode in `config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is open source and available under the MIT License.

## Support

For support and questions:
- Check the troubleshooting section
- Review the code comments
- Create an issue in the repository

## Roadmap

- [ ] Email notifications
- [ ] PDF generation for POs
- [ ] Advanced reporting
- [ ] Mobile app
- [ ] Integration with accounting systems
- [ ] Multi-language support

---

**Note**: This is a production-ready system with security best practices implemented. Always test thoroughly in a development environment before deploying to production.

