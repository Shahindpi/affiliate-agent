import { pageMetadata } from "@/lib/seo";
export const metadata = pageMetadata("/categories", "Product categories", "Explore Dewdora product categories.");
import { ListPage } from "@/components/public/list-page";
export default async function Page({ searchParams }: { searchParams: Promise<{ page?: string }> }) {
  const { page } = await searchParams;
  return <ListPage kind="categories" title="Categories" query={{ page: page || "1" }} />;
}
