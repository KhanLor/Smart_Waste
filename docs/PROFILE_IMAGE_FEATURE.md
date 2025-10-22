# Profile Image Upload Feature

## Overview
Users can now upload and update their profile images across all user roles (Resident, Collector, Admin, and Authority).

## Features
- ✅ Upload profile images (JPG, PNG, GIF, WebP)
- ✅ Automatic image optimization and resizing (max 400x400px)
- ✅ File size validation (max 5MB)
- ✅ Real-time preview after upload
- ✅ Replaces old profile images automatically
- ✅ Secure upload handling with validation

## File Structure

### New Files Created
- `/api/upload_profile_image.php` - Handles profile image uploads
- `/uploads/profiles/` - Directory for storing profile images
- `/dashboard/admin/profile.php` - Admin profile page
- `/dashboard/authority/profile.php` - Authority profile page

### Modified Files
- `/dashboard/resident/profile.php` - Added image upload feature
- `/dashboard/collector/profile.php` - Added image upload feature
- `/dashboard/admin/_sidebar.php` - Added profile link
- `/dashboard/authority/_sidebar.php` - Added profile link

## Database Schema
The feature uses the existing `profile_image` column in the `users` table:
```sql
profile_image VARCHAR(255) NULL
```

## How to Use

### For Users
1. Navigate to your profile page from the sidebar
2. Click the camera icon on your profile avatar
3. Select an image file (JPG, PNG, GIF, or WebP)
4. The image will be automatically uploaded and displayed
5. Old profile images are automatically replaced

### For Developers
The upload API endpoint: `/api/upload_profile_image.php`

**Request:**
- Method: POST
- Content-Type: multipart/form-data
- Field: `profile_image` (file)

**Response:**
```json
{
  "success": true,
  "message": "Profile image updated successfully",
  "image_url": "http://localhost/smart_waste/uploads/profiles/profile_1_1234567890.jpg"
}
```

## Security Features
- File type validation (only images allowed)
- File size validation (max 5MB)
- MIME type checking
- Image dimension validation
- Secure filename generation
- Session-based authentication
- Old file cleanup

## Image Optimization
- Automatic resize to max 400x400px (maintains aspect ratio)
- Compression (85% quality for JPEG/WebP)
- Transparency preservation for PNG/GIF

## Styling
- Circular profile avatar with camera button overlay
- Responsive design for mobile devices
- Smooth hover effects
- Loading spinner during upload
- Success/error alerts

## Browser Support
- Modern browsers with File API support
- Chrome, Firefox, Safari, Edge
- Mobile browsers (iOS Safari, Chrome Mobile)

## Permissions
Ensure the following directories have write permissions:
- `/uploads/profiles/` (755 or 775)

## Error Handling
The system handles:
- File upload errors
- Invalid file types
- Oversized files
- Database update failures
- File system errors
- Session validation

## Future Enhancements
- [ ] Crop functionality
- [ ] Multiple image formats
- [ ] Avatar templates
- [ ] Profile image gallery
- [ ] Social media import
