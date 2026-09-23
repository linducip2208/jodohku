<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($sections as $s)
<sitemap><loc>{{ $s['loc'] }}</loc><lastmod>{{ $s['updated'] }}</lastmod></sitemap>
@endforeach
</sitemapindex>
