# Blog Posts

## Purpose
The purpose of this extension is to pull data from a remote WordPress RESTful API and display it in a dedicated block.

## Requirements
- MediaWiki 1.39+
- PHP 8.1+

## Configuration

The following options are present in `extension.json`, under `config.BlogPostsConfig.value` -

| Name         | Type    | Default | Description
|--------------|---------|---------|-------------
| blogURL      | String  | null    | url of the REST API to query
| morePostsUrl | String  | null    | URL for the "more posts" link
| postsPerPage | Integer | 4       | Number of posts to display per page
| initialPage  | Integer | 1       | The initial page number to start from

## Dependencies
This extension depends on the existence of FontAwesome for its icons.

## Changelog

### 0.0.1
- Modernized to use service injection and instance-based hook handlers
- Replaced `AutoloadClasses` with `AutoloadNamespaces` (PSR-4)
- Added hook interfaces (`ParserFirstCallInitHook`, `ResourceLoaderGetConfigVarsHook`)
- Replaced `global $wgBlogPostsConfig` with `MainConfig` service injection
- Moved PHP classes to `includes/` with `MediaWiki\Extension\BlogPosts` namespace
- Renamed `modules/` to `resources/`
- Updated to `manifest_version` 2
