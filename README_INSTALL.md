# ISO Admin Command Framework v1.7

Domain: `isoadmin.co.za`  
Target: existing Laravel installation on shared hosting with no console access.

This package does not require Composer, NPM, Vite, Artisan, SSH or command-line access after upload.

## What is included

- Mobile-first admin layout.
- Login/logout and password change.
- Employee loading and editing.
- Department creation and assignment.
- Role-based permission matrix.
- Default roles: Director, Manager, Employee.
- Time attendance management.
- Attendance CSV upload.
- Director manual attendance CSV upload.
- Attendance email import from `cc@isoadmin.co.za` using IMAP.
- Automatic employee matching by CSV `Name`.
- Daily attendance summaries per user.
- Earliest timestamp = start time.
- Latest timestamp = checkout time.
- Employee profile document management.
- Uploads for medicals, sick notes, warnings, certificates, company policies and vehicle policies.
- Optional expiry date per employee document.
- Reminder advance time per employee document.
- Expiring/expired employee document reminder centre.
- Vehicle and fuel tracking module.
- Vehicle service tracking with KM-based service intervals.
- Service records with service date, service ODO and notes.
- Upcoming/overdue service reminders based on latest fuel-up ODO.
- Director/manager vehicle creation.
- Vehicle assignment to employees, managers and directors.
- Vehicle policy check when a vehicle is assigned.
- Manual fuel-up records with automatic KM travelled calculation from latest ODO.
- Fuel CSV import using the provided fuel export structure.
- NATIS, license disk and vehicle document uploads.
- License disk/document expiry reminders for directors/managers.
- Personal dashboard widget editor with drag/drop ordering and small/medium/large widget sizes.
- Protected web updaters for v1.1 through v1.7 database updates.

## Upload instructions

1. Back up your current Laravel files and database.
2. Upload the package contents into the Laravel root folder, keeping the folder structure intact.
3. Replace files when asked, especially:
   - `routes/web.php`
   - `app/Models/User.php`
   - `app/Models/EmployeeDocument.php`
   - `resources/views/layouts/app.blade.php`
4. Confirm your `.env` database settings are correct.
5. Log in as a Director.
6. Run the latest updater:

```text
https://isoadmin.co.za/updates/v1-7
```

7. Click **Apply v1.7 Update**.
8. Open **Vehicles & Fuel** and configure service intervals per vehicle.

## Fresh install

If this is a fresh install, add this to `.env` before running `/install`:

```env
INSTALLER_KEY=change-this-to-a-long-random-key
```

Then visit:

```text
https://isoadmin.co.za/install
```

A fresh v1.7 install creates employee, attendance, document, vehicle, fuel, vehicle document, service tracking and dashboard tables automatically.

## Attendance CSV format

The attendance importer expects the attendance export to contain these columns:

```text
Name, Time, Attendance Status
```

Other columns can exist in the CSV. The importer ignores them.

## Attendance calculation rule

For every employee and date:

```text
earliest Time = start time
latest Time = checkout time
```

If only one record exists for the employee on the day, start and checkout will be the same and the day is flagged as a single-punch record.

## Employee matching

Rows are matched to active employees by:

1. `Attendance CSV Name` on the employee profile.
2. Employee `Name`.

If the employee does not exist in the system, the row is skipped.

## Email import setup

Create the mailbox in DirectAdmin/cPanel:

```text
cc@isoadmin.co.za
```

Add these values to `.env`:

```env
ATTENDANCE_MAIL_HOST=mail.isoadmin.co.za
ATTENDANCE_MAIL_PORT=993
ATTENDANCE_MAIL_ENCRYPTION=ssl
ATTENDANCE_MAIL_USERNAME=cc@isoadmin.co.za
ATTENDANCE_MAIL_PASSWORD=your-mailbox-password
ATTENDANCE_MAILBOX=INBOX
ATTENDANCE_IMPORT_KEY=change-this-to-a-long-random-key
ATTENDANCE_DELETE_PROCESSED=true
```

## Automated attendance email import without console access

Create a DirectAdmin cron job that calls this URL:

```text
https://isoadmin.co.za/attendance-email-import/YOUR_ATTENDANCE_IMPORT_KEY
```

A safe interval is every 15 or 30 minutes.

## Hosting requirement for attendance email import

The email importer uses PHP IMAP functions. If your hosting does not have the PHP IMAP extension enabled, CSV upload will still work, but email fetching will show an IMAP extension error. Ask the host to enable PHP IMAP for the site.

## Employee document module

Managers and Directors can attach documents to each employee profile.

Supported document categories:

```text
Medical
Sick Note
Warning
Certificate
Company Policy
Vehicle Policy
Other
```

Vehicle Policy is used by the vehicle assignment system. When a vehicle is assigned to a person, the platform checks whether the person has an active, non-expired Vehicle Policy document.

## Vehicle and fuel module

Directors and managers can add vehicles with:

```text
Make
Model
ODO
Registration Number optional
Vehicle CSV Name optional
Notes optional
Service Interval KM optional
Service Reminder KM Before Due optional
```

Vehicles can be assigned to employees, managers and directors.

When a vehicle is assigned, the system checks the person for a valid **Vehicle Policy** employee document. If missing, inactive or expired, the assignment saves but a warning is shown to managers/directors.

## Fuel CSV import

Fuel import is done per vehicle from:

```text
Vehicles & Fuel > Open Vehicle > Import Fuel CSV
```

The importer keeps the fuel export data columns:

```text
car_name
model
km/l
odometer
km
litres
price
city_percentage
fuelup_date
date_added
tags
notes
missed_fuelup
partial_fuelup
latitude
longitude
brand
```

Rules:

- Import is attached to the selected vehicle.
- Duplicate rows are ignored.
- Latest CSV odometer updates the vehicle ODO if it is higher than the current ODO.
- `price` is treated as price per litre.
- `total_cost` is calculated as litres × price.

## Vehicle documents

Vehicle documents can be attached to each vehicle.

Supported vehicle document types:

```text
NATIS Document
License Disk
Insurance
Service Record
Other
```

Each upload can be marked as:

```text
Has expiry date: yes/no
Expiry date: selected date
Reminder advance time: 7, 14, 30, 45, 60, 90, 120, 180 or 365 days before expiry
```

The system calculates:

```text
reminder_date = expiry_date - selected advance days
```

Documents appear in the vehicle reminder centre when the reminder date is reached.

Open:

```text
https://isoadmin.co.za/vehicles/reminders
```

## Vehicle service tracking

Each vehicle can have a KM-based service interval and reminder distance.

Configure this under:

```text
Vehicles & Fuel > Open Vehicle > Edit Vehicle
```

Fields:

```text
Service Interval KM
Service Reminder KM Before Due
```

Example:

```text
Service Interval KM = 10000
Service Reminder KM Before Due = 1000
```

This means the vehicle is due every 10 000 km and enters the service reminder centre when it is within 1 000 km of the next service.

Managers/directors can add service records under:

```text
Vehicles & Fuel > Open Vehicle > Add Service Record
```

Service records capture:

```text
Service Date
Service ODO
Service Notes
```

Calculation rule:

```text
Next Service ODO = Last Service ODO + Service Interval KM
KM Remaining = Next Service ODO - Latest Fuel-up ODO
```

Open the service reminder centre:

```text
https://isoadmin.co.za/vehicles/service-reminders
```

## New permissions in v1.4

```text
vehicle.view
vehicle.create
vehicle.edit
vehicle.assign
vehicle.fuel.view
vehicle.fuel.manage
vehicle.fuel.import
vehicle.documents.view
vehicle.documents.upload
vehicle.documents.manage
vehicle.reminders.view
```

Directors get all vehicle permissions. Managers get vehicle operational permissions by default. Employees do not receive vehicle access by default unless a role grants it.

## Previous permissions

```text
employee_documents.view
employee_documents.upload
employee_documents.manage
attendance.view
attendance.import
attendance.manage
attendance.manual_upload
```

---

# Version 1.5 Update

## Run the update

After uploading the v1.5 files, log in as Director and open:

```text
https://isoadmin.co.za/updates/v1-5
```

Click **Run v1.5 Update**.

## Fuel-up odometer calculation

Manual fuel-up entry no longer requires the user to enter **KM Travelled**.

The user now enters:

```text
Fuel-up date
Latest odometer
Litres
Price per litre
Total cost, optional
Fuel brand, optional
Notes, optional
```

The system calculates:

```text
KM Travelled = Latest ODO - previous lower ODO reading
KM/L = calculated KM Travelled / litres
```

If no previous lower odometer reading exists, the fuel-up is still saved and KM Travelled remains blank until there is enough odometer history.

CSV fuel imports still keep the CSV `km` field where it is supplied by the source export.

## Editable dashboard widgets

Each user can edit their own dashboard.

Open:

```text
https://isoadmin.co.za/dashboard/edit
```

Users can:

```text
Show or hide widgets
Drag and drop widgets to reorder them
Resize widgets to small, medium or large
Save a personal dashboard layout
```

Widget sizing logic:

```text
Small  = compact metric only
Medium = normal summary
Large  = deeper dashboard detail
```

## New v1.5 permission

```text
dashboard.customize
```

Directors, managers and employees receive this permission by default so every user can customise their own dashboard.

---

# Version 1.6 Update

## Run the update

After uploading the v1.6 files, log in as Director and open:

```text
https://isoadmin.co.za/updates/v1-6
```

Click **Apply v1.6 Update**.

## New user onboarding

When a manager or director creates a new employee profile:

```text
1. A temporary password can be typed manually or generated with the Generate button.
2. If the password field is left blank, the system auto-generates a secure temporary password.
3. The system emails the user their login URL, email address and temporary password.
4. On first login the user is forced to change the temporary password before accessing the dashboard.
```

If the login email fails because SMTP is not configured, the employee is still created and the temporary password is shown once as a warning message.

## Password resets

If a manager or director edits an employee and enters a new password:

```text
1. The password is saved as a temporary password.
2. The updated login details are emailed to the user.
3. The user must change the password again on next login.
```

Leaving the password field blank on edit keeps the existing password unchanged.

## SMTP settings

The feature uses Laravel mail settings from `.env`. Example shared-hosting SMTP setup:

```env
MAIL_MAILER=smtp
MAIL_HOST=mail.isoadmin.co.za
MAIL_PORT=465
MAIL_USERNAME=no-reply@isoadmin.co.za
MAIL_PASSWORD=your-mailbox-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@isoadmin.co.za
MAIL_FROM_NAME="ISO Admin"
```

Use the correct mailbox details from DirectAdmin/cPanel.

## Database columns added

```text
users.must_change_password
users.password_changed_at
users.credentials_emailed_at
```

Existing users are marked as already changed so they are not locked out after the update.

---

# Version 1.7 Update

## Run the update

After uploading the v1.7 files, log in as Director and open:

```text
https://isoadmin.co.za/updates/v1-7
```

Click **Apply v1.7 Update**.

## New vehicle service features

```text
- Service interval KM on vehicle create/edit.
- Service reminder KM chooser on vehicle create/edit.
- Add vehicle service records with date, ODO and notes.
- Service status displayed on the vehicle profile.
- Service reminder centre for due soon, overdue, missing baseline and all active vehicles.
- Dashboard widget for vehicle service reminders.
```

## New v1.7 permissions

```text
vehicle.service.view
vehicle.service.manage
vehicle.service.reminders.view
```

Directors and managers receive these permissions by default.

---

## Version 2.5 Update

This update is built on top of v1.7 and adds the requested central-admin improvements from the supplied update document.

### Upload

Upload the contents of this package into the Laravel root and overwrite existing files when prompted.

Then log in with an account that has `settings.manage` and open:

```text
https://isoadmin.co.za/updates/v2-5
```

Click **Apply v2.5 Update**.

### Added in v2.5

- New **System Administrator** role above Director.
- The logged-in user applying v2.5 is automatically attached to the System Administrator role.
- Directors keep all existing permissions through the permission matrix.
- Direct user permissions via employee edit page.
- Menus are hidden when the logged-in user does not have the matching permission.
- New **My Profile** menu.
- Change Password moved under **My Profile**.
- System Settings → Leave Types.
- Leave types can be marked as deductible or non-deductible against leave allocation.
- Cron Jobs page for reminder URLs and attendance email import.
- Manual attendance email import can be run from Cron Jobs by users with `cron_jobs.run`.
- Vehicle fields added:
  - Year model
  - Colour
  - Tracking company name
  - Tracking company contact
  - Tracking device/account number
  - Tracking notes

### New permissions

```text
profile.view
cron_jobs.view
cron_jobs.run
leave_types.view
leave_types.manage
```

### Database changes

v2.5 creates or updates:

```text
permission_user
leave_types
vehicles.year_model
vehicles.colour
vehicles.tracking_company_name
vehicles.tracking_company_contact
vehicles.tracking_device_number
vehicles.tracking_notes
```

### Leave type defaults

The updater seeds these default leave types:

```text
Annual Leave - deductible
Sick Leave - deductible
Family Responsibility Leave - deductible
Unpaid Leave - non-deductible
Special Leave - non-deductible
```

### Cron job setup

Open:

```text
https://isoadmin.co.za/cron-jobs
```

The page shows configured cron URLs when these `.env` keys exist:

```env
ATTENDANCE_IMPORT_KEY=change-this-to-a-long-random-key
DOCUMENT_REMINDER_KEY=change-this-to-a-long-random-key
```

The attendance import cron URL remains:

```text
https://isoadmin.co.za/attendance-email-import/YOUR_ATTENDANCE_IMPORT_KEY
```

The new Cron Jobs page also provides a permission-controlled manual email import button.

---

# Version 2.6 Update

## Run the update

After uploading the v2.6 files, log in as System Administrator and open:

```text
https://isoadmin.co.za/updates/v2-6
```

Click **Run v2.6 Update**.

## Cartrack vehicle tracking API

This update adds a Cartrack Fleet API integration using server-side HTTP Basic Authentication.

Open:

```text
https://isoadmin.co.za/settings/vehicle-tracking
```

Configure:

```text
Enable Cartrack Integration
Region Code, normally za for South Africa
API Base URL, normally https://fleetapi-za.cartrack.com
API Username
API Password
Timeout Seconds
Sync Odometer yes/no
Sync Location yes/no
Sync Status yes/no
Cron Key
```

Then click **Test Connection**. The test calls:

```text
/rest/vehicles
```

## Vehicle tracking sync

Open:

```text
https://isoadmin.co.za/vehicle-tracking
```

Use **Sync Cartrack** to pull the latest vehicle list and tracking fields where supplied by the API.

The system attempts to match Cartrack vehicles to local vehicles by:

```text
1. Cartrack Vehicle ID
2. Registration number
3. Tracking device / account number
4. Vehicle CSV name / local display name
```

Each local vehicle profile also has a manual Cartrack link section.

## Shared-hosting cron URL

Use the cron URL shown on the Vehicle Tracking API settings page. It will look like:

```text
https://isoadmin.co.za/vehicle-tracking/sync/YOUR-CARTRACK-CRON-KEY
```

A safe interval is every 15 to 30 minutes.

## New v2.6 permissions

```text
vehicle_tracking.view
vehicle_tracking.sync
vehicle_tracking.link
vehicle_tracking.settings.view
vehicle_tracking.settings.manage
```

Directors and managers receive operational tracking permissions. Only System Administrator receives integration settings permissions.

## Database changes

This update adds Cartrack/tracking columns to `vehicles` and creates:

```text
vehicle_tracking_snapshots
```


## v2.6.3 Repair Update

Run after upload:

https://isoadmin.co.za/updates/v2-6-3

This repairs older `public_holidays` table schemas that do not have the `name` column, reseeds public holidays, rebuilds attendance summaries and re-syncs System Administrator permissions.
