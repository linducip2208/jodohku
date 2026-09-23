@props(['image' => null, 'type' => 'website', 'schemas' => [], 'publishedAt' => null, 'updatedAt' => null])
@php
$seo = app(\App\Services\SeoService::class);
$meta = $seo->meta([
  'title' => trim($__env->yieldContent('title')),
  'description' => trim($__env->yieldContent('meta_description')),
  'canonical' => trim($__env->yieldContent('canonical')) ?: null,
  'robots' => trim($__env->yieldContent('robots')) ?: null,
  'image' => $image,
  'og_type' => $type,
]);
$allSchemas = array_values(array_filter(is_array($schemas) ? $schemas : []));
@endphp
<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
@if($meta['keywords'])<meta name="keywords" content="{{ $meta['keywords'] }}">@endif
<link rel="canonical" href="{{ $meta['canonical'] }}">
<meta name="robots" content="{{ $meta['robots'] }}">
<meta property="og:type" content="{{ $meta['og_type'] }}">
<meta property="og:site_name" content="{{ $seo->siteName() }}">
<meta property="og:title" content="{{ $meta['og_title'] }}">
<meta property="og:description" content="{{ $meta['og_description'] }}">
<meta property="og:image" content="{{ $meta['og_image'] }}">
<meta property="og:url" content="{{ $meta['og_url'] }}">
<meta property="og:locale" content="{{ $meta['locale'] }}">
<meta name="twitter:card" content="{{ $meta['twitter_card'] }}">
<meta name="twitter:title" content="{{ $meta['twitter_title'] }}">
<meta name="twitter:description" content="{{ $meta['twitter_description'] }}">
<meta name="twitter:image" content="{{ $meta['twitter_image'] }}">
@if($meta['twitter_handle'])<meta name="twitter:site" content="{{ $meta['twitter_handle'] }}">@endif
@php
$google = $seo->site('google_site_verification', '');
$bing = $seo->site('bing_site_verification', '');
@endphp
@if($google)<meta name="google-site-verification" content="{{ $google }}">@endif
@if($bing)<meta name="msvalidate.01" content="{{ $bing }}">@endif
@foreach($allSchemas as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
