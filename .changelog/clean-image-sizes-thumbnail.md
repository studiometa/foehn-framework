### Changed

- **Breaking:** `CleanImageSizes` keeps `thumbnail` as the only core image size WordPress writes on upload. It also removes `medium` and `large`, so a theme that prints these sizes without an image transformer gets the full image. The sizes a theme adds stay
