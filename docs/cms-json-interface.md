# Nuxt CMS interface

This transport is opt-in. Existing Blade pages, public device APIs, payloads and
database schemas are unchanged. Nuxt runs separately and reads the CMS over HTTP.

## Session and request headers

`GET /cms-api/session` returns `{api_version: 1, csrf_token, user}` and sets the
normal Laravel session cookie. `user` is null before login; otherwise it contains
id, name, email, role, role_name, superadmin and effective permissions.

For JSON admin requests send `Accept: application/json` and
`X-CMS-Interface: nuxt`. Keep the session cookie between requests. Mutations also
require `X-CSRF-TOKEN` from the session bootstrap response. Login uses existing
`POST /login` with email and password; refresh the session bootstrap after login.
Logout uses existing `POST /logout`. Never use integration bearer tokens for CMS
administration. Normal authentication, rate limiting and RBAC still apply.

## Responses

Existing admin GET routes return `{data: <existing controller view data>,
meta: {view, api_version: 1}}`. Paginated fields contain
`{data, current_page, last_page, per_page, total}`. Product objects use
ProductResource, translated master attributes and independent AI/tablet flags.
Raw PIM payloads and credential fields are omitted. Device activation codes are
only included on the existing, edit-permission-protected device edit endpoints.

`GET /admin/fit-and-go?product_summary=1` returns compact product cards in both
`aiProducts` and `productGroups.*.products`: id, sku, name, image, stock,
variants_count and presentation_configured. Grouping, newest-first order, floor
selection and configuration status are unchanged. The presentation endpoint
still returns the complete color/photo/description configuration on demand.
Without this query parameter, existing JSON product fields remain available.
The parameter has no effect on other modules or public device APIs.

Successful form redirects become `{success: true, message, errors: [], redirect}`
with status 200. Form validation remains 422 with `errors`. Authentication,
permission and lock failures retain their HTTP status. CSV exports retain their
existing content type and permission checks. Without the opt-in header the
existing responses, including redirects, remain unchanged.

The Nuxt gateway pins the CMS origin, accepts only known module paths, forwards
session/CSRF and streams uploads. It refuses cross-origin mutations. Responses
are not cached; data is kept only in current frontend memory. Do not add a local
catalog database, browser storage or copied product media.

## Verification

Run `php artisan test --compact`. `CmsJsonResponseTest` covers all module GETs,
nested product variants, channel flags, login/session metadata, permission and
lock rejection, no credential fields, and unchanged Blade rendering.
