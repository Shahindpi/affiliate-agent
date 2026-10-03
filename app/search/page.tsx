import { routes } from "@/lib/routes";
import { SiteShell } from "@/components/public/site-shell";
import Link from "next/link";
import { PostCard, ProductCard } from "@/components/public/cards";
import {
  safePublicGet,
  type PublicPost,
  type PublicProduct,
} from "@/lib/public-api";
import type { ApiResponse } from "@/types/api";
import type { Category } from "@/types/category";
import type { Brand } from "@/types/product";
type Search = {
  posts?: PublicPost[];
  products?: PublicProduct[];
  categories?: Category[];
  brands?: Brand[];
};
export default async function Page({
  searchParams,
}: {
  searchParams: Promise<{ q?: string }>;
}) {
  const { q } = await searchParams;
  const query = q?.trim() || "";
  const response = query
    ? await safePublicGet<ApiResponse<Search>>(
        "search",
        { success: false, data: {} },
        { q: query },
      )
    : { data: {} as Search };
  const total =
    (response.data.posts?.length || 0) +
    (response.data.products?.length || 0) +
    (response.data.categories?.length || 0) +
    (response.data.brands?.length || 0);
  return (
    <SiteShell>
      <h1 className="text-4xl font-black">Search</h1>
      <form className="mt-8 flex max-w-xl gap-2">
        <input
          name="q"
          type="search"
          defaultValue={query}
          placeholder="Search products, articles, categories and brands"
          className="min-w-0 flex-1 rounded-lg border p-3"
        />
        <button className="rounded-lg bg-[#165e46] px-5 text-white">
          Search
        </button>
      </form>
      {query && (
        <>
          {total === 0 && (
            <p className="mt-10 rounded-xl border bg-white p-6 text-[#567069]">
              No results found for “{query}”. Try a broader search.
            </p>
          )}
          {Boolean(
            response.data.categories?.length || response.data.brands?.length,
          ) && (
            <section className="mt-10">
              <h2 className="text-2xl font-bold">Explore</h2>
              <div className="mt-5 flex flex-wrap gap-3">
                {response.data.categories?.map((category) => (
                  <Link
                    key={`category-${category.id}`}
                    href={routes.categories.show(category.slug)}
                    className="rounded-full border bg-white px-4 py-2 font-semibold hover:border-[#2c9873]"
                  >
                    {category.name} category
                  </Link>
                ))}
                {response.data.brands?.map((brand) => (
                  <Link
                    key={`brand-${brand.id}`}
                    href={routes.brands.show(brand.slug)}
                    className="rounded-full border bg-white px-4 py-2 font-semibold hover:border-[#2c9873]"
                  >
                    {brand.name} brand
                  </Link>
                ))}
              </div>
            </section>
          )}
          {Boolean(response.data.products?.length) && (
            <section>
              <h2 className="mt-10 text-2xl font-bold">Products</h2>
              <div className="mt-5 grid gap-6 md:grid-cols-3">
                {response.data.products?.map((product) => (
                  <ProductCard key={product.id} product={product} placement="search_results" />
                ))}
              </div>
            </section>
          )}
          {Boolean(response.data.posts?.length) && (
            <section>
              <h2 className="mt-10 text-2xl font-bold">Articles</h2>
              <div className="mt-5 grid gap-6 md:grid-cols-3">
                {response.data.posts?.map((post) => (
                  <PostCard key={post.id} post={post} />
                ))}
              </div>
            </section>
          )}
        </>
      )}
    </SiteShell>
  );
}
