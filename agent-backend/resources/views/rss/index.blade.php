<?php echo '<?xml version="1.0" encoding="UTF-8"?>' ?>

<rss version="2.0">
    <channel>

        <title>{{ config('app.name') }}</title>

        <link>{{ config('app.frontend_url') }}</link>

        <description>
            Latest articles and affiliate recommendations from {{ config('app.name') }}.
        </description>

        <language>en-US</language>

        <generator>Laravel Dewdora API</generator>

        <lastBuildDate>
            {{ optional($posts->first()?->published_at)->toRfc2822String() }}
        </lastBuildDate>

        @foreach ($posts as $post)
            <item>

                <title><![CDATA[{{ $post->title }}]]></title>

                <link>
                    {{ config('app.frontend_url') }}/posts/{{ $post->slug }}
                </link>

                <guid isPermaLink="true">
                    {{ config('app.frontend_url') }}/posts/{{ $post->slug }}
                </guid>

                <description><![CDATA[
                    {{ $post->excerpt }}
                ]]></description>

                <pubDate>
                    {{ optional($post->published_at)->toRfc2822String() }}
                </pubDate>

            </item>
        @endforeach

    </channel>
</rss>