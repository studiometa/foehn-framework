### Changed

- Releases run from the `Release` workflow: it moves every package version and inter-package pin, folds the `.changelog/` fragments into `CHANGELOG.md`, commits, tags, then tests, publishes the Vite plugin, splits the packages, builds the runtime image, deploys the demo and creates the GitHub release. Pull requests add a fragment in `.changelog/` instead of editing `CHANGELOG.md`, and a `Changelog` check asks for one when `packages/` or `docker/` change
