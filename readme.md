# gitsee: a minimalistic drop-in PHP git log parser and webinterface

## features

- reduced set of `git log` functions 
- parse git log messages within a repository
- filter different repository properties through an HTML frontend
- e.g. choose how many commits will be shown and since when
- run through the commit history
- show stats of changes
- replace identificators for ticket systems

## usage

just put the `gitsee.php` file within your repository, or place it anywhere where it is accessible through a PHP-enabled webserver and configure the repository path.

## requirements

- PHP 5.6 or higher
- installed git and permission to exec shell commands

## pending features

- save settings in a cookie
- show graph

## uses following technologies

- PHP
- RegExp
- GIT
- HTML5
- JQuery
- CSS3
- SVG
- Webfonts