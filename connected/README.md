# 🏦 Nexus Pay - Banking Dashboard

A modern, secure banking dashboard built with HTML5 and PHP. This application provides a complete banking interface with account management, transaction tracking, user authentication, and an integrated chat system.

## Features

- **Modern UI/UX**: Clean, responsive design with gradient backgrounds and smooth animations
- **User Authentication**: Secure login system with password hashing
- **User Registration**: Public sign-up system with email validation
- **Admin Dashboard**: Complete user and account management system
- **User Management**: Ban/unban users, delete accounts, create new accounts
- **Account Management**: Add money to any account, view all transactions
- **Auto Sign-out**: Banned/deleted users are automatically signed out
- **Auto Account Creation**: New users get checking and savings accounts automatically
- **Nexus Chat**: Advanced iMessage-style chat system with group chats and real-time messaging
- **Money Transfers**: Transfer money between your own accounts or to other users (savings to savings only)
- **Profile Customization**: Set custom display names and profile pictures
- **Security Questions**: Password reset functionality with security questions
- **Right-Click Menus**: Context menus for conversation management (delete, report, open)
- **Live Updates**: Real-time message updates every second
- **Group Chats**: Create and manage group conversations
- **Reported Chats**: Admin panel for managing reported conversations
- **Account Overview**: View all accounts with balances and account numbers
- **Transaction History**: Track deposits, withdrawals, and transfers
- **Security Dashboard**: Monitor account security status
- **Mobile Responsive**: Works perfectly on desktop, tablet, and mobile devices
- **Database Management**: Automatic database setup and table creation

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)
- Web server (Apache/Nginx)
- PDO MySQL extension enabled

## Installation

### 1. Download and Setup

1. Download all files to your web server directory
2. Ensure your web server has write permissions for the directory
3. Make sure PHP and MySQL are properly configured

### 2. Database Setup

1. Create a MySQL database (or use an existing one)
2. Note down your database credentials:
   - Database host (usually `localhost`)
   - Database name
   - Database username
   - Database password

### 3. Run Installation

1. Open your web browser and navigate to: `http://your-domain.com/install.php`
2. Fill in the installation form:
   - **Database Host**: Your MySQL host (usually `localhost`)
   - **Database Name**: The database you created
   - **Database Username**: Your MySQL username
   - **Database Password**: Your MySQL password
   - **Admin Username**: Choose an admin username
   - **Admin Password**: Choose a strong admin password
3. Click "Install Bank Dashboard"
4. Wait for the installation to complete

### 4. Access Dashboard

1. After successful installation, click the "Go to Dashboard" link
2. Or navigate to: `http://your-domain.com/index.php`
3. Login with your admin credentials

### 5. Optional: Add Sample Data

1. Navigate to: `http://your-domain.com/sample_data.php`
2. This will create sample accounts and transactions for demonstration

### 6. User Registration

1. Users can sign up at: `http://your-domain.com/signup.php`
2. New users will have 'user' role by default
3. Admins can manage users through the admin panel

## File Structure

```
Bank-of-the-Redwoods/
├── install.php          # Installation script
├── index.php           # Main dashboard
├── admin.php           # Admin dashboard
├── signup.php          # User registration
├── check_status.php    # User status checker
├── fix_database.php    # Database migration script
├── sample_data.php     # Sample data generator
├── config.php          # Database configuration (auto-generated)
├── .htaccess           # Security configuration
├── chat/               # Nexus Chat directory
│   ├── index.php       # Main chat interface
│   ├── launcher.php    # Chat launcher page
│   ├── send_message.php # Message sending endpoint
│   ├── get_messages.php # Message retrieval endpoint
│   ├── create_conversation.php # Conversation creation endpoint
│   ├── delete_conversation.php # Conversation deletion endpoint
│   ├── report_conversation.php # Chat reporting endpoint
│   └── search_users.php # User search endpoint
├── customize.php       # Profile customization page
├── forgot_password.php # Password reset page
├── transfer_money.php  # Money transfer endpoint
├── update_report_status.php # Report status update endpoint
└── README.md           # This file
```

## Database Schema

The installation creates the following tables:

### Users Table
- `id` - Primary key
- `username` - Unique username
- `password` - Hashed password
- `email` - User email (optional)
- `role` - User role (admin/user/banned)
- `created_at` - Account creation timestamp

### Accounts Table
- `id` - Primary key
- `user_id` - Foreign key to users table
- `account_number` - Unique account number
- `account_type` - Type (checking/savings/credit)
- `balance` - Current account balance
- `created_at` - Account creation timestamp

### Transactions Table
- `id` - Primary key
- `account_id` - Foreign key to accounts table
- `transaction_type` - Type (deposit/withdrawal/transfer)
- `amount` - Transaction amount
- `description` - Transaction description
- `created_at` - Transaction timestamp

### Users Table (Updated)
- `id` - Primary key
- `username` - Unique username
- `password` - Hashed password
- `email` - User email
- `role` - User role (admin/user/banned)
- `display_name` - Custom display name
- `profile_picture` - Profile picture URL
- `security_question1` - First security question
- `security_answer1` - First security answer
- `security_question2` - Second security question
- `security_answer2` - Second security answer
- `created_at` - Account creation timestamp

### Conversations Table
- `id` - Primary key
- `conversation_type` - Type (direct/group)
- `name` - Group name (for group chats)
- `created_at` - Creation timestamp

### Conversation Participants Table
- `id` - Primary key
- `conversation_id` - Foreign key to conversations table
- `user_id` - Foreign key to users table
- `joined_at` - Join timestamp

### Messages Table (Updated)
- `id` - Primary key
- `sender_id` - Foreign key to users table (sender)
- `conversation_id` - Foreign key to conversations table
- `message` - Message content
- `created_at` - Message timestamp

### Reported Chats Table
- `id` - Primary key
- `conversation_id` - Foreign key to conversations table
- `reported_by` - Foreign key to users table (reporter)
- `reason` - Report reason
- `status` - Status (pending/reviewed/resolved)
- `created_at` - Report timestamp

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Protection**: Uses prepared statements throughout
- **Session Management**: Secure session handling
- **Input Validation**: All user inputs are validated and sanitized
- **XSS Protection**: Output is properly escaped

## Customization

### Changing Bank Name
Edit the `BANK_NAME` constant in `config.php` after installation.

### Styling
The application uses CSS Grid and Flexbox for layout. You can customize the colors by modifying the CSS variables in the `<style>` sections.

### Adding Features
The modular structure makes it easy to add new features:
- Add new PHP files for additional functionality
- Extend the database schema as needed
- Add new dashboard cards and sections

## Troubleshooting

### Installation Issues
- **Database Connection Error**: Check your database credentials and ensure MySQL is running
- **Permission Error**: Ensure the web server has write permissions to create `config.php`
- **PDO Error**: Make sure the PDO MySQL extension is enabled in PHP

### Dashboard Issues
- **Login Problems**: Verify the database was created correctly during installation
- **Missing Data**: Check if the database tables were created properly
- **Styling Issues**: Ensure your browser supports CSS Grid and Flexbox

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## License

This project is open source and available under the MIT License.

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify your server meets the requirements
3. Check your PHP error logs for detailed error messages

## New Features

### Money Transfers
- **Internal Transfers**: Transfer money between your own checking and savings accounts
- **External Transfers**: Send money to other users' savings accounts only
- **Transfer History**: View all incoming and outgoing transfers
- **Real-time Updates**: Account balances update immediately after transfers

### Profile Customization
- **Display Names**: Set custom display names for your profile
- **Profile Pictures**: Upload profile picture URLs or use generated avatars
- **Quick Avatars**: Choose from various avatar styles (cartoon, robot, pixel art, etc.)
- **Live Preview**: See changes in real-time as you customize

### Password Reset System
- **Security Questions**: Set up two security questions during registration
- **Multi-step Process**: Username verification → Security questions → New password
- **Account Protection**: Users without security questions cannot reset passwords
- **Secure Process**: All steps are validated and secure

### Enhanced Nexus Chat
- **Real-time Updates**: Messages update every second automatically
- **Group Chats**: Create and manage group conversations
- **Right-click Menus**: Context menus for conversation management
- **Conversation Actions**: Delete conversations, report chats, open conversations
- **Profile Integration**: Uses display names and profile pictures in chat
- **Live Search**: Real-time user search for starting conversations
- **Mobile Responsive**: Optimized for all device sizes

### Admin Features
- **Reported Chats Management**: View and manage reported conversations
- **Report Status Updates**: Mark reports as reviewed or resolved
- **Conversation Viewing**: Admins can view any conversation
- **Enhanced User Management**: Better user overview and management

## Admin Features

The admin dashboard (`admin.php`) provides comprehensive management capabilities:

- **User Management**: View all users, ban/unban accounts, delete users
- **Account Management**: View all accounts, add money to any account
- **Transaction Monitoring**: View recent transactions across all accounts
- **Reported Chats**: Manage and review reported conversations
- **Statistics**: Real-time overview of users, accounts, and total assets
- **Account Creation**: Create new accounts for any user with initial balance
- **Auto Sign-out**: Banned/deleted users are automatically signed out
- **System Status**: Real-time monitoring of system features

## User Registration

The sign-up system (`signup.php`) includes:

- **Email Validation**: Ensures valid email addresses
- **Password Strength**: Real-time password strength indicator
- **Duplicate Prevention**: Checks for existing usernames and emails
- **Security**: Password hashing and input validation
- **Auto Account Creation**: New users automatically get checking and savings accounts

## Nexus Chat

The chat system (`chat/`) provides iMessage-style messaging:

- **Shared Authentication**: Uses the same login system as the bank
- **Connected Accounts**: All users are "Connected Accounts" for messaging
- **Real-time Interface**: Auto-refreshing conversations every 5 seconds
- **Mobile Responsive**: Works perfectly on all devices
- **Secure Messaging**: Messages are stored securely in the database
- **Conversation History**: View all past conversations and messages
- **User Search**: Search for users to start new conversations
- **New Conversation Button**: Easy access to start new chats
- **Instant Messaging**: Real-time message delivery and updates

## Future Enhancements

Potential features for future versions:
- Two-factor authentication
- Real-time transaction notifications
- Bill payment system
- Investment portfolio tracking
- Mobile app integration
- API endpoints for third-party integrations
- Advanced reporting and analytics
- Automated fraud detection 