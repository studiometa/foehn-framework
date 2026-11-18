### Added

- The starter ships a `renovate.json`. It extends the shared preset `github>studiometa/renovate-config` (3-day release age, automatic merge when the checks pass, dependency dashboard), removes the wait for security updates, and updates the Føhn packages together. A new project gets it with `composer create-project`; an existing project can copy it from the starter and install the Renovate GitHub App.
