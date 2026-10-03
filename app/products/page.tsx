import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/products", "Products", "Explore affiliate products and useful tools selected by Dewdora.");
import { ListPage } from "@/components/public/list-page";
export default async function Page({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const { page } = await searchParams;
  return <ListPage kind="products" title="Products" query={{ page: page || "1" }} />;
}
