### Fixed

- A hit from the page cache drop-in no longer sends the `responseHeaders`. The web server already adds its server-level headers to this PHP response, as to a page that PHP renders, so nginx sent each of these headers twice (for example `X-Frame-Options`). nginx hits still send them. An invalid `responseHeaders` value no longer stops the drop-in from serving hits: only `wp foehn cache:config` refuses it
