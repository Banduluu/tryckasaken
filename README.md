### Setup Instructions

**Database Setup**
   - The system automatically creates the database and tables on first run
   - Default database name: `tric_db`
   - Tables are created using `DatabaseSchema.php`

**Access the Application**
   - Open browser and navigate to: `http://localhost/code2/`


## Default Admin Account

After installation, create an admin account through the registration form and manually update the user_type in the database to 'admin'.


## Project Structure

```
├── index.php                 # Landing page
├── config/
│   └── Database.php          # Database connection configuration
├── database/
│   └── DatabaseSchema.php    # Database schema definitions
├── includes/
│   └── database-setup.php    # Database initialization
├── pages/
│   ├── admin/               # Admin panel pages
│   ├── auth/                # Authentication pages
│   ├── driver/              # Driver dashboard pages
│   └── passenger/           # Passenger dashboard pages
├── public/
│   ├── css/                 # Stylesheets
│   └── uploads/             # File upload directory
├── services/
│   ├── BookingService.php   # Booking business logic
│   └── RequestService.php   # Request handling logic
└── templates/
    ├── layouts/             # Page layouts
    └── pages/               # Page templates
```


