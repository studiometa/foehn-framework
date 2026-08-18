/**
 * Callout block behaviour.
 *
 * Picked up by convention from assets/js/blocks/callout.js, so WordPress loads it
 * only on pages that render a callout. Plain JS, outside the Vite pipeline, for
 * the same reason as the block's stylesheet: the block works in a checkout that
 * has never run `npm install`.
 */
document.addEventListener('click', (event) => {
  const button = event.target.closest('.callout__dismiss');

  if (!button) {
    return;
  }

  const callout = button.closest('.callout');

  if (callout) {
    callout.remove();
  }
});
