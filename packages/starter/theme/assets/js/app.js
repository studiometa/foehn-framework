import { Base, createApp } from '@studiometa/js-toolkit';

/**
 * The theme's root component.
 *
 * Register your own components here; js-toolkit mounts each one against the
 * `data-component` attributes it finds in the markup.
 */
class App extends Base {
  static config = {
    name: 'App',
    components: {},
  };
}

export default createApp(App);
