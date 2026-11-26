# Header, login and error pages

## Header with navigation

`Includes/FrontdeskHeader` is the header for the project's `Page.ss`:

```html
<% include Atwx\SilverstripeFrontdeskKit\Includes\FrontdeskHeader %>
```

From `lg` up the main navigation is horizontal; below it is in a burger menu.
`secondary_navigation` (administration, settings) is in the user menu on large
screens and under "Weitere" in the burger menu on small ones. Logged-out
visitors see only the logo or site title and a login button.

```yaml
Atwx\SilverstripeFrontdeskKit\Controller\FrontdeskController:
  logo: 'app/client/images/logo.svg'   # otherwise SiteConfig.Title
  main_navigation:
    students:
      title: 'Schüler'
      segment: 'students'                 # link and active match
      controller: 'App\Controllers\StudentController'   # shown if canView()
      default: true                       # active on the start page
    followups:
      title: 'Wiedervorlagen'
      segment: 'followups'
      link: 'followups?view=mine-due'     # link with a query string
    applications:
      title: 'Kurzbewerbungen'
      segment: 'intake'
      link: 'intake?Type=kurzbewerbung'
      permission: 'FDK_INTAKE_VIEW'        # a code or a list (any of them)
      badge: 'App\Navigation::newApplications'   # count; 0 shows no badge
  secondary_navigation:
    flow:
      title: 'Abläufe'
      link: 'admin/flow'                  # CMS path, active below admin/flow
      permission: 'CMS_ACCESS_LeftAndMain'
    help:
      title: 'Hilfe'
      link: 'https://example.org/help'    # absolute: opens in a new tab
```

### Icons and a second counter

`icon` puts a Lucide icon before the title; with `icon_only: true` the
horizontal navigation shows just the icon (the title is its tooltip and
screen-reader label, the burger menu still shows it). `badge_secondary` is a
second counter next to `badge`, in another color:

```yaml
  main_navigation:
    todos:
      title: 'To-dos'
      segment: 'followups'
      link: 'followups?view=mine-todos'
      icon: 'square-check'
      icon_only: true
      badge: 'Atwx\Flow\Controller\FollowUpController::countTodos'
      badge_secondary: 'Atwx\Flow\Controller\FollowUpController::countTodoBasket'
```

The kit bundles a set of Lucide icons (see `fdkIcons` in
`client/src/js/main.js`: phone, mail, school, calendar, calendar-days, bell,
square-check, message-square, users, user, alarm-clock, pin, reply, undo-2,
star, paperclip, list-todo, inbox, clock, tag, info, triangle-alert,
circle-check, sticky-note, house, building-2, graduation-cap, plane, euro,
handshake, briefcase, flag, check, pencil, file-text, …). A project adds more
by defining `window.fdkExtraIcons = { Name: iconNode }` before the kit's
script loads.

### Submenus

An entry with `children` is a group: a dropdown in the horizontal navigation
and a titled section in the burger menu (and in the user menu, if used in
`secondary_navigation`). Children take the same keys as other entries; one
level only.

```yaml
  main_navigation:
    finance:
      title: 'Finanzen'
      children:
        costs:       { title: 'Kosten', segment: 'costs', controller: 'App\Controllers\CostController' }
        controlling: { title: 'Controlling', segment: 'controlling', permission: 'ADMIN' }
```

The group is hidden when none of its children is visible, marked active when
one of them is, and shows the sum of the children's numeric badges unless it
has a `badge` of its own. Only one dropdown is open at a time; a click outside
or Escape closes it.

Entries are keyed, so a project can change or remove one by its key. The
entries are template globals (`$FrontdeskMainNavigation`,
`$FrontdeskSecondaryNavigation`) and work on every controller, not only on
FrontdeskController.

## Wide tables

From `md` up a list or sub-list table wider than the page scrolls
horizontally inside its card; the page itself does not scroll sideways. Row
action menus (`···`) are positioned fixed while open, so the scroll container
does not cut them off; scrolling closes them.

## Phones

Below `md` (768 px) list and sub-list tables become stacked cards (label before
each value, empty values hidden, actions in the top right corner), the filter
bar is behind a "Filtern" button, page actions wrap and dialogs use the full
width.

## Login, MFA and password pages

`templates/Security.ss` shows the Security controller (log in, lost and change
password, log out, permission denied) and the silverstripe/mfa screens
(register an authenticator with QR code, verify, backup codes, skip) in the
frontdesk look, without navigation. silverstripe/mfa requires
silverstripe/login-forms, whose theme would otherwise replace these pages;
the kit switches it back to the site's themes:

```yaml
Atwx\SilverstripeFrontdeskKit\Security\FrontdeskSecurityExtension:
  replace_login_forms: true          # false: keep the login-forms look
  silvergate_login_url: 'https://intra.atw.io'  # default; or FRONTDESK_SILVERGATE_URL in .env
  silvergate_login_path: '/websites/website-login?domain={host}'  # default
```

With atwx/silverstripe-gate-client installed, the login page shows "Über Silvergate anmelden" below the form.
It links straight to the manager's login for the site's domain,
e.g. `https://intra.atw.io/websites/website-login?domain=flow-demo.atw.cx`; the manager checks the
member's permission for the domain and logs them in. A `silvergate_login_url` containing `{host}` is used as is.

## Error pages

404, 403, 500 and other errors from `httpError()` of a controller are shown as
`templates/FrontdeskErrorPage.ss` for browser requests. Ajax, htmx and
requests that do not accept HTML (APIs) keep the plain response; the CMS is
excluded. The message of `httpError()` is shown in dev mode only.

```yaml
Atwx\SilverstripeFrontdeskKit\Security\FrontdeskErrorExtension:
  enabled: true
  excluded_controllers:
    - 'App\Controllers\ApiController'
```

## Overriding

- `Includes/FrontdeskAssets` (styles and scripts of the login and error pages):
  projects with their own build override it with their links, e.g.
  `app/templates/Atwx/SilverstripeFrontdeskKit/Includes/FrontdeskAssets.ss`.
- `Includes/FrontdeskBareHeader` (logo without navigation), `Security.ss`,
  `FrontdeskErrorPage.ss`, `Includes/FrontdeskHeader` and
  `Includes/FrontdeskNavEntry` like any template.
