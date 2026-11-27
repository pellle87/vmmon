# Publishing a new version

1. Update the code in `vmmon/`. Bump `VMMON_VERSION` in `vmmon/config.php` (every change +0.01).
2. Keep `vmmon/sql/schema.sql` up to date and keep **only** the newest migration file (`vmmon/sql/<version>.sql`) next to it.
   The migration must be safe to run twice (guarded `ALTER`s).
3. Add a line `v<version>: what changed` to `vmmon/README.txt` – it is shown as release notes and in the updater.
4. Commit and push. Then either run **Actions → Release → Run workflow**, or tag:

   ```bash
   git tag v2.96 && git push origin v2.96
   ```

   The workflow checks that the tag equals `VMMON_VERSION`, builds `Vmmon_2.96.zip` and `vmmon.zip` and creates the release.
   Without GitHub Actions: `./make-release.sh`, then `gh release create v2.96 Vmmon_2.96.zip --notes "…"`.

The in-app updater picks the first release asset named `Vmmon_*.zip`. The release must contain the folder `vmmon/` at the top of the zip.

## Rough usage indication

GitHub shows the download count per release asset (Releases page, or `gh api repos/pellle87/vmmon/releases`) – a rough sign of how many
install or update from here. Stars, forks and the Traffic tab (Insights) give a little more.
