<?php

/**
 * The page shown during maintenance (`wp maintenance-mode activate`, or an update).
 *
 * The drop-in the installer writes, `web/wp-content/maintenance.php`, prints this
 * file with a 503 and Retry-After. WordPress, Twig and the database are not loaded
 * at that point, so it is HTML with its styles inline.
 */

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    <title>Føhn — Back shortly</title>
    <style>
      body {
        margin: 0;
        min-height: 100vh;
        display: grid;
        place-items: center;
        font: 1.125rem/1.6 Georgia, 'Times New Roman', serif;
        color: #111;
        background: #f4f4f4;
      }

      main {
        max-width: 34rem;
        padding: 2rem;
      }

      p:first-child {
        font: 600 0.75rem/1 system-ui, sans-serif;
        letter-spacing: 0.12em;
        text-transform: uppercase;
      }

      h1 {
        font-size: 2.5rem;
        font-weight: 400;
        line-height: 1.1;
        margin: 1rem 0;
      }
    </style>
  </head>
  <body>
    <main>
      <p>Føhn</p>
      <h1>The studio is hanging new prints.</h1>
      <p>The site is being updated. Come back in a minute.</p>
    </main>
  </body>
</html>
