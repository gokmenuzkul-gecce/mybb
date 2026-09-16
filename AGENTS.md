# MyBB Forum — Repo Notes

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
tid=176, headerinclude tid=962, footer tid=65) — not into files.

All templates live in `sid = -2` (MyBB Master Style) with 971 rows; theme 2
(`Default`) points its `templateset` at `1` but that set is empty, so Master
Style is what actually renders. Edits to any template therefore affect every
theme.

### Template `div` balance

The custom `header` and `footer` templates are intentionally asymmetric:
`header` has two unclosed `<div>`s (+2, closed by `footer`) and `footer` opens
with `</div>` before `</main>` (−2). They cancel across the page — an
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

## Localization

Turkish is the board default. `inc/languages/turkish/` has full key coverage
(0 missing vs `english`). Not everything translatable lives in language files —
these DB tables also carry user-visible English and were translated in place:

- `mybb_tasks` (`title`, `description`)
- `mybb_helpdocs` (`name`, `description`, `document`) and `mybb_helpsections`
- `mybb_usergroups` (`title`, `description`, `usertitle`)

`mybb_datacache` holds serialized caches (`usergroups`, `helpdocs`, …). Delete
the specific rows after editing the source tables — **never** `DELETE FROM
mybb_datacache` wholesale, that drops `internal_settings`/`version` and the
board starts returning HTTP 503.

`cache/themes/theme1/nextgen.css` exists but is **not** registered in
`mybb_themestylesheets`, so MyBB never loads it; `themes/crypto-web3/global.css`
supplies the same classes and is what actually renders.

## Theme components

Beyond the base `nextgen-*` shell, `themes/crypto-web3/global.css` styles four
redesigned areas. Each pairs with specific templates in `mybb_templates`:

- **Logo** — `.crypto-brand-logo` / `.crypto-brand-mark` / `.crypto-brand-ring`
  in the `header` template.
- **Search popup** — `.nextgen-search-modal`, opened by `.nextgen-search-trigger`
  via the `DOMContentLoaded` handler in `headerinclude`.
- **Login popup** — `.nextgen-login*`, a two-column grid. The wrapper lives in
  `header_welcomeblock_guest`; the `<tr>` fields come from
  `header_welcomeblock_guest_login_modal`, injected as `{$loginform}`. Styling
  the inner rows requires touching both templates.
- **Postbit** — `postbit`, `postbit_author_user`, `postbit_avatar`, plus
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
- **Member profile** — `member_profile` is a two-part layout:
  `.nextgen-profile-hero` (avatar, name, title pill, fact cards) above a
  `.nextgen-profile-grid` of two equal columns. The stock template used a
  `<fieldset>` with a `width="75%"` table and a right-aligned avatar cell; both
  are gone, so `fieldset` styling no longer applies to this page. The fact
  cards need `align-content: center` — as bare auto grid rows they stretch to
  match the tallest sibling, which ballooned them to 101px.
- **Forum rows on the index** — `forumbit_depth1_cat` (the category shell),
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

The form's `table.tborder` must also be excluded from the mobile
`.tborder table { min-width: 620px }` floor; that floor otherwise pushes the
editor off-screen at ≤760px. The form stacks its cells into blocks there and the
smilie sidebar drops below the editor.

`regdateformat` was changed from `"M Y"` to `"m.Y"` so registration dates render
numerically instead of with English month abbreviations (`my_date()` does not
translate `M`).

## Gotchas

- `bburl` / `homeurl` are stored in the database. A stale value breaks every
  asset URL, since `{$mybb->settings['bburl']}` prefixes all CSS/JS links.
- After editing `mybb_settings`, regenerate `inc/settings.php` — MyBB reads the
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
table alone does nothing — rebuild the cache through MyBB's own handler
(`$mybb->cache->update_usertitles()`) or the ACP, so the serialized format on
disk stays correct.

### A forumbit template must keep its table shell

`forumbit_depth1_cat` is the `<table>` that wraps a category's `<thead>`,
`<tbody>` and every child row. It reads as pure header markup, but dropping the
opening `<table ...>` or the closing `</table>` makes the browser discard the
foster-parented `<tr>`/`<td>` tags entirely. The page then renders as a flat
list of cells with no row classes at all — `.nextgen-forum-row` matches zero
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
the hook runs once per forum per page — keep the work memoised.

### Activating a plugin without the ACP

Registration is just the codename in the `plugins` datacache; the ACP then
writes the cache and calls `{codename}_activate()`. Headlessly, require
`global.php`, append the codename to `$cache->read('plugins')['active']`, call
`$cache->update('plugins', ...)`, then invoke the activate function. Booting
`global.php` needs `THIS_SCRIPT` defined, and any script doing this should be
deleted afterwards.
