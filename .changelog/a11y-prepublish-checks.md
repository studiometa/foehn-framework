### Added

- The block editor checks the accessibility of the content before publication. An **Accessibility** panel of the pre-publish sidebar lists the `core/image` blocks with no alternative text, the images of Føhn block attributes whose attachment has no alternative text, a second level 1 heading, and a jump in heading levels. Apply the new **Decorative** style to a `core/image` block that needs no alternative text. The issues are warnings; set `blockPublicationOnAccessibilityIssues: true` in `FoehnConfig` to refuse publication while they exist
- The `headingLevel` block attribute control: a select of the heading levels 1 to 6, stored as an integer. The pre-publish checks read it as the level of a heading the block renders. Give it to each heading level attribute of your blocks
- `studiometa/foehn-installer` copies the new `prepublish-checks.js` script into `web/wp-content/foehn/`. Run `composer install` to get it
