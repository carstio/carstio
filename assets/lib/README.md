# Libraries kept in the repository

JavaScript dependencies are normally listed in `importmap.php` and
downloaded into `assets/vendor/` (not committed) by
`php bin/console importmap:install`.

That only works for libraries published as ES modules that load as a
single file. The few that are not are committed here, unmodified, each
with its licence, and are loaded with a plain `<script>` where they are
needed.

| Library | Version | File | Used by | Why it is here |
|---|---|---|---|---|
| [FullCalendar](https://fullcalendar.io) standard bundle (MIT) | 6.1.20 | `fullcalendar/fullcalendar.global.min.js` | `assets/controllers/calendar_controller.js` (dashboard) | As ES modules it is split over several files that share internal state; the import map downloads each of them as a separate bundle with its own copy of that state, which does not work. The "global" build is one self-contained file. |

## Updating one

1. Download the same file of the new version from the package's official
   release, for FullCalendar:
   `https://cdn.jsdelivr.net/npm/fullcalendar@<version>/index.global.min.js`
   (and `LICENSE.md` from the same place).
2. Replace the file here without editing it, update the version in the
   table above, and check the page that uses it.
