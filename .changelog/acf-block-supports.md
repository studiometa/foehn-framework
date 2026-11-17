### Fixed

- The block supports of an ACF block now reach the front end: the alignment, the colors, the spacing, the typography, the border, the anchor and the custom class render on the first tag of the template, as they do in the editor preview. A template that calls `get_block_wrapper_attributes()` or prints these styles itself now gets them twice: remove them from the template.
