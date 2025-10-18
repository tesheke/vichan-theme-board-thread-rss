# how to update
- mod.php?/themes
- select 'board thread rss'
- 'Reconfigure'
- 'Install theme'
- mod.php?/rebuild
- do 'Rebuild' to update template cache

# not implemented
- remove the thread rss xml
- remove the board rss xml

# how to link to rss
add the following to vichan/templates/thread.html, index.html, etc.
```
<link rel="alternate" type="application/rss+xml" title="thread RSS" href="{{ config.domain }}{{ config['root'] }}{{ board.uri }}/{{ config.dir.res }}{{ thread.id }}_rss20.xml" />
```
```
<link rel="alternate" type="application/rss+xml" title="board RSS" href="{{ config.domain }}{{ config['root'] }}{{board.uri}}/recent_rss20.xml" />
```

