import { Base, createApp } from '@studiometa/js-toolkit';
import { Counter } from './components/Counter.js';

class App extends Base {
  static config = {
    name: 'App',
    components: {
      Counter,
    },
  };
}

export default createApp(App);
