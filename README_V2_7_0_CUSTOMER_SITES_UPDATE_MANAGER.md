# ISO Admin Command Framework v2.7.0

## Customer Sites, Equipment, Site Medical Compliance and Update Manager

This release expands the customer module and introduces a safer update workflow.

### Customer hierarchy

- Customer / client
- Site / location under each customer
- Equipment installed at each location
- Site-specific medical requirements
- Employee assignment to sites
- Compliance check against active employee profile documents and expiry dates

### Equipment

Each site can track equipment as:

- Permanent
- Rented

Captured fields include equipment name, type, serial number, asset number, install date, rental dates, status and notes.

### Medical compliance model

The medical document remains attached to the employee profile. The site defines what medicals are required for access. The system checks each assigned employee against the active required documents and expiry dates.

This avoids duplicate medical uploads per customer and makes one valid employee medical reusable across sites where the same requirement applies.

### Update Manager

Added under System Settings. It supports:

- GitHub repository and branch settings
- GitHub token storage for private repositories
- Downloading a GitHub ZIP archive
- Uploading a deployment ZIP
- Applying ZIP updates from the web interface
- Creating a backup ZIP before web apply

### Install

Upload the package and run:

`/updates/v2-7-0`
