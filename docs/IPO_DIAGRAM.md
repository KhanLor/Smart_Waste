# Input-Process-Output (IPO) Diagram
## Smart Waste Management System

---

## 1. RESIDENT MODULE

### INPUT
- **User Registration**
  - First Name, Last Name
  - Email Address
  - Username, Password
  - Phone Number
  - Home Address
  - Profile Image (optional)

- **Waste Report Submission**
  - Report Title/Description
  - Location/Address
  - Waste Type (general, recyclable, hazardous, organic)
  - Priority Level (low, medium, high, urgent)
  - Photo Evidence (optional)
  - GPS Coordinates

- **Schedule Viewing**
  - Collection Day Selection
  - Area/Zone Filter

- **Chat Messages**
  - Message Text
  - Recipient Selection (Authority/Collector)

### PROCESS
- Validate user credentials
- Authenticate login sessions
- Store user profile data in database
- Process and upload waste report with images
- Geocode location data
- Filter collection schedules by day and area
- Send and receive real-time chat messages
- Display collector location on map
- Send push notifications for schedule updates

### OUTPUT
- **Dashboard Display**
  - Total reports submitted
  - Pending reports count
  - Upcoming collection schedule
  - Recent notifications

- **Report Status Updates**
  - Report confirmation message
  - Status changes (pending → in-progress → completed)
  - Notifications when collector is assigned

- **Collection Schedule Information**
  - Schedule day and time
  - Assigned collector name
  - Collection route details
  - Real-time collector tracking

- **Notifications**
  - Schedule change alerts
  - Report status updates
  - Chat message notifications

---

## 2. AUTHORITY MODULE

### INPUT
- **User Authentication**
  - Username/Email
  - Password

- **Report Management**
  - Report ID for viewing
  - Status update (pending, in-progress, completed, cancelled)
  - Priority reassignment
  - Collector assignment

- **Schedule Creation**
  - Collection Day (Monday-Sunday)
  - Collection Time
  - Area/Zone
  - Waste Type
  - Assigned Collector ID
  - Route Coordinates (optional)

- **Collector Management**
  - Search/Filter criteria
  - Collector ID for viewing stats
  - Task assignment data

- **Resident Management**
  - Search filters (name, email, address)
  - View resident details

- **Chat Communication**
  - Message text
  - Recipient (resident/collector)

### PROCESS
- Authenticate authority credentials
- Query and filter waste reports from database
- Assign collectors to reports
- Update report status and priority
- Create and manage collection schedules
- Store schedule data with geocoded routes
- Track collector locations in real-time
- Process and analyze system statistics
- Generate reports (PDF/Excel export)
- Send notifications to residents and collectors
- Manage chat conversations
- Perform database backups

### OUTPUT
- **Dashboard Analytics**
  - Total waste reports
  - Urgent reports count
  - Active schedules
  - Collections completed today
  - Recent report list
  - Today's schedule overview

- **Report Management Display**
  - Filtered report list
  - Report details with images
  - Status update confirmations
  - Collector assignment confirmations

- **Schedule Management**
  - Active schedules list
  - Schedule creation confirmation
  - Schedule update/delete confirmation
  - Collection history

- **Real-time Tracking**
  - Collector locations on map
  - Active collectors list
  - Route visualization
  - Last updated timestamps

- **Analytics & Reports**
  - Collection statistics charts
  - Report trends by priority/type
  - Performance metrics
  - Exported data files (PDF/Excel)

- **Notifications**
  - Push notifications to residents/collectors
  - Email notifications
  - In-app notification badges

---

## 3. COLLECTOR MODULE

### INPUT
- **User Authentication**
  - Username/Email
  - Password

- **Task Management**
  - Task ID for viewing
  - Status update (pending, in-progress, completed)
  - Evidence photo upload

- **Location Tracking**
  - GPS Coordinates (latitude, longitude)
  - Heading/Direction
  - Timestamp

- **Schedule Viewing**
  - Date selection
  - Filter by status

- **Chat Communication**
  - Message text
  - Recipient selection

### PROCESS
- Authenticate collector credentials
- Retrieve assigned tasks from database
- Update task status in real-time
- Upload and store evidence images
- Continuously track and update GPS location
- Calculate distance and route optimization
- Process schedule assignments
- Send location updates to tracking system
- Manage chat communications
- Generate collection history

### OUTPUT
- **Dashboard Display**
  - Tasks assigned today
  - Tasks completed
  - Tasks pending
  - Active route information

- **Task List**
  - Assigned tasks with details
  - Location/address information
  - Priority indicators
  - Status indicators

- **Task Completion**
  - Status update confirmation
  - Evidence upload success
  - Collection timestamp

- **Collection History**
  - Completed tasks list
  - Evidence images
  - Completion dates
  - Statistics (total collected, by date)

- **Real-time Location**
  - Current location broadcast
  - Route tracking data
  - Timestamp updates

- **Notifications**
  - New task assignments
  - Schedule changes
  - Chat messages

---

## 4. ADMIN MODULE

### INPUT
- **User Authentication**
  - Admin username/email
  - Admin password

- **User Management**
  - New user creation data
  - User role assignment (resident, collector, authority, admin)
  - User status updates (active, suspended)
  - Search/filter criteria

- **System Settings**
  - Application configuration
  - Notification settings
  - Email configuration
  - Push notification keys

- **Database Management**
  - Backup triggers
  - Restore file selection

### PROCESS
- Authenticate admin credentials with highest privileges
- Create, update, delete user accounts
- Assign and modify user roles
- Query all system data across modules
- Generate comprehensive system reports
- Configure system-wide settings
- Perform database backups and restores
- Monitor system health and performance
- Manage notification configurations
- Export user and activity data

### OUTPUT
- **Admin Dashboard**
  - Total users by role
  - System-wide statistics
  - Recent activities log
  - System health indicators

- **User Management Display**
  - Complete user list with filters
  - User details and activity
  - Role assignment confirmations
  - User creation/update confirmations

- **System Reports**
  - Activity logs
  - User statistics
  - Collection performance reports
  - Exported data files

- **System Configuration**
  - Settings update confirmations
  - Backup success/failure messages
  - Configuration validation results

- **Database Backups**
  - Backup files (.sql)
  - Backup timestamp
  - Success/error messages

---

## 5. NOTIFICATION SYSTEM

### INPUT
- **Notification Triggers**
  - New waste report creation
  - Report status change
  - Schedule creation/update
  - Task assignment to collector
  - Chat message sent
  - Collection completion

- **User Subscription Data**
  - Push notification endpoint
  - Browser/device information
  - User preferences

### PROCESS
- Monitor database for triggering events
- Queue notifications for processing
- Match notifications to subscribed users
- Generate notification content
- Send push notifications via web push API
- Send email notifications via PHPMailer
- Track notification delivery status
- Handle notification preferences
- Retry failed notifications

### OUTPUT
- **Push Notifications**
  - Browser notifications with title and message
  - Action buttons (View, Dismiss)
  - Notification badges on navigation

- **Email Notifications**
  - Formatted email messages
  - Action links
  - System branding

- **In-app Notifications**
  - Notification list display
  - Unread count badges
  - Notification previews in sidebar

---

## 6. TRACKING SYSTEM

### INPUT
- **Location Updates**
  - Collector GPS coordinates
  - Timestamp
  - Heading/bearing
  - Accuracy data

- **Map Interactions**
  - Zoom level
  - Center coordinates
  - Collector selection
  - Path toggle

### PROCESS
- Receive real-time location data from collectors
- Store location history in database
- Calculate route paths
- Update map markers in real-time
- Generate polylines for traveled routes
- Calculate distances and ETAs
- Filter and display active collectors
- Handle map rendering and updates

### OUTPUT
- **Real-time Map Display**
  - Collector markers with custom icons
  - Collector names and IDs
  - Route polylines
  - Last update timestamps
  - Current location indicators

- **Collector List**
  - Active collectors
  - Last seen information
  - Click-to-locate functionality

- **Path Visualization**
  - Historical route paths
  - Color-coded routes per collector
  - Toggle on/off functionality

---

## 7. CHAT SYSTEM

### INPUT
- **Message Data**
  - Sender ID and role
  - Receiver ID and role
  - Message text content
  - Timestamp

- **Conversation Selection**
  - Conversation ID
  - Participant filter

### PROCESS
- Validate sender/receiver permissions
- Store messages in database
- Mark messages as read/unread
- Retrieve conversation history
- Filter conversations by participant
- Count unread messages
- Send real-time chat notifications
- Update chat UI dynamically

### OUTPUT
- **Chat Interface**
  - Conversation list
  - Message thread display
  - Unread message badges
  - Sent/received timestamps

- **Chat Notifications**
  - New message alerts
  - Unread count updates
  - Message preview in sidebar

- **Message Status**
  - Sent confirmation
  - Read receipts (visual indicators)

---

## 8. ANALYTICS & REPORTING SYSTEM

### INPUT
- **Date Range Selection**
  - Start date
  - End date

- **Filter Criteria**
  - Report type
  - Priority level
  - Status
  - Collector ID
  - Area/zone

- **Export Options**
  - File format (PDF, Excel, CSV)
  - Data scope selection

### PROCESS
- Query database with date and filter parameters
- Aggregate statistics (counts, averages, trends)
- Generate chart data (pie, bar, line graphs)
- Process data for export formats
- Create formatted reports
- Calculate performance metrics
- Analyze collection efficiency
- Track report resolution times

### OUTPUT
- **Visual Charts**
  - Reports by priority (pie chart)
  - Reports by type (bar chart)
  - Weekly collection trends (line chart)
  - Status distribution (donut chart)

- **Statistics Cards**
  - Total reports count
  - Completion rate
  - Average response time
  - Active collectors
  - Coverage area metrics

- **Exported Files**
  - PDF reports with charts and tables
  - Excel spreadsheets with raw data
  - CSV files for further analysis

- **Performance Metrics**
  - Collector efficiency ratings
  - Area-wise collection statistics
  - Response time analysis
  - Completion trends

---

## SYSTEM ARCHITECTURE SUMMARY

### OVERALL INPUT
- User interactions (web interface)
- GPS/location data
- Image uploads
- Form submissions
- API requests
- Real-time data streams

### OVERALL PROCESS
- User authentication & authorization
- Database operations (CRUD)
- File storage management
- Real-time communication
- Geolocation processing
- Notification dispatching
- Data analytics & aggregation
- Report generation
- Background job processing

### OVERALL OUTPUT
- Web pages (HTML/CSS/JS)
- JSON API responses
- Push notifications
- Email notifications
- PDF/Excel reports
- Real-time map updates
- Database records
- Log files
- Backup files

---

## TECHNOLOGY STACK

### Frontend
- HTML5, CSS3, Bootstrap 5
- JavaScript (Vanilla & jQuery)
- Leaflet.js (Maps)
- Chart.js (Analytics)
- Font Awesome (Icons)

### Backend
- PHP 7.4+
- MySQL Database
- Apache Server (XAMPP)

### External Services
- Web Push API (Notifications)
- PHPMailer (Email)
- Pusher (Real-time communication)
- Minishlink Web Push (Push notifications)

### Storage
- MySQL Database Tables:
  - users
  - waste_reports
  - collection_schedules
  - collection_history
  - collector_locations
  - notifications
  - chat_messages
  - push_subscriptions
  - settings

---

*Last Updated: October 27, 2025*
