/**
 * Contact sheet behaviour, on the Interactivity API.
 *
 * A script module loaded only on pages that render the block. The store answers the
 * directives the Twig template writes: the context of each sheet holds whether its
 * viewer is open and which photograph it shows, and the state derives what the viewer
 * prints from that context.
 */
import { getContext, getElement, store } from '@wordpress/interactivity';

const { actions } = store('theme/contact-sheet', {
  state: {
    get photograph() {
      const context = getContext();

      return context.photographs[context.current] ?? { src: '', alt: '' };
    },
    get position() {
      const context = getContext();

      return `${context.current + 1} / ${context.photographs.length}`;
    },
  },
  actions: {
    open() {
      const context = getContext();
      const { ref } = getElement();

      context.current = Number(ref.dataset.index);
      context.isOpen = true;
    },
    close() {
      getContext().isOpen = false;
    },
    previous() {
      const context = getContext();
      const total = context.photographs.length;

      context.current = (context.current - 1 + total) % total;
    },
    next() {
      const context = getContext();

      context.current = (context.current + 1) % context.photographs.length;
    },
    onKeydown(event) {
      const context = getContext();

      if (!context.isOpen) {
        return;
      }

      if (event.key === 'Escape') {
        context.isOpen = false;
      } else if (event.key === 'ArrowLeft') {
        actions.previous();
      } else if (event.key === 'ArrowRight') {
        actions.next();
      }
    },
  },
});
