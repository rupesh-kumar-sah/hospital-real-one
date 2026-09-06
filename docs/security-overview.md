# MediCare HMS security overview

MediCare HMS uses layered access controls for administrative operations:

- **Hidden endpoint:** the physical administrator directory is `mgmt-x7k2p` by default and is selected with the `ADMIN_PATH` environment variable. It is not linked from the public portal, patient pages, staff pages, JavaScript, `robots.txt`, or a sitemap.
- **Network-level Cloudflare Access gate:** Cloudflare Access can require a verified email one-time code before requests reach the configured admin path.
- **IP restriction:** the application checks `ADMIN_ALLOWED_IPS` and returns a generic HTTP 404 for mismatches. Forwarded client IP headers are accepted only when the connecting proxy matches `TRUSTED_PROXY_IPS`.
- **Two-factor authentication:** administrators enroll RFC 6238 TOTP with an authenticator application. Backup codes are stored as password hashes.
- **Device verification:** successful password plus MFA verification registers a Secure, HttpOnly, SameSite=Strict browser cookie. A missing, expired, or revoked cookie requires fresh MFA and creates a new-device audit event. Administrators can revoke devices from the Registered Devices page.
- **Password lifecycle:** administrator-provisioned accounts receive random temporary passwords and must change them before dashboard access.

These controls reduce exposure but do not guarantee that an application is impossible to compromise. Keep the allowlists narrow, rotate secrets, apply database migrations, and review audit logs.

## Cloudflare free-plan setup

Cloudflare configuration is account-level and cannot be completed by PHP code. Configure it as follows:

1. Add the production domain to Cloudflare and select the Free plan.
2. Change the registrar nameservers to the two nameservers Cloudflare assigns.
3. Add the backend hostname as a proxied (` orange cloud `) DNS record pointing at the Render custom domain. Keep the Render hostname private in application documentation where possible.
4. In **Security > Bots**, enable the available free bot protection/bot fight setting.
5. In **Security > WAF**, enable the managed free ruleset and add rate limits for authentication endpoints.
6. In **Zero Trust > Access > Applications**, create a **Self-hosted** application for exactly `https://YOUR_DOMAIN/<ADMIN_PATH>/*`. Do not include `/patient/*`, `/doctor/*`, `/nurse/*`, `/pharmacy/*`, `/lab/*`, `/receptionist/*`, or `/api/*`.
7. Add an Access policy requiring the hospital administrator email addresses and enable one-time PIN/email verification.
8. In Render, set `TRUSTED_PROXY_IPS` to the documented Cloudflare/Render proxy ranges used by the deployment. Never trust arbitrary `X-Forwarded-For` values.
9. Set `ADMIN_ALLOWED_IPS` to the hospital's real egress IP or CIDR ranges. Production requests fail closed when this variable is empty.
10. Confirm Cloudflare SSL/TLS is **Full (strict)** and enforce HTTPS redirects at Cloudflare and Render.

Cloudflare Access status must be verified in the Cloudflare dashboard and by an external request: the request should be rejected by Cloudflare before PHP access logs show an application request.
