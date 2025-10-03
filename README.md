# Smart Waste Management System

A comprehensive web application for managing waste collection operations with role-based dashboards for residents, collectors, authorities, and admins.

## 🌟 Features

### For Residents
- **Waste Report Submission**: Submit detailed reports with images, location, and priority levels
- **Collection Schedule Management**: View and confirm upcoming waste collection schedules
- **Eco Points System**: Earn points for active participation and responsible waste management
- **Feedback System**: Submit suggestions, complaints, and appreciation
- **Real-time Chat**: Communicate directly with waste management authorities
- **Mobile-Responsive Design**: Access from any device with a modern, intuitive interface

### For Waste Authorities
- **Dashboard Overview**: Real-time statistics and monitoring of operations
- **Report Management**: Review, assign, and update user-submitted reports
- **Schedule Management**: Create and manage collection schedules
- **Collector Assignment**: Assign waste collectors to specific routes
- **Analytics & Reporting**: Track performance metrics and generate reports
- **Chat Support**: Provide real-time assistance to residents

### For Collectors
- **Collector Dashboard**: Live stats for today's assigned, completed, and remaining tasks
- **My Routes**: Filter tasks by day/status; quick view of time, area/street, waste type
- **Collections History**: Date/status filters with CSV export (last 30 days by default)
- **Task Actions**: Start/Complete tasks with server-side validation and history recording
- **Evidence Uploads**: Attach photo evidence to tasks
- **Map View**: Stops plotted on a map; uses stored coordinates and caches geocoded results
- **Profile**: Update phone/address and change password
- **Chat**: Embedded chat interface
- **Push Notifications**: Opt-in browser notifications for assignment updates

## 🏗️ System Architecture

- **Backend**: PHP 7.4+ with MySQL database
- **Frontend**: Bootstrap 5, Font Awesome icons
- **Database**: MySQL with comprehensive schema for all features
- **Authentication**: Secure session-based authentication with role management
- **File Upload**: Secure image upload for waste reports
- **Real-time Updates**: Auto-refresh functionality for chat and notifications

## 📋 Requirements

- **Server**: XAMPP, WAMP, or any PHP-compatible web server
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Browser**: Modern browser with JavaScript enabled

## 🚀 Installation & Setup

### 1. Server Setup
1. Install XAMPP (recommended) or your preferred PHP server
2. Start Apache and MySQL services
3. Navigate to your web server directory (e.g., `htdocs` for XAMPP)

### 2. Application Setup
1. Clone or download this project to your web server directory
2. Create a MySQL database named `smart_waste`
3. Import the database schema:
   ```sql
       -- Run the schema file
    mysql -u root -p smart_waste < script/schema.sql
   ```

### 3. Configuration
1. Edit `config/config.php` to match your database settings:
   ```php
   $DB_HOST = 'localhost';
   $DB_USER = 'root';
   $DB_PASS = '';
   $DB_NAME = 'smart_waste';
   ```

2. Update the base URL in `config/config.php`:
   ```php
   define('BASE_URL', '/your-project-folder/');
   ```

3. (Optional) Web Push configuration for notifications:
   - Set `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` in `config/config.php`.
   - Ensure `sw.js` is accessible at the app root (e.g., `/smart_waste/sw.js`).

### 4. Database migrations (idempotent)
Visit this helper to ensure new columns (e.g., schedule coordinates) exist:
```
http://localhost/your-project-folder/ensure_settings_tables.php
```

### 5. File Permissions
1. Create uploads directory and set permissions:
   ```bash
   mkdir uploads/reports
   chmod 755 uploads/reports
   ```

Also create evidence folder (for collector photo uploads):
```bash
mkdir -p uploads/evidence
chmod 755 uploads/evidence
```

### 6. Access the Application
1. Open your web browser
2. Navigate to `http://localhost/your-project-folder/`
3. Use the default login credentials (see below)

## 🔑 Default Login Credentials

| Role | Email | Password | Description |
|------|-------|----------|-------------|
| **Admin** | admin@smartwaste.local | Admin@123 | System administrator |
| **Authority** | authority@smartwaste.local | Authority@123 | Waste management authority |
| **Collector** | collector@smartwaste.local | Collector@123 | Waste collection staff |
| **Resident** | resident@smartwaste.local | Resident@123 | Demo resident account |

## 📱 User Roles & Permissions

### Resident
- Submit waste reports with images
- View collection schedules
- Earn eco points
- Submit feedback
- Chat with authorities
- View personal dashboard

### Authority
- Monitor all waste reports
- Manage collection schedules
- Assign collectors to routes
- View analytics and statistics
- Chat with residents
- Manage system operations

### Collector
- View assigned collection routes
- Update collection status
- Report completion
- View work schedule

### Admin
- Full system access
- User management
- System configuration
- Database maintenance

## 🗄️ Database Schema

The system includes the following main tables:

- **users**: User accounts and profiles
- **waste_reports**: Submitted waste management reports
- **report_images**: Images attached to reports
- **collection_schedules**: Waste collection schedules
- **collection_history**: Historical collection data
- **points_transactions**: Eco points tracking
- **feedback**: User feedback and suggestions
- **chat_messages**: Real-time chat functionality
- **notifications**: System notifications

## 🎨 Key Features in Detail

### Waste Report System
- **Report Types**: Overflow, missed collection, damaged bin, illegal dumping
- **Priority Levels**: Low, medium, high, urgent
- **Image Upload**: Multiple image support with validation
- **Location Tracking**: GPS coordinates and address input
- **Status Tracking**: Pending, assigned, in progress, completed

### Collection Schedule Management
- **Flexible Scheduling**: Daily, weekly, biweekly, monthly options
- **Route Assignment**: Assign collectors to specific areas
- **Waste Type Support**: General, recyclable, organic, hazardous
- **Real-time Updates**: Live schedule modifications

### Eco Points System
- **Earning Points**: Report submission, feedback, responsible behavior
- **Point Categories**: Earned, spent, bonus, penalty
- **Transaction History**: Complete audit trail
- **Gamification**: Encourage user engagement

### Chat System
- **Real-time Messaging**: Instant communication between users
- **File Sharing**: Support for images and documents
- **Read Receipts**: Message status tracking
- **Notification System**: Alert users of new messages

## 🔧 Customization

### Styling
- Modify CSS in individual dashboard files
- Update color schemes and branding
- Customize responsive breakpoints

### Functionality
- Add new report types
- Implement additional notification methods
- Extend the points system
- Add new user roles

### Database
- Modify table structures
- Add new fields and relationships
- Implement additional indexes for performance

## 📊 Performance Optimization

- **Database Indexing**: Optimized queries with proper indexes
- **Image Compression**: Efficient image handling
- **Caching**: Session-based caching for user data
- **Responsive Design**: Mobile-first approach for better performance

## 🔒 Security Features

- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: HTML escaping for all output
- **Session Security**: Secure session management
- **File Upload Security**: Type and size validation
- **Role-based Access Control**: Strict permission enforcement

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config/config.php`
   - Ensure MySQL service is running
   - Check database name exists

2. **Upload Directory Issues**
   - Verify `uploads/reports` directory exists
   - Check file permissions (755)
   - Ensure PHP has write access

3. **Session Issues**
   - Check PHP session configuration
   - Verify session directory permissions
   - Clear browser cookies if needed

4. **Image Upload Problems**
   - Check file size limits in PHP configuration
   - Verify allowed file types
   - Ensure upload directory is writable

### Debug Mode
Enable error reporting in `config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 📈 Future Enhancements

- **Mobile App**: Native iOS/Android applications
- **IoT Integration**: Smart bin sensors and monitoring
- **AI Analytics**: Predictive maintenance and route optimization
- **Blockchain**: Transparent waste tracking and verification
- **API Integration**: Third-party service connections
- **Multi-language Support**: Internationalization features

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- Create an issue in the repository
- Contact the development team
- Check the documentation

## 🎯 System Requirements

### Minimum Requirements
- **PHP**: 7.4+
- **MySQL**: 5.7+
- **Memory**: 128MB RAM
- **Storage**: 100MB free space

### Recommended Requirements
- **PHP**: 8.0+
- **MySQL**: 8.0+
- **Memory**: 512MB RAM
- **Storage**: 500MB free space

---

**Smart Waste Management System** - Making waste management smarter, more efficient, and environmentally friendly! 🌱♻️
