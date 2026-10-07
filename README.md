# Blog Posts

## Purpose
The purpose of this extension is to pull data from a remote WordPress RESTful API and display it in a dedicated block.

## Requirements
- MediaWiki 1.39+
- PHP 8.1+

## Configuration

The following options are present in `extension.json`, under `config.BlogPostsConfig.value` -

| Name           | Type    | Default | Description
|----------------|---------|---------|-------------
| blogURL        | String  | null    | URL of the REST API to query. Fetched server-side only; never sent to the browser
| requestHeaders | Array   | []      | Extra HTTP headers for that fetch, as `name => value`
| morePostsUrl   | String  | null    | URL for the "more posts" link, as readers see it
| postsPerPage   | Integer | 4       | Number of posts to display per page
| initialPage    | Integer | 1       | The initial page number to start from

### Fetching through an internal address

`blogURL` is only ever fetched by the server, so it does not have to be the
blog's public address. When the public hostname sits behind an edge that
challenges plain HTTP clients, point `blogURL` at the blog's web server
directly and use `requestHeaders` to tell it which site is being asked for:

```php
$wgBlogPostsConfig['blogURL']        = 'http://blog-web-server/?rest_route=/wp/v2/posts/';
$wgBlogPostsConfig['requestHeaders'] = [
	'Host' => 'blog.example.org',
	'X-Forwarded-Proto' => 'https',
];
$wgBlogPostsConfig['morePostsUrl']   = 'https://blog.example.org';
```

Post links and image URLs in the response are unaffected: WordPress builds
them from its own `home`/`siteurl` settings, not from the request.

### When the fetch fails

The widget's container carries `data-blogposts-state`: `ok`, `empty` (the
blog answered with no posts) or `error` (the fetch failed). The reason for an
`error` is logged to the `BlogPosts` log channel, with the HTTP status. A page
rendered with a failed fetch is parser-cached for five minutes only, so it
recovers on its own once the blog answers again.

## Dependencies
This extension depends on the existence of FontAwesome for its icons.

## Changelog

### 0.1.0
- Add `requestHeaders`, so `blogURL` can address the blog's web server directly
- A failed fetch is told apart from an empty blog (`data-blogposts-state`),
  is logged with its HTTP status, and is parser-cached for five minutes only
- Stop publishing `blogURL` to client-side `mw.config`; no client code used it

### 0.0.1
- Modernized to use service injection and instance-based hook handlers
- Replaced `AutoloadClasses` with `AutoloadNamespaces` (PSR-4)
- Added hook interfaces (`ParserFirstCallInitHook`, `ResourceLoaderGetConfigVarsHook`)
- Replaced `global $wgBlogPostsConfig` with `MainConfig` service injection
- Moved PHP classes to `includes/` with `MediaWiki\Extension\BlogPosts` namespace
- Renamed `modules/` to `resources/`
- Updated to `manifest_version` 2
