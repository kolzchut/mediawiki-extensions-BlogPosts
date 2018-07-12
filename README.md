# Blog Posts

## Purpose
The purpose of this extension is to pull data from a remote WordPress RESTful API and display it in a dedicated block.

## Configuration

The following options are present in `extension.json`, under `config.BlogPostsConfig` -

| Name | Type | Default | Description |
|-----------|-------------|------|----|
| blogURL | String | null | url of the REST API to query 
| postsPerPage | Integer | 4 | Number of posts to display per page 
| initialPage | Integer | 1 | The initial page number to start from 