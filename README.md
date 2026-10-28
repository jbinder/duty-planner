# Duty Planner

WordPress plugin for planning recurring duties ("spots"). People sign up with a display name and email, get reminders, and admins get a weekly alert about duties that still need people.

The plugin lives in [`duty-planner/`](duty-planner/). See [Installation](#installation) below.

## Features

- **Spots** recur daily or weekly (chosen weekdays), every N days/weeks, optionally with an end date. They are either timed (start + duration) or all-day.
- **Public holidays** (optional, per spot): tick *Skip Austrian public holidays* and the spot doesn't take place on statutory holidays in Austria (New Year, Epiphany, Easter Sunday/Monday, 1 May, Ascension, Whit Sunday/Monday, Corpus Christi, 15 Aug, 26 Oct, 1 Nov, 8 Dec, 25/26 Dec). Holidays are computed, so they work for any year. Dates people had already signed up for before you ticked the box stay in the calendar, so no registration gets lost.
- **Min/max people** per spot. The calendar marks each date *Needs people* (red, striped), *Places free* (amber) or *Fully booked* (green) with an icon, a count and a fill bar showing the minimum.
- **Public calendar** via the shortcode `[duty_planner]` (options: `view="month|list"`, `weeks="6"`, `spots="1,2"`). It has a month grid that becomes an agenda on phones, a list view, and a sign-up dialog. Only display names are public. Emails are never sent to the browser.
- **Formatted descriptions:** the description of a spot is edited with WordPress' small text editor (bold, italic, bullet/numbered lists, links). Only these safe tags are kept; scripts and event handlers are removed.
- **Note field** (optional, per spot): give it a label such as "What will you bring?" and people can leave a short note (max. 200 characters) when signing up. Notes are shown publicly next to the name in the calendar, in the admin schedule, and via the `{note}` email placeholder. Without a label the field is hidden.
- **Unlisted calendar page** (optional): the page stays reachable by its link but is left out of automatic page menus, site search, the sitemap and the public REST page list, and is marked `noindex`.
- **Languages:** English and German (informal "du"), switchable under *Settings → Language*. Choose a language explicitly or let it follow the WordPress language. The setting covers the calendar, all emails and the plugin's admin screens, including dates and weekday/month names. Email texts you haven't edited switch along; edited texts stay as written.
- **Allowlist** (optional): addresses or `@domains`, set globally or per spot (a spot's own list overrides the global one).
- **Emails** (editable templates with placeholders):
  - confirmation, ending with a small thank-you box: how often the person has already done each duty (past dates, matched by email), or a welcome for first-timers (placeholder `{stats}`);
  - reminder (offset **per spot**; all-day spots count back from a configurable reference time);
  - cancellation;
  - "date cancelled" notice;
  - overview of a person's registrations (`{list}`), requested from the calendar.
- **Self-service changes** via a signed personal link in every confirmation and reminder email (placeholder `{manage_url}`; the old `{cancel_url}` still works). The link opens a page where people can change their name and note, cancel, or cancel and pick another date in the calendar. The email address can't be changed there: the link is tied to it, so a new address means cancelling and signing up again. Every change needs a button press, so mail scanners can't change or cancel anything.
- **Lost the email?** Below the calendar, *Change or cancel your registration* asks for an email address and sends a list of that person's upcoming duties with their links. The answer on the page is the same whether or not the address is registered, so it doesn't reveal who signed up, and each address gets at most one such email every 10 minutes.
- **Weekly admin alert**: recipients, weekday and time are configurable. It lists duties below their minimum in the next week (or the rest of the current one), and sends nothing if every duty is covered.
- **Weekly mail to the mailing list** (optional): set a mailing list address (e.g. the whole building), plus a weekday, time and period (1–8 weeks, default 2, starting with the following or the current week). Everyone on the list gets an overview of the period's duties. Duties below their minimum come first, with how many people are still needed and a request to jump in. Covered duties are listed with a thank-you to the people who signed up, with their notes. Only first names go out ("Laura Müller" or "Müller, Laura" → "Laura"; "Laura M." if two Lauras are in the same mail). It goes out every week, even when everything is covered. Set a **location** (e.g. "Open Space") for the default subject "{location}: Plan für die nächsten {weeks} Wochen". Placeholders `{open}`, `{covered}`, `{status}`, `{location}` and `{weeks}`.
- **Admin screens**:
  - Schedule: fill status, registrants with emails, add/remove people, cancel/restore single dates.
  - Spots.
  - Settings, including "Send admin alert now" and "Send mailing list mail now" buttons.
- Protection for public sign-up: overbooking lock, duplicate check, honeypot, nonce and a per-IP rate limit.
- Hooks into WordPress privacy export/erase. Uninstall deletes the data only if you opt in.

Background jobs (reminders, admin alert, mailing list mail) run on WP-Cron every 15 minutes.

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
   - optionally enter the **mailing list** address for the weekly overview to everyone, plus its weekday, time and number of weeks.
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

### Translations

Translations live in `duty-planner/languages/`: `duty-planner.pot` (template) and `duty-planner-de_DE.po/.mo` (German). After changing or adding strings:

```sh
docker compose run --rm cli wp i18n make-pot wp-content/plugins/duty-planner \
  wp-content/plugins/duty-planner/languages/duty-planner.pot --skip-js --exclude=languages
docker compose run --rm cli wp i18n update-po wp-content/plugins/duty-planner/languages/duty-planner.pot
# translate the new entries in duty-planner-de_DE.po (e.g. with Poedit), then:
docker compose run --rm cli wp i18n make-mo wp-content/plugins/duty-planner/languages
```

To add a language, add a `duty-planner-<locale>.po/.mo` pair and register the locale in `I18n::languages()`.
