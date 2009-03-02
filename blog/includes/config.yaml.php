<?php header("Status: 403"); exit("Access denied."); ?>
---
database: 
  host: localhost
  username: hopper
  password: pnkrcksql
  database: hopper
  prefix: 
  adapter: mysql
name: The Hopper Deck
description: 
url: http://thehopperdeck.com/blog
chyrp_url: http://thehopperdeck.com/blog
email: alex@thehopperdeck.com
locale: en_US
theme: stardust
posts_per_page: 5
feed_items: 20
clean_urls: true
post_url: (year)/(month)/(day)/(url)/
timezone: America/Anguilla
can_register: false
default_group: 2
guest_group: 5
enable_trackbacking: true
send_pingbacks: false
enable_xmlrpc: true
secure_hashkey: 0ba2d97b5dbbb9003af55cd6e4b62bf6
uploads_path: /uploads/
enabled_modules: 
  - cacher
  - comments
  - markdown
  - read_more
  - smartypants
enabled_feathers: 
  - text
  - audio
  - chat
  - link
  - photo
  - quote
  - video
routes: 
cache_expire: 1800
default_comment_status: denied
allowed_comment_html: 
  - strong
  - em
  - blockquote
  - code
  - pre
  - a
comments_per_page: 25
defensio_api_key: 
auto_reload_comments: 30
enable_reload_comments: false
