# BroCode_AdminhtmlQuickLinks

Personal quick links for Magento 2 admin users. Star any backend page, and it sits one
click away in the admin header on every page after that.

**Module page:** [brocode.at/modules/module-adminhtml-quicklinks/](https://brocode.at/modules/module-adminhtml-quicklinks/)

```bash
composer require brocode/module-adminhtml-quicklinks
bin/magento module:enable BroCode_AdminhtmlQuickLinks
bin/magento setup:upgrade
bin/magento cache:flush
```

In production mode, also run `setup:di:compile` and `setup:static-content:deploy` as
usual.

![Pinned quick links in the admin header](docs/images/header-chips.png)

## The problem

Admin work keeps returning to the same few pages: a config section, the order grid, one
product being worked on this week. Magento has no bookmarks for the backend. A browser
bookmark doesn't help either, because every admin URL carries a secret key that
changes with each login. The bookmark stops working the next morning and redirects to
the dashboard.

## What this does

- **A star in the admin header** favourites the page you are on, and clicking it again
  removes the page. A filled star means the current page is already a quick link. The
  label defaults to the page title ("Orders", "Products") and can be renamed later.
  Configuration pages all share the title "Configuration", so their label adds the
  section, and the scope when it isn't the default: "Configuration: Catalog",
  "Configuration: Payment Methods (Second Website)". The scope is part of the link, so
  it reopens the section at that website or store view.
- **A starred grid keeps its filters and search keyword.** Magento stores a grid's
  filters in the user's saved grid view, not in the URL, so a plain link to a filtered
  grid opens it with whatever was used last. A quick link saved from a filtered grid
  carries the filters and keyword itself and applies them when it is opened. Its label
  uses the text of the grid's own filter chips, so a website filter reads "Customers:
  Second Website", not the website id. A link saved from an unfiltered grid clears the grid's filters and keyword
  when opened, so "Orders" always shows all orders, whatever filter was used last.
  This works for every grid built on Magento's UI listing component. Several links to
  one grid can coexist, one per filter set, and the star is filled only while the grid
  shows exactly the filters of one of them. Links saved before grid states were
  recorded, and custom links added by URL, open the grid as it was last used; on a
  grid they never fill the star, so the unfiltered view can still be starred.
- **Pinned links show as buttons at the very top of every page**, above the page
  title, so each one is a single click away, with no dropdown to open first. They sit
  in the strip Magento reserves for system messages on every page; system messages,
  when there are any, appear right below the chips. Drag a chip to change the order.

  That strip exists so the page doesn't jump when the messages box, which Magento
  draws after the page has rendered, appears. The chips keep that guarantee:
  - **No system messages:** nothing will load there, so the chips use the reserved
    space.
  - **A system message pending:** the space stays reserved below the chips. The
    module checks this server-side on every page, after Magento's own messages
    component has synced them, so it is exact even on the first page after a message
    appears.
  - The result: the header moves exactly as much as in core when a message appears,
    and not at all when none does.

![Quick links above a system message](docs/images/header-with-messages.png)
- **They stay reachable while you scroll, on every page.**
  - On pages with Magento's sticky action bar (configuration, edit forms, most grids),
    the chips appear inside that bar, between the page title and its buttons, once it
    turns sticky. Chips that don't fit are cut off at the buttons and fade out, and the
    bar keeps its normal height.
  - Pages without that bar (invoice, shipment and credit memo grids, reports, themes,
    …) get a bar of their own: page title and chips. It appears once the chip row has
    scrolled out of view, and it uses core's markup, so it looks like Magento's own.
    It sits in the space Magento's sticky grid toolbars already leave free for core's
    bar, so it doesn't cover them.

![Quick links inside the sticky action bar on a configuration page](docs/images/sticky-bar.png)

![The quick-links bar on a page without an action bar (Sales → Invoices)](docs/images/sticky-own-bar.png)

- **Unpinned links go into the dropdown next to the star.** Pinning is per link, so the
  daily pages stay visible and the occasional ones wait in the dropdown. The dropdown
  always ends with a fixed *Manage quick links →* entry.

![The star dropdown with unpinned links and the manage entry](docs/images/star-dropdown.png)

- **System → Tools → BroCode Quick Links** is where the links are managed, in a table
  styled like Magento's own grids. Every change shows in the header chips on that page
  right away, without a reload. You can:
  - reorder by drag and drop, or with the ▲/▼ buttons, which work from the keyboard too
  - rename a link
  - pin or unpin it
  - delete it
  - add a custom link: any admin URL pasted from the address bar, or an external
    http(s) link (docs, a ticket board, a staging shop)

![System → Tools → BroCode Quick Links](docs/images/manage-page.png)

- **External links can be edited in place**: both the label and the URL. Internal links
  let you edit the label only, because their target came from a real admin page.

![Editing an external link](docs/images/manage-edit.png)

![The BroCode Quick Links entry under System → Tools](docs/images/menu-entry.png)

## How the links survive a new login

An admin URL is stored as its route path, without the base URL, the admin frontName or
the `key/<hash>` segment: `sales/order/view/order_id/5`, not
`https://shop.test/admin/sales/order/view/order_id/5/key/3f9a…/`. On every render, the
link is rebuilt through the backend URL builder, which adds a fresh key for the current
session.

The route is stored by its route **id**, not its frontName. The backend computes the
secret key from the id (`adminhtml`), while the URL shows the frontName (`admin`), so a
link stored as `admin/dashboard` would carry a key the router rejects. The admin
frontName is read from the configuration, not assumed to be `admin`; the test install
runs on `backend`.

## Security

- **Every link belongs to one admin user.** The user id always comes from the session,
  never from the request. An attempt to read, edit or delete another user's link gets
  the same "does not exist" answer as a missing id.
- **Only absolute `http://` and `https://` URLs are accepted.** `javascript:`, `data:`,
  relative and protocol-relative input is rejected on the server. Every value is
  escaped on output, and external links open with `rel="noopener noreferrer"`.
- **All writes are POST requests**, checked by Magento's backend form-key validation.
- **The header and the management page are guarded by one ACL resource,**
  `BroCode_AdminhtmlQuickLinks::quicklinks`, found under System → Tools in the role
  editor. A role without it sees no star and no links.
- **Deleting an admin user deletes that user's links** through a foreign key with
  `ON DELETE CASCADE`.

## Not in this version

- Sharing links between users, or setting them per role. Every list is personal.
- A limit on the number of links. The header row wraps instead of hiding links; in
  the sticky bars, links past the available width are cut off.

## Compatibility

- PHP 8.1, 8.2, 8.3, 8.4
- Tested on Magento Open Source 2.4.8-p5 with the default backend theme
- Admin only: no frontend code. Adds one table, `brocode_adminhtml_quicklink`.

## Verification status

Unit tests cover:
- URL normalisation: key and frontName stripping, route-id mapping, query strings,
  rejected schemes, unknown admin routes
- href rebuilding
- splitting a link's saved grid filters from the rest of its URL, and recognising an
  unfiltered state
- the repository: per-user scoping, append-on-create, reordering, validation

Integration tests cover, against a real database:
- per-user scoping
- stored sort order
- the cascade on admin-user deletion
- detecting a pending system message, including the ACL case where none renders

Run them from a Magento install that has the module under `app/code`:

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/BroCode/AdminhtmlQuickLinks/Test/Unit
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml.dist "$PWD/app/code/BroCode/AdminhtmlQuickLinks/Test/Integration"
```

A browser end-to-end run against 2.4.8-p5 (Playwright, custom admin frontName
`backend`) exercised:
- starring and un-starring pages
- following a chip after logging out and back in
- adding external and pasted admin links
- renaming and pinning
- arrow and drag-and-drop reordering after a reload, on the manage page and by
  dragging the header chips, each side following the other without a reload
- saving a filtered, keyword-searched order grid, and reopening it in a new session
  with filters and keyword restored, whatever the grid showed before
- the star following the grid: filled for a saved filter set, empty after the filters
  change
- an unfiltered grid link resetting filters and keyword left by a filtered one
- a link without saved state leaving the star empty on its grid, so the unfiltered
  view can be starred as its own link
- a configuration section saved and reopened as "Configuration: Catalog", and a
  website-scoped section saved as "Configuration: Payment Methods (Second Website)" and
  reopened at that scope after a new login
- a website-filtered customer grid labelled "Customers: Second Website"
- filters and keyword applied even when the grid's saved views load late (forced with
  a delayed bookmarks script)
- header chips and dropdown following pin, unpin and reorder on the manage page
- the chips in Magento's sticky action bar: bar height unchanged, no overlap with the
  buttons, and kept in sync when the star adds or removes a link
- the module's own bar on a page without an action bar: shown only while the chip
  row is out of view, kept in sync with the star, and never created on pages that
  have Magento's bar
- no extra layout shift from the chips when a system message loads: header position
  at DOM ready against the final render, with and without a pending message, compared
  to the same page without links
- rejection of `javascript:` URLs, cross-user writes and requests without a form key
