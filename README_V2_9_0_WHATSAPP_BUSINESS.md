# ISO Admin Command Framework v2.9.0

## WhatsApp Business Integration

Adds a full WhatsApp Business module using Meta's official **WhatsApp Cloud API**: a shared team inbox, template messages, media, broadcasts, employee opt-in management and automatic notifications for leave decisions and expiring documents.

The module is self-contained. It does **not** replace `routes/web.php`, `UpdateController.php` or any existing controller, so v2.7/v2.8 code on the live server is untouched.

## Added

- **Shared inbox** (`/whatsapp`)
  - Conversation list with search and filters (unread, open 24h window, employees).
  - Chat thread that refreshes every few seconds with new messages and sent / delivered / read ticks.
  - Free-form replies, attachments (images, PDFs, Office documents, audio, video) and approved templates.
  - 24-hour customer service window shown per conversation; free-form messages are blocked once it closes, as WhatsApp requires.
  - Inbound images, documents, voice notes, video, locations, contacts, reactions and button replies.
  - Inbound media is downloaded from Meta on first view and kept in private storage.
  - Failed messages show the WhatsApp error and a Retry button.
  - Start a new conversation with an employee or any number using an approved template.
  - Contact panel: display name, link to an employee, opt-in / opt-out and notes.
- **Employee Opt-ins** (`/whatsapp/contacts`): every active employee with their WhatsApp number (from profile mobile, then phone) and consent status, with bulk opt-in / opt-out.
- **Broadcasts** (`/whatsapp/broadcasts`): send an approved template to all employees, departments or roles. Only opted-in contacts are included. `{name}` / `{first_name}` personalise values. Delivery, read and failure counts per broadcast.
- **Templates** (`/whatsapp/templates`): sync templates from Meta and see which are approved and sendable. Positional (`{{1}}`) and named (`{{customer_name}}`) parameters are supported.
- **Automatic notifications** (each can be switched on separately):
  - Leave approved / declined → the employee.
  - Employee document reaching its reminder date → the employee.
  - Vehicle document reaching its reminder date → the assigned driver.
  - Each event is sent once only, and a WhatsApp failure never blocks the leave approval itself.
- **Consent handling**: contacts reply `STOP` to opt out and `START` to opt in. Opted-out contacts never receive templates, notifications or broadcasts.
- **Optional auto-reply** when a new conversation starts.
- **Settings** (`/settings/whatsapp`): credentials, webhook, cron, notifications, connection test and a `hello_world` test send. The access token and app secret are stored **encrypted** and are never shown in the browser again.
- **Security**: every webhook is checked against Meta's `X-Hub-Signature-256` signature; unsigned or wrongly signed requests are rejected and logged.
- **Retries**: temporary API failures are queued and retried by the cron job (up to 3 attempts). Permanent errors such as an invalid number or a missing template fail at once.
- **Permissions** (module *WhatsApp*):

| Permission | System Administrator | Director | Manager |
|---|---|---|---|
| `whatsapp.view` | ✓ | ✓ | ✓ |
| `whatsapp.send` | ✓ | ✓ | ✓ |
| `whatsapp.contacts.manage` | ✓ | ✓ | |
| `whatsapp.broadcast` | ✓ | ✓ | |
| `whatsapp.templates.manage` | ✓ | ✓ | |
| `whatsapp.settings.view` | ✓ | | |
| `whatsapp.settings.manage` | ✓ | | |

Defaults are applied only the first time a permission is created. Later changes on the Permissions page are kept when the update is run again.

## New files

- `app/Providers/WhatsAppServiceProvider.php`
- `routes/whatsapp.php`
- `app/Http/Controllers/WhatsApp*Controller.php` (8 controllers)
- `app/Models/WhatsApp*.php` (5 models)
- `app/Services/WhatsApp/*` (Cloud API client, messenger, webhook processor, notifier, broadcaster, template sync, settings, installer, phone number normaliser)
- `resources/views/whatsapp/**`
- `public/iso-whatsapp-v290-activate.php` (one-use activation script)
- `tests/Feature/WhatsApp/*`, `tests/Unit/WhatsAppPhoneNumberTest.php`

## Changed files (repo copies)

- `bootstrap/providers.php`: registers `WhatsAppServiceProvider`
- `resources/views/layouts/app.blade.php`: WhatsApp menu link with unread count, and page icon
- `VERSION`, `CHANGELOG.md`, `phpunit.xml` (test database only)

> **Do not upload `bootstrap/providers.php` or `resources/views/layouts/app.blade.php` from this repo over the live server.** The live layout has v2.7/v2.8 menu items that are not in this repo. Use the activation script below, which adds the WhatsApp entries into the live files.

## Apply (no command line required)

1. Back up the site files and database.
2. Upload the **new files** listed above, keeping the folder structure.
3. Edit `public/iso-whatsapp-v290-activate.php` and change `ISO_ACTIVATE_KEY` from `CHANGE-ME` to your own long random value (16+ characters).
4. Open `https://isoadmin.co.za/iso-whatsapp-v290-activate.php?key=YOUR_KEY`.
   It registers the provider, adds the WhatsApp menu link to the live layout (with `.bak-` backups), clears compiled views and caches, and can be run more than once safely.
5. **Delete `public/iso-whatsapp-v290-activate.php`** once it reports `SUCCESS`.
6. Log in as System Administrator and open `https://isoadmin.co.za/updates/v2-9-0`, then click **Apply v2.9.0 Update**.

## Meta / WhatsApp setup

1. In [Meta for Developers](https://developers.facebook.com/), create a **Business** app and add the **WhatsApp** product.
2. Add and verify the ISO business phone number under WhatsApp > API Setup. Note the **Phone Number ID** and **WhatsApp Business Account ID**.
3. In Meta Business Settings > System Users, create a system user, assign the app and WhatsApp account, and generate a **permanent token** with `whatsapp_business_messaging` and `whatsapp_business_management`. (The temporary token on API Setup expires after 24 hours.)
4. Copy the **App Secret** from App Settings > Basic.
5. Enter all of these at `/settings/whatsapp`, tick **Enable**, save, then click **Test Connection**.
6. In WhatsApp > Configuration, set the webhook:
   - Callback URL: `https://isoadmin.co.za/whatsapp/webhook`
   - Verify token: the value shown on the settings page
   - Subscribe to the **messages** field.
7. Add the cron URL shown on the settings page as a DirectAdmin cron job every 5–15 minutes, for example:

```text
*/10 * * * * curl -s "https://isoadmin.co.za/whatsapp/cron/YOUR_WHATSAPP_CRON_KEY" > /dev/null
```

8. Create notification templates in WhatsApp Manager (category **Utility**), then click **Sync from Meta** on `/whatsapp/templates`:

| Template | Body example |
|---|---|
| `leave_request_update` | Hi {{1}}, your {{2}} request for {{3}} has been {{4}}. Log in to ISO Admin for details. |
| `document_expiry_reminder` | Hi {{1}}, your {{2}} expires on {{3}}. Please submit an updated copy to HR. |
| `vehicle_document_reminder` | Hi {{1}}, the {{3}} for {{2}} expires on {{4}}. Please arrange renewal. |

9. Record employee opt-ins under **WhatsApp > Employee Opt-ins** (or ask employees to send `START`), then enable the notifications you want.

## WhatsApp rules to know

- **24-hour window**: you can reply freely for 24 hours after a contact's last message. Outside that window, only approved templates can be sent.
- **Opt-in**: Meta requires consent before business-initiated messages. With *Require opt-in* on (default), notifications and broadcasts only go to opted-in contacts.
- **Costs**: Meta charges per template message by category (utility, marketing, authentication). Replies inside the 24-hour window are free.
- Templates with media headers or dynamic URL buttons can be used in Meta but cannot be sent from the platform. These show as *Meta only* on the Templates page.

## Troubleshooting

- *Last webhook received* stays **Never**: check the callback URL and verify token in Meta and that the **messages** field is subscribed.
- Webhooks rejected with `Invalid signature`: the App Secret in settings does not match the Meta app.
- Error `190` / token expired: replace the temporary token with a System User permanent token.
- Error `131047` (re-engagement): the 24-hour window has closed, so send a template.
- Error `132001`: the template name or language does not exist in Meta. Check the language code (for example `en` vs `en_US`).
