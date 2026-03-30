# Google Calendar Sync (PHP + MySQL)

Complete OAuth 2.0 and Google Calendar synchronization module for SI Jadwal.

## 1) Install dependency

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/si-jadwal/google-calendar-sync
composer install
```

## 2) Prepare Google Cloud OAuth credentials

1. Open Google Cloud Console.
2. Create/select a project.
3. Enable **Google Calendar API**.
4. Create OAuth Client ID (Web application).
5. Add Authorized redirect URI:
   - `http://localhost/si-jadwal/index.php?route=api-google-callback`
6. Copy `Client ID` and `Client Secret`.

## 3) Configure app

Edit `app/config/config.php` and fill:
- `GOOGLE_CLIENT_ID` (paste Client ID)
- `GOOGLE_CLIENT_SECRET` (paste Client Secret)
- `GOOGLE_REDIRECT_URI`
- DB credentials (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`)
- Optional: `TOKEN_ENCRYPTION_KEY` (recommended 32+ chars)

`google-calendar-sync/config.php` is now only a compatibility shim.

## 4) Create database tables

Run SQL file:

```bash
mysql -u root -p si-jadwal_db < /Applications/XAMPP/xamppfiles/htdocs/si-jadwal/google-calendar-sync/schema.sql
```

Or import `schema.sql` from phpMyAdmin.

## 5) Use from Account Settings

Open:

`http://localhost/si-jadwal/index.php?route=pengaturan&tab=akun`

Use buttons in **Google Calendar** card:
- Connect Google Calendar
- Sync Now
- Disconnect

## 7) Flow summary

1. **Connect Google Calendar** (`index.php?route=api-google-connect`)
   - Redirects user to Google OAuth consent.
2. **Google callback** (`index.php?route=api-google-callback`)
   - Exchanges auth code for `access_token` + `refresh_token`.
   - Saves token to `user_tokens`.
3. **Sync Now** (`index.php?route=api-google-sync`)
   - Reads schedules from DB.
   - Creates events for rows without `google_event_id`.
   - Updates events if schedule fields changed.
   - Deletes orphaned Google events when local schedule deleted.
4. **Disconnect** (`index.php?route=api-google-disconnect`)
   - Deletes user token row and clears local `google_event_id`.

## 8) Notes

- Timezone is fixed to `Asia/Jakarta`.
- Token refresh is automatic when token expired.
- Duplicate prevention uses:
  - local `google_event_id`
  - Google `extendedProperties.private.schedule_id`
- Prepared statements are used for all DB writes/reads.
