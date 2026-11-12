### Added

- `PageCacheConfig::$storage`: with `PageCacheStorage::Bucket`, the page cache stores pages in the `humanmade/s3-uploads` bucket under `cache/pages/`, and the nginx snippet from `wp foehn cache:config --server=nginx` serves hits from the bucket. All instances of a site then read and purge one cache. Use it only on a site with more than one instance: hits are slower, only nginx serves them, the bucket must allow anonymous reads, and each deploy must run `wp foehn cache:clear`.
