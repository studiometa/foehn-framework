### Fixed

- A container template that prints `content` twice mounts its inner blocks once in the editor, at the first place, and the editor warns in the console. It mounted two `InnerBlocks` in one block, which WordPress does not support.
