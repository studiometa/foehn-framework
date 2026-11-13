import { Base } from '@studiometa/js-toolkit';

/**
 * A button that copies the address of the page to the clipboard.
 *
 * Mounted by the loader in app.js wherever a template emits
 * `data-component="CopyLink"`, which is the point of keeping it here: the theme's own
 * components are found by file name, with no import and no registration.
 *
 * The address is an option rather than `location.href`, so a cached page or a page
 * reached through a section request still copies its canonical URL.
 */
export class CopyLink extends Base {
  static config = {
    name: 'CopyLink',
    refs: ['label'],
    options: {
      url: String,
      copied: { type: String, default: 'Link copied' },
    },
  };

  async onClick() {
    try {
      await navigator.clipboard.writeText(this.$options.url || window.location.href);
    } catch {
      // No clipboard (an insecure origin, a refused permission): the label stays as
      // it is, so the button does not claim a copy that did not happen.
      return;
    }

    this.$refs.label.textContent = this.$options.copied;
  }
}
