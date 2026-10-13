### Changed

- Each merge into `main` releases with the `Release` workflow: it folds the `.changelog/` fragments into `CHANGELOG.md` with the version that they give, moves every package version and inter-package pin, commits, tags and creates the GitHub release, then publishes the Vite plugin, splits the packages, builds the runtime image and deploys the demo. Pull requests add a fragment in `.changelog/` instead of editing `CHANGELOG.md`, and a `Changelog` check asks for one when `packages/` or `docker/` change
