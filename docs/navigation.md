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

Entries are keyed, so a project can change or remove one by its key. The
entries are template globals (`$FrontdeskMainNavigation`,
`$FrontdeskSecondaryNavigation`) and work on every controller, not only on
FrontdeskController.

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
```

With atwx/silverstripe-gate-client installed, the login page shows "Über Silvergate anmelden" below the form.

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
