# Duty Planner

WordPress plugin for planning recurring duties ("spots"). People sign up with a display name and email, get reminders, and admins get a weekly alert about duties that still need people.

The plugin lives in [`duty-planner/`](duty-planner/). To install it, copy or zip that folder into `wp-content/plugins/` and activate it.

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

Background jobs run on WP-Cron every 15 minutes. On low-traffic sites, set up a real cron job that calls `wp-cron.php`.

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
