import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CopyLink } from '../../../theme/assets/js/components/CopyLink.js';

function createCopyLink(url) {
  const el = document.createElement('button');
  el.setAttribute('data-component', 'CopyLink');
  if (url) {
    el.setAttribute('data-option-url', url);
  }
  el.innerHTML = '<span data-ref="label">Copy link</span>';
  document.body.appendChild(el);
  return el;
}

describe('CopyLink', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('copies the URL from its option', async () => {
    const writeText = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
    const el = createCopyLink('https://foehn.test/projects/corridors/');
    const component = new CopyLink(el);
    await component.$mount();

    el.click();
    await vi.waitFor(() => expect(el.textContent).toBe('Link copied'));

    expect(writeText).toHaveBeenCalledWith('https://foehn.test/projects/corridors/');

    component.$destroy();
  });

  it('copies the page address when no URL is given', async () => {
    const writeText = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();
    const el = createCopyLink();
    const component = new CopyLink(el);
    await component.$mount();

    el.click();
    await vi.waitFor(() => expect(writeText).toHaveBeenCalledWith(window.location.href));

    component.$destroy();
  });

  it('keeps its label when the clipboard refuses', async () => {
    vi.spyOn(navigator.clipboard, 'writeText').mockRejectedValue(new Error('denied'));
    const el = createCopyLink('https://foehn.test/');
    const component = new CopyLink(el);
    await component.$mount();

    el.click();
    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(el.textContent).toBe('Copy link');

    component.$destroy();
  });
});
