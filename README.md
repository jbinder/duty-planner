# Duty Planner

WordPress plugin for planning recurring duties ("spots"). People sign up with a display name and email, get reminders, and admins get a weekly alert about duties that still need people.

The plugin lives in [`duty-planner/`](duty-planner/). See [Installation](#installation) below.

## Features

- **Spots** recur daily or weekly (chosen weekdays), every N days/weeks, optionally with an end date. They are either timed (start + duration) or all-day.
- **Min/max people** per spot. The calendar marks each date *Needs people* (red, striped), *Places free* (amber) or *Fully booked* (green) with an icon, a count and a fill bar showing the minimum.
- **Public calendar** via the shortcode `[duty_planner]` (options: `view="month|list"`, `weeks="6"`, `spots="1,2"`). It has a month grid that becomes an agenda on phones, a list view, and a sign-up dialog. Only display names are public. Emails are never sent to the browser.
- **Unlisted calendar page** (optional): the page stays reachable by its link but is left out of automatic page menus, site search, the sitemap and the public REST page list, and is marked `noindex`.
- **Allowlist** (optional): addresses or `@domains`, set globally or per spot (a spot's own list overrides the global one).
- **Emails** (editable templates with placeholders):
  - confirmation;
  - reminder (offset **per spot**; all-day spots count back from a configurable reference time);
  - cancellation;
  - "date cancelled" notice.
- **Self-cancellation** via a signed link in every email. The link opens a confirmation step, so mail scanners can't cancel anyone.
- **Weekly admin alert**: recipients, weekday and time are configurable. It lists duties below their minimum in the next week (or the rest of the current one), and sends nothing if every duty is covered.
- **Admin screens**:
  - Schedule: fill status, registrants with emails, add/remove people, cancel/restore single dates.
  - Spots.
  - Settings, including a "Send admin alert now" button.
- Protection for public sign-up: overbooking lock, duplicate check, honeypot, nonce and a per-IP rate limit.
- Hooks into WordPress privacy export/erase. Uninstall deletes the data only if you opt in.

Background jobs run on WP-Cron every 15 minutes.

## Installation

**Requirements:** WordPress 6.0+, PHP 7.4+ and MySQL or MariaDB. SQLite-based setups are not supported, because the plugin uses a database lock to prevent overbooking.

### 1. Build the zip

Run this in the repo root:

```sh
mkdir -p build && zip -r build/duty-planner.zip duty-planner -x '*.DS_Store'
```

The zip must contain the `duty-planner/` folder itself, not just the files inside it. This command does that.

### 2. Upload and activate

In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose `duty-planner.zip`, click **Install Now**, then **Activate**.

If you'd rather copy files over SFTP or SSH, put the `duty-planner/` folder into `wp-content/plugins/` and activate it under **Plugins**.

### 3. Set it up

1. **Pages → Add New**: create the calendar page and put the shortcode `[duty_planner]` on it: in the block editor with a *Shortcode* block, in Elementor with the *Shortcode* widget. Selecting the page in the settings (next step) does **not** add the calendar to it. The page title is the headline above the calendar. The slug is the URL, which can be hard to guess, e.g. `/duty-plan-7f3k/`.
2. **Duty Planner → Settings**:
   - choose that page as **Calendar page**. Tick **Keep the calendar page unlisted** if it should be reachable only by its link. The link (with a *Copy link* button) is then shown at the top of the plugin's Schedule, Spots and Settings screens, and the page is labelled *Duty Planner calendar* in the Pages list.
   - fill in the **alert recipients**, plus the weekday and time for the weekly alert.
   - optionally set the sender name/email, the allowlist and the email texts.
3. **Duty Planner → Add spot**: create your duties.

### 4. Check email and cron on the live site

- **Email delivery:** many hosts don't send `wp_mail` reliably. Use an SMTP plugin (e.g. WP Mail SMTP), then sign up yourself once to confirm the confirmation email arrives.
- **Cron:** WP-Cron only fires when someone visits the site. On quiet sites, ask your host to run a real cron job every 5–15 minutes:

  ```
  wget -q -O - https://your-site.example/wp-cron.php?doing_wp_cron >/dev/null 2>&1
  ```

### Updating

Rebuild the zip and upload it again. WordPress asks whether to replace the installed version. Your spots, registrations and settings are kept.

## Development

```sh
docker compose up -d                          # WordPress :8080, Mailpit :8025
docker compose run --rm cli wp core install --url=http://localhost:8080 --title=Dev \
  --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email
docker compose run --rm cli wp plugin activate duty-planner
docker compose run --rm cli wp cron event run dutyplan_tick   # run reminders/alert now
php tests/recurrence-test.php                                 # recurrence unit tests
```

All mail sent in the dev environment lands in Mailpit at http://localhost:8025.
