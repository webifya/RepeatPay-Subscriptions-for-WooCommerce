# Creating a WordPress.org submission ZIP

Do not submit GitHub's automatically generated **Source code** archives to WordPress.org. They contain development files that are intentionally kept in this repository.

Use one of these release workflows instead:

1. Open **Actions → Build WordPress.org release → Run workflow**. When it finishes, download the `subscribely-wordpress-org-*` artifact from the workflow run.
2. For a versioned release, ensure the plugin header, `WFS_VERSION`, and `Stable tag` match, then push a tag such as `v0.5.9`. The workflow creates a draft GitHub Release and attaches the clean WordPress.org ZIP. Review and publish the draft release from GitHub.

The generated ZIP contains only `LICENSE`, `readme.txt`, the main plugin file, and runtime PHP files from `includes/`. The workflow fails if hidden or development files enter the archive.
