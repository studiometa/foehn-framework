import { describe, it, expect, beforeEach } from 'vitest';
import { Counter } from '../../../theme/assets/js/components/Counter.js';

function createCounter(step) {
  const el = document.createElement('div');
  el.setAttribute('data-component', 'Counter');
  if (step) {
    el.setAttribute('data-option-step', String(step));
  }
  el.innerHTML = `
    <span data-ref="count">0</span>
    <button data-ref="increment">+</button>
    <button data-ref="decrement">-</button>
  `;
  document.body.appendChild(el);
  return el;
}

describe('Counter', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('displays 0 on mount', async () => {
    const el = createCounter();
    const counter = new Counter(el);
    await counter.$mount();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('0');

    counter.$destroy();
  });

  it('increments on click', async () => {
    const el = createCounter();
    const counter = new Counter(el);
    await counter.$mount();

    el.querySelector('[data-ref="increment"]').click();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('1');

    counter.$destroy();
  });

  it('decrements on click', async () => {
    const el = createCounter();
    const counter = new Counter(el);
    await counter.$mount();

    el.querySelector('[data-ref="decrement"]').click();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('-1');

    counter.$destroy();
  });

  it('supports custom step option', async () => {
    const el = createCounter(5);
    const counter = new Counter(el);
    await counter.$mount();

    el.querySelector('[data-ref="increment"]').click();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('5');

    el.querySelector('[data-ref="increment"]').click();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('10');

    counter.$destroy();
  });

  it('increments and decrements correctly', async () => {
    const el = createCounter();
    const counter = new Counter(el);
    await counter.$mount();

    el.querySelector('[data-ref="increment"]').click();
    el.querySelector('[data-ref="increment"]').click();
    el.querySelector('[data-ref="decrement"]').click();

    expect(el.querySelector('[data-ref="count"]').textContent).toBe('1');

    counter.$destroy();
  });
});
