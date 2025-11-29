## 🔧 Installation

### 1. Clone Repository
```bash
git clone https://github.com/Banduluu/tryckasaken.git
cd tryckasaken
```

### 2. Database Setup
1. Start XAMPP (Apache & MySQL)
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Create database: `tric_db`
4. Import schema: `database/tric_db.sql`

### 4. ESP32 Setup
1. Open `Hardware Codes/rfid_attendance_system.ino` in Arduino IDE
2. Install required libraries:
   - MFRC522
   - SH1106Wire (ESP8266 and ESP32 OLED driver)
   - ArduinoJson
3. Update WiFi credentials:
```cpp
const char* ssid = "Your_WiFi_Name";
const char* password = "Your_WiFi_Password";
```
4. Update server IP:
```cpp
const char* serverUrl = "http://YOUR_SERVER_IP/tryckasaken/pages/driver/rfid-attendance-handler.php";
```
5. Upload to ESP32

### 5. Access Application
- **Main Site**: http://localhost/tryckasaken
- **Admin Panel**: http://localhost/tryckasaken/pages/admin/dashboard.php
- **Driver Portal**: http://localhost/tryckasaken/pages/driver/trydashboard.php
- **Passenger Portal**: http://localhost/tryckasaken/pages/passenger/dashboard-lipa.php

## 👤 Default Accounts

After importing the database, use these credentials:

### Admin
- Email: `vincent@gmail.com`
- Password: *vincent*

## 🔐 RFID Card Management

Access the RFID Management page at: `pages/admin/rfid-management.php`

### Step-by-Step Guide

#### 1. Verify Driver First
Before assigning RFID cards, ensure the driver is verified:
- Go to **Driver Verification** page
- Review driver documents
- Click **Approve** to verify the driver

#### 2. Assign RFID Card to Driver

**Method A: Manual Assignment (Known Card UID)**
1. Click **Manage Cards** button on RFID Management page
2. In the modal, select the verified driver from the dropdown
3. Enter the RFID card UID (e.g., `E317A32A`)
4. Click **Assign Card** button
5. Success message will confirm the assignment

**Method B: Learning Mode (Scan New Cards)**
1. Enable **Learning Mode** toggle on the page (turns green)
2. On ESP32 device, tap any RFID card on the reader
3. Card UID appears in the "Unknown Cards" table on the page
4. Click **Register** button next to the card
5. Select the driver from the dropdown modal
6. Click **Assign to Driver** button
7. Disable Learning Mode toggle when done

#### 3. Update Existing Card
1. Click **Update Card** button next to a driver
2. Enter the new RFID UID
3. Click **Update Card** button
4. Old card UID is replaced with new one

#### 4. Remove Card from Driver
1. Click **Remove Card** button next to a driver
2. Confirm the action
3. Card UID is cleared, driver can be assigned a new card

#### 5. Block/Unblock Cards

**To Block a Card:**
1. Click the **shield icon** 🛡️ next to a driver's card
2. In the modal, select the reason:
   - **Blocked** - General blocking
   - **Lost** - Card was lost
   - **Stolen** - Card was stolen
3. Enter optional details/notes
4. Click **Block Card** button
5. Card status changes to red with the selected reason

**To Unblock a Card:**
1. Click the **shield icon** 🛡️ next to a blocked card
2. Click **Unblock Card** button
3. Card status returns to **Active** (green)

#### 6. Test Card Functionality
1. Click **Test Card** button on the page
2. Enter the RFID UID to test
3. Click **Test** button
4. System displays:
   - Driver information
   - Card status
   - Last attendance record
   - Verification status

### Card Statuses
- **Active** (Green) - Card operational, driver can clock in/out
- **Blocked** (Red) - Manually blocked by admin
- **Lost** (Orange) - Card reported lost
- **Stolen** (Red) - Card reported stolen

### ESP32 Behavior
- **Active Card**: 1 beep, green LED, shows "WELCOME [Driver Name]"
- **Blocked/Lost/Stolen**: 5 rapid beeps, red LED, shows "CARD BLOCKED"
- **Unknown Card**: 3 beeps, shows "CARD NOT REGISTERED" (if Learning Mode OFF)
- **Learning Mode ON**: Unknown cards are sent to admin page for registration

## 📊 Admin Action Logs

Track all administrative actions:
- User suspensions/activations/deletions
- User profile edits (including role changes)
- Driver verification approvals/rejections
- RFID card assignments/updates/removals/blocking
- Booking cancellations
- Driver assignments to bookings

Access logs at: `pages/admin/action-logs.php`

## 🗂️ Project Structure

```
tryckasaken/
├── config/
│   └── Database.php              # Database connection
├── database/
│   └── tric_db.sql               # Complete database schema
├── Hardware Codes/
│   └── rfid_attendance_system.ino # ESP32 firmware
├── pages/
│   ├── admin/                    # Admin panel
│   │   ├── dashboard.php
│   │   ├── rfid-management.php
│   │   ├── action-logs.php
│   │   └── ...
│   ├── driver/                   # Driver portal
│   │   ├── trydashboard.php
│   │   └── rfid-attendance-handler.php
│   ├── passenger/                # Passenger portal
│   │   └── dashboard-lipa.php
│   └── auth/                     # Authentication
├── public/
│   ├── css/                      # Stylesheets
│   ├── images/                   # Assets
│   └── uploads/                  # User uploads
├── services/                     # Business logic
└── templates/                    # Page templates
```

## 🔄 Workflow

1. **Driver Registration** → Driver signs up and uploads documents
2. **Admin Verification** → Admin reviews and approves driver
3. **RFID Assignment** → Admin assigns RFID card to verified driver
4. **Attendance Tracking** → Driver taps card on ESP32 to clock in/out
5. **Booking Flow** → Passenger books → Admin assigns driver → Driver completes trip

