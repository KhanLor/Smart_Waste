# Report Notifications Feature

## Overview
A comprehensive notification system has been implemented for both residents and authorities to track updates on waste reports. Users will now receive real-time notifications when there are updates to their reports.

## Features

### For Residents
✅ **Notification Badge on Reports Page**: Shows the count of unread report updates at the top of the "My Reports" page
✅ **Individual Report Indicators**: Each report card displays a notification badge if there are unread updates
✅ **Auto-mark as Read**: Notifications are automatically marked as read when viewing report details
✅ **Report Status Updates**: Residents receive notifications when authorities update their report status
✅ **Points Notification**: Residents are notified when they earn eco points for submitting reports

### For Authorities
✅ **New Report Notifications**: Authorities receive notifications when residents submit new reports
✅ **Notification Badge on Reports Management Page**: Shows count of new unread reports
✅ **Individual Report Indicators**: New reports are highlighted with a "New" badge
✅ **Auto-mark as Read**: Notifications are marked as read when viewing report details
✅ **Priority Information**: Notifications include report priority and location information

## File Changes

### Modified Files

#### 1. `dashboard/resident/reports.php`
**Changes:**
- Added query to count unread report notifications
- Added notification badge in the page header showing total unread updates
- Added notification indicators on individual report cards
- Modified report query to fetch unread notification counts for each report
- Updated JavaScript to mark notifications as read when viewing report details
- Auto-reload page after 2 seconds to update badge counts

**New Code Sections:**
```php
// Count unread report notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND is_read = 0");

// Get unread notifications for each report
$report_notifications = [];
// ... fetches unread count per report
```

**UI Changes:**
```html
<!-- Notification badge in header -->
<span class="badge bg-danger rounded-pill">X new updates</span>

<!-- Notification badge on report card -->
<span class="badge bg-danger rounded-pill">
    <i class="fas fa-bell"></i> X updates
</span>
```

#### 2. `dashboard/authority/reports.php`
**Changes:**
- Added query to count unread report notifications (new reports submitted by residents)
- Added notification badge in the page header
- Added "New" badge indicator on newly submitted report cards
- Modified report query to track unread notifications per report
- Updated JavaScript to mark notifications as read when viewing report details
- Existing notification creation logic remains intact (creates notifications when status is updated)

**New Code Sections:**
```php
// Count unread report notifications
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND is_read = 0");

// Get unread notifications for each report
$report_notifications = [];
// ... fetches new reports for authority
```

**UI Changes:**
```html
<!-- Notification badge in header -->
<span class="badge bg-danger rounded-pill">X new reports</span>

<!-- New report indicator -->
<span class="badge bg-success rounded-pill">
    <i class="fas fa-bell"></i> New
</span>
```

#### 3. `dashboard/resident/submit_report.php`
**Changes:**
- Moved user data fetch before form submission to ensure user information is available
- Existing notification creation for authorities is now properly functional
- Notifications are sent to all users with 'authority' or 'admin' roles when a new report is submitted

**Logic Flow:**
1. Resident submits a report
2. Report is saved to database
3. Resident receives success notification and eco points
4. All authorities/admins receive notification about the new report
5. Notification includes: reporter name, priority, report type, and location

### New Files

#### 4. `api/mark_report_notifications_read.php`
**Purpose:** API endpoint to mark report-related notifications as read when a user views report details

**Functionality:**
- Accepts POST request with JSON payload containing `report_id`
- Marks all notifications for the current user related to that specific report as read
- Returns JSON response with success status and count of notifications marked

**Security:**
- Requires user to be logged in (uses `require_login()`)
- Only marks notifications for the current authenticated user
- Validates report_id before processing

**API Usage:**
```javascript
fetch('../../api/mark_report_notifications_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ report_id: reportId })
})
```

## Database Schema

### Notifications Table (Already Exists)
The feature uses the existing `notifications` table:

```sql
CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    reference_type VARCHAR(50) NULL,
    reference_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id),
    KEY idx_notifications_read (is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Important Fields:**
- `reference_type`: Set to 'report' for report-related notifications
- `reference_id`: Contains the `waste_reports.id` 
- `is_read`: Boolean flag to track read/unread status

## Notification Types

### 1. Resident Notifications
**Type:** Report Status Update
```php
Title: "Report Status Updated"
Message: "Your report '{report_title}' status has been updated to {status}"
Type: 'info'
Reference: report_id
```

**Type:** Report Submission Confirmation
```php
Title: "Report Submitted Successfully"
Message: "Your waste report '{title}' has been submitted. You earned {points} eco points!"
Type: 'success'
Reference: report_id
```

### 2. Authority Notifications
**Type:** New Report Submitted
```php
Title: "New Waste Report: {report_title}"
Message: "{resident_name} submitted a {PRIORITY} priority {report_type} report at {location}"
Type: 'info'
Reference: report_id
```

## User Flow

### Resident Flow
1. **Submit Report**: Resident fills out and submits a waste report
2. **Receive Confirmation**: Resident gets notification confirming submission and eco points earned
3. **Authority Takes Action**: Authority updates the report status (e.g., pending → assigned → completed)
4. **Receive Update**: Resident receives notification about status change
5. **View Notification**: Badge appears on "My Reports" page and on the specific report card
6. **Check Details**: When resident views the report, notification is marked as read
7. **Badge Updates**: Notification badges update automatically

### Authority Flow
1. **New Report Submitted**: Resident submits a new report
2. **Receive Notification**: Authority receives notification about the new report
3. **View Badge**: "New report" badge appears on Reports Management page
4. **Locate Report**: The specific report card shows a "New" indicator
5. **Review Report**: Authority clicks to view report details
6. **Auto-mark Read**: Notification is automatically marked as read
7. **Update Status**: Authority updates the report status
8. **Resident Notified**: Resident receives notification about the update

## Visual Indicators

### Notification Badges
- **Resident Header**: Red pill badge showing count (e.g., "3 new updates")
- **Authority Header**: Red pill badge showing count (e.g., "5 new reports")
- **Resident Report Card**: Red pill with bell icon and count
- **Authority Report Card**: Green "New" pill badge

### Color Scheme
- **Unread Notifications**: Red/Danger (`bg-danger`)
- **New Reports (Authority)**: Green/Success (`bg-success`)
- **Icons**: Font Awesome bell icon (`fa-bell`)

## Technical Implementation

### JavaScript Functions
Both resident and authority pages include:
```javascript
function viewReport(reportId) {
    // Mark notifications as read via API
    fetch('../../api/mark_report_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId })
    }).then(() => {
        // Reload page after 2 seconds to update badges
        setTimeout(() => location.reload(), 2000);
    });
    
    // Show modal with report details
    // ...
}
```

### PHP Query Patterns
**Count Total Unread:**
```php
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND is_read = 0");
```

**Count Per Report:**
```php
$stmt = $conn->prepare("SELECT reference_id, COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND reference_type = 'report' AND reference_id IN (...) AND is_read = 0 GROUP BY reference_id");
```

### Performance Considerations
- Notifications are fetched in bulk for all visible reports (single query with IN clause)
- Results are stored in associative arrays (`$report_notifications`) for O(1) lookup
- Only unread notifications are counted to minimize database load
- Automatic cleanup happens when viewing report details

## Testing Checklist

### Resident Testing
- [ ] Submit a new report and verify notification appears
- [ ] Check "My Reports" page shows unread count badge
- [ ] Verify report cards show notification indicators
- [ ] View a report and confirm notification is marked as read
- [ ] Verify badge counts update after viewing
- [ ] Test with multiple reports and updates

### Authority Testing
- [ ] Check notification when resident submits new report
- [ ] Verify "Reports Management" page shows new report count
- [ ] Confirm report cards show "New" badge
- [ ] View a new report and verify notification marked as read
- [ ] Update report status and verify resident receives notification
- [ ] Test with multiple new reports

### Integration Testing
- [ ] Submit report as resident → Authority receives notification
- [ ] Authority updates status → Resident receives notification
- [ ] View report as resident → Notification marked as read
- [ ] View report as authority → Notification marked as read
- [ ] Test concurrent updates and multiple users

## Future Enhancements

### Potential Additions
1. **Email Notifications**: Send email alerts for important updates
2. **Push Notifications**: Browser push notifications for real-time alerts
3. **Notification Preferences**: Allow users to customize notification types
4. **Notification History**: Dedicated page to view all notifications
5. **Mark All as Read**: Bulk action to mark all notifications as read
6. **Filter by Report Type**: Show notifications only for specific report types
7. **Sound Alerts**: Audio notification for new updates
8. **Desktop Notifications**: Native OS notifications

### Possible Improvements
- Add notification timestamps in the badge tooltip
- Implement real-time notifications using WebSockets
- Add notification archive feature
- Create notification summary emails (daily digest)
- Add read receipts for critical notifications

## Troubleshooting

### Issue: Notifications not appearing
**Solution:**
1. Check if notifications table exists in database
2. Verify user is logged in and user_id is set
3. Check browser console for JavaScript errors
4. Verify database queries are executing successfully

### Issue: Badge count not updating
**Solution:**
1. Clear browser cache
2. Check if page auto-reload is working
3. Verify JavaScript fetch is completing successfully
4. Check network tab for API response

### Issue: Notifications not marked as read
**Solution:**
1. Verify API endpoint is accessible
2. Check PHP error logs for database errors
3. Ensure user has permission to update notifications
4. Verify report_id is being passed correctly

## Conclusion

This notification system provides a seamless communication channel between residents and authorities, ensuring that all parties are kept informed about report status changes. The implementation is efficient, user-friendly, and integrates naturally with the existing Smart Waste Management system.
