<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach([route('home'), route('pricing'), route('ratings.index'), route('terms'), route('privacy')] as $url)
<url><loc>{{ $url }}</loc><changefreq>weekly</changefreq></url>
@endforeach
</urlset>
