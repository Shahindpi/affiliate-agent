<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Homepage -->

    <url>
        <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
        <lastmod>{{ optional($lastModified)->toAtomString() }}</lastmod>
    </url>

    <!-- Published Posts -->

    @foreach ($posts as $post)
        <url>
            <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}/posts/{{ $post->slug }}</loc>

            <lastmod>{{ optional($post->updated_at)->toAtomString() }}</lastmod>

            <changefreq>weekly</changefreq>

            <priority>0.9</priority>
        </url>
    @endforeach

    <!-- Categories -->

    @foreach ($categories as $category)
        <url>
            <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}/categories/{{ $category->slug }}</loc>

            <lastmod>{{ optional($category->updated_at)->toAtomString() }}</lastmod>

            <changefreq>weekly</changefreq>

            <priority>0.8</priority>
        </url>
    @endforeach

    <!-- Tags -->

    @foreach ($tags as $tag)
        <url>
            <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}/tags/{{ $tag->slug }}</loc>

            <lastmod>{{ optional($tag->updated_at)->toAtomString() }}</lastmod>

            <changefreq>weekly</changefreq>

            <priority>0.7</priority>
        </url>
    @endforeach

    <!-- Affiliate Products -->

    @foreach ($products as $product)
        <url>
            <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}/products/{{ $product->slug }}</loc>

            <lastmod>{{ optional($product->updated_at)->toAtomString() }}</lastmod>

            <changefreq>weekly</changefreq>

            <priority>0.8</priority>
        </url>
    @endforeach

    <!-- Brands -->

    @foreach ($brands as $brand)
        <url>
            <loc>{{ config('app.frontend_url', 'http://localhost:3000') }}/brands/{{ $brand->slug }}</loc>

            <lastmod>{{ optional($brand->updated_at)->toAtomString() }}</lastmod>

            <changefreq>monthly</changefreq>

            <priority>0.6</priority>
        </url>
    @endforeach

</urlset>