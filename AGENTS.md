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

## `board_promos` plugin (announcements, ads, sponsors)

Installed and active. State lives entirely in the database, so a fresh clone
needs `install()` + activation, not just a file copy.

### Installing from the CLI

There is no `inc/functions_plugins.php` in 1.8.40 — `find_replace_templatesets()`
comes from `inc/adminfunctions_templates.php`, and the install functions are
ordinary plugin functions you call directly:

```php
require_once MYBB_ROOT.'global.php';
require_once MYBB_ROOT.'inc/plugins/board_promos.php';  // not loaded until active
board_promos_install();
```

`board_promos_install()` is idempotent per step, so re-running it repairs a
partial install instead of erroring. Activation is a cache write:

```php
$c = $cache->read('plugins');
$c['active']['board_promos'] = 'board_promos';
$cache->update('plugins', $c);
board_promos_activate();     // adds the footer variable + find_replace_templatesets
rebuild_settings();
```

### The SQLite driver does not escape `insert_query()` values

`db_sqlite`'s `quote_val()` only wraps values in quotes. Every string bound for
`insert_query()`/`update_query()` must be escaped by the caller, or an
apostrophe truncates the query — `"VIP Club'ı keşfet"` in the seed data did
exactly that and produced an unhelpful blank "SQL Error" page. Use
`$db->escape_string($value)` on the whole array, as
`board_promos_create_templates()` and the seed loops already do.

### Avoid the ACP-only `Form` class

`new Form(...)` (`inc/class_form.php`) is loaded by the ACP only; using it in a
front-end script dies with `Class "Form" not found`. `sponsor.php` builds its
form as plain HTML for this reason. Likewise prefer `validate_email_format()`
from `inc/functions.php` over anything only defined further downstream.

### URL handling

`board_promos_safe_url()` accepts `http(s)://`, `/absolute` and bare relative
board paths, and rejects any other scheme. It deliberately does NOT guess a
scheme for `tronscan.org/x`: the same heuristic rewrites the valid relative
path `sponsor.php` into `https://sponsor.php`. External links must be written
with their scheme.

When building an href that already contains `&`, pass the raw `&` to
`htmlspecialchars_uni()` — passing `&amp;` double-escapes into `&amp;amp;` in
the rendered page.

### Verifying

```bash
curl -s http://127.0.0.1:12000/index.php | grep -c nextgen-announce-slide
curl -s http://127.0.0.1:12000/sponsor.php | grep -c nextgen-sponsor-form-wrap
curl -s -o /dev/null -w '%{redirect_url}' 'http://127.0.0.1:12000/promo.php?go=sponsor&id=1'
```

The sponsor form's honeypot field is `website` and must stay hidden — a filled
value returns the success page without writing a row, which is the intended
behaviour, not a bug.

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

Installing from the CLI follows the `board_promos` recipe; the task row and
settings group are created by `market_ticker_install()`, and the plugin must
then be added to the `plugins` cache and `market_ticker_activate()` called.

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

## ACP management area for the seven plugins

Every plugin ships an ACP module under `admin/modules/<codename>/` with a
`module_meta.php` (`<codename>_meta()`, `_action_handler()`,
`_admin_permissions()`). Verified working, all returning real module content:

| Plugin | ACP menu label | Sub-pages |
| --- | --- | --- |
| `board_promos` | Duyuru & Reklam | requests, announcements, ads, sponsors |
| `rss_news_bot` | RSS Haber Botu | queue, feeds |
| `simulated_community` | Simüle Topluluk | dashboard |
| `market_ticker` | Canlı Borsa Tablosu | ticker (+ settings deep link) |
| `social_login` | Sosyal Giriş | providers, accounts (+ settings deep link) |
| `vip_membership` | VIP Üyelik | orders, plans, networks |
| `crypto_forumcards` | Forum Kartları | icons |

The menu labels live in `module_meta.php`, NOT in the `config-settings` group
titles — the two disagree for `board_promos` (`Duyuru, Reklam ve Sponsor` in
`mybb_settinggroups`, `Duyuru & Reklam` in the sidebar). Read the label from the
file when checking the sidebar.

`config-settings&action=change&gid=N` deep links resolve for all six setting
groups (gid 31–36). The sub-menu link must carry a **raw** `&` — `add_menu_items()`
runs links through `htmlspecialchars_uni()`, so a pre-escaped `&amp;` renders as
`&amp;amp;`.

### ACP menu `disporder` must be unique

`$page->add_menu_item(..., N, $sub_menu)` with `N` already taken by another
module silently collapses the two entries into one sidebar item — no error, no
duplicate, just a module you cannot reach. Several of these plugins were authored
independently and `board_promos` and `rss_news_bot` both landed on 66. The
core modules use 1/10/20/30/40/50, so the plugin block is 60–66 and is packed
contiguously in the intended order. When adding a plugin ACP module, check the
whole range first:

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

### Ad placements must all render

`board_promos_ads()` used to wire only `index_top`, while the ACP still offered
`index_mid`, `index_bottom` and `global_footer`. Ads created in the ACP for those
placements were silently invisible. All four now anchor on markup that ships in
the custom templates: `index_top` → `.nextgen-promo-grid`, `index_mid` →
`.nextgen-forum-directory`, `index_bottom` → `.nextgen-community-notice`, and
`global_footer` → `<nav class="nextgen-mobile-nav"` (matched only when a
`global_footer` ad is actually sold, so unsold pages stay clean). The
`{$promo_footer_ad}` placeholder in `footer` is still unused — the footer slot is
injected through `pre_output_page` instead.

An unsold slot renders the `promo_ads_placeholder` invitation (icon + copy +
`sponsor.php` CTA) rather than nothing, so an empty placement still looks
deliberate. `promo_ads_placeholder` is a setting, so flipping it in
`mybb_settings` is invisible until `inc/settings.php` is regenerated — and it
must be regenerated through `rebuild_settings()` (boot `global.php` from a
throwaway CLI script), not hand-written, or the file loses MyBB's header and
`addcslashes` escaping. `board_promos`'s placeholder branch is the only writer
of `.nextgen-ad-placeholder*`; the older
`.nextgen-ad-placeholder .nextgen-ad-button` rules are dead because that markup
no longer emits a `.nextgen-ad-button`.

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
