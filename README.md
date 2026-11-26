# WP No-Registration Content Gate

A lightweight WordPress plugin (for [Poems for the Wall](https://poemsforthewall.org)) that replaces login/registration gates with a frictionless entry form on protected pages.

Visitors enter their name, job title, how they'll use the content, and email, plus an optional comms opt-in. The plugin creates or updates **one WordPress user per email** (no password or login needed), sets a signed persistent cookie, and reveals protected content. Existing users are preserved and recognised.

### Unlock modes (Content Gate → Settings)

| Mode | Use case |
|------|----------|
| **Site-wide** (default) | **Poems for the Wall:** one successful unlock opens every gated page. Same cookie everywhere. |
| **Scoped** | **Generic sites:** content is grouped by **gate scope** (slug on each page/block). Unlock scope `pack-a` only opens content in that group. The same email unlocks additional scopes on the **same user record** — no duplicate accounts. |

Scoped scopes are stored in user meta `wnrcg_unlocked_scopes`. Blocks support a **Gate scope** attribute; shortcode: `[wnrcg_gated scope="pack-a"]…[/wnrcg_gated]`.

## Install

1. Copy this repository folder to `wp-content/plugins/wp-no-registration-content-gate/`.
2. Activate **WP No-Registration Content Gate** in Plugins.
3. Use **Content Gate** in the admin menu (dashboard, protected content list, settings).
4. Restrict content like Ultimate Member — **entire page** or **part of a page**:
   - **Entire page:** **Content gate** panel / meta box → **Restrict entire page**.
   - **Section only:** insert the **Gated content** block (block editor), or `[wnrcg_gated]…[/wnrcg_gated]` in classic.
   - Unlock uses the frictionless form + cookie (not UM login/registration).

## Admin

| Screen | Location |
|--------|----------|
| Dashboard | **Content Gate → Dashboard** — counts and setup steps |
| Protected pages | **Content Gate → Protected content** — table of gated posts/pages |
| Settings | **Content Gate → Settings** — form copy, cookie lifetime, display-name behaviour |
| Per-page toggle | Page/post editor → **Content gate** panel (block) or meta box (classic) |
| List tables | **Pages** / **Posts** → **Content gate** column and **Content gate** post state |
| Users & export | **Users → All Users** — extra columns + **Export CSV (gate fields)** |

## Test

1. **First visit (logged out, no cookie):** Open a protected page — you should see only the unlock form, not the poem in View Source.
2. **Submit the form:** You should return to the same URL with content visible and a `wnrcg_unlocked_token` cookie (365 days, HttpOnly, SameSite=Lax, Secure on HTTPS).
3. **Return visit:** Clear cookies or use a private window vs normal window to confirm bypass behaviour.
4. **Logged-in WP user:** Any logged-in user bypasses the gate on protected pages.
5. **Historic user:** Submit with an email that already exists in `wp_users` — login and password unchanged; Administrator/Editor roles are not downgraded.
6. **CSV export:** Users → **Export CSV (gate fields)** — only users with unlock data (requires `list_users`).
7. **Validation errors:** Submit with a required field empty — error list and repopulated values should both appear.

## Caching

Full-page caches must not serve one HTML body to both locked and unlocked visitors. By default the plugin sets **`DONOTCACHEPAGE`** on gated singular views when the visitor may see the unlock form (**Content Gate → Settings → Discourage page cache**). You can still:

- Exclude protected URLs in your CDN/page cache, or
- Use cache plugins that vary on cookie (rare), or
- Override with the `wnrcg_gate_should_prevent_cache` filter (third argument: post ID).

Block themes: gated `core/post-content` is replaced via the `render_block` filter as well as `the_content`.

## Filters

- `wnrcg_protected_post_types` — post types that can be protected (default: `page`, `post`).
- `wnrcg_update_display_name` — set true to overwrite `display_name` on unlock for existing users (default: false; name stored in `wnrcg_last_submitted_name`).
- `wnrcg_gate_should_prevent_cache` — whether to define `DONOTCACHEPAGE` on gated singular views (default from settings).
- `wnrcg_rate_limit_max_attempts` — unlock attempts per IP per window (default: 10).
- `wnrcg_rate_limit_window` — rate limit window in seconds (default: 900).
- `wnrcg_unlocked` — action after successful unlock: `($user_id, $post_id, $scope)`.

## Form fields (admin)

**Content Gate → Gate form fields** defines the unlock form. Field definitions live in `wp_options` (`wnrcg_form_fields`). Answers are stored in standard WordPress tables:

| Storage | Table / column |
|--------|----------------|
| Email | `wp_users.user_email` |
| Name | `wp_users.display_name` (or `wnrcg_last_submitted_name` meta) |
| Marketing opt-in | `wp_usermeta.wnrcg_comms_opt_in` (`yes` / `no`) |
| Custom questions | `wp_usermeta.wnrcg_{field_id}` |

Built-in locked fields: **name**, **email**, **comms_opt_in**. Add custom questions (text, textarea, select) for your site — e.g. job title, “how will you use this content”, etc.

## User meta (system)

| Meta key | Purpose |
|----------|---------|
| `wnrcg_comms_opt_in` | Marketing opt-in |
| `wnrcg_unlocked_at` | First unlock (ISO 8601) |
| `wnrcg_last_unlocked_at` | Most recent unlock |
| `wnrcg_last_submitted_name` | Name when display name not overwritten |
| `wnrcg_*` | Custom field answers (per field ID) |

## Tests

With [PHPUnit](https://phpunit.de/) installed:

```bash
cd wp-no-registration-content-gate
phpunit -c phpunit.xml.dist
```

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE).