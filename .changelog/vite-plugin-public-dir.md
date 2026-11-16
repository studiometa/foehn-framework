### Fixed

- `@studiometa/foehn-vite-plugin` sets `publicDir: false`. Vite took the `public/` directory of the project, which `studiometa/foehn-installer` copies to the document root, as its own public directory: it copied its files (`.user.ini`, `wordfence-waf.php`, `robots.txt`…) into the theme build, and its dev server served them. A project that sets `publicDir` keeps its value. Delete the copied files from the theme build directory, or build again from a clean directory
