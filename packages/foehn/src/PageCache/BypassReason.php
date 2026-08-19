<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * Why a request was not served from, or written to, the page cache.
 *
 * The value is what goes out as `X-Foehn-Cache-Reason`. A page cache whose decisions
 * cannot be read off the response is a page cache nobody can debug in production,
 * which is the state every "why is this page stale?" ticket starts from.
 */
enum BypassReason: string
{
    // Request shape — decided from the superglobals alone, before WordPress boots.
    case Disabled = 'disabled';
    case Environment = 'environment';
    case Method = 'method';
    case PostData = 'post-data';
    case Host = 'host';
    case Path = 'path';
    case QueryString = 'query-string';
    case Cookie = 'cookie';
    case ExcludedPath = 'excluded-path';
    case Maintenance = 'maintenance';
    case Cli = 'cli';
    case Ajax = 'ajax';
    case Cron = 'cron';
    case Rest = 'rest';
    case XmlRpc = 'xmlrpc';
    case DoNotCache = 'do-not-cache';

    // Context — needs WordPress, so only the writer can see these.
    case Admin = 'admin';
    case Feed = 'feed';
    case Trackback = 'trackback';
    case Robots = 'robots';
    case Embed = 'embed';
    case Preview = 'preview';
    case CustomizePreview = 'customize-preview';
    case Search = 'search';
    case LoggedIn = 'logged-in';
    case PasswordRequired = 'password-required';

    // Response and body.
    case Status = 'status';
    case ContentType = 'content-type';
    case Redirect = 'redirect';
    case BodyTooShort = 'body-too-short';
    case BodyIncomplete = 'body-incomplete';
    case BodyExcluded = 'body-excluded';

    // Read side only.
    case Expired = 'expired';
    case NotCached = 'not-cached';
}
