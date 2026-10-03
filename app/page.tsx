import { routes } from "@/lib/routes";
import { pageMetadata, JsonLd, siteOrigin } from "@/lib/seo";
import { AffiliateCarousel } from "@/components/public/affiliate-carousel";
import { LatestProducts } from "@/components/public/latest-products";
import { HeroBannerSection, type HeroBanner } from "@/components/public/hero-banner";
import Link from "next/link";
import { NewsletterForm } from "@/components/public/forms";
import { SiteShell } from "@/components/public/site-shell";
import { RetryHomepage } from "@/components/public/retry-homepage";
import { unstable_rethrow } from "next/navigation";
import { PostCard, ProductCard } from "@/components/public/cards";
import { safePublicGet, PublicApiError, type PublicPost, type PublicProduct, type PublicCategory } from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";

export const metadata = pageMetadata("/", "Product discovery and buying guides");
const homepageSectionKeys = ["latest_products", "popular_products", "hero_banner", "featured_categories", "featured_products", "featured_brands", "latest_reviews", "buying_guides", "popular_posts", "newsletter"] as const;
type Section = typeof homepageSectionKeys[number];
type Home = {
  latest_products: PublicProduct[]; popular_products: PublicProduct[]; hero_banners: HeroBanner[];
  hero_products: PublicProduct[]; popular_posts: PublicPost[]; latest_posts: PublicPost[];
  featured_categories: PublicCategory[];
  featured_brands: { id: number; name: string; slug: string; logo?: string | null }[];
  homepage_sections: Record<Section, boolean>;
};
const sections = Object.fromEntries(homepageSectionKeys.map(key => [key, true])) as Record<Section, boolean>;
export default async function HomePage() {
  let response: ApiResponse<Partial<Home>>;
  try {
    response = await safePublicGet<ApiResponse<Partial<Home>>>("homepage", { data: {}, success: false });
  } catch (error) {
    unstable_rethrow(error);
    // An upstream failure should be visible without exposing Laravel's exception details.
    console.error("Dewdora homepage API failed", error);
    return <SiteShell><section role="alert" className="rounded-2xl border border-[#dce6d9] bg-white p-8"><h1 className="text-3xl font-bold">Homepage temporarily unavailable</h1><p className="mt-3 text-[#567069]">We could not load the latest products and articles. Please try again shortly.</p><RetryHomepage />{error instanceof PublicApiError && error.status === 500 && <p className="mt-5 text-sm text-[#567069]">Site administrators: check the Laravel log and pending database migrations.</p>}</section></SiteShell>;
  }
  const source = response.data || {};
  const list = <T,>(value: T[] | undefined): T[] => Array.isArray(value) ? value : [];
  const data: Home = {
    latest_products: list(source.latest_products), popular_products: list(source.popular_products),
    hero_banners: list(source.hero_banners), hero_products: list(source.hero_products),
    popular_posts: list(source.popular_posts), latest_posts: list(source.latest_posts),
    featured_categories: list(source.featured_categories), featured_brands: list(source.featured_brands),
    homepage_sections: { ...sections, ...source.homepage_sections },
  };
  const reviews = data.latest_posts.filter(post => post.post_type === "review");
  const guides = data.latest_posts.filter(post => post.post_type === "tutorial" || post.post_type === "article");
  const visible = data.homepage_sections;
  return <SiteShell>
    <JsonLd data={{ "@context": "https://schema.org", "@type": "WebSite", name: "Dewdora", url: siteOrigin }} />
    <JsonLd data={{ "@context": "https://schema.org", "@type": "Organization", name: "Dewdora", url: siteOrigin, logo: `${siteOrigin}/dewdora-logo.svg` }} />
    {visible.latest_products && <LatestProducts products={data.latest_products} />}
    {visible.popular_products && <div className="mt-14"><AffiliateCarousel products={data.popular_products} /></div>}
    {visible.hero_banner && <HeroBannerSection banner={data.hero_banners[0]} />}
    {visible.featured_categories && data.featured_categories.length > 0 && <section className="mt-14"><h2 className="text-2xl font-bold">Explore product categories</h2><div className="mt-5 flex flex-wrap gap-3">{data.featured_categories.map(c => <Link key={c.id} href={routes.categories.show(c.slug)} className="rounded-full bg-white px-5 py-3 font-semibold shadow-sm hover:text-[#2c9873]">{c.name}</Link>)}</div></section>}
    {visible.featured_products && <section className="mt-14"><div className="flex justify-between gap-3"><div><p className="text-sm font-bold uppercase tracking-widest text-[#2c9873]">Selected tools</p><h2 className="mt-2 text-3xl font-bold">Featured products</h2></div><Link href={routes.products.index} className="font-semibold text-[#165e46]">View all →</Link></div><div className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-4">{data.hero_products.map(product => <ProductCard key={product.id} product={product} placement="homepage_featured" />)}</div>{data.hero_products.length === 0 && <p className="mt-6 text-[#567069]">Products will appear here when published.</p>}</section>}
    {visible.featured_brands && data.featured_brands.length > 0 && <section className="mt-14"><h2 className="text-3xl font-bold">Featured brands</h2><div className="mt-6 grid gap-3 sm:grid-cols-2 md:grid-cols-4">{data.featured_brands.map(brand => <Link key={brand.id} href={routes.brands.show(brand.slug)} className="rounded-xl border bg-white p-5 text-center font-bold hover:border-[#2c9873]">{brand.name}</Link>)}</div></section>}
    {visible.latest_reviews && reviews.length > 0 && <section className="mt-14"><h2 className="text-3xl font-bold">Latest reviews</h2><div className="mt-6 grid gap-6 md:grid-cols-3">{reviews.map(post => <PostCard key={post.id} post={post} />)}</div></section>}
    {visible.buying_guides && guides.length > 0 && <section className="mt-14"><h2 className="text-3xl font-bold">Buying guides &amp; how-tos</h2><div className="mt-6 grid gap-6 md:grid-cols-3">{guides.map(post => <PostCard key={post.id} post={post} />)}</div></section>}
    {visible.popular_posts && <section className="mt-14"><div className="flex justify-between"><h2 className="text-3xl font-bold">Popular reads</h2><Link href={routes.posts.index} className="font-semibold text-[#165e46]">View all →</Link></div><div className="mt-6 grid gap-6 md:grid-cols-3">{data.popular_posts.map(post => <PostCard key={post.id} post={post} />)}</div>{data.popular_posts.length === 0 && <p className="mt-6 text-[#567069]">Articles will appear here when published.</p>}</section>}
    {visible.newsletter && <section className="mt-16 rounded-3xl bg-[var(--color-primary)] p-8 text-white sm:p-10"><h2 className="text-2xl font-bold">Fresh finds in your inbox</h2><p className="mt-3 text-[#d7ecdf]">Get new articles and tools as they arrive.</p><NewsletterForm /></section>}
  </SiteShell>;
}
