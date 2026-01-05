# Notification System Setup Guide

This guide explains how to set up and use the new notification system and Chief Procurement Officer (CPO) approval workflow.

## New Features Added

### 1. Inventory Low Stock Notifications
- Automatic notifications when inventory items fall below reorder point
- Notifications sent to CPO, Inventory Manager, and Admin users
- Real-time notifications when stock is adjusted
- Visual indicators on dashboard and inventory pages

### 2. Chief Procurement Officer (CPO) Role
- New CPO role with approval permissions
- Updated approval workflow to use CPO as primary approver
- CPO can approve requisitions, inventory issues, and stock transfers
- CPO receives notifications for all approval requests

### 3. Notification Center
- Centralized notification management
- Real-time notification updates
- Mark as read/unread functionality
- Notification categories (inventory, approval, requisition, etc.)

## Database Updates Required

### 1. Run the Updated Schema
Execute the updated `schema.sql` file to add:
- New `notifications` table
- Updated roles with CPO
- Updated approval rules pointing to CPO role
- New sample users including CPO

### 2. Key Database Changes
```sql
-- New notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    category ENUM('inventory', 'requisition', 'approval', 'purchase_order', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Updated roles with CPO
INSERT INTO roles (name, description, permissions) VALUES
('chief_procurement_officer', 'Chief Procurement Officer (CPO)', '{"approve_requisition": true, "view_requisitions": true, "view_analytics": true, "approve_inventory_issues": true, "approve_stock_transfers": true, "create_po": true, "manage_vendors": true, "view_inventory": true, "manage_notifications": true}');

-- Updated approval rules to use CPO role (ID: 2)
UPDATE approval_rules SET role_to_notify = 2 WHERE id IN (1, 2, 3);
```

## File Structure Changes

### New Files Added
- `includes/notifications.php` - Notification system class
- `notification_center.php` - Notification management page
- `cron/check_low_stock.php` - Automated low stock checking script
- `NOTIFICATION_SETUP.md` - This setup guide

### Modified Files
- `schema.sql` - Added notifications table and updated roles
- `inventory.php` - Added low stock notification triggers
- `approval.php` - Added notification system integration
- `requisition.php` - Added approval notifications for CPO
- `dashboard.php` - Added notification count display
- `config/config.php` - Added notification permission checks

## Setup Instructions

### 1. Database Setup
```bash
# Backup your existing database first
mysqldump -u username -p procurement_platform > backup.sql

# Run the updated schema
mysql -u username -p procurement_platform < schema.sql
```

### 2. Set Up Automated Low Stock Checking (Optional)
Add to your crontab to check for low stock daily:
```bash
# Edit crontab
crontab -e

# Add this line to check daily at 9 AM
0 9 * * * php /path/to/procurement/cron/check_low_stock.php
```

### 3. Test the System
1. Login as admin and create some inventory items with low stock
2. Login as CPO user (username: cpo, password: password)
3. Create a requisition and submit it for approval
4. Check that CPO receives notification and can approve
5. Verify low stock notifications are created

## User Roles and Permissions

### Chief Procurement Officer (CPO)
- **Username**: cpo
- **Password**: password (change in production)
- **Permissions**:
  - Approve requisitions
  - View requisitions
  - View analytics
  - Approve inventory issues
  - Approve stock transfers
  - Create purchase orders
  - Manage vendors
  - View inventory
  - Manage notifications

### Updated Approval Workflow
1. **Low Value (0 - K20,000)**: CPO approval with auto-approve option
2. **Medium Value (K20,001 - K100,000)**: CPO approval required
3. **High Value (K100,001 - K1,000,000)**: CPO approval required
4. **Executive (>K1,000,000)**: Admin approval required

## Notification Types

### Inventory Notifications
- **Low Stock Alert**: When items fall below reorder point
- **Out of Stock**: When items reach zero stock
- **Stock Adjustment**: When stock is manually adjusted

### Approval Notifications
- **New Approval Request**: Sent to CPO when requisition is submitted
- **Approval Decision**: Sent to requester when approved/rejected

### System Notifications
- **General system messages**
- **Error notifications**
- **Success confirmations**

## Troubleshooting

### Notifications Not Appearing
1. Check database connection
2. Verify notification table exists
3. Check user permissions
4. Review error logs

### Low Stock Notifications Not Working
1. Ensure inventory items have reorder points set
2. Check that CPO/Inventory Manager users exist
3. Run the cron script manually to test
4. Verify notification system is loaded

### Approval Workflow Issues
1. Check approval rules in database
2. Verify CPO role has correct permissions
3. Ensure CPO user is active
4. Check requisition submission process

## Security Considerations

1. **Change Default Passwords**: Update all default passwords in production
2. **Notification Permissions**: Only authorized users can manage notifications
3. **Audit Trail**: All notification actions are logged
4. **Data Privacy**: Notifications contain sensitive business information

## Performance Optimization

1. **Notification Cleanup**: Old notifications are automatically cleaned up after 30 days
2. **Indexing**: Database indexes are created for optimal performance
3. **Caching**: Consider implementing notification caching for high-traffic systems
4. **Batch Processing**: Low stock checks can be run in batches

## Support

For issues or questions:
1. Check the error logs in your web server
2. Review the database for any constraint violations
3. Verify all required files are present
4. Test with a fresh database installation

## Future Enhancements

Potential improvements for the notification system:
- Email notifications
- SMS notifications
- Push notifications for mobile
- Notification templates
- Advanced filtering and search
- Notification scheduling
- Integration with external systems
