import { Base } from '@studiometa/js-toolkit';

export class Counter extends Base {
  static config = {
    name: 'Counter',
    refs: ['count', 'increment', 'decrement'],
    options: {
      step: { type: Number, default: 1 },
    },
  };

  #value = 0;

  mounted() {
    this.#render();
  }

  onIncrementClick() {
    this.#value += this.$options.step;
    this.#render();
  }

  onDecrementClick() {
    this.#value -= this.$options.step;
    this.#render();
  }

  #render() {
    this.$refs.count.textContent = String(this.#value);
  }
}
