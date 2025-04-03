# Vandel Booking Plugin - Installation and Update Guide

This guide explains how to install or update the Vandel Booking Plugin to the latest version, including all the new features and enhancements.

## Prerequisites

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Recommended PHP extensions: zip, calendar, intl

## New Features in Version 1.0.3

- **Calendar View**: A new calendar interface for managing bookings visually
- **Enhanced Client Management**: Improved client profiles with statistics
- **Improved ZIP Code Management**: Fixed issues with the ZIP code feature
- **Database Structure Enhancements**: More robust database tables
- **Bug Fixes**: Several critical bugs have been fixed

## Fresh Installation

1. Download the plugin ZIP file from the provided source
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New**
4. Click the **Upload Plugin** button at the top of the page
5. Choose the downloaded ZIP file and click **Install Now**
6. After installation, click **Activate Plugin**

## Updating from an Older Version

1. Deactivate the current Vandel Booking plugin from **Plugins > Installed Plugins**
2. Delete the current version of the plugin (don't worry, your data will be preserved)
3. Follow the Fresh Installation steps above to install the new version
4. The database will be updated automatically on plugin activation

## File Structure Updates

The following new files have been added:

- `includes/database/class-installer.php` - Handles database installation and updates
- `includes/admin/class-calendar-view.php` - Implements the new calendar interface
- `includes/admin/class-zip-code-ajax-handler.php` - Fixed ZIP code handler
- `assets/js/admin/calendar.js` - JavaScript for the calendar functionality
- `assets/css/client-management.css` - Styles for client management interface

## Post-Installation Steps

1. Visit the plugin settings at **Dashboard > Vandel Booking > Settings** to configure the plugin
2. Check if all database tables have been created properly
3. Try accessing the new Calendar view via **Dashboard > Vandel Booking > Calendar**
4. Test the client management features by adding a test client

## Troubleshooting

If you encounter any issues after installation or upgrade:

### Database Tables Not Created

If the database tables are not created automatically, you can force the creation by:

1. Deactivating and then reactivating the plugin
2. If that doesn't work, go to the WordPress admin dashboard
3. Click on **Vandel Booking > Settings**
4. Look for any error messages related to database creation

### ZIP Code Feature Not Working

1. Make sure the `includes/admin/class-zip-code-ajax-handler.php` file is present
2. Check that the ZIP code feature is enabled in settings
3. Verify that the ZIP codes table exists in your database

### Calendar View Not Showing

1. Ensure your server has the required JavaScript capabilities
2. Check if the `assets/js/admin/calendar.js` file is present
3. Check your browser console for any JavaScript errors

## User Guides

### Using the Calendar View

1. Navigate to **Vandel Booking > Calendar**
2. Use the month navigation to move between months
3. Click on a booking to view details
4. You can change booking status directly from the calendar modal
5. Use the filters to switch between month, week, and day views

### Client Management

1. Go to **Vandel Booking > Clients**
2. Add new clients using the "Add New Client" button
3. View client details by clicking on a client name
4. Client statistics are automatically calculated
5. You can import/export clients using the CSV format

## Need Help?

If you encounter any issues not covered in this guide, please contact plugin support at support@vandelbooking.com.

## Changelog

### Version 1.0.3
- Added Calendar view for managing bookings
- Enhanced client management system
- Fixed ZIP code AJAX handler issues
- Improved database structure with proper versioning
- Added support for client statistics
- Added import/export functionality for clients
- Fixed various bugs related to booking management

### Version 1.0.2
- Added ZIP code-based service area restrictions
- Improved booking form usability
- Added email notification templates
- Fixed booking submission issues

### Version 1.0.1
- Added client management features
- Improved booking notes functionality
- Added support for multiple service options
- Fixed minor bugs

### Version 1.0.0
- Initial release