# MyBB Forum â€” Repo Notes

Crypton Web3 Community forum, MyBB 1.8.40, Turkish language pack.

## Running locally

The database is committed at `cache/mybb.sqlite` (SQLite, 75 `mybb_*` tables),
but `inc/config.php` and `inc/settings.php` are gitignored, so they must be
regenerated after a fresh clone.

```bash
sudo apt-get install -y php-cli php-sqlite3 php-mbstring php-gd php-curl php-xml php-zip
# inc/config.php: database type 'sqlite', database '/workspace/project/cache/mybb.sqlite'
touch install/lock
# regenerate inc/settings.php from the database (mirrors rebuild_settings())
php -S 0.0.0.0:12000 -t /workspace/project
```

`inc/settings.php` is generated verbatim from the `mybb_settings` table using
MyBB's own `rebuild_settings()` format: one `$settings['name'] = "value";` line
per row, ordered by `title` ascending, with `addcslashes($value, '\\"$')`
escaping.

## Theme

The custom design lives in `themes/crypto-web3/` (`global.css`, `header.html`,
`headerinclude.html`, `index.html`) and is linked directly from the
`headerinclude` template. Design markup (`nextgen-*`, `crypto-brand-*` classes)
is baked into the global templates in the database (header tid=160, index
tid=176, headerinclude tid=962, footer tid=65) â€” not into files.

All templates live in `sid = -2` (MyBB Master Style) with 971 rows; theme 2
(`Default`) points its `templateset` at `1` but that set is empty, so Master
Style is what actually renders. Edits to any template therefore affect every
theme.

### Template `div` balance

The custom `header` and `footer` templates are intentionally asymmetric:
`header` has two unclosed `<div>`s (+2, closed by `footer`) and `footer` opens
with `</div>` before `</main>` (âˆ’2). They cancel across the page â€” an
unbalanced `header` or `footer` alone is *not* a bug, so never "fix" them
without checking the sum against a full rendered page.

The stock `header_welcomeblock_guest` / `header_welcomeblock_member` DO carry
two `</div>`s that the stock `header` relied on. Because the custom `header`
closes `.upper`/`#panel` itself, keeping those two tags over-closes the
container and breaks the layout. They have been removed; keep them removed when
updating the language-independent welcomeblock templates.

Verify a change with:

```bash
curl -s http://127.0.0.1:12000/index.php | python3 -c "
import re,sys
b=re.sub(r'<script.*?</script>',' ',sys.stdin.read(),flags=re.S|re.I)
print(re.findall(r'<div\b',b).__len__() - re.findall(r'</div>',b).__len__())"
```

A healthy page reports `0`. `/tmp/audit_templates.py` diffs every DB template
against stock `install/resources/mybb_theme.xml` and reports imbalance.

### Things deliberately NOT depended on

Tailwind CDN and `cache/themes/theme1/nextgen.css` are both unused: the design
is plain CSS in `themes/crypto-web3/global.css`, which defines every
`nextgen-*` / `crypto-brand-*` class. The Tailwind `<script>` and
`tailwind.config` block were removed from `headerinclude` so the theme renders
without external network access. FontAwesome (CDN) is still used for icons.

Keep Tailwind out. Its preflight sets `img { display: block }`, which turns any
row of inline `<img>`s into a vertical stack. That is exactly how the post-icon
row (`.posticons_label`, one `<label>` + `<img>` per icon) and the smilie
insertion table broke. Restoring the CDN silently wrecks both again, so the
`.posticons_label { display: inline-flex }` rule in `global.css` is a
belt-and-braces guard, not a redundant one.

## Replying to a PM opens an empty editor

`private.php` builds the reply body in two places, and both used to seed the
editor with the original message quoted. The owner wants replies to start blank
so the member writes their own text.

- The full reply form (`action=send&do=reply`, around line 900) now quotes only
  on `do == 'forward'`. `reply` and `replyall` set `$message = ''` and keep the
  `Re:` subject, recipient and signature/receipt options.
- The quick reply block (`action=read`, around line 1235) no longer builds
  `$quoted_message` at all; the template's `<textarea>` ships empty.

Forwarding still quotes, so the two paths deliberately diverge. If you touch
either branch, keep the `do == 'forward'` guard — that is the whole behaviour.
`$quoted_message` in `private_quickreply` is now dead as a variable name; the
quick-reply textarea is a plain `#message` with no SCEditor.

## Localization

Turkish is the board default. `inc/languages/turkish/` has full key coverage
(0 missing vs `english`). Not everything translatable lives in language files â€”
these DB tables also carry user-visible English and were translated in place:

- `mybb_tasks` (`title`, `description`)
- `mybb_helpdocs` (`name`, `description`, `document`) and `mybb_helpsections`
- `mybb_usergroups` (`title`, `description`, `usertitle`)

`mybb_datacache` holds serialized caches (`usergroups`, `helpdocs`, â€¦). Delete
the specific rows after editing the source tables â€” **never** `DELETE FROM
mybb_datacache` wholesale, that drops `internal_settings`/`version` and the
board starts returning HTTP 503.

`cache/themes/theme1/nextgen.css` exists but is **not** registered in
`mybb_themestylesheets`, so MyBB never loads it; `themes/crypto-web3/global.css`
supplies the same classes and is what actually renders.

## Theme components

Beyond the base `nextgen-*` shell, `themes/crypto-web3/global.css` styles four
redesigned areas. Each pairs with specific templates in `mybb_templates`:

- **Logo** â€” `.crypto-brand-logo` / `.crypto-brand-mark` / `.crypto-brand-ring`
  in the `header` template.
- **Search popup** â€” `.nextgen-search-modal`, opened by `.nextgen-search-trigger`
  via the `DOMContentLoaded` handler in `headerinclude`.
- **Login popup** â€” `.nextgen-login*`, a two-column grid. The wrapper lives in
  `header_welcomeblock_guest`; the `<tr>` fields come from
  `header_welcomeblock_guest_login_modal`, injected as `{$loginform}`. Styling
  the inner rows requires touching both templates.
- **Postbit** â€” `postbit`, `postbit_author_user`, `postbit_avatar`, plus
  `postbit_online` / `postbit_offline`, `postbit_reputation`,
  `postbit_warninglevel` and `postbit_profilefield`. The author column is a
  centred card: `.nextgen-author-card` (avatar + presence pill), then
  `.author_information`, then `.nextgen-author-stats` / `.nextgen-meta-row`.
  `{$post['onlinestatus']}` must stay a **direct child** of
  `.nextgen-author-card`, otherwise the corner-pinning rule
  (`.nextgen-author-card > .nextgen-presence`) never matches and the pill
  renders unstyled in normal flow. Stats are label-left / value-right rows
  (`b` carries `order: 2; margin-left: auto`), which stays readable at any
  username length, unlike the earlier 3-up grid.
- **Member profile** â€” `member_profile` is a two-part layout:
  `.nextgen-profile-hero` (avatar, name, title pill, fact cards) above a
  `.nextgen-profile-grid` of two equal columns. The stock template used a
  `<fieldset>` with a `width="75%"` table and a right-aligned avatar cell; both
  are gone, so `fieldset` styling no longer applies to this page. The fact
  cards need `align-content: center` â€” as bare auto grid rows they stretch to
  match the tallest sibling, which ballooned them to 101px.
- **Forum rows on the index** â€” `forumbit_depth1_cat` (the category shell),
  `forumbit_depth2_forum`, `forumbit_depth2_cat` and
  `forumbit_depth2_forum_lastpost`. Each row is a per-forum icon chip that
  **leads the row** (its own first cell, 48px, hover scale plus a float
  animation), the read/unread dot as a corner badge on that chip, the forum
  name with its description, the two counters, and a last-post block whose
  avatar sits to the **right** of the last-post text. The chip colour comes
  from `--glyph`, set inline from `{$forum['icon_color']}`.
  `.nextgen-forum-name` needs `min-width: 0`, otherwise a long forum name
  overflows its cell instead of wrapping.
  The icon chip's `translateY` hover and the child `<i>`'s float animation are
  deliberately on separate elements; combining them on one node makes the
  animation win and the hover do nothing.

The login modal reuses MyBB's jQuery-modal plugin, so the theme also overrides
the generic `.modal` and `.blocker` classes to keep the overlay consistent.

### Selector traps with `:has()`

Two rules key off `#message`, the textarea SCEditor replaces, to identify the
posting form (new thread / new reply):

- `.tborder:has(#message)` sets the form table to full width and gives the
  editor its working height.
- `.tborder:has(#clickable_smilies):not(:has(#message))` narrows only the
  *standalone* smilie box. The smilie block is embedded inside the posting form,
  so without `:not(:has(#message))` the form table collapses and the editor
  shrinks to a few hundred pixels wide.

`#message` alone is not a reliable "is a posting form" test. The User CP
signature editor (`usercp_editsig`) carries `#clickable_smilies` and a
`#signature` textarea but *no* `#message`, so the standalone-box rule matched it
and pinned the whole signature form to 340px — the "textarea is tiny/broken"
report. That rule now also excludes `:not(:has(#signature))`, and the signature
form gets its own sizing block. Both selectors have to grow together: any new
page that embeds `#clickable_smilies` next to a non-`#message` editor needs the
same exclusion, or it inherits the popup width.

The same `#message` selector is also a trap in the other direction. The PM
quick reply (`private_quickreply`) *is* a bare `<textarea id="message">` with
`rows="8"` and no SCEditor, so `form #message { min-height: 480px }` stretched
it to a full posting editor. It is scoped back down through
`.tborder:has(#quickreply_e) #message`.

The form's `table.tborder` must also be excluded from the mobile
`.tborder table { min-width: 620px }` floor; that floor otherwise pushes the
editor off-screen at â‰¤760px. The form stacks its cells into blocks there and the
smilie sidebar drops below the editor.

`regdateformat` was changed from `"M Y"` to `"m.Y"` so registration dates render
numerically instead of with English month abbreviations (`my_date()` does not
translate `M`).

## Gotchas

- `bburl` / `homeurl` are stored in the database. A stale value breaks every
  asset URL, since `{$mybb->settings['bburl']}` prefixes all CSS/JS links.
- After editing `mybb_settings`, regenerate `inc/settings.php` â€” MyBB reads the
  PHP file on every request and does not re-read the table.
- `headerinclude` links `global.css` with a `?v=N` cache-buster. Bump it after
  editing the stylesheet or browsers keep serving the old file.

### MyBB base styles win on specificity, not source order

MyBB's own rules are written for a table layout and frequently outrank the
theme's class-only selectors. Two that have already caused visible regressions:

- `.nextgen-login-submit` lost to `input.button` (0,1,1 beats 0,1,0), so the
  submit button rendered as a small grey box instead of the gradient CTA. The
  theme rule is written as `input.nextgen-login-submit` to match specificity.
- `.nextgen-login-table` ties with `.tborder` at 0,1,0; `.tborder` comes later in
  the file, so the login table picked up a grey panel, border and rounded
  corners that fought the modal. The theme rule now carries `!important` on the
  properties that must reset, and the stale wrapper rules are deleted rather
  than left to compete.

Prefer raising specificity (`input.foo`, `td.foo`) over sprinkling
`!important`; reserve `!important` for resetting a rival's property, and delete
superseded rules instead of layering a new one on top.

### `unapprovedposts` is a ghost counter the ACP cannot repair

The homepage posts column appends MyBB's unapproved counter to the real count:
`{$posts}{$unapproved['unapproved_posts']}` renders `0 (10)` when
`mybb_forums.unapprovedposts` is stale. Those rows are built by
`get_forum_unapproved()` (`inc/functions_forumlist.php`), which emits
`<span title="...">(N)</span>` only when the counter is non-zero **and**
`is_moderator($fid, "canviewunapprove")` passes — so it is visible to the admin
and nobody else.

`unapprovedposts` is only ever adjusted **incrementally** by
`update_forum_counters()`; the ACP "Recount & Rebuild" tool does not touch it at
all. A counter left behind when an unapproved post is hard-deleted therefore
survives every rebuild, and the column keeps advertising posts that do not exist
(`fid=11` and `fid=15` each carried one while holding zero posts).

Repair it against the real rows, through MyBB's own handler:

```php
define('IN_MYBB', 1); define('THIS_SCRIPT', 'index.php');
require_once MYBB_ROOT.'global.php';
$n = (int)$db->fetch_field($db->simple_select('posts',
    'COUNT(*) AS n', "fid='{$fid}' AND visible='0'"), 'n');
update_forum_counters($fid, array('unapprovedposts' => $n));
```

`IN_MYBB` is required, not just `THIS_SCRIPT` — without it `global.php` dies with
"Direct initialization of this file is not allowed". The `forums` datacache holds
no `unapprovedposts` key, so the template reads the live table and no cache
invalidation is needed. Delete any one-off repair script afterwards.

### Avatars are capped at 100x100

`maxavatardims` / `useravatardims` are `100x100`, and `images/default_avatar.png`
is 100x100. Rendering them at 126px upscaled the bitmap to 1.26x and read as
blurry and dated. Keep the rendered box near native size (104px in the postbit,
112px with padding on the profile) and check `naturalWidth` against the
rendered width when a "blurry avatar" report comes in.

### Usertitles live in the database

Post-count titles (`Newbie`, `Junior Member`, ...) are rows in `mybb_usertitles`,
not language strings, so the Turkish language pack leaves them in English. They
are also cached in `mybb_datacache` under the `usertitles` key. Updating the
table alone does nothing â€” rebuild the cache through MyBB's own handler
(`$mybb->cache->update_usertitles()`) or the ACP, so the serialized format on
disk stays correct.

### A forumbit template must keep its table shell

`forumbit_depth1_cat` is the `<table>` that wraps a category's `<thead>`,
`<tbody>` and every child row. It reads as pure header markup, but dropping the
opening `<table ...>` or the closing `</table>` makes the browser discard the
foster-parented `<tr>`/`<td>` tags entirely. The page then renders as a flat
list of cells with no row classes at all â€” `.nextgen-forum-row` matches zero
elements while `grep` on the raw HTML still finds 18 of them, which is a
confusing way to find out. Keep `<table>`/`</table>` balanced in that template
and assert the count when editing it.

### Last-post avatars need a plugin, not just a template

`build_forumbits()` only ever exposes `lastposteruid`, `lastposter`,
`lastpostsubject` and `lastposttid` to the templates, and
`forumbit_depth2_forum_lastpost` has no avatar placeholder. The avatar is added
by `inc/plugins/crypto_forumcards.php`, which hooks `build_forumbits_forum` and
attaches `lastpost_avatar`, `icon_class` and `icon_color` to `$forum`. Two
things to know:

- `format_avatar()` takes the **raw** `mybb_users.avatar` column value. An
  uploaded avatar is already stored as a full path
  (`./uploads/avatars/avatar_1.jpg?dateline=...`), so prefixing
  `avataruploadpath` doubles it into
  `./uploads/avatars/./uploads/avatars/...`. Pass the column value straight
  through.
- A category row stores `lastpost = 0` and renders a child's last post, so its
  own `lastposteruid` is useless. `crypto_forumcards_category_posters()` walks
  `parentlist` once to find the newest descendant poster.

The plugin is a data provider only; the markup ships in the theme's templates.
`build_forumbits_forum` fires inside the recursion in `build_forumbits()`, so
the hook runs once per forum per page â€” keep the work memoised.

### Activating a plugin without the ACP

Registration is just the codename in the `plugins` datacache; the ACP then
writes the cache and calls `{codename}_activate()`. Headlessly, require
`global.php`, append the codename to `$cache->read('plugins')['active']`, call
`$cache->update('plugins', ...)`, then invoke the activate function. Booting
`global.php` needs `THIS_SCRIPT` defined, and any script doing this should be
deleted afterwards.

### Theme templates live in the database, not on disk

Editing `themes/crypto-web3/*.html` changes nothing until the matching row in
`mybb_templates` is updated. Every theme template here exists in two places:

- `themes/crypto-web3/<name>.html` — the source of truth for review and diff
- `mybb_templates` where `title='<name>' AND sid=-2` — what MyBB actually renders

`sid=-2` is the global override; a row keyed to a real `sid` wins over it, so
check for one before editing. After any theme template edit, push the file into
the DB and assert the two match:

    disk = open('themes/crypto-web3/headerinclude.html').read()
    db.execute("update mybb_templates set template=? "
               "where title='headerinclude' and sid=-2", (disk,))

The half-applied edit is the failure mode to watch for: the file looks right,
the page does not change, yet `grep` on the file confirms the fix. The theme's
CSS link carries a `?v=N` cache-buster that also lives in the `headerinclude`
row — bump it in both places whenever `global.css` changes.

### A `strpos` guard on a bare class prefix can match your own JavaScript

`pre_output_page` injectors that skip work when their markup is already present
usually test for a class name. If that name also appears in inline JS in
`headerinclude` (any `querySelector('.nextgen-slider-dot')`, say), the guard
matches the script text, every page looks already-rendered, and the feature
silently never appears. Guard on the markup itself — `class="nextgen-slider"` —
not on the bare prefix.

### `sprintf` and SQL `LIKE` patterns

`LIKE 'moved|%'` inside a query later passed to `sprintf()` throws
`ValueError: Unknown format specifier` — the `%` is read as a conversion. Use
`str_replace` with a placeholder, or escape it as `%%`.

### VIP approval congratulations

`vip_membership_send_welcome()` sends the PM and
`vip_membership_welcome_banner()` renders the one-time banner, both driven by
`vip_orders.welcomed`. The column is added by
`vip_membership_upgrade_tables()`, which runs off `vip_membership_create_tables()`
so a fresh install and an upgrade both get it. The banner clears the flag for
the **approved** order only; clearing every row for the user would consume a
welcome that a second, later order still owes.

### Disabled MyBB features still answer HTTP 200

`portal.php` with `portal=0` returns 200 with an error body
("portal sayfasını kullanamazsınız"), not a 404. Judge these by the body, not the
status code. Note that `inc/settings.php` is a flat generated cache — a settings
write stays invisible until `rebuild_settings()` regenerates it.

### Test harness

`requests` is not installed; use stdlib `urllib` plus `http.cookiejar`. Do not
drive HTTP tests through the CLI PDO SQLite driver — an open handle blocks the
PHP process's writes and the requests hang. Seed state with Python's `sqlite3`
first, then issue the HTTP calls.

Front-end auth is a `mybbuser=<uid>_<loginkey>; sid=<sid>` cookie, and a row in
`mybb_sessions`. ACP auth needs `mybb_adminsessions` instead, keyed by the admin
uid. The `sid` column is 32 chars.

Every `curl` to the board creates a guest row in `mybb_sessions`. Sweep
`uid=0` before committing the database, or the diff is full of session noise.

Vocabulary used in the task list (the user's own words, worth keeping):
"vitrin" = the homepage VIP teaser, "tebrik" = the approval congratulation
banner, "duyuru" = announcements, "sari alan" = the yellow inline error/message
area.

### Environment can be reset between turns

PHP was reinstalled twice mid-project and `/tmp` was wiped with it, so `/tmp`
test scripts and a running `php -S` are both gone at the start of a turn.
Re-provision before assuming a failure is real:

```bash
sudo apt-get update -q          # needed first: a stale index cannot find php-cli
sudo apt-get install -y php-cli php-sqlite3 php-mbstring php-gd php-curl php-xml
php -S 0.0.0.0:12000 -t /workspace/project > /tmp/php-server.log 2>&1 &
```

The base image is Debian trixie and the packages are versioned (`php8.4-cli`),
but the unversioned aliases resolve once the apt index is refreshed. `php -v`
should read 8.4.x, which renders every page with 0 deprecations.

The board's own state survives a reset — `inc/config.php`, `inc/settings.php`
and `cache/mybb.sqlite` are all still on disk. Only the toolchain is transient.

## `board_promos` plugin — REMOVED

The announcements / ads / sponsors plugin was removed on request. The homepage
no longer renders the announcement strip, the ad slots or the sponsor strip, and
the ACP has no `board_promos` area.

What was removed:

- `inc/plugins/board_promos.php`, `promo.php`, `sponsor.php`
- `admin/modules/board_promos/` (all five files)
- Tables `mybb_promo_announcements`, `mybb_promo_ads`, `mybb_promo_sponsors`,
  `mybb_promo_sponsor_requests`, `mybb_promo_clicks`
- Settings `promo_announcements_on`, `promo_ads_on`, `promo_ads_placeholder`,
  `promo_sponsors_on`, `promo_sponsor_notify_uid`, `promo_sponsor_intro`,
  `promo_sponsor_cooldown`, the `board_promos` setting group, and the
  `promo_announcements` / `promo_sponsor_page` / `promo_sponsor_strip` templates
- The codename was dropped from the `plugins` datacache

**Do NOT drop `mybb_promotions` / `mybb_promotionlogs`** — those are core MyBB
tables (present in `install/resources/mysql_db_tables.php`), unrelated to the
plugin despite the name.

`announcements.php` is also core MyBB, not part of the plugin: it serves
`mybb_announcements` (forum-wide notices) and is linked from `archive/index.php`
and `inc/init.php`. It was kept.

### Removing a plugin: don't write while a SELECT cursor is open

The first removal pass died with a blank "MyBB SQL Error" page. The cause was
`$db->update_query(...)` inside the `while($t = $db->fetch_array($q))` loop over
`mybb_templates` — the open SELECT cursor and the write target the same table,
and SQLite refuses. Buffer the rows into an array first, then write, or do the
cleanup with Python's `sqlite3` directly.

`{$promo_footer_ad}` had also accumulated **three** copies in the `footer`
template (`sid=1`, tid=975) because `board_promos_activate()` was run more than
once — `find_replace_templatesets()` is not idempotent. When removing such a
placeholder, strip every occurrence with `str_replace`, not a single replace.

### The SQLite driver does not escape `insert_query()` values

`db_sqlite`'s `quote_val()` only wraps values in quotes. Every string bound for
`insert_query()`/`update_query()` must be escaped by the caller, or an
apostrophe truncates the query — `"VIP Club'ı keşfet"` in seed data did exactly
that and produced an unhelpful blank "SQL Error" page. Use
`$db->escape_string($value)` on the whole array before it reaches the query.

### Avoid the ACP-only `Form` class

`new Form(...)` (`inc/class_form.php`) is loaded by the ACP only; using it in a
front-end script dies with `Class "Form" not found`. Front-end scripts must
build forms as plain HTML. Likewise prefer `validate_email_format()` from
`inc/functions.php` over anything only defined further downstream.

### Rebuilding `inc/settings.php` after a settings change

`inc/settings.php` is a flat generated cache; a write to `mybb_settings` stays
invisible until it is regenerated. Boot `global.php` from a throwaway CLI
script and call `rebuild_settings()` — do not hand-write the file, or it loses
MyBB's header and `addcslashes` escaping:

```php
define('IN_MYBB', 1);
define('THIS_SCRIPT', 'index.php');
require_once './global.php';
rebuild_settings();
```

Delete the script afterwards.

When building an href that already contains `&`, pass the raw `&` to
`htmlspecialchars_uni()` — passing `&amp;` double-escapes into `&amp;amp;` in
the rendered page.

## `market_ticker` plugin (live crypto prices)

Home-page strip fed by CoinGecko's keyless `/simple/price` endpoint. Binance's
API is blocked from this environment; CoinGecko works.

Prices are served from the `market_ticker` datacache, never fetched during a
page view. `index_start` refreshes opportunistically at most once per the
configured window, and the `market_ticker` scheduled task (every 5 min) does
the same on a timer. A failed fetch keeps the previous snapshot, so the strip
goes stale rather than blank — `market_ticker_refresh()` returns the old cache
rather than `false` in every failure branch.

Coins and currency are settings (`market_ticker_coins`,
`market_ticker_currency`); up to 12 coin ids are accepted and sanitised to
`[a-z0-9-]` before being used in the request URL, so a setting cannot inject
query parameters.

Installing from the CLI: create the task row and settings group via
`market_ticker_install()`, then add the codename to the `plugins` datacache and
call `market_ticker_activate()`. There is no `inc/functions_plugins.php` in
1.8.40 — `find_replace_templatesets()` comes from
`inc/adminfunctions_templates.php`, and the install/activate functions are
ordinary plugin functions you `require_once` and call directly.

## `social_login` plugin (Google / GitHub / Discord OAuth 2.0)

Plain OAuth authorization-code client — no SDK. Providers differ only by URL
and JSON shape, so `social_login_providers()` is one table and
`social_login_fetch_profile()` normalises the result.

Security invariants that must not be relaxed:

- `state` is a random nonce in `mybb_social_states`, consumed exactly once and
  compared with `hash_equals()`. A wrong or reused state is rejected.
- Only `email_verified` addresses are trusted for account matching. An
  unverified match must never resolve to an existing member.
- An existing local account is never auto-linked. If the verified email
  belongs to a member who is not the current `$mybb->user`, the attempt is
  refused with `email_taken`. Silently linking would let whoever controls that
  provider address take over the local account.
- `redirect` is restricted to same-board paths (no `//`, no `:`), so the
  callback cannot become an open redirect.

`social_login.php` is a root-level script (`THIS_SCRIPT == 'social_login.php'`),
so `member.php`'s action dispatch is bypassed. Test hooks that guard on
`THIS_SCRIPT` must be run with the name set to `member.php` or the button
renderer legitimately returns the input unchanged.

MyBB caps passwords at **30 characters**. `bin2hex(random_bytes(12))` (24 hex)
is the largest safe random password for the `UserDataHandler('insert')` path;
16 bytes (32 hex) fails `invalid_password_length` and account creation silently
returns `create`.

The buttons render after the `</table>` that closes the login/register form,
anchored on `name="username"` (falling back to `name="password"`).

To exercise the flow, temporarily set `social_*_on=1` plus dummy `*_id`/
`*_secret` values, run the checks, then set them back to `0`/`''` and
`rebuild_settings()`. Leaving dummy secrets in the committed database would be
a credential leak.

## ACP management area for the plugins

Every plugin ships an ACP module under `admin/modules/<codename>/` with a
`module_meta.php` (`<codename>_meta()`, `_action_handler()`,
`_admin_permissions()`). Verified working, all returning real module content:

| Plugin | ACP menu label | Sub-pages |
| --- | --- | --- |
| `rss_news_bot` | RSS Haber Botu | queue, feeds |
| `simulated_community` | Simüle Topluluk | dashboard |
| `market_ticker` | Canlı Borsa Tablosu | ticker (+ settings deep link) |
| `social_login` | Sosyal Giriş | providers, accounts (+ settings deep link) |
| `vip_membership` | VIP Üyelik | orders, plans, networks |
| `crypto_forumcards` | Forum Kartları | icons |

`board_promos` used to be in this table; it has been removed, and its
`admin/modules/board_promos/` directory is gone. Its `disporder` slot (66) is
now free — the remaining plugin block is no longer contiguous, which is fine as
long as each value is unique.

The menu labels live in `module_meta.php`, NOT in the `config-settings` group
titles — the two can disagree. Some plugins had a different label in
`mybb_settinggroups` than in the sidebar. Read the label from the file when
checking the sidebar.

`config-settings&action=change&gid=N` deep links resolve for the remaining six
setting groups (gid 31–36 minus the removed one). The sub-menu link must carry a
**raw** `&` — `add_menu_items()` runs links through `htmlspecialchars_uni()`, so
a pre-escaped `&amp;` renders as `&amp;amp;`.

### ACP menu `disporder` must be unique

`$page->add_menu_item(..., N, $sub_menu)` with `N` already taken by another
module silently collapses the two entries into one sidebar item — no error, no
duplicate, just a module you cannot reach. Several of these plugins were authored
independently and two once landed on the same number. The core modules use
1/10/20/30/40/50, so the plugin block is 60–66. When adding a plugin ACP module,
check the whole range first:

```bash
grep -h "add_menu_item" admin/modules/*/module_meta.php
```

Assert the rendered sidebar too — top-level items are
`<li><a href="index.php?module=X">Label</a>`, and the sub-menu/toolbar links use
a different shape, so a loose regex counts a module more than once.

### The ACP approve/reject flow is GET-confirm, POST-execute

`vip_membership-orders` renders a confirmation form on `GET ...&action=approve`
and only performs the grant when the follow-up `POST` arrives (the verb is the
flag; there is no hidden field). A test that follows the `Düzenle`-style link
with `GET` will see `status` stay `pending` and look like a broken handler. Fetch
the confirm form, lift its `my_post_key`, then POST `oid` + `action`.

### Testing ACP pages headlessly

A row in `mybb_adminsessions` alone is not enough — `lastactive` must be within
7200s of now, `ip` must be `my_inet_pton(get_ip())` (`x'7f000001'` for
127.0.0.1), and `authenticated` must be `1` or the 2FA gate kicks in. Then send
`mybbuser=1_<loginkey>; sid=<sid>; adminsid=<sid>`.

A short page (~2KB) is the ACP login form, not the module. Real module pages run
6–18KB (the RSS queue is ~100KB). Do not grep for the Turkish string
`hata oluştu` as an error signal: `lang.unknown_error` is embedded in every ACP
page's `<script>` block. Sweep `mybb_adminsessions` for the test `useragent`
afterwards and leave the user's real session alone.

### Thread list styling

Stock `.trow_sep` is a full-width `#ddd` band — the "white stripe" across the
dark thread card. Restyled as an uppercase label with a hairline top border.
`forumdisplay_threadlist`'s table carries `nextgen-thread-list`, which supplies
the drifting sheen (`::after`, disabled under `prefers-reduced-motion`).
`forumdisplay_thread` had reply/view badges duplicating the dedicated Yanıtlar /
Okunma columns; those are removed, and the status cell is driven from the real
`thread_status` classes (`folder`, `dot_folder`, `newfolder`, `hotfolder`,
`closefolder`, `newhotfolder`).

## Warning and error styling

`.pm_alert` (stock MyBB's yellow bar) and `.red_alert` are themed as callouts
in `global.css`. `.error` / `.error_inline` — the login-failure and validation
block — are styled too; field-level `.error` inside `.trow1`/`.trow2`/`td` is
narrowed back to an inline chip so it does not become a full-width callout.

## Pushing disk templates into the database

`themes/crypto-web3/{header,headerinclude,index}.html` are the source of
truth, but MyBB renders the rows in `mybb_templates` (`sid=-2`). After editing
a file on disk, copy it into the matching row (`header` tid=160,
`headerinclude` tid=962, `index` tid=176) or the change never reaches the
page. The `?v=N` cache-buster in `headerinclude` lives in that DB row too —
bumping only the file on disk leaves the browser loading the old stylesheet.
Templates that have no disk copy — the whole registration flow
(`member_register`, `member_register_agreement` and its 11 sub-templates),
`member_login`, `forumdisplay_thread`, `error_inline`, `error_inline_item`,
`global_pm_alert` — live only in `mybb_templates` (`sid=-2`). Edit them there
and bump the `?v=N` cache-buster in `headerinclude`; a CSS edit without the
bump ships unseen.

Two constraints on the registration form: `inc/jscripts/member.js` binds to
`#registration_form`, `#username` and `#password2`, and the submit control must
stay `<input type="submit" name="regsubmit">` — MyBB checks that field name
server-side, so a `<button>` silently breaks the POST. `{$passboxes}` emits
bare `<tr>` rows, so the password section has to keep a `<table>` wrapper.

## User dropdown: the logout button is a child of the panel

`header_welcomeblock_member` (tid=169, `sid=-2`, **no disk copy**) used to place
`a.nextgen-usermenu-logout` as a sibling of `.nextgen-usermenu-links`. Because
`.nextgen-usermenu` is a flex row, the button sat beside the trigger in the
header and — once absolutely positioned — spilled outside the opened card.
It is now the **first child inside** `.nextgen-usermenu-links`, pinned with
`position: absolute; top: 8px; right: 8px`, and the panel carries
`padding-top: 46px` to clear it. Moving the button back out of the panel makes
it reappear in the header; keep it in the panel.

The panel also no longer has its `1px solid rgba(96,165,250,.22)` border — that
rectangle read as a stray frame floating behind the card. The edge comes from
the background plus `box-shadow`. Do not re-add a border without checking it
against the opened state in a browser.

## Thread list: column contract and the empty `<td>` trap

`forumdisplay_thread` renders **eight** `<td>` cells (status chip, jump arrow,
subject, replies, views, rating, last post, checkbox) and the header in
`forumdisplay_threadlist` spans them with `colspan="3"` + four single cells.
Two things break that alignment, both already fixed — don't undo them:

- The subject cell (`{width: 100%}`, counters `{width: 1%}`) absorbs leftover
  width. Header padding must match the body (`7px 10px` vs `4px 10px`) or the
  label sits on a different rhythm than the data under it.
- **Never `display: none` an empty `<td>`.** `.nextgen-thread-jump:empty` did
  that and the row dropped to seven cells while the header still spanned eight,
  so every later cell shifted one column left (`Yanıtlar` body at x=319 under a
  header at x=474). Hide the content or leave the cell in the grid with
  `padding: 0` so an empty arrow is a 6px hairline.

`$colspan` in `forumdisplay.php` is computed (`7` with ratings, `6` without,
`+1` for a moderator), so hard-coding a colspan in `forumdisplay_threadlist` /
`forumdisplay_threads_sep` breaks those two variants. Leave `{$colspan}` alone.
Merging the arrow cell into the subject cell would mean editing that PHP — not
worth it just to remove a gutter.

Column widths are written as px on the body cells and inline on the header
cells; verify with a real browser, not by eye:

```python
# getBoundingClientRect on header vs body cells — left/right must match
sorted({x['l'] for x in header + body}) == [211, 253, 273, 928, 1014, 1093, 1207, 1349]
```

### The dot chip centres on the row, and one ancient rule hid a specificity bug

`td.nextgen-thread-status` carries `vertical-align: middle` so the chip lands on
the row centre, matching the announcement rows (which use the stock
`.forumdisplay_announcement` cell and are centred by `.nextgen-thread-row > td`).

Two rules used to fight this and produced the "icon is not where it belongs"
report for regular rows while the announcement row looked fine:

- `.nextgen-thread-status .thread_status { display: inline-block }` (0,2,0)
  out-specified the chip rule (0,1,0), so the 9px `::before` dot inherited an
  inline-block box and sat ~8px above the chip's centre. The chip rule now stands
  alone; do not re-add a descendant-selector `display` for that span.
- `td.nextgen-thread-status { vertical-align: top }` pinned the chip ~22px above
  the row centre, while the announcement row stayed centred — so the two dot
  columns never lined up.

Verify by row centre, not by eye: `chipCentre - rowCentre` must be ≈ 0 for the
announcement row *and* every regular row. Rows can be 51px (one-line) or 71px
(two-line, multi-line) — the constant is the row centre, not the subject's top.

### Header: only the action row pins, not the whole header

`#header` is **not** sticky (it cannot be — the brand row must not stay behind),
so the action row pins itself. `headerinclude` measures the brand row (`--logo-h`),
the row's own height (`--panel-h`, kept as a placeholder on `#panel` so the
document does not jump) and the page box's document top (`--topoffset`), then
toggles `html.panel-pinned` once `pageYOffset > logoH`. The pinned row is
`position: fixed` with a real background and `backdrop-filter`, otherwise the
thread rows show straight through it.

Heights are measured with `.panel-pinned` removed first, so the signed/unsigned
`--panel-h` never feeds back into itself. Only `--topoffset` is recomputed while
pinned, and `panel-pinned` is the last write in `update()`, so a scroll can never
flip the class twice. Admin/mod CP links are a `<span>` inside the account menu
in the stock template, not a bar above the page — `--topoffset` addresses the
`#container` box, which is the only thing that could sit above it.

Panel bars: `#panel .upper` / `#panel .lower` were painted `#111c30` / `#0b1425`,
which showed as a blue-black slab behind the search trigger. Both are
`transparent` with no padding, and `.lower` has **no** `border-bottom` — that
hairline was the grey line under the account menu. The `<br class="clear">` in
`header_welcomeblock_member` is `display: none`; together with `.lower`'s old
padding it stacked ~40px of dead space under the menu.

### Two grey-border sources on the thread list

`.nextgen-thread-row > td` strips its border, but two other row types did not:

- `td.forumdisplay_announcement` (announcement rows) only carries the stock
  `.trow1`, whose `border: 1px solid; border-color: #fff #ddd #ddd #fff` frames
  each cell in grey. Override it with `border: 0` plus the same bottom hairline.
- `.trow_sep` had a theme `border-top: 1px` that drew the line above the
  category label.

### The NBSP in `.thread_status` is a grid item

Stock markup is `<span class="thread_status">&nbsp;</span>`. Under
`display: inline-grid; place-items: center`, the text node is an **anonymous grid
item** and takes part in alignment, so the 9px `::before` dot was pushed onto the
NBSP's baseline row (measured centre y=634 in a box centred at y=643 — 9px high).
`display: flex` ignores the text node's box for alignment and `font-size: 0`
collapses its line box, so the dot lands dead centre. Scope the chip rule to bare
`.thread_status` (not `.nextgen-thread-status .thread_status`) so announcement
rows get it too.

## Thread counter chips: no ring, tight vertical padding

`.nextgen-thread-replies a` and `.nextgen-thread-viewcount` are flat tinted
pills (`padding: 2px 9px`, `border-radius: 999px`) with **no** `inset` ring.
They previously carried `padding: 5px 10px` plus `box-shadow: inset 0 0 0 1px`,
which grew every row that wrapped to two lines. The title cell is
`.nextgen-thread-main { width: 100% }` while the counter cells are `width: 1%`,
so leftover width goes to long subjects instead of the counters. `forumdisplay_thread`
has no disk copy — edit it in `mybb_templates` (`sid=-2`).
