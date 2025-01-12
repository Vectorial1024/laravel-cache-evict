# Change Log of `laravel-cache-evict`
Note: you may refer to `README.md` for description of features.

## Dev (WIP)

## 2.0.0 (2025-01-12)
- Adopted Laravel's `Number::fileSize()` to show the estimated storage size cleaned stats
  - Therefore, further requires `ext-intl`

## 1.0.3 (2025-01-07)
Special note: this update is made in response to the external rugpull as discovered in #4. All previous versions are "tainted" and will not be supported, effective immediately. Update your installed version now!!!
- No longer depends on `ramazancetinkaya/byte-formatter` as culprit of rugpull
  - A StackOverflow-copied solution is being used for now
  - A proper solution will be made later
- Added a changelog at `CHANGELOG.md`

## 1.0.2 (2024-10-29)
Hotfix: avoid `database` eviction race condition (38d70027b1778685a3c5ddffb4e10a9892bf4896); improve test case stability (2f7fecf581d5598231671ba5511e219aa94122b3)

## 1.0.1 (2024-10-29)
The v1.0.1 release of the library.
- Added the earlier-promised auto tests (#2)
- Reorganized the README
- Removed `package.json` (#2) to fix possible installation failures

## 1.0.0 (2024-10-27)
Initial release.

This is a utility library for Laravel that can efficiently remove many expired cache items in Laravel to prevent storage overload.
- Supports the `file` and `database` cache driver
- Supports self-defined cache eviction strategies
- Uses PHP generators to avoid using too much memory while scanning for expired items
