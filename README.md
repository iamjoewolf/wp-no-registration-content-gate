# WP No-Registration Content Gate

A lightweight WordPress plugin that replaces login/registration gates with a frictionless entry form on protected pages.

Visitors enter their name, job title, how they'll use the content and their email address, plus an optional comms opt-in. The plugin creates or updates a standard WordPress user (no password or login needed), sets a signed persistent cookie so they aren't asked again, and reveals the protected content straight away. Existing users are recognised and preserved.

## Features
- Per-page "protected" toggle (Block and Classic editors)
- Logged-in users and returning visitors bypass the gate
- Protected content is not output in the HTML until unlocked
- Creates/updates WordPress users without sending registration emails
- Signed HMAC unlock cookie (365 days)
- Extra columns and CSV export on the Users screen
- UK GDPR-friendly opt-in (unchecked by default)

## Licence
GPL-2.0-or-later. See LICENSE.













