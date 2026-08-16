# RatTube Plugin Checklist

Status snapshot based on the codebase as of 2026-08-16. Covers everything built beyond the original foundation spec (`wordpress-plugin-foundation-prompt.md`) plus what's still outstanding.

## Done

### Plugin scaffold
- [x] Plugin header, text domain, constants, file/folder structure
- [x] Class-based bootstrap (`RATTube_Plugin`) wiring all components on `plugins_loaded`
- [x] Activation: registers CPT, grants capabilities, creates converter page, flushes rewrites
- [x] Deactivation: flushes rewrites
- [x] Uninstall: deletes plugin options always; deletes Rat Media posts + converter page only if "Delete Data On Uninstall" setting is enabled

### Rat Media CPT
- [x] `rat_media` post type with labels, REST support, custom capability map (`map_meta_cap`)
- [x] Post meta: source URL, output format, status, file attachment ID, requested/resolved name, output basename, queue state, worker message — all with sanitize + auth callbacks
- [x] Frontend/archive access to `rat_media` singular and archive blocked (404) for everyone
- [x] Admin list table "View" row action, meta box showing status/format/player/download link

### Frontend converter page
- [x] Auto-created page at `/rat-media-convert` via `[rattube_converter]` shortcode
- [x] Nonce-protected form (source URL, optional name, format select)
- [x] Sanitization + validation on submit, creates a `rat_media` post with meta
- [x] Redirect-with-notice pattern for success/error messages
- [x] Access currently restricted to logged-in `manage_options` users only

### Conversion pipeline
- [x] Async processing via WP-Cron (`rattube_process_submission_event`, scheduled ~5s after submission + `spawn_cron()`)
- [x] MP3 extraction via yt-dlp + ffmpeg (`proc_open`, array-form command, `escapeshellarg` on user-influenced values)
- [x] Output moved into `uploads/rattube-outputs`, attached as a WP attachment with generated metadata
- [x] Failure states captured in `_rattube_status` / `_rattube_worker_message`, logged via `rattube_add_admin_log()`
- [x] yt-dlp invoked with an auto-detected `--js-runtimes` argument (node, falling back to deno) so YouTube's signature/PO-token challenge doesn't cause a 403 when deno isn't installed
- [x] `wp-admin/includes/media.php` is loaded before `wp_generate_attachment_metadata()` — previously missing, which crashed the cron worker with a fatal error right after a successful download/move, leaving the post stuck at "processing" with no attachment

### Tools admin page (Rat Media → RatTube Tools)
- [x] One-click install/update for yt-dlp (GitHub release) and ffmpeg (johnvansickle static build, Linux only)
- [x] Manual path override fields for yt-dlp/ffmpeg, validated before saving
- [x] Binary resolution order: manual override → plugin-installed local binary → system `PATH`
- [x] Diagnostics table (proc_open availability, disabled functions, resolved paths, detected versions, last log entry)
- [x] One-click conversion test button (runs the worker against the most recent MP3 submission)

### Admin UX / settings
- [x] Settings → RatTube page: default output format, worker placeholder toggle, delete-on-uninstall toggle
- [x] Submission warning/error log table on the settings page
- [x] Admin menu shortcut ("Frontend Converter") that opens the public page in a new tab

### Extensibility
- [x] `rattube_submission_created` / `rattube_after_submission_prepared` actions
- [x] `rattube_allowed_output_formats` / `rattube_converter_slug` filters
- [x] readme.txt documents install steps and extension hooks

## To Do

### Frontend UX
- [ ] Update template copy in `templates/frontend-converter-page.php` — still says "Conversion is not enabled in this foundation release," which is no longer true
- [ ] Give the submitter a way to see progress/completion and download their file from the frontend (today the player/download only exists in wp-admin)
- [ ] Consider AJAX/REST status polling instead of a static post-redirect message

### Format support
- [ ] The format dropdown offers MP3/MP4/WAV but the worker only supports MP3 (`process_submission()` hard-fails anything else) — either implement MP4/WAV conversion or restrict the dropdown/settings default to MP3 until they're built

### Access control
- [ ] Decide who besides administrators should be able to submit (custom role/capability vs. broader logged-in access vs. staying admin-only) — submissions trigger real shell commands against user-supplied URLs, so this is a deliberate security decision, not just a UI toggle
- [ ] Once opened beyond admins, add rate limiting / duplicate-submission protection (nothing currently stops spamming the form with cron jobs)

### Output file exposure
- [ ] Converted MP3s are normal WP attachments in `uploads/rattube-outputs` — their URLs are publicly guessable/accessible even though the `rat_media` post itself is blocked on the frontend. Decide whether that's acceptable or needs access-controlled delivery (e.g. signed URLs, auth-gated download endpoint)

### Reliability
- [ ] No retry logic for transient failures (network blips, temporary yt-dlp errors)
- [ ] No timeout enforcement around long-running yt-dlp/ffmpeg `proc_open` calls
- [ ] No locking against a post being reprocessed concurrently (e.g. resubmission while already queued/processing)
- [ ] No cleanup/expiration policy for old files in `uploads/rattube-outputs` or old `rat_media` posts — storage will grow unbounded
- [ ] An uncaught PHP error/fatal anywhere in `process_submission()` leaves the post stuck at `processing` forever with no way to detect or recover it from the UI (this is exactly what happened with the missing `media.php` require) — worth wrapping the run in a try/catch (or a shutdown-function check) that marks the post failed instead of silently dying

### Tools installer hardening
- [ ] No checksum/signature verification on downloaded yt-dlp or ffmpeg binaries
- [ ] Automated ffmpeg install only supports Linux; no macOS/Windows path (manual override still works there)
- [ ] Extraction depends on `tar`/`xz` being available to PHP — no fallback bundling

### Testing & tooling
- [ ] `tests/` directory is an empty placeholder — no PHPUnit (or other) test suite exists yet
- [ ] No PHPCS/WPCS config checked in to enforce the coding standards the spec calls for
- [ ] No CI workflow for lint/tests
- [ ] `languages/` has no compiled `.pot`/`.mo` files yet

### Admin UX gaps
- [ ] No bulk "retry conversion" action from the Rat Media list table (only the Tools page can re-run, and only against the single most recent MP3 post)
- [ ] No admin filter/search by status (`submitted`/`queued`/`processing`/`completed`/`failed`) in the Rat Media list table
