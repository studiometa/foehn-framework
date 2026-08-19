/**
 * Callout block behaviour.
 *
 * Picked up by convention from assets/js/blocks/callout.js and registered as a
 * script module, so it is served with `type="module"`: `import` works here,
 * including bare specifiers such as `@wordpress/interactivity`, which WordPress
 * resolves through the import map it prints for registered modules.
 *
 * Loaded only on pages that render a callout, deferred, and in strict mode.
 * Plain JS outside the Vite pipeline on purpose, so the block behaves correctly
 * in a checkout that has never run `npm install`.
 */

/**
 * Remove the callout a dismiss button belongs to.
 *
 * Delegated from the document rather than bound per button: a module is deferred,
 * so it runs after the markup exists, but delegation also survives callouts that
 * arrive later from a query block or any other partial render.
 *
 * @param {MouseEvent} event The click that may have hit a dismiss button.
 */
function onClick(event) {
  const button = event.target.closest('.callout__dismiss');

  if (!button) {
    return;
  }

  button.closest('.callout')?.remove();
}

document.addEventListener('click', onClick);
