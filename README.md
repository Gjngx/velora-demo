# velora-demo

## Sync plugins from WordPress

The [plugin sync workflow](.github/workflows/sync-plugins.yml) checks the FTP server each hour and opens or updates a pull request when plugins are installed, updated, or deleted on WordPress. It uses the same FTP secrets as deployment. The first merged PR records a baseline in `.github/plugin-sync-state.json`; later PRs only delete files that were in that merged baseline. An empty FTP response cannot trigger deletion.

Plugin settings in the WordPress database are not included. In repository **Settings → Actions → General → Workflow permissions**, enable **Allow GitHub Actions to create and approve pull requests** before using the workflow.
