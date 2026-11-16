### Fixed

- `wp foehn verify --output=<path>` resolves a relative path from the directory where the command runs. Before, it resolved it from `ABSPATH`, often `web/wp`, so `mkdir -p build && wp foehn verify --output=build/report.json` did not write the report in that `build` directory. A CI job that read the report under `ABSPATH` must read it from the working directory, or give an absolute path
