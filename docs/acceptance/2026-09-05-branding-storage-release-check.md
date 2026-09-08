# Public upload storage release check

## Incident and verified cause

The production logo PNG and PNG-compressed ICO were successfully written under
`storage/app/public/branding`, but `/storage/branding/...` returned HTTP 404.
`public/storage` was missing. Localhost already had the equivalent working
Windows junction. No branding setting or image regeneration was required.

On Hostinger, `php artisan storage:link` failed because the PHP runtime could
not call `exec`. After verifying the destination was neither an existing file
nor a symlink, the approved repair created this link using SSH:

```sh
ln -s /home/u762348670/apps/jwecommerce/storage/app/public /home/u762348670/apps/jwecommerce/public/storage
```

Both existing branding URLs then returned HTTP 200 with `image/png` and
`image/x-icon` respectively. The uploaded files and database settings were
preserved. This is an environment repair, not an application-code deployment.

## Required release smoke check

1. Verify `public/storage` resolves to the application's `storage/app/public`.
2. Never overwrite an existing directory or unexpected symlink. Inspect and
   resolve any mismatch before changing it.
3. If missing, use Laravel's storage link command. If hosting restrictions
   prevent it, use the validated SSH symlink operation above only on the
   approved application path; do not relax PHP security settings.
4. Check a real uploaded public image and the current favicon over HTTPS.
   File existence and upload success alone are insufficient acceptance.
5. Check rendered logo natural dimensions and the favicon link in the browser.
   Do not replace customer branding to perform the smoke check.

The public link exposes only the configured public disk, never private proofs,
credentials or private review attachments.
